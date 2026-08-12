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

- ✅ **SLA plat (un seul TTO/TTR) au lieu de SLA par priorité — confirmé par recherche
  (2026-08-10), fait en Sprint 14.** Vérifié par recherche web (voir sources dans l'historique de conversation,
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

**Audit de complétude — bonnes pratiques ITIL/ISO27001/GLPI (2026-08-10)**

Suite à la demande explicite de l'utilisateur ("je veux une configuration complète de GLPI dans le
respect des bonnes pratiques, dis-moi ce qui manque") : recherche sur les pratiques recommandées
pour la configuration initiale d'un outil ITSM comme GLPI, ITIL 4 et ISO 27001. Ce que couvre déjà
le wizard vs ce qui manque, avec le pourquoi de chaque manque — pas de jugement de priorité fait
unilatéralement, à trancher avec l'utilisateur.

*Déjà couvert par le wizard* : structure d'entités (mono/multi-site/MSP), calendrier (partagé ou
par site/client), SLA par niveau de priorité (partagé ou par site/client, Sprint 14), branding
basique, profils de démarrage.

*Manques identifiés, par ordre approximatif d'impact ITIL* :

1. **OLA (Operational Level Agreement) — fait (Sprint 14, 2026-08-10).**
   Engagement interne entre le helpdesk et les équipes support, qui vient épauler le SLA externe
   (ex : SLA "résolution sous 4h" au client ⇒ OLA interne "niveau 1 trie sous 30 min, niveau 2
   diagnostique sous 2h"). Implémenté dans `SlaBuilder` (même classe que le SLA externe, avec
   `sla_astreinte` pour la couverture 24/7), quasi symétrique à SLA côté GLPI (`OLA` étend la
   même classe `LevelAgreement` que `SLA`, `glpi_olas`, `olas_id_tto`/`olas_id_ttr` sur les
   tickets, même moteur `RuleTicket`).

