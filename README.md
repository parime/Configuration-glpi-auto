# Configuration GLPI Auto

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version: 8.2+](https://img.shields.io/badge/PHP-8.2%2B-8892B0.svg)](https://php.net)
[![GLPI Version: 11.0+](https://img.shields.io/badge/GLPI-11.0%2B-FF6B6B.svg)](https://glpi-project.org)
[![Build Status](https://github.com/parime/Configuration-glpi-auto/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/parime/Configuration-glpi-auto/actions)
[![Latest Release](https://img.shields.io/github/v/release/parime/Configuration-glpi-auto)](https://github.com/parime/Configuration-glpi-auto/releases)

Configuration GLPI Auto est un plugin revolutionnaire pour GLPI qui transforme une installation vierge en une plateforme operationnelle en quelques clics.

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

### Via Composer
```bash
cd /chemin/vers/glpi/plugins
composer require parime/configuration-glpi-auto
```

### Installation manuelle
1. Telechargez le plugin depuis [GitHub Releases](https://github.com/parime/Configuration-glpi-auto/releases)
2. Extrayez dans le dossier plugins/ de GLPI
3. Renommez le dossier en 'configurationglpiauto'
4. Installez et activez via l'interface GLPI

## Documentation

- [Guide complet](docs/user-guide.md)
- [Documentation developpeur](docs/development/architecture.md)
- [API Reference](docs/api/index.md)

## Contribution

Les contributions sont les bienvenues ! Veuillez lire [CONTRIBUTING.md](CONTRIBUTING.md) pour les directives.

## Licence

GPLv3 - Voir [LICENSE](LICENSE) pour plus de details.
