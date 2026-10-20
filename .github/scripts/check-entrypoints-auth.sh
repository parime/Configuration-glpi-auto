#!/bin/sh
# Chaque point d'entree HTTP du plugin (front/*.php, ajax/*.php) doit appeler un controle
# d'autorisation GLPI explicite. Un point d'entree qui s'autorise autrement (jeton, identite...)
# doit figurer, avec sa justification, dans .github/security-entrypoints-allowlist.txt : un
# nouveau fichier sans controle fait ainsi echouer la CI au lieu de passer inapercu.
ALLOWLIST=.github/security-entrypoints-allowlist.txt
status=0
for f in front/*.php ajax/*.php; do
  [ -f "$f" ] || continue
  if grep -qE 'Session::check(Right|RightsOr|SeveralRightsOr|LoginUser|CentralAccess|HelpdeskAccess)\(|->check\(' "$f"; then
    continue
  fi
  if [ -f "$ALLOWLIST" ] && grep -qE "^$f[[:space:]]*:[[:space:]]*[^[:space:]]" "$ALLOWLIST"; then
    echo "exception justifiee : $(grep -E "^$f[[:space:]]*:" "$ALLOWLIST")"
    continue
  fi
  echo "::error file=$f::point d'entree sans controle d'autorisation (Session::checkRight/checkLoginUser/->check()) ni exception justifiee dans $ALLOWLIST"
  status=1
done
exit $status
