# Politique de sécurité

## Versions supportées

Ce projet est en développement actif (pré-1.0) — seule la dernière version publiée sur `main` est
maintenue. Mettez à jour vers la dernière version avant de signaler un problème.

## Signaler une vulnérabilité

Si vous découvrez une faille de sécurité dans ce plugin, **ne créez pas d'issue publique**.
Signalez-la de façon privée via l'onglet **Security > Report a vulnerability** de ce dépôt GitHub
(Security Advisories), afin qu'elle soit traitée avant d'être rendue publique.

Décrivez si possible : les étapes pour reproduire, l'impact potentiel, et la version du plugin/GLPI
concernée.

## Analyse automatisée en place

Chaque Pull Request passe par :

- **Trivy** — vulnérabilités connues des dépendances, secrets, mauvaises configurations.
- **Semgrep** — analyse par motifs (patterns de code dangereux).
- **Dependabot** — alertes et mises à jour automatiques des dépendances vulnérables.

Voir `.github/workflows/continuous-integration.yml` pour le détail exact des jobs.
