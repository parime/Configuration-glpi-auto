# Configuration GLPI Auto

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version: 8.2+](https://img.shields.io/badge/PHP-8.2%2B-8892B0.svg)](https://php.net)
[![GLPI Version: 11.0+](https://img.shields.io/badge/GLPI-11.0%2B-FF6B6B.svg)](https://glpi-project.org)
[![Build Status](https://github.com/parime/Configuration-glpi-auto/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/parime/Configuration-glpi-auto/actions)
[![Latest Release](https://img.shields.io/github/v/release/parime/Configuration-glpi-auto)](https://github.com/parime/Configuration-glpi-auto/releases)

Configuration GLPI Auto est un plugin pour GLPI qui vise a transformer une installation vierge en une plateforme operationnelle en quelques clics.

> **Etat du projet (2026-08-12)** : `v0.30.1`. Voir [CHANGELOG.md](CHANGELOG.md) et
> [ROADMAP.md](ROADMAP.md) pour l'etat sprint par sprint.

## Table des matieres

- [Fonctionnalites](#fonctionnalites)
- [Prerequis](#prerequis)
- [Installation](#installation)
- [Utilisation](#utilisation)
- [Documentation](#documentation)
- [Contribution](#contribution)
- [Licence](#licence)

## Fonctionnalites

- Assistant graphique en 17 etapes avec barre de progression et un mode express (application
  directe des reglages recommandes, sans repasser par chaque etape)
- 4 profils predefinis (Installation simple, Plusieurs sites ou services, Plusieurs entreprises
  clientes / MSP, Personnalise) qui pre-remplissent les etapes suivantes avec des valeurs adaptees
- Structure d'entites (mono-site, multi-site, ou MSP) avec apercu en temps reel
- Calendrier, SLA/OLA avec escalade automatique entre niveaux de support (N1 -> N2 -> N3)
- Categories de tickets thematiques (11 branches selectionnables, jusqu'a 3 niveaux) et catalogue
  de services en libre-service (formulaires natifs GLPI 11, routage automatique vers la bonne
  categorie)
- Statuts d'elements et raisons d'attente avec relance/cloture automatiques
- Personnalisation graphique : couleur et logo, palette GLPI native ou personnalisee, reglages
  differencies par client/site en mode MSP
- Modeles de tickets (simplifie / complet) assignes automatiquement selon le profil GLPI de
  l'utilisateur, droits LDAP (par site et par fonction/departement)
- Bibliotheques de gabarits de taches, solutions, suivis et validations, avec variables Twig
  dynamiques (donnees reelles du ticket/demandeur), modeles de changement et de probleme
- Lieux, fabricants, categories de base de connaissances, rubriques documentaires (classification
  ISO 27001) et niveaux de criticite, intitules du module Projets
- Interface traduite en 5 langues (francais, anglais, allemand, italien, espagnol)

## Prerequis

- PHP 8.2+
- GLPI 11.0+
- Base de donnees: MySQL 5.7+, MariaDB 10.2+, PostgreSQL 9.6+

## Installation

Pas de package Composer (GLPI n'est pas distribue via Packagist, voir CHANGELOG.md). Deux options :

### Depuis une release

1. Telechargez l'archive depuis [GitHub Releases](https://github.com/parime/Configuration-glpi-auto/releases)
2. Extrayez-la dans le dossier `plugins/` de GLPI (elle contient deja `vendor/`, pret a l'emploi)
3. Installez et activez via l'interface GLPI ou `bin/console plugin:install|activate configurationglpiauto`

### Depuis le code source

```bash
git clone https://github.com/parime/Configuration-glpi-auto.git
cd Configuration-glpi-auto
composer install --no-dev   # vendor/autoload.php est requis au runtime, voir setup.php
```

Puis copiez/liez le dossier dans `plugins/configurationglpiauto` d'une instance GLPI 11, et
installez/activez comme ci-dessus. Un stack Docker de test (GLPI + MariaDB) est fourni dans
`docker-compose.test.yml`.

## Utilisation

Une fois le plugin active, l'assistant est accessible depuis **Administration > Profils de
configuration > Configuration**. Choisissez un profil de depart (etape 1), puis parcourez les 17
etapes en ajustant chaque reglage a vos besoins — rien n'est cree dans GLPI avant la derniere
etape (Recapitulatif). Le mode express (bouton disponible des l'etape 1) applique directement les
reglages recommandes du profil choisi, sans repasser par chaque etape. Voir le
[tutoriel](docs/TUTORIAL.md) pour une capture d'ecran de chaque etape.

## Documentation

- [Tutoriel](docs/TUTORIAL.md) — parcours pas a pas des 17 etapes de l'assistant, avec capture
  d'ecran de chacune.
- [CHANGELOG.md](CHANGELOG.md) et [ROADMAP.md](ROADMAP.md) pour le detail technique de chaque
  fonctionnalite.

## Contribution

Les contributions sont les bienvenues ! Veuillez lire [CONTRIBUTING.md](CONTRIBUTING.md) pour les directives.

## Licence

GPLv3 - Voir [LICENSE](LICENSE) pour plus de details.
