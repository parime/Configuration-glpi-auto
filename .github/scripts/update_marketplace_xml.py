#!/usr/bin/env python3
"""Insère une nouvelle entrée <version> en tête du catalogue marketplace XML.

Usage: update_marketplace_xml.py <fichier.xml> <version> <compatibility> <download_url>
                                  <version_indent> <field_indent>

version_indent : indentation des balises <version>/</version> (ex: "   " ou "    ").
field_indent   : indentation des balises <num>/<compatibility>/<download_url> (ex: "      ").

Idempotent : si <num>{version}</num> est déjà présent, ne fait rien (code 0).
Insertion texte volontaire (pas de parseur XML) pour ne pas reformatter le
reste du fichier (description longue, accents, etc.).
"""
import sys


def main() -> int:
    xml_path, version, compat, download_url, version_indent, field_indent = sys.argv[1:7]

    with open(xml_path, encoding="utf-8") as f:
        content = f.read()

    if f"<num>{version}</num>" in content:
        print(f"La version {version} est déjà présente dans {xml_path}, rien à faire.")
        return 0

    entry = (
        f"{version_indent}<version>\n"
        f"{field_indent}<num>{version}</num>\n"
        f"{field_indent}<compatibility>{compat}</compatibility>\n"
        f"{field_indent}<download_url>{download_url}</download_url>\n"
        f"{version_indent}</version>\n"
    )

    marker = "<versions>\n"
    idx = content.find(marker)
    if idx == -1:
        print(f"::error::Balise <versions> introuvable dans {xml_path}.")
        return 1
    idx += len(marker)

    content = content[:idx] + entry + content[idx:]

    with open(xml_path, "w", encoding="utf-8", newline="\n") as f:
        f.write(content)

    print(f"Version {version} ajoutée en tête de {xml_path}.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
