[🇫🇷 Français](#-français) · [🇬🇧 English](#-english)

## 🇫🇷 Français

**Politique de sécurité**

### Versions supportées

Ce projet est en développement actif (pré-1.0) : seule la dernière version publiée sur `main` est
maintenue. Mettez à jour vers la dernière version avant de signaler un problème.

### Signaler une vulnérabilité

Si vous découvrez une faille de sécurité dans ce plugin, **ne créez pas d'issue publique**.
Signalez-la de façon privée via l'onglet **Security > Report a vulnerability** de ce dépôt GitHub
(Security Advisories), afin qu'elle soit traitée avant d'être rendue publique.

Décrivez si possible : les étapes pour reproduire, l'impact potentiel, et la version du plugin/GLPI
concernée.

### Analyse automatisée en place

Chaque Pull Request passe par :

- **Trivy** : vulnérabilités connues des dépendances, secrets, mauvaises configurations.
- **Semgrep** : analyse par motifs (patterns de code dangereux).
- **Dependabot** : alertes et mises à jour automatiques des dépendances vulnérables.

Voir `.github/workflows/continuous-integration.yml` pour le détail exact des jobs.

## 🇬🇧 English

**Security policy**

### Supported versions

This project is in active development (pre-1.0): only the latest version published on `main` is
maintained. Update to the latest version before reporting an issue.

### Reporting a vulnerability

If you discover a security flaw in this plugin, **do not create a public issue**. Report it
privately via this GitHub repository's **Security > Report a vulnerability** tab (Security
Advisories), so it can be addressed before being made public.

Please describe, if possible: the steps to reproduce, the potential impact, and the plugin/GLPI
version affected.

### Automated analysis in place

Every Pull Request goes through:

- **Trivy**: known dependency vulnerabilities, secrets, misconfigurations.
- **Semgrep**: pattern-based analysis (dangerous code patterns).
- **Dependabot**: alerts and automatic updates for vulnerable dependencies.

See `.github/workflows/continuous-integration.yml` for the exact job details.
