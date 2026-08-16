# Configuration GLPI Auto

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version: 8.2+](https://img.shields.io/badge/PHP-8.2%2B-8892B0.svg)](https://php.net)
[![GLPI Version: 11.0+](https://img.shields.io/badge/GLPI-11.0%2B-FF6B6B.svg)](https://glpi-project.org)
[![Build Status](https://github.com/parime/Configuration-glpi-auto/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/parime/Configuration-glpi-auto/actions)
[![Latest Release](https://img.shields.io/github/v/release/parime/Configuration-glpi-auto)](https://github.com/parime/Configuration-glpi-auto/releases)

Configuration GLPI Auto est un plugin pour GLPI qui vise a transformer une installation vierge en une plateforme operationnelle en quelques clics.

*Configuration GLPI Auto is a GLPI plugin that aims to turn a blank installation into an operational platform in just a few clicks.*

> Voir [CHANGELOG.md](CHANGELOG.md) pour l'historique des versions publiées (badge « Latest
> Release » ci-dessus pour la dernière en date) et [ROADMAP.md](ROADMAP.md) pour ce qui est prévu.

> *See [CHANGELOG.md](CHANGELOG.md) for the release history (the "Latest Release" badge above
> shows the most recent one) and [ROADMAP.md](ROADMAP.md) for what's planned.*

## Table des matieres
*Table of contents*

- [Fonctionnalites](#fonctionnalites)
- *[Features](#fonctionnalites)*
- [Prerequis](#prerequis)
- *[Requirements](#prerequis)*
- [Installation](#installation)
- *[Installation](#installation)*
- [Utilisation](#utilisation)
- *[Usage](#utilisation)*
- [Documentation](#documentation)
- *[Documentation](#documentation)*
- [Contribution](#contribution)
- *[Contributing](#contribution)*
- [Licence](#licence)
- *[License](#licence)*

## Fonctionnalites
*Features*

- Assistant graphique en 18 etapes avec barre de progression et un mode express (application
  directe des reglages recommandes, sans repasser par chaque etape)
- *An 18-step graphical wizard with a progress bar and an express mode (applies the recommended
  settings directly, without going through every step)*
- 4 profils predefinis (Installation simple, Plusieurs sites ou services, Plusieurs entreprises
  clientes / MSP, Personnalise) qui pre-remplissent les etapes suivantes avec des valeurs adaptees
- *4 predefined profiles (Simple install, Multiple sites or departments, Multiple client companies
  / MSP, Custom) that pre-fill the following steps with values suited to each*
- Structure d'entites (mono-site, multi-site, ou MSP) avec apercu en temps reel
- *Entity structure (single-site, multi-site, or MSP) with a real-time preview*
- Calendrier, SLA/OLA avec escalade automatique entre niveaux de support (N1 -> N2 -> N3)
- *Calendar, SLA/OLA with automatic escalation between support tiers (L1 -> L2 -> L3)*
- Categories de tickets thematiques (11 branches selectionnables, jusqu'a 3 niveaux) et catalogue
  de services en libre-service (formulaires natifs GLPI 11, routage automatique vers la bonne
  categorie)
- *Topic-based ticket categories (11 selectable branches, up to 3 levels) and a self-service
  catalog (native GLPI 11 forms, automatic routing to the right category)*
- Statuts d'elements et raisons d'attente avec relance/cloture automatiques
- *Asset statuses and pending reasons with automatic follow-up/closure*
- Personnalisation graphique : couleur et logo, palette GLPI native ou personnalisee, reglages
  differencies par client/site en mode MSP
- *Visual customization: color and logo, native or custom GLPI palette, settings differentiated
  per client/site in MSP mode*
- Modeles de tickets (simplifie / complet) assignes automatiquement selon le profil GLPI de
  l'utilisateur, droits LDAP (par site et par fonction/departement)
- *Ticket templates (simplified / full) automatically assigned based on the user's GLPI profile,
  LDAP rights (per site and per role/department)*
- Bibliotheques de gabarits de taches, solutions, suivis et validations, avec variables Twig
  dynamiques (donnees reelles du ticket/demandeur), modeles de changement et de probleme
- *Libraries of task, solution, follow-up and validation templates, with dynamic Twig variables
  (real ticket/requester data), change and problem templates*
- Lieux, fabricants, categories de base de connaissances, rubriques documentaires (classification
  ISO 27001) et niveaux de criticite, intitules du module Projets
- *Locations, manufacturers, knowledge base categories, document topics (ISO 27001 classification)
  and criticality levels, Projects module dropdowns*
- Interface traduite en 5 langues (francais, anglais, allemand, italien, espagnol)
- *Interface translated into 5 languages (French, English, German, Italian, Spanish)*

## Prerequis
*Requirements*

- PHP 8.2+
- GLPI 11.0+
- Base de donnees: MySQL 5.7+, MariaDB 10.2+, PostgreSQL 9.6+
- *Database: MySQL 5.7+, MariaDB 10.2+, PostgreSQL 9.6+*

## Installation
*Installation*

Pas de package Composer (GLPI n'est pas distribue via Packagist, voir CHANGELOG.md). Deux options :

*No Composer package (GLPI isn't distributed via Packagist, see CHANGELOG.md). Two options:*

### Depuis une release
*From a release*

1. Telechargez l'archive depuis [GitHub Releases](https://github.com/parime/Configuration-glpi-auto/releases)
1. *Download the archive from [GitHub Releases](https://github.com/parime/Configuration-glpi-auto/releases)*
2. Extrayez-la dans le dossier `plugins/` de GLPI (elle contient deja `vendor/`, pret a l'emploi)
2. *Extract it into GLPI's `plugins/` folder (it already contains `vendor/`, ready to use)*
3. Installez et activez via l'interface GLPI ou `bin/console plugin:install|activate configurationglpiauto`
3. *Install and activate it via the GLPI interface or `bin/console plugin:install|activate configurationglpiauto`*

### Depuis le code source
*From source*

```bash
git clone https://github.com/parime/Configuration-glpi-auto.git
cd Configuration-glpi-auto
composer install --no-dev   # vendor/autoload.php est requis au runtime, voir setup.php
```

Puis copiez/liez le dossier dans `plugins/configurationglpiauto` d'une instance GLPI 11, et
installez/activez comme ci-dessus. Un stack Docker de test (GLPI + MariaDB) est fourni dans
`docker-compose.test.yml`.

*Then copy/symlink the folder into `plugins/configurationglpiauto` of a GLPI 11 instance, and
install/activate it as above. A test Docker stack (GLPI + MariaDB) is provided in
`docker-compose.test.yml`.*

## Utilisation
*Usage*

Une fois le plugin active, l'assistant est accessible depuis **Administration > Profils de
configuration > Configuration**. Choisissez un profil de depart (etape 1), puis parcourez les 18
etapes en ajustant chaque reglage a vos besoins — rien n'est cree dans GLPI avant la derniere
etape (Recapitulatif). Le mode express (bouton disponible des l'etape 1) applique directement les
reglages recommandes du profil choisi, sans repasser par chaque etape. Voir le
[tutoriel](docs/TUTORIAL.md) pour une capture d'ecran de chaque etape.

*Once the plugin is active, the wizard is available from **Administration > Configuration
profiles > Configuration**. Choose a starting profile (step 1), then go through the 18 steps,
adjusting each setting to your needs — nothing is created in GLPI before the last step (Summary).
Express mode (a button available from step 1) applies the chosen profile's recommended settings
directly, without going through every step. See the [tutorial](docs/TUTORIAL.md) for a screenshot
of each step.*

## Documentation
*Documentation*

- [Tutoriel](docs/TUTORIAL.md) — parcours pas a pas des 18 etapes de l'assistant, avec capture
  d'ecran de chacune.
- *[Tutorial](docs/TUTORIAL.md) — a step-by-step walkthrough of the wizard's 18 steps, with a
  screenshot of each.*
- [CHANGELOG.md](CHANGELOG.md) et [ROADMAP.md](ROADMAP.md) pour le detail technique de chaque
  fonctionnalite.
- *[CHANGELOG.md](CHANGELOG.md) and [ROADMAP.md](ROADMAP.md) for the technical detail of each
  feature.*

## Contribution
*Contributing*

Les contributions sont les bienvenues ! Veuillez lire [CONTRIBUTING.md](CONTRIBUTING.md) pour les directives.

*Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for the guidelines.*

## Licence
*License*

GPLv3 - Voir [LICENSE](LICENSE) pour plus de details.

*GPLv3 - See [LICENSE](LICENSE) for details.*
