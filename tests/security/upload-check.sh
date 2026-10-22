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


# Test de l'import de Blueprint de bout en bout (vraies requetes multipart, vraie session GLPI) :
#   - sans droit : import refuse, aucun profil cree ;
#   - script PHP, image, JSON qui n'est pas un Blueprint : refuses proprement
#     (pas d'erreur 500), aucun profil cree ;
#   - Blueprint piege (XSS sous les noms de colonnes et dans le nom, champs propres a l'instance,
#     geocodage vers une adresse interne) : importe, puis neutralise une fois restaure ;
#   - donnees hors format Blueprint (plusieurs Mo) : non stockees.
#
# Usage : upload-check.sh <url GLPI> <commande `setup.php uploads`> <commande `setup.php blueprint`>
set -u
BASE=$1 STATE_CMD=$2 BLUEPRINT_CMD=$3
P=$BASE/plugins/configurationglpiauto/front
A=$BASE/plugins/configurationglpiauto/ajax
JAR=$(mktemp)
DIR=$(mktemp -d)
FAIL=0
state() { eval "$STATE_CMD" | tail -1 | jq -r ".$1"; }
profiles() { state profiles; }
expect() { # libelle, attendu, obtenu
  if [ "$2" = "$3" ]; then echo "OK  $1 ($3)"; else echo "::error::$1 : attendu $2, obtenu $3"; FAIL=1; fi
}
csrf() { curl -s -c "$JAR" -b "$JAR" "$BASE/front/central.php" | grep -o 'glpi:csrf_token" content="[^"]*"' | sed 's/.*content="//;s/"//'; }
login() { # identifiant, mot de passe
  local try token
  for try in 1 2 3 4 5; do
    rm -f "$JAR"
    token=$(curl -s -c "$JAR" -b "$JAR" "$BASE/" | grep -o 'name="_glpi_csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
    curl -s -o /dev/null -c "$JAR" -b "$JAR" --data-urlencode "login_name=$1" --data-urlencode "login_password=$2" \
      --data-urlencode "_glpi_csrf_token=$token" -d noAUTO=1 "$BASE/front/login.php"
    [ "$(curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" "$BASE/front/central.php")" = 200 ] && return
    sleep 3
  done
  echo "::error::connexion impossible pour $1"; exit 1
}
import() { # fichier local, nom annonce -> "code url_de_redirection"
  curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -c "$JAR" -b "$JAR" -F import=1 \
    -F "_glpi_csrf_token=$(csrf)" -F "blueprint_file=@$1;filename=$2;type=application/json" "$P/blueprint_import.php"
}
calendar_is_json() {
  local body
  body=$(curl -s -c "$JAR" -b "$JAR" "$P/wizard.php")
  for var in initialCalendarDays initialCalendarDayHours; do
    if grep -qE "var $var = (\[[0-9,]*\]|\{[^;<]*\});" <<< "$body"; then
      echo "OK  $1 : $var est du JSON"
    else
      echo "::error::$1 : $var n'est pas du JSON : $(grep -o "var $var = [^;]*;" <<< "$body" | head -1)"
      FAIL=1
    fi
  done
}

printf '<?php echo "UPLOAD-EXECUTE"; ?>\n' > "$DIR/script"
base64 -d <<< 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' > "$DIR/image"
printf '{"foo": "bar"}\n' > "$DIR/notblueprint"
{ printf '{"format_version": 1, "config": {"junk": "'; head -c 8000000 /dev/zero | tr '\0' a; printf '"}}\n'; } > "$DIR/big"
eval "$BLUEPRINT_CMD" | tail -1 > "$DIR/trapped"
jq -e .config > /dev/null < "$DIR/trapped" || { echo "::error::Blueprint piege non genere"; exit 1; }

n0=$(profiles)

login securite-sans-droit 'Secrev#2026'
expect "sans droit : import refuse" 403 "$(import "$DIR/trapped" blueprint.json | cut -d' ' -f1)"
expect "sans droit : aucun profil cree" "$n0" "$(profiles)"

login securite-delegue 'Secrev#2026'
for f in script:shell.php script:blueprint.json image:blueprint.json notblueprint:blueprint.json; do
  code=$(import "$DIR/${f%%:*}" "${f#*:}" | cut -d' ' -f1)
  case "$code" in
    5*) echo "::error::import de ${f%%:*} (${f#*:}) : erreur serveur $code"; FAIL=1 ;;
    *) echo "OK  import de ${f%%:*} (${f#*:}) : pas d'erreur serveur ($code)" ;;
  esac
  expect "import de ${f%%:*} (${f#*:}) : aucun profil cree" "$n0" "$(profiles)"
done

result=$(import "$DIR/trapped" blueprint.json)
profile=$(grep -o 'profile.form.php?id=[0-9]*' <<< "$result" | cut -d= -f2)
expect "Blueprint piege importe" $((n0 + 1)) "$(profiles)"
if [ -z "$profile" ]; then
  echo "::error::Blueprint piege : pas de redirection vers le nouveau profil ($result)"; FAIL=1
else
  page=$(curl -s -c "$JAR" -b "$JAR" "$P/profile.form.php?id=$profile")
  if grep -qF "<svg/onload=alert('xss-canary')>" <<< "$page"; then
    echo "::error::nom du Blueprint importe restitue sans echappement"; FAIL=1
  else
    echo "OK  nom du Blueprint importe echappe"
  fi
  curl -s -o /dev/null -c "$JAR" -b "$JAR" \
    -d "restore_all=1&itemtype=GlpiPlugin%5CConfigurationglpiauto%5CConfigurationProfile&id=$profile&_glpi_csrf_token=$(csrf)" \
    "$P/history_restore.php"
  calendar_is_json "Blueprint piege importe puis restaure"
  endpoint=$(state endpoint)
  case "$endpoint" in
    *169.254.*) echo "::error::Blueprint restaure : geocodage pointe vers une adresse interne ($endpoint)"; FAIL=1 ;;
    *) echo "OK  Blueprint restaure : geocodage interne non applique ($endpoint)" ;;
  esac
fi

# Un fichier au format Blueprint charge de plusieurs Mo hors du format : seul le format est stocke.
import "$DIR/big" blueprint.json > /dev/null
latest=$(state latest)
if [ "$latest" -lt 1000000 ]; then
  echo "OK  Blueprint de 8 Mo hors format : seul le format est stocke ($latest octets)"
else
  echo "::error::Blueprint de 8 Mo hors format stocke tel quel ($latest octets)"; FAIL=1
fi

rm -rf "$JAR" "$DIR"
exit $FAIL
