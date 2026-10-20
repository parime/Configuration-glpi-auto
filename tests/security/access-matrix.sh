#!/usr/bin/env bash
# Matrice de controle d'acces de bout en bout (vraies requetes HTTP, vraie session GLPI).
#
# Usage : access-matrix.sh <url GLPI> <delegate|admin> <login> <mot de passe>
#   delegate : droits de ce plugin seulement -> audit refuse ; l'assistant reste utilisable mais
#              sa soumission ne doit ecrire aucun objet natif privilegie (verifie par la CI en
#              comparant `setup.php snapshot` avant/apres ce script)
#   admin    : super-admin -> tout reste accessible (detecte un controle trop strict)
#
# Couvre la revue de securite de la marketplace GLPI : droit propre au plugin (delegable)
# suffisant pour une operation privilegiee, et XSS stocke via calendar_days/calendar_day_hours.
set -u
BASE=$1 ROLE=$2 LOGIN=$3 PASSWORD=$4
JAR=$(mktemp)
FAIL=0
code() { curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" "$@"; }
csrf() { curl -s -c "$JAR" -b "$JAR" "$BASE/front/central.php" | grep -o 'glpi:csrf_token" content="[^"]*"' | sed 's/.*content="//;s/"//'; }
expect() { # libelle, attendu, obtenu
  if [ "$2" = "$3" ]; then echo "OK  $1 ($3)"; else echo "::error::$ROLE - $1 : attendu $2, obtenu $3"; FAIL=1; fi
}

for try in 1 2 3 4 5; do
  rm -f "$JAR"
  token=$(curl -s -c "$JAR" -b "$JAR" "$BASE/" | grep -o 'name="_glpi_csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
  curl -s -o /dev/null -c "$JAR" -b "$JAR" --data-urlencode "login_name=$LOGIN" --data-urlencode "login_password=$PASSWORD" \
    --data-urlencode "_glpi_csrf_token=$token" -d noAUTO=1 "$BASE/front/login.php"
  [ "$(code "$BASE/front/central.php")" = 200 ] && break
  [ "$try" = 5 ] && { echo "::error::connexion impossible pour $LOGIN"; exit 1; }
  sleep 3
done

P=$BASE/plugins/configurationglpiauto/front
if [ "$ROLE" = delegate ]; then DENY=403; DENY_POST=403; else DENY=200; DENY_POST=302; fi

expect "assistant (consultation)"  200         "$(code "$P/wizard.php")"
expect "audit (consultation)"      $DENY       "$(code "$P/audit.php")"
expect "audit (correction)"        $DENY_POST  "$(code -d "fix=notifications_disabled&_glpi_csrf_token=$(csrf)" "$P/audit.php")"

# Soumission de l'assistant demandant toutes les familles de regles natives, avec une charge XSS
# envoyee directement sous les noms de COLONNES (contournement de prepareInput() de la 1.3.0).
if [ "$ROLE" = delegate ]; then
  code --data-urlencode "calendar_days=0;alert(document.cookie)//" --data-urlencode "calendar_day_hours=1;alert(1)//" \
    -d "finish=1&validation_supervisor_routing_enabled=1&field_unicity_enabled=1&manufacturer_dictionary_enabled=1&software_dictionary_enabled=1&os_dictionary_enabled=1&virtual_printers_ignored=1&software_categories_enabled=1&asset_types_enabled=1&assetsign_triggers_enabled=1&custom_palette_enabled=1&_glpi_csrf_token=$(csrf)" \
    "$P/wizard.php" > /dev/null
  page=$(curl -s -c "$JAR" -b "$JAR" "$P/wizard.php")
  for var in initialCalendarDays initialCalendarDayHours; do
    if grep -qE "var $var = (\[[0-9,]*\]|\{[^;<]*\});" <<< "$page"; then
      echo "OK  $var est du JSON"
    else
      echo "::error::$var n'est pas du JSON valide : $(grep -o "var $var = [^;]*;" <<< "$page" | head -1)"
      FAIL=1
    fi
  done
fi

rm -f "$JAR"
exit $FAIL
