# Configuration-glpi-auto
Configurez automatiquement une instance GLPI neuve en quelques clics. Créez modèles, SLA, calendriers, entités, catalogue de services, actifs personnalisés, personnalisation graphique, étiquettes et bien plus. Choisissez les modules à déployer, le plugin s'occupe du reste selon les bonnes pratiques GLPI.

# Cahier des charges – GLPI Foundation

## Contexte

Tu es un développeur senior PHP, expert de GLPI 11, de son architecture interne, de son système de plugins et des bonnes pratiques Open Source.

Ta mission est de concevoir **GLPI Foundation**, un plugin Open Source permettant de transformer automatiquement une instance GLPI fraîchement installée en une plateforme complète, opérationnelle et conforme aux bonnes pratiques GLPI et ITIL.

Ce projet doit être pensé comme un **projet Open Source professionnel**, et non comme un simple plugin.

Le résultat attendu est un dépôt GitHub complet, prêt à être publié publiquement.

---

# Références obligatoires

Tu dois systématiquement t'appuyer sur les recommandations officielles suivantes.

## Développement GLPI

https://glpi-developer-documentation.readthedocs.io/en/master/plugins/index.html

https://glpi-plugins.readthedocs.io/fr/latest/example/

https://github.com/pluginsGLPI/example

https://github.com/glpi-project/glpi

## Documentation utilisateur

https://help.glpi-project.org/

https://glpi-user-documentation.readthedocs.io/

## Dépôt servant de référence pour la qualité du projet

https://github.com/parime/remise-glpi

Tu dois reprendre la même philosophie de développement, la même ergonomie, la même qualité de code, la même qualité de documentation et la même qualité de CI/CD.

Si tu identifies des améliorations possibles par rapport à ce dépôt, tu dois les implémenter.

---

# Objectif

Après installation du plugin, l'administrateur doit disposer d'un assistant graphique ("Wizard").

Le Wizard doit guider l'utilisateur étape par étape.

L'utilisateur choisit uniquement les modules qu'il souhaite déployer.

Le plugin effectue ensuite automatiquement toute la configuration de GLPI.

Objectif :

Transformer une installation vierge de GLPI en une plateforme totalement opérationnelle en moins de cinq minutes.

---

# Interface utilisateur

L'interface doit être moderne.

Elle doit utiliser exclusivement les composants UI officiels de GLPI.

Le design doit être inspiré du plugin Remise GLPI.

Le Wizard doit comporter les étapes suivantes :

* Accueil
* Choix du profil
* Choix des modules
* Configuration
* Prévisualisation
* Validation
* Déploiement
* Rapport final

Chaque étape doit afficher une barre de progression.

Chaque action doit être expliquée.

Un mode sombre doit être compatible.

---

# Profils de déploiement

Prévoir plusieurs profils.

Installation minimale

PME

ETI

Grande entreprise

MSP

ISO 27001

ITIL

Personnalisé

Chaque profil active automatiquement les modules adaptés.

---

# Modules

Chaque module doit être totalement indépendant.

L'ajout d'un nouveau module doit être très simple.

Modules minimum :

## Configuration générale

Paramètres généraux

Tickets

Problèmes

Changements

Tâches

Notifications

Emails

Authentification

Profils

Règles

Actions automatiques

Intitulés

Sources

Types

Priorités

Statuts

Niveaux de service

Templates

## Calendriers

Création

Horaires

Fuseaux horaires

Import des jours fériés selon le pays

Fermetures exceptionnelles

Vacances

## SLA

SLA

OLA

Escalades

Matrice de priorité

Objectifs

## Entités

Assistant de création

Création d'arborescence

Import CSV

Prévisualisation

## Catalogue de services

Création complète

Incidents

Demandes

Matériel

Logiciels

Téléphonie

Accès

Réseau

Serveurs

Messagerie

RH

## Templates

Tickets

Problèmes

Changements

Tâches

Solutions

Emails

Validations

## Actifs personnalisés

Compatibilité Generic Objects

Compatibilité Fields

Création automatique des objets

Création des champs

Création des catégories

## Branding

Import du logo

Favicon

Couleurs

Variables CSS

Page de connexion

Personnalisation graphique

## Plugins

Détection automatique des plugins installés.

Si un plugin est présent :

Configurer automatiquement :

Tag

Fields

Generic Objects

Dashboard

PDF

Behaviors

Formcreator

Archires

Et tout autre plugin reconnu.

---

# Mode Audit

Le plugin doit analyser une instance existante.

Détecter :

Absence de SLA

Absence de calendrier

Notifications mal configurées

Profils incomplets

Catégories manquantes

Catalogue absent

CSS par défaut

Plugins installés mais non utilisés

Proposer une correction automatique.

---

# Blueprints

