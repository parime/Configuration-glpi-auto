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


# Test XSS de bout en bout : apres `setup.php xss`, chaque champ texte libre (configuration,
# arborescence d'entites, type de carburant, point d'historique, blueprint) contient
# <svg/onload=alert('xss-canary')>. Aucune page ne doit restituer cette charge telle quelle.
#
# Usage : xss-check.sh <url GLPI> <json de setup.php xss>
set -u
BASE=$1 IDS=$2
CANARY="<svg/onload=alert('xss-canary')>"
JAR=$(mktemp)
FAIL=0
RENDERED=0
id() { jq -r ".$1" <<< "$IDS"; }
check() { # libelle, url
  local body status
  body=$(curl -s -w '\n%{http_code}' -c "$JAR" -b "$JAR" "$2")
  status=${body##*$'\n'}
  body=${body%$'\n'*}
  if [ "$status" != 200 ]; then
    echo "::error::$1 : code HTTP $status (page non verifiee)"; FAIL=1; return
  fi
  if grep -qF "$CANARY" <<< "$body"; then
    echo "::error::$1 : charge XSS restituee sans echappement"; FAIL=1; return
  fi
  grep -q "xss-canary" <<< "$body" && RENDERED=$((RENDERED + 1))
  echo "OK  $1"
}

for try in 1 2 3 4 5; do
  rm -f "$JAR"
  token=$(curl -s -c "$JAR" -b "$JAR" "$BASE/" | grep -o 'name="_glpi_csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
  curl -s -o /dev/null -c "$JAR" -b "$JAR" --data-urlencode "login_name=glpi" --data-urlencode "login_password=glpi" \
    --data-urlencode "_glpi_csrf_token=$token" -d noAUTO=1 "$BASE/front/login.php"
  [ "$(curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" "$BASE/front/central.php")" = 200 ] && break
  [ "$try" = 5 ] && { echo "::error::connexion impossible"; exit 1; }
  sleep 3
done

P=$BASE/plugins/configurationglpiauto/front
for url in wizard.php profile.php "profile.form.php?id=$(id profile)" "profile.form.php?id=$(id profile)&export_snapshot=1" \
  history.php "history_restore.php?id=$(id history)" \
  "history_restore.php?itemtype=GlpiPlugin%5CConfigurationglpiauto%5CConfigurationProfile&id=$(id profile)" \
  fueltype.php "fueltype.form.php?id=$(id fueltype)" audit.php blueprint_import.php deploy.php; do
  check "front/$url" "$P/$url"
done

rm -f "$JAR"
if [ "$RENDERED" -lt 5 ]; then
  echo "::error::la charge n'apparait (echappee) que sur $RENDERED page(s) : les donnees piegees ne sont pas affichees, le test ne prouve rien"
  FAIL=1
else
  echo "OK  charge affichee echappee sur $RENDERED pages"
fi
exit $FAIL
