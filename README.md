# Configuration GLPI Auto

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version: 8.2+](https://img.shields.io/badge/PHP-8.2%2B-8892B0.svg)](https://php.net)
[![GLPI Version: 11.0+](https://img.shields.io/badge/GLPI-11.0%2B-FF6B6B.svg)](https://glpi-project.org)
[![Build Status](https://github.com/parime/Configuration-glpi-auto/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/parime/Configuration-glpi-auto/actions)
[![Latest Release](https://img.shields.io/github/v/release/parime/Configuration-glpi-auto)](https://github.com/parime/Configuration-glpi-auto/releases)

🇫🇷 **Français** | [🇬🇧 English](README.en.md)

<p align="center"><strong>Une instance GLPI neuve, configurée selon les bonnes pratiques ITIL/ISO 27001, en 18 étapes guidées plutôt qu'en jours de réglages manuels.</strong></p>

Configuration GLPI Auto est un plugin pour GLPI qui vise a transformer une installation vierge en une plateforme operationnelle en quelques clics.

Une installation GLPI neuve est une page blanche : aucune entité, aucun calendrier, aucun SLA, aucune catégorie de ticket, aucun modèle. Tout configurer correctement à la main, en respectant les bonnes pratiques ITIL et les exigences ISO 27001, prend typiquement plusieurs jours à un administrateur qui découvre GLPI, avec le risque d'oublier un réglage important (escalade SLA, classification documentaire, droits par site...). Ce plugin condense ce travail en un assistant guidé de 18 étapes : vous répondez à des questions sur votre organisation, l'assistant construit la configuration correspondante, et rien n'est créé dans GLPI avant que vous ne validiez le récapitulatif final.

> Voir [CHANGELOG.md](CHANGELOG.md) pour l'historique des versions publiées (badge « Latest
> Release » ci-dessus pour la dernière en date) et [ROADMAP.md](ROADMAP.md) pour ce qui est prévu.

📖 **[Voir le tutoriel complet](docs/TUTORIAL.md)** : les 18 étapes de l'assistant, une capture
d'écran par étape (disponible en français et en anglais).

## Table des matieres

- [Ce qui le distingue](#ce-qui-le-distingue)
- [Aperçu](#aperçu)
- [Fonctionnalites](#fonctionnalites)
- [Prerequis](#prerequis)
- [Installation](#installation)
- [Utilisation](#utilisation)
- [Documentation](#documentation)
- [Contribution](#contribution)
- [Licence](#licence)

## Ce qui le distingue

- **Rien n'est créé avant la fin** : les 18 étapes ne font que composer une configuration en mémoire, avec un aperçu qui se met à jour en direct (voir capture ci-dessous) ; vous pouvez revenir en arrière, tout changer, recommencer, sans jamais polluer GLPI avant de valider le récapitulatif final.
- **Un mode express pour les pressés** : un seul clic à l'étape 1 applique directement les réglages recommandés du profil choisi, sans parcourir les 17 étapes suivantes une à une : pour une installation simple, une instance GLPI opérationnelle en quelques secondes.
- **Pensé multi-site et MSP dès le départ** : la même arborescence d'entités, les mêmes SLA et la même personnalisation graphique peuvent être différenciés par site ou par client, sans jongler entre plusieurs installations GLPI séparées.
- **Conformité ISO 27001 intégrée** : rubriques documentaires et niveaux de criticité de la base de connaissances sont proposés dès l'assistant, pas ajoutés après coup en fouillant la documentation GLPI.
- **Zéro donnée orpheline** : chaque réglage proposé (catégories, statuts, modèles...) est directement utilisable : le catalogue de services généré à l'étape 7 route déjà automatiquement vers la bonne catégorie créée à l'étape 6, par exemple.

## Aperçu

**Le choix du profil de départ** : quatre profils prédéfinis pré-remplissent les 17 étapes suivantes avec des valeurs adaptées à votre organisation, ajustables ensuite à volonté ; un mode express applique directement les réglages recommandés sans repasser par chaque étape :

![Étape 1 : Choix du profil](docs/screenshots/01-profil.png)

**La structure d'entités, avec aperçu en direct** : mono-site, multi-site ou MSP : l'arborescence se construit à gauche, l'aperçu à droite se met à jour à chaque changement, avant tout enregistrement :

![Étape 2 : Structure des entités](docs/screenshots/02-entites.png)

Toutes les autres captures d'écran (les 18 étapes en détail) sont dans le [tutoriel](docs/TUTORIAL.md).

## Fonctionnalites

- Assistant graphique en 18 etapes avec barre de progression et un mode express (application
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
cd /chemin/vers/glpi/plugins 
git clone https://github.com/parime/Configuration-glpi-auto.git
mv Configuration-glpi-auto  /chemin/vers/glpi/plugins/configurationglpiauto
cd configurationglpiauto
composer install --no-dev   # vendor/autoload.php est requis au runtime, voir setup.php
```

 Un stack Docker de test (GLPI + MariaDB) est fourni dans
`docker-compose.test.yml`.

## Utilisation

Une fois le plugin active, l'assistant est accessible depuis **Configuration > Profils de
configuration > Configuration**. Choisissez un profil de depart (etape 1), puis parcourez les 18
etapes en ajustant chaque reglage a vos besoins ; rien n'est cree dans GLPI avant la derniere
etape (Recapitulatif). Le mode express (bouton disponible des l'etape 1) applique directement les
reglages recommandes du profil choisi, sans repasser par chaque etape. Voir le
[tutoriel](docs/TUTORIAL.md) pour une capture d'ecran de chaque etape.

## Documentation

- [Tutoriel](docs/TUTORIAL.md) : parcours pas a pas des 18 etapes de l'assistant, avec capture
  d'ecran de chacune.
- [CHANGELOG.md](CHANGELOG.md) et [ROADMAP.md](ROADMAP.md) pour le detail technique de chaque
  fonctionnalite.

## Contribution

Les contributions sont les bienvenues ! Veuillez lire [CONTRIBUTING.md](CONTRIBUTING.md) pour les directives.

## Licence

GPLv3 - Voir [LICENSE](LICENSE) pour plus de details.
