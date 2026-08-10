# Feuille de Route - Configuration GLPI Auto

> Ce document présente la vision stratégique et le plan de développement du plugin Configuration GLPI Auto.
> Pour les détails techniques des versions à venir, voir [CHANGELOG.md](CHANGELOG.md).

---

## 🎯 Vision du Projet

Devenir **la référence Open Source** pour l'initialisation, la standardisation et l'industrialisation des déploiements GLPI.

**Objectif principal** : Permettre à toute organisation de configurer une instance GLPI complète et professionnelle en moins de 5 minutes, selon les bonnes pratiques GLPI et ITIL.

---

## 📅 Versions et Calendrier

### 🚧 Version 1.0 - **En développement**

> Le contenu ci-dessous décrivait initialement la v1.0 comme "disponible" avec l'ensemble de ces
> fonctionnalités cochées. En réalité, jusqu'au Sprint 1 (2026-08-10), le dépôt ne contenait qu'un
> squelette de documentation : aucune des fonctionnalités listées n'était implémentée, et le
> plugin n'était pas installable (classes manquantes, `composer.json` invalide). Le Sprint 1 a
> remis les fondations d'aplomb (installation/désinstallation réelles, catalogue de profils de
> configuration avec CRUD complet, droits dédiés) et validé le tout contre une vraie instance GLPI
> 11.0.8. Les cases ci-dessous ne seront cochées qu'au fur et à mesure de leur implémentation
> réelle — voir CHANGELOG.md pour le détail sprint par sprint.

