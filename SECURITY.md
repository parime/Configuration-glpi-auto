# Politique de sécurité
*Security policy*

## Versions supportées
*Supported versions*

Ce projet est en développement actif (pré-1.0) — seule la dernière version publiée sur `main` est
maintenue. Mettez à jour vers la dernière version avant de signaler un problème.

*This project is in active development (pre-1.0) — only the latest version published on `main` is
maintained. Update to the latest version before reporting an issue.*

## Signaler une vulnérabilité
*Reporting a vulnerability*

Si vous découvrez une faille de sécurité dans ce plugin, **ne créez pas d'issue publique**.
Signalez-la de façon privée via l'onglet **Security > Report a vulnerability** de ce dépôt GitHub
(Security Advisories), afin qu'elle soit traitée avant d'être rendue publique.

*If you discover a security flaw in this plugin, **do not create a public issue**. Report it
privately via this GitHub repository's **Security > Report a vulnerability** tab (Security
Advisories), so it can be addressed before being made public.*

Décrivez si possible : les étapes pour reproduire, l'impact potentiel, et la version du plugin/GLPI
concernée.

*Please describe, if possible: the steps to reproduce, the potential impact, and the plugin/GLPI
version affected.*

## Analyse automatisée en place
*Automated analysis in place*

Chaque Pull Request passe par :

*Every Pull Request goes through:*

- **Trivy** — vulnérabilités connues des dépendances, secrets, mauvaises configurations.
- ***Trivy** — known dependency vulnerabilities, secrets, misconfigurations.*
- **Semgrep** — analyse par motifs (patterns de code dangereux).
- ***Semgrep** — pattern-based analysis (dangerous code patterns).*
- **Dependabot** — alertes et mises à jour automatiques des dépendances vulnérables.
- ***Dependabot** — alerts and automatic updates for vulnerable dependencies.*

Voir `.github/workflows/continuous-integration.yml` pour le détail exact des jobs.

*See `.github/workflows/continuous-integration.yml` for the exact job details.*
