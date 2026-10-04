#!/usr/bin/env python3
"""Genere les pages du wiki GitHub a partir de la documentation du depot.

Usage: build_wiki.py <racine_du_depot> <dossier_de_sortie> <owner/repo> <branche>

Les pages sont decrites dans .github/wiki-pages.json : [{"page": "Installation",
"source": "INSTALLATION.md", "title": "Installation", "section": "..."}]. Le wiki n'est donc
qu'une vue de la documentation versionnee : il est regenere a chaque mise a jour de la branche
principale (workflow wiki.yml) et ne doit jamais etre modifie a la main.

Liens reecrits : un lien vers un fichier publie dans le wiki pointe vers sa page ; tout autre
fichier du depot pointe vers GitHub (blob) ; une image locale pointe vers sa version brute.
"""
import json
import os
import re
import sys

root, out, repo, branch = sys.argv[1:5]
with open(os.path.join(root, '.github', 'wiki-pages.json'), encoding='utf-8') as f:
    pages = json.load(f)

by_source = {os.path.normpath(p['source']): p['page'] for p in pages}
blob = f'https://github.com/{repo}/blob/{branch}/'
raw = f'https://raw.githubusercontent.com/{repo}/{branch}/'
IMAGE_EXT = ('.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp')


def rewrite(md: str, source_dir: str) -> str:
    def link(m):
        bang, text, target = m.group(1), m.group(2), m.group(3)
        if re.match(r'^(https?:|mailto:|#)', target):
            return m.group(0)
        path, _, anchor = target.partition('#')
        rel = os.path.normpath(os.path.join(source_dir, path)).replace('\\', '/')
        if bang or rel.lower().endswith(IMAGE_EXT):
            return f'{bang}[{text}]({raw}{rel})'
        if os.path.normpath(rel) in by_source:
            return f'[{text}]({by_source[os.path.normpath(rel)]}{"#" + anchor if anchor else ""})'
        return f'[{text}]({blob}{rel}{"#" + anchor if anchor else ""})'

    md = re.sub(r'(!?)\[([^\]]*)\]\(([^)\s]+)\)', link, md)
    # <img src="docs/..."> en HTML dans les README
    md = re.sub(r'(<img[^>]*src=")(?!https?:)([^"]+)"',
                lambda m: f'{m.group(1)}{raw}{os.path.normpath(os.path.join(source_dir, m.group(2))).replace(os.sep, "/")}"', md)
    return md


os.makedirs(out, exist_ok=True)
notice = (f'> 📄 Page générée automatiquement depuis [`{{src}}`]({blob}{{src}}) — '
          f'modifiez ce fichier dans le dépôt, pas cette page.\n\n')

for p in pages:
    with open(os.path.join(root, p['source']), encoding='utf-8') as f:
        content = f.read()
    body = rewrite(content, os.path.dirname(p['source']))
    with open(os.path.join(out, p['page'] + '.md'), 'w', encoding='utf-8', newline='\n') as f:
        f.write(notice.format(src=p['source']) + body.rstrip() + '\n')

# Barre laterale : pages groupees par section, dans l'ordre du fichier de configuration.
sidebar, current = [], None
for p in pages:
    if p.get('section') != current:
        current = p.get('section')
        sidebar.append(f'\n**{current}**\n')
    sidebar.append(f'- [{p["title"]}]({p["page"]})')
with open(os.path.join(out, '_Sidebar.md'), 'w', encoding='utf-8', newline='\n') as f:
    f.write('\n'.join(sidebar).strip() + '\n')
with open(os.path.join(out, '_Footer.md'), 'w', encoding='utf-8', newline='\n') as f:
    f.write(f'Wiki généré depuis la branche `{branch}` de [{repo}](https://github.com/{repo}) — '
            f'[signaler un problème](https://github.com/{repo}/issues)\n')

print(f'{len(pages)} pages generees dans {out}')
