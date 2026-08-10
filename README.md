# Configuration GLPI Auto

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version: 8.2+](https://img.shields.io/badge/PHP-8.2%2B-8892B0.svg)](https://php.net)
[![GLPI Version: 11.0+](https://img.shields.io/badge/GLPI-11.0%2B-FF6B6B.svg)](https://glpi-project.org)
[![Build Status](https://github.com/parime/Configuration-glpi-auto/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/parime/Configuration-glpi-auto/actions)
[![Latest Release](https://img.shields.io/github/v/release/parime/Configuration-glpi-auto)](https://github.com/parime/Configuration-glpi-auto/releases)

Configuration GLPI Auto est un plugin pour GLPI qui vise a transformer une installation vierge en une plateforme operationnelle en quelques clics.

> **Etat du projet (2026-08-10)** : en developpement actif, pas encore publie. La liste de
> fonctionnalites ci-dessous decrit la vision du plugin ; seul le catalogue de profils de
> configuration (CRUD) est reellement implemente a ce stade (Sprint 1). Voir
> [CHANGELOG.md](CHANGELOG.md) et [ROADMAP.md](ROADMAP.md) pour l'etat sprint par sprint.

## Table des matieres

- [Fonctionnalites](#fonctionnalites)
- [Prerequis](#prerequis)
- [Installation](#installation)
- [Utilisation](#utilisation)
- [Documentation](#documentation)
- [Contribution](#contribution)
- [Licence](#licence)

## Fonctionnalites

- Assistant graphique moderne avec barre de progression
- Plusieurs profils predefinis (PME, ETI, Grande entreprise, MSP, ISO 27001, ITIL)
- Configuration automatique des entites, SLA, calendriers, catalogues de services
- Mode Audit pour analyser les instances existantes
- Export/Import de configurations via Blueprints
- Assistant intelligent pour la creation des lieux avec geocodage
- Support multilingue (Francais, Anglais)

## Prerequis

- PHP 8.2+
- GLPI 11.0+
- Base de donnees: MySQL 5.7+, MariaDB 10.2+, PostgreSQL 9.6+

## Installation

Aucune release publiee pour l'instant (voir "Etat du projet" ci-dessus) — pas de package Composer,
pas de release GitHub. Pour tester en local :

```bash
git clone https://github.com/parime/Configuration-glpi-auto.git
cd Configuration-glpi-auto
composer install --no-dev   # vendor/autoload.php est requis au runtime, voir setup.php
```

Puis copiez/liez le dossier dans `plugins/configurationglpiauto` d'une instance GLPI 11, et
installez/activez via `bin/console plugin:install|activate configurationglpiauto` ou l'interface
GLPI. Un stack Docker de test (GLPI + MariaDB) est fourni dans `docker-compose.test.yml`.

## Documentation

Pas encore de documentation utilisateur/API dediee — a venir au fur et a mesure des sprints (voir
[ROADMAP.md](ROADMAP.md)).

## Contribution

Les contributions sont les bienvenues ! Veuillez lire [CONTRIBUTING.md](CONTRIBUTING.md) pour les directives.

## Licence

GPLv3 - Voir [LICENSE](LICENSE) pour plus de details.