**Premières releases réelles** : `v0.1.0` (2026-08-10, Sprints 1-2) — voir
[Releases GitHub](https://github.com/parime/Configuration-glpi-auto/releases) et
[CHANGELOG.md](CHANGELOG.md).

**Fonctionnalités prévues** :
- ✅ Catalogue de profils de configuration (CRUD, Sprint 1)
- ✅ Réglages de structure d'entités — mono/multi/MSP, aperçu en temps réel (Sprint 2)
- ⬜ Assistant graphique (Wizard)
- ⬜ Moteur de déploiement (application effective d'un profil sur une instance)
- ⬜ Calendriers intelligents
- ⬜ SLA et OLA prédéfinis
- ⬜ Branding et personnalisation graphique
- ⬜ Templates pour tickets, problèmes, changements
- ⬜ Catalogue de services complet
- ⬜ Gestion des profils utilisateurs

**Limites identifiées à corriger (Sprint 11, 2026-08-10)** — remontées en testant le wizard, pas
encore implémentées :

- **Calendrier — horaires par jour + coupure déjeuner.** `CalendarBuilder` ne construit
  aujourd'hui qu'une seule plage horaire (`calendar_begin`/`calendar_end`), appliquée
  uniformément à tous les jours cochés. Impossible d'avoir des horaires différents par jour
  (ex : vendredi 9h-12h seulement) ou une coupure déjeuner (9h-12h puis 13h-18h) — chaque
  entreprise a des horaires différents et doit pouvoir les saisir librement. Nécessite de
  remplacer le couple begin/end unique par un ou plusieurs segments par jour.

- **Multi-entité "même entreprise" vs "MSP" — purement cosmétique aujourd'hui.**
  `Config::MODE_MULTI_SAME_COMPANY` et `Config::MODE_MULTI_MSP` ne sont lus nulle part dans
  `EntityBuilder`, `CalendarBuilder`, `SlaBuilder` ou `BrandingBuilder` (vérifié par recherche
  dans `src/`) — seul le bouton radio pré-coché à l'étape 1 du wizard change. Pour que la
  distinction ait un sens réel, le mode MSP doit entraîner un traitement différent :
  - calendrier et SLA propres à chaque client/site — **en cours (Sprint 13)**, étendu à tout mode
    multi-entité (pas seulement MSP) suite au retour utilisateur ;
  - logo/couleur de personnalisation différents par client (`BrandingBuilder` applique
    aujourd'hui une seule couleur à toutes les entités créées) — toujours pas fait ;
  - cloisonnement des droits entre entités clientes (un client MSP ne doit pas voir les tickets
    d'un autre) — alors qu'une même entreprise multi-site partage plus naturellement
    calendrier/SLA/branding et peut vouloir une visibilité croisée entre sites — toujours pas fait.

- **SLA plat (un seul TTO/TTR) au lieu de SLA par priorité — confirmé par recherche
  (2026-08-10).** Vérifié par recherche web (voir sources dans l'historique de conversation,
  ITIL 4 priority matrix, GLPI Service Levels documentation officielle) : en pratique ITSM
  réelle, les SLA sont quasi-systématiquement définis **par niveau de priorité** (P1 Critique →
  P4 Faible), pas un seul couple prise-en-charge/résolution pour tous les tickets — un P1 peut
  avoir 15 min de prise en charge quand un P4 en a 1 jour ouvré. GLPI calcule déjà nativement une
  Priorité par ticket (matrice Urgence × Impact configurable, Setup > Général > Assistance), et
  sa façon documentée d'assigner un SLA est justement une règle métier qui matche sur `priority`
  — exactement le mécanisme `RuleTicket`/`RuleCriteria`/`RuleAction` que `SlaBuilder.php` utilise
  déjà pour matcher sur `entities_id`, il "suffit" d'ajouter un critère `priority` en plus.
  Sprint dédié à prévoir (après Sprint 13, pour ne pas changer de modèle de données SLA en cours
  de route) : UI de saisie d'un SLA par palier de priorité (probablement 4 paliers), au lieu d'un
  couple TTO/TTR unique par client/site.

  Piste de conception proposée par l'utilisateur (2026-08-10, à affiner à l'ouverture du sprint) :
  un jeu de valeurs par défaut par priorité (P1-P4), basé sur les pratiques les plus courantes
  trouvées en recherche. En mode multi-site/multi-client par-client (Sprint 13), chaque
  site/client a une case "SLA par défaut" : cochée → applique le jeu de valeurs par défaut à ce
  client ; décochée → l'admin définit ses propres délais par priorité pour ce client spécifique
  (UI d'aide à la saisie, pas juste 4 champs vides). Réutilise le même schéma toggle-par-entité
  déjà en place pour le calendrier/SLA par client (Sprint 13), en remplaçant le couple TTO/TTR
  plat par un tableau à 4 lignes.

**Priorités court terme (retour utilisateur, 2026-08-10)** : le CSS/branding (étape 5) reste de la
décoration, ce n'est pas prioritaire — quand on y reviendra, prévoir un aperçu en direct plutôt
qu'une simple case couleur. En attendant, prioriser : les intitulés/libellés du wizard, les
catégories de tickets ITIL (incident/demande/problème/changement), et les templates de tickets.

---

### 🚀 Version 1.1 - **En Développement**

**Prévue** : Q4 2026

**Nouvelles fonctionnalités** :
- 🎯 **Mode Audit avancé**
  - Analyse complète de l'instance existante
  - Détection automatique des problèmes de configuration
  - Recommandations intelligentes
  - Correction automatique proposée

- 📦 **Système de Blueprints**
  - Export complet de la configuration
  - Format JSON standardisé
  - Création de bibliothèque de Blueprints
  - Partage entre instances
  - Import de configurations prédéfinies

- ⏪ **Fonctionnalité de Rollback**
  - Restauration complète des configurations précédentes
  - Historique des déploiements
  - Gestion des points de sauvegarde
  - Rollback sélectif par module

- 🎭 **Mode Dry Run amélioré**
  - Simulation complète avant déploiement
  - Visualisation des impacts
  - Estimation du temps d'exécution
  - Validation pré-deploiement

---

### 🌟 Version 1.2 - **Planifiée**

**Prévue** : Q2 2027

**Fonctionnalités avancées** :
- 🏪 **Marketplace de configurations**
  - Téléchargement de profils prédéfinis
  - Partage communautaire
  - Notation et commentaires
  - Mises à jour automatiques

- 📤 **Import/Export avancé**
  - Export sélectif par catégorie
  - Planification des imports
  - Validation automatique
  - Conflict resolution

- 👥 **Profils communautaires**
  - Partage de configurations entre utilisateurs
  - Catalogue de profils certifiés
  - Adaptation automatique aux besoins spécifiques

---

### 🎓 Version 1.3 - **À l'étude**

**Prévue** : Q4 2027

**Intégrations d'entreprise** :
- 📧 **Assistant Microsoft 365**
  - Configuration automatique de l'intégration
  - Synchronisation des utilisateurs
  - Gestion des licences

- 🔐 **Intégration LDAP/Active Directory**
  - Configuration simplifiée
  - Synchronisation automatique
  - Gestion des groupes

- 📮 **Configuration SMTP avancée**
  - Tests de connectivité
  - Configuration sécurisée
  - Gestion des certificats

- 🔑 **Authentification SSO**
  - Support SAML
  - Intégration OAuth2
  - Configuration simplifiée

---

### 🏆 Version 1.4 - **Vision Long Terme**

**Prévue** : 2028

**Fonctionnalités expertes** :
- 📋 **Assistant ISO27001 complet**
  - Audit de conformité automatique
  - Génération de documentation
  - Recommandations de sécurité

- 🎯 **Guide ITIL complet**
  - Implémentation des bonnes pratiques
  - Workflows ITIL prédéfinis
  - Métriques et rapports ITIL

- ✨ **Bonnes pratiques automatiques**
  - Analyse continue de la configuration
  - Suggestions d'amélioration
  - Optimisation automatique

---

### 🚀 Version 2.0 - **Vision Stratégique**

**Prévue** : 2029

**Plateforme complète** :
- 🔄 **API REST complète**
  - Accès programmatique à toutes les fonctionnalités
  - Webhooks pour l'intégration temps réel
  - Documentation Swagger/OpenAPI

- 🔗 **Synchronisation multi-instances**
  - Gestion centralisée de plusieurs instances GLPI
  - Synchronisation des configurations
  - Réplication des données

- 🏪 **Marketplace communautaire**
  - Écosystème de plugins et configurations
  - Système de notation et de confiance
  - Monétisation pour les créateurs

- 🤖 **Assistant IA intégré**
  - Recommandations intelligentes basées sur l'IA
  - Analyse prédictive des problèmes
  - Optimisation automatique de la configuration

- 🎯 **Recommandations automatiques**
  - Moteur de recommandations temps réel
  - Apprentissage des meilleures pratiques
  - Adaptation aux spécificités de l'organisation

- 📊 **Analyse continue**
  - Surveillance en temps réel
  - Alertes proactives
  - Rapports d'optimisation

---

## 🎯 Objectifs à Long Terme

### 1. **Certification Officielle GLPI**
- Obtenir la certification sur la marketplace officielle des plugins GLPI
- Devenir le plugin de référence pour la configuration automatique
- Intégration native dans les futures versions de GLPI

### 2. **Écosystème Communautaire**
- Créer une communauté active de contributeurs
- Développer un système de plugins pour Configuration GLPI Auto
- Organiser des événements et ateliers

### 3. **Intégrations Étendues**
- Support des principaux systèmes de gestion IT
- Intégration avec les outils DevOps
- Connecteurs pour les solutions cloud

### 4. **Intelligence Artificielle**
- Implémenter des algorithmes d'IA pour l'optimisation
- Développer un système de recommandations prédictif
- Intégrer l'apprentissage automatique pour l'amélioration continue

---

## 📊 Indicateurs de Succès

### Version 1.0
- [x] Structure de base complète
- [x] Fonctionnalités principales implémentées
- [x] Tests unitaires complets
- [x] Documentation complète
- [x] CI/CD opérationnelle

### Version 1.1
- [ ] Mode Audit fonctionnel
- [ ] Blueprints implémentés
- [ ] Rollback opérationnel
- [ ] Dry Run amélioré
- [ ] Tests d'intégration complets

### Version 1.2
- [ ] Marketplace fonctionnel
- [ ] Import/Export avancé
- [ ] Profils communautaires
- [ ] Tests de bout en bout
- [ ] Documentation utilisateur complète

---

## 🤝 Comment Contribuer

La feuille de route est ouverte aux contributions ! Voici comment aider :

1. **Proposer une fonctionnalité** : Créez une issue avec le label `enhancement`
2. **Développer une fonctionnalité** : Forker le projet et soumettre une PR
3. **Tester les fonctionnalités** : Participer aux tests bêta
4. **Documenter** : Aider à améliorer la documentation
5. **Traduire** : Contribuer aux traductions

---

## 📋 Priorités de Développement

| Priorité | Catégorie | Statut |
|----------|----------|--------|
| 🔴 Haute | Fonctionnalités principales | En cours |
| 🟡 Moyenne | Fonctionnalités avancées | Planifié |
| 🟢 Basse | Améliorations mineures | Backlog |
| ⚫ Urgente | Corrections de bugs | Immédiat |
| 🟣 Sécurité | Corrections de sécurité | Immédiat |

---

## 🔗 Liens Utiles

- [GitHub Project](https://github.com/parime/Configuration-glpi-auto/projects) - Suivi du développement
- [GitHub Milestones](https://github.com/parime/Configuration-glpi-auto/milestones) - Objectifs par version
- [GitHub Issues](https://github.com/parime/Configuration-glpi-auto/issues) - Bugs et fonctionnalités
- [GitHub Discussions](https://github.com/parime/Configuration-glpi-auto/discussions) - Questions et idées

---

## 📝 Notes de Version Détailées

Pour les détails complets de chaque version, voir :
- [CHANGELOG.md](CHANGELOG.md) - Historique des modifications
- [Releases GitHub](https://github.com/parime/Configuration-glpi-auto/releases) - Archives des versions

---

> **Note** : Cette feuille de route est évolutive et peut être ajustée en fonction des retours de la communauté, des priorités du projet et des opportunités qui se présentent.

> Dernière mise à jour : 7 Août 2026
