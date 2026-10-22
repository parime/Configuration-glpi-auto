#!/usr/bin/env bash

#
# -------------------------------------------------------------------------
# Configuration GLPI Auto plugin for GLPI
# Copyright (C) 2026 Vincent GUILLOTTE
# https://github.com/parime/Configuration-glpi-auto
# -------------------------------------------------------------------------
# LICENSE
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version. See LICENSE for the full text.
# -------------------------------------------------------------------------
#


# Matrice de controle d'acces de bout en bout (vraies requetes HTTP, vraie session GLPI).
#
# Usage : access-matrix.sh <url GLPI> <none|delegate|admin> <login> <mot de passe> <json de setup.php>
#   none     : aucun droit sur ce plugin -> chaque page lui est refusee
#   delegate : droits de ce plugin seulement -> pages du plugin utilisables, audit refuse, et la
#              soumission de l'assistant n'ecrit aucun objet natif privilegie (la CI compare
#              `setup.php snapshot` avant/apres) ; geocodage vers une adresse interne refuse ;
#              blueprint piege neutralise une fois restaure
#   admin    : super-admin -> tout reste accessible (detecte un controle trop strict)
#
# Chaque page de front/ et ajax/ est soit testee ici (son chemin apparait dans ce fichier), soit
# justifiee dans tests/security/not-covered.txt : la CI le verifie.
set -u
BASE=$1 ROLE=$2 LOGIN=$3 PASSWORD=$4 IDS=$5
JAR=$(mktemp)
FAIL=0
id() { jq -r ".$1" <<< "$IDS"; }
code() { curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" "$@"; }
page() { curl -s -c "$JAR" -b "$JAR" "$@"; }
csrf() { curl -s -c "$JAR" -b "$JAR" "$BASE/front/central.php" | grep -o 'glpi:csrf_token" content="[^"]*"' | sed 's/.*content="//;s/"//'; }
expect() { # libelle, attendu, obtenu
  if [ "$2" = "$3" ]; then echo "OK  $1 ($3)"; else echo "::error::$ROLE - $1 : attendu $2, obtenu $3"; FAIL=1; fi
}
shows() { # libelle, texte, attendu (oui|non), contenu
  local got=non; grep -qF "$2" <<< "$4" && got=oui
  expect "$1" "$3" "$got"
}
calendar_is_json() {
  local page_body
  page_body=$(page "$P/wizard.php")
  for var in initialCalendarDays initialCalendarDayHours; do
    if grep -qE "var $var = (\[[0-9,]*\]|\{[^;<]*\});" <<< "$page_body"; then
      echo "OK  $1 : $var est du JSON"
    else
      echo "::error::$ROLE - $1 : $var n'est pas du JSON : $(grep -o "var $var = [^;]*;" <<< "$page_body" | head -1)"
      FAIL=1
    fi
  done
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
A=$BASE/plugins/configurationglpiauto/ajax
case "$ROLE" in
  none)     OPEN=403; AUDIT=403; AUDIT_FIX=403 ;;
  delegate) OPEN=200; AUDIT=403; AUDIT_FIX=403 ;;
  *)        OPEN=200; AUDIT=200; AUDIT_FIX=302 ;;
esac

# --- Chaque page du plugin : refusee sans droit, utilisable avec ---
expect "front/wizard.php"                       $OPEN "$(code "$P/wizard.php")"
expect "front/profile.php"                      $OPEN "$(code "$P/profile.php")"
expect "front/profile.form.php (blueprint)"     $OPEN "$(code "$P/profile.form.php?id=$(id profile)")"
expect "front/blueprint_import.php"             $OPEN "$(code "$P/blueprint_import.php")"
expect "front/history.php"                      $OPEN "$(code "$P/history.php")"
expect "front/history_restore.php (historique)" $OPEN "$(code "$P/history_restore.php?id=$(id history)")"
expect "front/fueltype.php"                     $OPEN "$(code "$P/fueltype.php")"
expect "front/fueltype.form.php"                $OPEN "$(code "$P/fueltype.form.php?id=$(id fueltype)")"
expect "front/audit.php (consultation)"         $AUDIT "$(code "$P/audit.php")"
expect "front/audit.php (correction)"           $AUDIT_FIX "$(code -d "fix=notifications_disabled&_glpi_csrf_token=$(csrf)" "$P/audit.php")"
if [ "$ROLE" = none ]; then
  expect "ajax/geocode.php" 403 "$(code "$A/geocode.php?q=rue%20de%20la%20Paix")"
  rm -f "$JAR"; exit $FAIL
fi

# --- SSRF : le service de geocodage configure pointe vers 127.0.0.1, le proxy refuse de l'appeler ---
shows "ajax/geocode.php : adresse interne refusee" "misconfigured_endpoint" oui \
  "$(page "$A/geocode.php?q=rue%20de%20la%20Paix")"

if [ "$ROLE" = delegate ]; then
  # Soumission de l'assistant demandant toutes les regles natives, avec une charge XSS envoyee
  # directement sous les noms de COLONNES (contournement de prepareInput() de la 1.3.0).
  code --data-urlencode "calendar_days=0;alert(document.cookie)//" --data-urlencode "calendar_day_hours=1;alert(1)//" \
    -d "finish=1&validation_supervisor_routing_enabled=1&field_unicity_enabled=1&manufacturer_dictionary_enabled=1&software_dictionary_enabled=1&os_dictionary_enabled=1&virtual_printers_ignored=1&software_categories_enabled=1&asset_types_enabled=1&assetsign_triggers_enabled=1&custom_palette_enabled=1&_glpi_csrf_token=$(csrf)" \
    "$P/wizard.php" > /dev/null
  calendar_is_json "assistant soumis avec une charge XSS"
  # Blueprint piege (memes charges, stockees dans le fichier) restaure en entier.
  code -d "restore_all=1&itemtype=GlpiPlugin%5CConfigurationglpiauto%5CConfigurationProfile&id=$(id profile)&_glpi_csrf_token=$(csrf)" \
    "$P/history_restore.php" > /dev/null
  calendar_is_json "blueprint piege restaure"
fi

rm -f "$JAR"
exit $FAIL