2. **Catégories de tickets + types ITIL (Incident/Demande/Problème/Changement) — fait (Sprint 17,
   2026-08-10).** ITIL 4 distingue 4 types de ticket avec des pratiques de gestion différentes
   (Incident Management, Request Management, Problem Management, Change Management) — GLPI a
   nativement `ITILCategory` et un champ `type` sur les tickets. `CategoryBuilder` construit une
   arborescence thématique réelle (IT, Bâtiment, Flotte, RH...) plutôt qu'une catégorie par type
   ITIL (le type Incident/Demande est déjà géré nativement par GLPI, une catégorie par type
   n'apportait rien — voir Sprint 17 dans le CHANGELOG).

3. **Templates de tickets — fait (Sprint 19, 2026-08-10).** Pas un template par catégorie au
   final (`TicketTemplateBuilder`) : la pratique ITSM courante réserve ça au catalogue de services
   (point 5, pas encore fait) ; à la place, deux templates par audience — un simplifié
   (titre+description) pour les profils sans droits élevés (Self-Service, Read-Only), un complet
   (catégorie+urgence obligatoires, rien de masqué) pour le reste — câblés via
   `glpi_profiles.tickettemplates_id`, un mécanisme natif GLPI par profil.

4. **Niveaux d'escalade SLA/OLA (`SlaLevel`/`OlaLevel`) — fait (Sprint 28, 2026-08-11 ; complété
   Sprint 34, 2026-08-12).** `SlaBuilder` crée un niveau d'escalade par palier de priorité (sauf le
   plus haut) sur la résolution (SLA) et, si activé, l'engagement interne (OLA), déclenché à un
   pourcentage configurable du délai écoulé (75% par défaut) : action = priorité relevée d'un cran
   (Sprint 28). **Sprint 34** lève la limite documentée ici jusqu'ici (« réassignation à un niveau 2
   hors périmètre, faute de savoir quel groupe serait le bon ») : recherche web confirmant N1/N2/N3
   comme convention ITSM standard (pas une invention propre à une organisation), `SupportTierBuilder`
   crée 3 groupes techniciens génériques ("Support N1/N2/N3"), `SlaBuilder` réaffecte automatiquement
   le ticket au niveau suivant (N1→N2 avant échéance, N2→N3 à l'échéance), réglable globalement et
   par client/site, chaque hop indépendamment activable. Le moteur natif GLPI (`SlaLevel`/`OlaLevel`,
   CronTasks `slaticket`/`olaticket`, actifs par défaut) s'en charge une fois les niveaux créés,
   aucun câblage supplémentaire nécessaire.

5. **Catalogue de services — fait (Sprint 23, 2026-08-11).** `ServiceCatalogBuilder`, sur le
   système natif de formulaires de GLPI 11 (`Glpi\Form\Form`) : 23 services sur 7 branches, chacun
   ne demandant que titre + description, routé automatiquement vers la bonne catégorie de ticket
   sans que l'utilisateur ait à la choisir. Validé de bout en bout avec un vrai compte Self-Service.

6. **Droits/profils GLPI par entité — cloisonnement — fait partiellement (Sprint 26, 2026-08-11).**
   `RuleRightBuilder` scaffolde une `RuleRight` par site (feuille de l'arborescence) : GLPI
   affecte automatiquement l'entité + un profil fixe à un utilisateur d'après son groupe AD/LDAP
   lors de la synchronisation — mécanisme confirmé sur un vrai export de production (37 règles
   `RuleRight` réelles), généralisé via un gabarit de nom de groupe configurable plutôt que les
   noms d'AD réels de l'export. Ne sert que si une synchronisation LDAP est prévue par
   l'organisation — sans ça, cette étape ne crée rien d'utile, ce qui est le comportement attendu.
   Restent hors périmètre (pattern différent, pas confirmé avec l'utilisateur) : l'affectation
   d'un profil global par fonction métier (ex. "Finance"/"DSI" → profil, indépendamment du site) et
   la gestion fine des droits par module au sein d'un même profil.

7. **Modèles de notifications — fait (Sprint 25, 2026-08-11).** GLPI a déjà de bons modèles par
   défaut ; le vrai manque était que plusieurs notifications de cycle de vie du ticket sont
   `is_active=0` d'origine — dont `Ticket`/`auto_reminder`, exactement celle que déclenchent les
   relances automatiques du Sprint 24 (bug réel corrigé au passage : les relances étaient créées
   mais jamais notifiées au demandeur).

8. **Workflow de validation (approbation) — fait partiellement (Sprint 25, 2026-08-11).** Ajout
   d'une étape "Validation comité (2/3)" en plus de la "Validation" (100%) native, pour les
   décisions collégiales. Le routage automatique vers un valideur (ex : manager N+1) reste hors
   périmètre — dépend d'une hiérarchie LDAP/organisationnelle propre à chaque instance.

9. **ISO 27001 — journalisation et piste d'audit.** La norme exige que les logs de sécurité
   couvrent qui/quoi/quand/où/comment (authentification, changements de droits, changements de
   configuration). GLPI a un module Journaux natif qui couvre une bonne partie de ça
   automatiquement sans configuration — à vérifier si la rétention/le niveau de détail par défaut
   suffit, plutôt qu'à reconstruire quoi que ce soit.

10. **Enquêtes de satisfaction post-résolution — fait (Sprint 25, 2026-08-11).** L'enquête native
    GLPI (1 à 5 étoiles + commentaire) était techniquement "activée" mais avec un taux
    d'échantillonnage à 0% — traité par GLPI comme entièrement désactivé. Activée à 100%. Une
    enquête multi-questions plus riche nécessiterait un outil externe (`inquest_config` = externe +
    URL), hors périmètre car dépendant de l'outil choisi par chaque organisation.

---

## 🔎 Troisième audit — inventaire complet des "Intitulés" (2026-08-11)

Après les deux audits précédents (liste ci-dessus, et l'export de production Sprints 22-28), un
troisième passage plus systématique : la page GLPI Configuration > Intitulés recense *tous* les
types d'objets pré-paramétrables, catégorie par catégorie. Extrait directement de l'instance de
test réelle (pas deviné), pour ne rien manquer. État par catégorie GLPI (pas de jugement de
priorité — à trancher avec l'utilisateur comme les audits précédents) :

**Assistance** (19 intitulés) — déjà fait : Gabarits de tickets (`TicketTemplateBuilder`),
Catégories ITIL (`CategoryBuilder`), Raisons d'attente (`WaitReasonBuilder`), Catégories du
catalogue de services (`ServiceCatalogBuilder`), Étapes de validation (`GeneralSettingsBuilder`).
**Fait (Sprint 29, 2026-08-11)** : Gabarits de changement/problème (`ChangeProblemTemplateBuilder`
— un modèle standard chacun, assigné à tous les profils ; pas de split base/support comme les
tickets, Self-Service n'a par défaut aucun droit sur Change/Problem), Catégories de tâches
(`TaskCategoryBuilder`, 14 catégories), Gabarits de tâche (`TaskTemplateBuilder`, 3 checklists
réutilisables), Types de solutions + vraie bibliothèque de gabarits de solution
(`SolutionLibraryBuilder`, 5 types × 2 gabarits, taxonomie de clôture ITIL générique), vraie
bibliothèque de gabarits de suivis (`FollowupLibraryBuilder`, 5 gabarits, distincts par nom de
ceux liés aux raisons d'attente), Gabarits de validation (`ValidationTemplateBuilder`, 5
gabarits). Sources des demandes (`RequestType`) — **vérifié, déjà suffisant** : GLPI ships 6
valeurs par défaut (Helpdesk/E-Mail/Phone/Direct/Written/Other), rien à construire. **Fait
(Sprint 30, 2026-08-11)** : Types de projet + types de tâche de projet
(`ProjectTaxonomyBuilder`, 5 + 8, généralisés sur la pratique PM standard — l'export de production
n'avait pas personnalisé ce point, seulement les statuts natifs), Gabarits de tâches de projets
(`ProjectTaskTemplateBuilder`, 3 checklists réutilisables). Statuts de projet
(`ProjectState`) — **vérifié, déjà suffisant** : GLPI ships les 3 statuts natifs
(New/Processing/Closed), non personnalisés non plus dans l'export de production audité — pas de
bonne pratique universelle identifiée pour en ajouter d'autres, `GeneralSettingsBuilder` continue
de se contenter de les *mapper* pour le suivi d'avancement des tâches. Pas fait, priorité basse
(usage assez spécifique) : Gabarits d'évènements externes (`PlanningExternalEventTemplate`),
Catégories d'évènements (`PlanningEventCategory`).

**Général** (5 intitulés) — fait : Statuts des éléments (`StateBuilder`). **Fait (Sprint 31,
2026-08-11)** : Lieux (`LocationBuilder` — mirroir de l'arborescence d'entités de l'étape 2, pas
une liste inventée : un `Location` par entité, même nom, même imbrication, scopé à l'entité
réelle), Fabricants (`ManufacturerBuilder`, ~29 fabricants IT/bureautique courants). Pas prévu
(sécurité anti-spam, pas de bonne pratique universelle à préremplir) : Listes noires
(`Blacklist`), Contenu de mail interdit (`BlacklistedMailContent`).

**Outils** — **fait (Sprint 31, 2026-08-11)** : Catégories de la base de connaissances
(`KnowbaseCategoryBuilder`) — réutilise les 11 thèmes de `CategoryBuilder` (étape 5) plutôt
qu'une seconde taxonomie inventée, filtré sur les branches effectivement sélectionnées.

**Gestion** — pas fait, priorité basse (gestion documentaire/actifs, pas Assistance) : Rubriques
des documents, Types de documents, Criticités.

**Règles** (`Configuration > Règles`) — fait : Règles d'affectation d'habilitations à un
utilisateur (`RuleRight`, Sprint 27). Étudié et volontairement pas fait : règles métier
tickets/changements/problèmes (`RuleTicket`/`RuleChange`/`RuleProblem`) — logique de routage/
priorisation propre à chaque organisation, inventer des règles arbitraires serait pire que ne
rien faire (même raisonnement que le "niveau 2" laissé de côté aux Sprints 27/28) ; règles
d'affectation de catégorie aux logiciels (`RuleSoftwareCategory`) — gestion d'actifs, hors
périmètre Assistance.

**Dictionnaires** — étudié et volontairement pas fait : couvrent exclusivement la normalisation
de données d'inventaire déjà importées (logiciels, fabricants, modèles/types de matériel,
systèmes d'exploitation — confirmé en listant la page réelle, aucun lien avec l'Assistance).
Sur un GLPI neuf sans inventaire, il n'y a rien à normaliser, et sans données réelles désordonnées
à calibrer, générer des règles regex de départ reviendrait à deviner — risque de mal normaliser
les vraies données plus tard. Format confirmé dans `RuleAction.php` (groupes de capture
référencés `#0`, `#1`... dans le champ de remplacement) si le sujet revient avec de vrais
exemples à traiter.

**Décision utilisateur (2026-08-11)** : traiter tout le bloc Assistance + Général/Outils listés
comme "pas fait" ci-dessus. Découpé en plusieurs sprints vu le volume — voir CHANGELOG.md pour
l'avancement réel sprint par sprint (ce document décrit l'état au moment de l'audit, pas l'état
courant). Sprint 29 (cycle de vie ticket/tâche/changement/problème), Sprint 31 (Général/Outils)
et Sprint 30 (Projets) sont faits — **la troisième vague d'audit est close**. Restent en attente,
non cadrés techniquement : les intitulés basse-priorité explicitement laissés de côté ci-dessus
(gabarits/catégories d'évènements planning), et le logo par entité (voir plus bas).

**Bug corrigé au passage (Sprint 31, 2026-08-11)** : `Config::prepareInput()`'s traitement de
`category_branches` castait `(array)` une chaîne JSON au lieu de la décoder — sur une instance
vraiment neuve (jamais soumise via le formulaire), `getDefaults()` fournit `category_branches`
sous forme de chaîne JSON, pas de tableau PHP, donc chaque nouvelle installation démarrait
silencieusement avec 0 branche sélectionnée à l'étape 5 au lieu des 11 documentées. Resté invisible
tant que l'instance de test accumulait des soumissions réelles (le tableau PHP écrase alors la
chaîne) — révélé par la remise à zéro complète de l'environnement demandée cette session. Corrigé :
`prepareInput()` décode maintenant explicitement si la valeur reçue est une chaîne.

**Demande complémentaire — fait (2026-08-11)** : personnalisation graphique par entité — un logo
uploadable par client/site (en plus de la couleur principale plugin-wide de `BrandingBuilder`),
visible sur l'entité correspondante. Confirmé en source qu'aucun champ logo natif n'existe sur
`Entity` — le mécanisme retenu (après confirmation avec l'utilisateur) réutilise
`custom_css_code` (déjà natif par entité, déjà utilisé par `BrandingBuilder` pour la couleur) :
le fichier uploadé est encodé en `data:` URI et injecté dans la variable CSS `--glpi-logo`
(confirmée dans le SCSS source de GLPI, `.glpi-logo { background: var(--glpi-logo) no-repeat; }`)
— pas d'upload `Document` séparé à maintenir, pas de sélecteur DOM fragile. `BrandingBuilder`
délimite chaque bloc CSS qu'il écrit par un marqueur de commentaire (`mergeCssBlock()`) pour que
couleur et logo coexistent sans s'écraser l'un l'autre lors des ré-exécutions.

**Demande complémentaire — fait (2026-08-11)** : `StateBuilder` (Sprint 16) créait ses 14 statuts
sans granularité (tout ou rien). Passé en cases à cocher individuelles, chacune activable/
désactivable indépendamment — même principe que `category_branches`. Cinq statuts ("En stock",
"Attribué", "Donné", "Vendu", "Attente restitution") marqués « recommandé » dans l'interface : ce
sont ceux utilisés par le plugin `remise-glpi` (https://github.com/parime/remise-glpi) pour
déclencher automatiquement son propre workflow de remise/don/vente/restitution sur changement
d'État — les décocher ne casse rien (remise-glpi référence un État par ID configuré dans ses
propres réglages, pas par nom exact), mais réduit l'interopérabilité entre les deux plugins si
l'organisation utilise `remise-glpi`. **Bug trouvé et corrigé au passage** : les noms de statuts
accentués ("Attribué", "Obsolète"...) étaient corrompus par la migration `addField()` — l'échappement
JSON `\uXXXX` perdait son antislash en traversant la clause SQL `DEFAULT`, transformant "Attribué"
en "Attribuu00e9". Corrigé en encodant le JSON par défaut avec `JSON_UNESCAPED_UNICODE` (caractères
UTF-8 bruts, aucun antislash à perdre).

---

## 🔍 Quatrième audit — vérification de complétude + recherche marché (fait, 2026-08-12)

**Contexte** : le Sprint 34 (icônes/traductions) puis sa correction v0.20.0 (gabarits de ticket/
changement/problème/solution/tâche/suivi/validation, oubliés puis mal exclus à tort) ont montré que
relire le code ne suffit pas à garantir qu'une fonctionnalité marche réellement — un
`DropdownTranslation` peut exister en base sans jamais s'afficher nulle part (`ITILTemplate` n'a
pas d'onglet Traductions), et inversement une classe peut sembler ne pas supporter un mécanisme
alors qu'elle le supporte bel et bien (`AbstractITILChildTemplate`). Audit mené en deux temps :
recherche web (2 agents en parallèle) + un audit de complétude en base, plus rapide et plus
exhaustif qu'un audit Playwright écran par écran pour ce cas précis (comparer le nombre de lignes
réellement traduites au nombre total de lignes par itemtype révèle un trou aussi sûrement qu'une
capture d'écran, sans le coût d'une visite par intitulé).

### Bugs trouvés et corrigés (v0.20.2)

Méthode : requête SQL comparant, pour chaque itemtype à icônes, le nombre total de lignes créées
au nombre de lignes ayant réellement une traduction dans `glpi_dropdowntranslations` — tout écart
est soit un item natif GLPI jamais touché par le plugin (attendu, ex. le gabarit "Default" natif de
`TicketTemplate`), soit un vrai trou. Trois vrais trous trouvés :

1. **`WaitReasonBuilder` ne traduisait jamais ses gabarits liés.** Chaque `PendingReason` peut
   créer son propre `ITILFollowupTemplate`/`SolutionTemplate` dédié (ex. "Attente de retour
   utilisateur" génère un gabarit de suivi ET un gabarit de solution du même nom) — la raison
   d'attente elle-même recevait bien son icône, mais ses deux gabarits liés, jamais. Aggravé par un
   second bug dans le correctif lui-même : la branche "déjà existant" de `getOrCreateReason()`
   retournait avant d'atteindre le nouveau code, donc réexécuter le wizard sur une instance déjà
   configurée ne rattrapait rien — corrigé en relisant les IDs de gabarits liés directement depuis
   la `PendingReason` existante.
2. **`ProjectTaskTemplateBuilder` n'avait jamais eu d'icônes du tout** — angle mort du même genre
   que celui corrigé en v0.20.0 pour `TicketTemplate`/`ChangeTemplate`/`ProblemTemplate` : la classe
   (`ProjectTaskTemplate extends CommonDropdown`) n'a simplement jamais été incluse dans la liste
   des itemtypes à traiter à l'époque. Nouveau réglage `project_task_template_icons_enabled` ajouté,
   3 icônes choisies (🎯 Cadrage initial, 📍 Point d'avancement, 🏁 Revue de clôture).
3. **`CategoryBuilder` : 37 sous-catégories de niveau 3 (sur 103 `ITILCategory` au total) n'avaient
   jamais de traduction**, alors que les 5 langues existaient déjà dans `Translations.php` pour
   chacune d'elles (travail déjà fait au Sprint "traductions", jamais branché) — la cause exacte :
   `Translations::applyIcon()` n'était appelée que si le nœud avait une icône (`isset($node['icon'])`),
   et les feuilles de l'arbre n'en ont volontairement jamais eu (choix de design du Sprint 16, pas
   remis en cause). Corrigé en découplant les deux : `applyIcon()` accepte maintenant une icône
   vide (`trim()` sur le résultat pour éviter un espace résiduel), et `CategoryBuilder` traduit
   désormais chaque nœud dès que `category_icons_enabled` est actif, icône ou pas. Résultat pratique
   pour l'utilisateur : une session en anglais/allemand/italien/espagnol voyait jusqu'ici les ~37
   catégories les plus fines (ex. "Ordinateur fixe", "Wifi") rester en français au milieu d'une
   arborescence sinon traduite — plus le cas.

Les trois corrections vérifiées de bout en bout sur l'instance de test réelle (pas seulement en
base) : soumission complète du wizard, recomptage SQL avant/après (66→103 pour `ITILCategory`,
0→3 pour `ProjectTaskTemplate`, 5→7 et 10→11 pour les gabarits liés de `WaitReasonBuilder`).
**Piège opérationnel rencontré en testant** : l'opcache PHP du conteneur GLPI n'a pas repris le
correctif du bug n°1 tout de suite malgré `opcache.validate_timestamps=On` — un redémarrage du
conteneur a été nécessaire. À garder en tête pour la prochaine session : si un correctif ne semble
"pas prendre" en test alors que le code est correct, redémarrer le conteneur avant de creuser plus
loin une fausse piste.

Passé en revue tous les builders restants pour le même genre de trou (création d'un itemtype à
icônes sans appel `applyIcon()` à proximité) — aucun autre trouvé à ce jour.

### Cinquième passage — audit navigateur réel (fait, 2026-08-12, v0.20.3)

L'audit ci-dessus était base de données + code, pas une navigation réelle dans l'interface —
l'utilisateur l'a fait remarquer, à raison (c'est précisément la leçon de la correction v0.20.0).
Deux choses vérifiées en parcourant l'admin GLPI par Playwright :

- **Configuration > Intitulés** : les captures d'écran/textes des pages déjà auditées confirment ce
  que la base disait — pas d'écart trouvé entre les deux méthodes sur les intitulés déjà couverts.
- **Configuration > Actions automatiques** : a révélé un vrai trou du même genre que
  `auto_reminder` (Sprint 25) — les tâches automatiques `cartridge`/`consumable`/`software`
  (alertes cartouches, consommables, expiration de licences) ships `Désactivé` par défaut, alors
  que leurs `Notification` correspondantes sont déjà `is_active = 1` : la notification a l'air
  configurée mais ne se déclenche jamais, faute de tâche pour la déclencher. Corrigé dans
  `GeneralSettingsBuilder::applyNotifications()` (même toggle "Notifications" que le reste, cohérent
  avec ce que la case promet). Vérifié en base (`state` 0→1) et dans l'admin réel (« Désactivé » →
  « Programmée »).
- **Configuration > Authentification** (LDAP/SMTP) et **> Collecteurs** confirmés non couverts —
  déjà identifiés en recherche web comme le point de friction #1, restent dans les propositions
  ci-dessous, pas traités dans cette passe (gros chantier, dépend de l'annuaire de chaque
  organisation).

### Recherche web — points de friction GLPI réels (résumé, détail complet demandé à l'utilisateur si besoin)

Recherché sur le forum GLPI officiel, les issues GitHub `glpi-project/glpi`, et le web général :
structure d'entités mal comprise dès le départ (aucune vue d'impact avant de choisir) ; confusion
SLA/OLA persistante malgré le preset déjà livré ; LDAP — connexion OK mais import KO, filtres
fragiles, messages d'erreur peu clairs (très fréquent, non couvert par le plugin aujourd'hui) ;
catégories de tickets mal filtrées par entité (mi-bug core, mi-config) ; droits/profils — risque
réel d'auto-élévation faute de séparation stricte par défaut (axe ISO 27001 direct, pas encore
traité) ; notifications email désactivées par défaut + pièges SMTP (très fréquent, bloquant, pas
couvert) ; prérequis serveur mal anticipés avant même d'atteindre la configuration applicative (hors
périmètre wizard, mais un contrôle de prérequis en tout début de parcours serait utile).

### Recherche web — patterns d'onboarding des concurrents ITSM (résumé)

ServiceNow (Guided Setup séquencé par dépendances, étapes verrouillées tant qu'un prérequis n'est
pas actif) ; Freshservice (onboarding minimal 3 étapes, approfondissement plus tard — idée de "mode
express" distinct du mode complet actuel) ; Jira Service Management (templates de projet métier
pré-configurés en un clic — IT/RH/Facilities avec workflows+SLA assortis, fort potentiel de
transposition) ; Zendesk (préparation du contenu self-service en amont plutôt qu'en fin de
parcours).

### Recherche — variables CSS GLPI 11 pour un branding exhaustif (résumé)

`BrandingBuilder` ne surcharge aujourd'hui que `--glpi-logo` (une seule des 6 variantes réelles :
`--glpi-logo-light/-light-reduced/-dark/-dark-reduced/-light-login/-dark-login` — le mode réduit, le
mode sombre et l'écran de login gardent donc le logo natif GLPI) et une couleur primaire approximée
(alors que `--tblr-primary`/`--tblr-primary-fg`/`--tblr-primary-darken` etc. existent, dérivées
nativement par `color-mix`). GLPI 11 expose aussi `--glpi-mainmenu-*` (menu principal), des
variables de portail helpdesk calculées depuis le menu, et surtout un **mécanisme officiel plus
propre que `custom_css_code`** apparu en 11.0 : déposer un `.scss` dans `files/_themes`, auto-
détecté comme palette sélectionnable — évite les soucis de spécificité (`!important` contre Tabler)
et couvre nativement le dark mode, deux limites confirmées de l'approche actuelle. GLPI ships aussi
17 palettes natives (`auror`, `dark`, `midnight`, `teclib`...) que le wizard ne propose pas de
sélectionner aujourd'hui.

---

## 📮 Propositions issues du quatrième audit — à trancher avec l'utilisateur

Aucune de ces pistes n'est implémentée — même méthode que les audits précédents, pas de décision de
priorité unilatérale.

1. **Séparation stricte des droits par défaut (axe sécurité/ISO 27001).** Le point de friction le
   plus sévère trouvé en recherche (risque d'auto-élévation via le droit "Profils" global) rejoint
   directement l'objectif ISO 27001 déjà affiché par ce plugin. Pourrait se traduire par un profil
   "Administrateur technique" par défaut plus restrictif que Super-Admin, proposé (pas imposé) dans
   `RuleRightBuilder`/le wizard.
2. **Diagnostic LDAP pas-à-pas.** Le point de friction le plus fréquent trouvé. `RuleRightBuilder`
   configure déjà l'affectation post-synchronisation, mais rien n'aide à *fiabiliser* la
   synchronisation elle-même (filtre, bind, TLS) — un mode "test de connexion + filtre pré-validé"
   serait un vrai gain, mais gros morceau (dépend fortement de l'annuaire de chaque organisation).
3. **Notifications : vérifier/activer les modèles de cycle de vie manquants par défaut**, au-delà de
   `auto_reminder` déjà traité (Sprint 25) — repasser sur la liste complète des notifications
   `is_active=0` d'origine.
4. **Mode "express" du wizard**, inspiré de Freshservice — un sous-ensemble de 4-5 réglages
   critiques (entité, calendrier, un SLA par défaut, branding minimal) pour une mise en service en
   quelques minutes, le mode actuel (17 étapes) devenant le mode "complet" à côté.
5. **Bibliothèque de "profils métier" prêts à l'emploi**, inspirée de Jira Service Management — au
   lieu du seul profil topique actuel (IT/Bâtiment/Flotte...), des jeux de catégories+SLA+gabarits
   par verticale (IT pur, RH, Facilities...) sélectionnables en un clic à l'étape 1.
6. ✅ **`BrandingBuilder` — couvrir les 6 variables de logo et la vraie palette de couleurs Tabler —
   fait (v0.21.0, 2026-08-12).** Voir CHANGELOG.md. Reste hors périmètre, plus gros chantier :
   proposer les palettes natives GLPI (`auror`/`dark`/`midnight`...) comme choix simple dans le
   wizard, ou générer une palette `.scss` custom dans `files/_themes` (mécanisme plus propre mais
   hors du modèle "un DropdownTranslation/un CSS custom par entité" utilisé partout ailleurs dans
   le plugin).
7. **Contrôle de prérequis serveur en tout début de wizard** (PHP/MySQL versions, droits fichiers) —
   hors configuration applicative à proprement parler, mais évite un échec avant même d'atteindre
   l'étape 1.

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
