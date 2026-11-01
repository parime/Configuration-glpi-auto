#!/bin/sh
# Variantes régionales des traductions : GLPI ne se replie pas d'une variante vers sa langue de
# base (es_MX ne lit pas es_ES.mo, il passe à la langue de l'instance puis à en_GB). Chaque
# variante est donc une copie de sa langue de base, à régénérer après toute modification :
#   sh .github/scripts/sync-locale-variants.sh          (copie .po et .mo)
#   sh .github/scripts/sync-locale-variants.sh --check  (CI : échoue si une variante n'est pas à jour)
set -e
cd "$(dirname "$0")/../../locales"

VARIANTS="en_GB:en_US
es_ES:es_MX es_419 es_AR es_CL es_CO es_EC es_VE
fr_FR:fr_CA fr_BE
pt_BR:pt_PT"

status=0
echo "$VARIANTS" | while IFS=: read -r base variants; do
  [ -f "$base.po" ] || continue
  for variant in $variants; do
    expected=$(sed "s/^\"Language: $base\\\\n\"$/\"Language: $variant\\\\n\"/" "$base.po")
    if [ "$1" = "--check" ]; then
      if [ "$expected" != "$(cat "$variant.po" 2>/dev/null)" ] || ! cmp -s "$base.mo" "$variant.mo"; then
        echo "::error::locales/$variant.po n'est pas à jour : lancer sh .github/scripts/sync-locale-variants.sh"
        exit 1
      fi
    else
      printf '%s\n' "$expected" > "$variant.po"
      cp "$base.mo" "$variant.mo"
    fi
  done
done || status=1
exit $status