Pouvoir exporter entièrement une configuration.

Créer un Blueprint JSON.

Importer un Blueprint.

Partager un Blueprint.

Créer une bibliothèque de Blueprints.

---

# Dry Run

Avant toute modification :

Afficher exactement :

Nombre d'objets créés

Nombre d'objets modifiés

Nombre d'objets supprimés

Temps estimé

Aucune modification réelle.

---

# Sauvegarde

Avant chaque déploiement.

Créer une sauvegarde logique.

Rollback complet.

Historique.

Journal détaillé.

---

# Rapports

HTML

PDF

JSON

Temps d'exécution

Objets créés

Objets modifiés

Erreurs

Avertissements

---

# Marketplace

Créer un système permettant d'importer des profils prédéfinis.

Exemples :

PME

MSP

ISO27001

ITIL

Education

Collectivité

Santé

Industrie

---

# Recommandations intelligentes

Créer un moteur de recommandations.

Exemples :

Vous n'avez aucun SLA.

Vous n'avez aucun calendrier.

Vous n'utilisez pas le plugin Tag.

Aucun modèle de ticket.

Notifications incomplètes.

Le plugin doit proposer :

Corriger automatiquement.

---

# Architecture

Architecture modulaire.

PSR-1

PSR-4

PSR-12

SOLID

KISS

DRY

YAGNI

Services

Repository

DTO

Factories

Value Objects

Injection de dépendances

Configuration centralisée

Aucun code mort.

Aucune duplication.

Documentation PHPDoc complète.

Toutes les chaînes doivent être traduisibles.

Français et Anglais minimum.

---

# GitHub

Créer un dépôt GitHub professionnel.

Configurer automatiquement :

.gitignore

.editorconfig

.gitattributes

CODEOWNERS

LICENSE

README

CHANGELOG

ROADMAP

CONTRIBUTING

SECURITY

CODE_OF_CONDUCT

SUPPORTED_GLPI

INSTALL

ARCHITECTURE

DEVELOPMENT

CI-CD

FAQ

TROUBLESHOOTING

BLUEPRINTS

Créer également :

Issue Templates

Bug Report

Feature Request

Pull Request Template

Discussion Templates

GitHub Discussions

GitHub Projects

GitHub Milestones

GitHub Labels

Wiki

GitHub Pages

Badges

---

# CI/CD

Reprendre toute la CI/CD du dépôt :

https://github.com/parime/remise-glpi

L'améliorer si nécessaire.

Mettre en place :

PHPStan

PHP-CS-Fixer

PHP_CodeSniffer

Rector

PHPUnit

Tests fonctionnels

Tests d'intégration

Composer Validate

Composer Audit

CodeQL

Trivy

OSSAR

Scorecard

Dependabot

Secret Scanning

License Checker

Validation YAML

Validation JSON

Validation XML

Détection de code mort

Détection de duplications

Mesure de couverture

Analyse de complexité

Compatibilité PHP

Compatibilité GLPI

Génération automatique de la documentation

Déploiement automatique GitHub Pages

---

# Gestion des versions

Mettre en place :

Conventional Commits

Semantic Versioning

Release Please (ou équivalent)

Création automatique des Releases GitHub

Création automatique du CHANGELOG

Création automatique du ZIP du plugin

Publication automatique des artefacts

Signature des releases si possible

---

# Tests

Créer des tests couvrant tous les modules.

Les GitHub Actions doivent empêcher tout merge si :

les tests échouent

la couverture diminue

la qualité du code baisse

une vulnérabilité critique est détectée

une régression est identifiée

---

# Documentation

Ne jamais créer un README gigantesque.

Créer plusieurs fichiers Markdown spécialisés.

Chaque fonctionnalité importante doit posséder sa propre documentation.

Toutes les captures d'écran doivent être stockées dans docs/images.

Les diagrammes d'architecture doivent être fournis au format Mermaid.

---

# Roadmap

## Version 1.0

Wizard

Configuration de base

Calendriers

SLA

Branding

Templates

Catalogue

Profils

## Version 1.1

Audit

Blueprints

Rollback

Dry Run

## Version 1.2

Marketplace

Import/Export avancé

Profils communautaires

## Version 1.3

Assistant Microsoft 365

LDAP

SMTP

SSO

## Version 1.4

Assistant ISO27001

ITIL

Bonnes pratiques

## Version 2.0

API REST

Synchronisation multi-instances

Marketplace communautaire

Assistant IA

Recommandations automatiques

Analyse continue de la configuration

---

# Objectif final

Ce projet doit devenir la référence Open Source pour l'initialisation, la standardisation et l'industrialisation des déploiements GLPI.

Il doit être conçu avec une qualité suffisante pour pouvoir être proposé ultérieurement sur la marketplace officielle des plugins GLPI.
