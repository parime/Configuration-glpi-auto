#!/bin/sh
# Chaque page front/*.php et ajax/*.php doit etre exercee par le test de controle d'acces de bout
# en bout (son chemin apparait dans tests/security/access-matrix.sh), ou listee avec sa
# justification dans tests/security/not-covered.txt. Une nouvelle page sans test d'acces fait donc
# echouer la CI au lieu de passer inapercue.
MATRIX=tests/security/access-matrix.sh
EXEMPT=tests/security/not-covered.txt
status=0
for f in front/*.php ajax/*.php; do
  [ -f "$f" ] || continue
  if grep -qF "$f" "$MATRIX"; then
    continue
  fi
  if [ -f "$EXEMPT" ] && grep -qE "^$f[[:space:]]*:[[:space:]]*[^[:space:]]" "$EXEMPT"; then
    continue
  fi
  echo "::error file=$f::page absente de $MATRIX (ni testee, ni justifiee dans $EXEMPT)"
  status=1
done
exit $status
