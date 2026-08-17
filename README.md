# Configuration GLPI Auto

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version: 8.2+](https://img.shields.io/badge/PHP-8.2%2B-8892B0.svg)](https://php.net)
[![GLPI Version: 11.0+](https://img.shields.io/badge/GLPI-11.0%2B-FF6B6B.svg)](https://glpi-project.org)
[![Build Status](https://github.com/parime/Configuration-glpi-auto/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/parime/Configuration-glpi-auto/actions)
[![Latest Release](https://img.shields.io/github/v/release/parime/Configuration-glpi-auto)](https://github.com/parime/Configuration-glpi-auto/releases)

<p align="center"><strong>Une instance GLPI neuve, configurée selon les bonnes pratiques ITIL/ISO 27001, en 18 étapes guidées plutôt qu'en jours de réglages manuels.</strong></p>
<p align="center"><em>A brand-new GLPI instance, configured to ITIL/ISO 27001 best practices, in 18 guided steps instead of days of manual tweaking.</em></p>

Configuration GLPI Auto est un plugin pour GLPI qui vise a transformer une installation vierge en une plateforme operationnelle en quelques clics.

*Configuration GLPI Auto is a GLPI plugin that aims to turn a blank installation into an operational platform in just a few clicks.*

Une installation GLPI neuve est une page blanche : aucune entité, aucun calendrier, aucun SLA, aucune catégorie de ticket, aucun modèle. Tout configurer correctement à la main — en respectant les bonnes pratiques ITIL et les exigences ISO 27001 — prend typiquement plusieurs jours à un administrateur qui découvre GLPI, avec le risque d'oublier un réglage important (escalade SLA, classification documentaire, droits par site...). Ce plugin condense ce travail en un assistant guidé de 18 étapes : vous répondez à des questions sur votre organisation, l'assistant construit la configuration correspondante, et rien n'est créé dans GLPI avant que vous ne validiez le récapitulatif final.

*A fresh GLPI install is a blank page: no entities, no calendar, no SLAs, no ticket categories, no templates. Configuring all of that correctly by hand — while following ITIL best practices and ISO 27001 requirements — typically takes a newcomer administrator several days, with the risk of missing something important (SLA escalation, document classification, per-site rights...). This plugin condenses that work into a guided 18-step wizard: you answer questions about your organization, the wizard builds the matching configuration, and nothing is created in GLPI until you confirm the final summary.*

> Voir [CHANGELOG.md](CHANGELOG.md) pour l'historique des versions publiées (badge « Latest
> Release » ci-dessus pour la dernière en date) et [ROADMAP.md](ROADMAP.md) pour ce qui est prévu.

> *See [CHANGELOG.md](CHANGELOG.md) for the release history (the "Latest Release" badge above
> shows the most recent one) and [ROADMAP.md](ROADMAP.md) for what's planned.*

## Table des matieres
*Table of contents*

- [Ce qui le distingue](#ce-qui-le-distingue)
- *[What sets it apart](#ce-qui-le-distingue)*
- [Aperçu](#aperçu)
- *[Screenshots](#aperçu)*
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

## Ce qui le distingue
*What sets it apart*

- **Rien n'est créé avant la fin** : les 18 étapes ne font que composer une configuration en mémoire, avec un aperçu qui se met à jour en direct (voir capture ci-dessous) — vous pouvez revenir en arrière, tout changer, recommencer, sans jamais polluer GLPI avant de valider le récapitulatif final.
- ***Nothing is created until the end**: the 18 steps only build up a configuration in memory, with a live-updating preview (see screenshot below) — you can go back, change anything, start over, without ever touching GLPI before confirming the final summary.*
- **Un mode express pour les pressés** : un seul clic à l'étape 1 applique directement les réglages recommandés du profil choisi, sans parcourir les 17 étapes suivantes une à une — pour une installation simple, une instance GLPI opérationnelle en quelques secondes.
- ***An express mode for the impatient**: a single click at step 1 applies the chosen profile's recommended settings directly, without going through the following 17 steps one by one — for a simple installation, an operational GLPI instance in a few seconds.*
- **Pensé multi-site et MSP dès le départ** : la même arborescence d'entités, les mêmes SLA et la même personnalisation graphique peuvent être différenciés par site ou par client, sans jongler entre plusieurs installations GLPI séparées.
- ***Built for multi-site and MSP from the start**: the same entity tree, the same SLAs and the same visual customization can be differentiated per site or per client, without juggling several separate GLPI installations.*
- **Conformité ISO 27001 intégrée** : rubriques documentaires et niveaux de criticité de la base de connaissances sont proposés dès l'assistant, pas ajoutés après coup en fouillant la documentation GLPI.
- ***ISO 27001 compliance built in**: knowledge base document topics and criticality levels are offered right in the wizard, not bolted on afterwards by digging through GLPI's documentation.*
- **Zéro donnée orpheline** : chaque réglage proposé (catégories, statuts, modèles...) est directement utilisable — le catalogue de services généré à l'étape 7 route déjà automatiquement vers la bonne catégorie créée à l'étape 6, par exemple.
- ***No orphaned data**: every setting the wizard proposes (categories, statuses, templates...) is immediately usable — the service catalog generated in step 7 already routes automatically to the right category created in step 6, for example.*

## Aperçu
*Screenshots*

**Le choix du profil de départ** — quatre profils prédéfinis pré-remplissent les 17 étapes suivantes avec des valeurs adaptées à votre organisation, ajustables ensuite à volonté ; un mode express applique directement les réglages recommandés sans repasser par chaque étape :

***The starting profile choice** — four predefined profiles pre-fill the following 17 steps with values suited to your organization, freely adjustable afterwards; an express mode applies the recommended settings directly without going through every step:*

![Étape 1 — Choix du profil](docs/screenshots/01-profil.png)

**La structure d'entités, avec aperçu en direct** — mono-site, multi-site ou MSP : l'arborescence se construit à gauche, l'aperçu à droite se met à jour à chaque changement, avant tout enregistrement :

***The entity structure, with a live preview** — single-site, multi-site or MSP: the tree is built on the left, the preview on the right updates with every change, before anything is saved:*

![Étape 2 — Structure des entités](docs/screenshots/02-entites.png)

Toutes les autres captures d'écran (les 18 étapes en détail) sont dans le [tutoriel](docs/TUTORIAL.md).

*All other screenshots (all 18 steps in detail) are in the [tutorial](docs/TUTORIAL.md).*

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
