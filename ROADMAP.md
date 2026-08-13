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

- ✅ **Multi-entité "même entreprise" vs "MSP" — plus purement cosmétique, note obsolète mise à
  jour (2026-08-12).** Écrite tôt dans le projet, avant que les sprints suivants n'aient
  concrètement traité chacun des trois points listés à l'époque :
  - calendrier et SLA propres à chaque client/site — **fait (Sprint 13)**, étendu à tout mode
    multi-entité (pas seulement MSP) ;
  - logo/couleur de personnalisation différents par client — logo déjà par-client depuis
    longtemps (`entity_logo_N`, un fichier par nœud de premier niveau) ; **couleur — fait
    (v0.25.0, 2026-08-12)**, nouveau réglage "Couleur différente par client/site" dans
    `BrandingBuilder::applyPerClientColors()`, même schéma de panneau par entité que le logo ;
  - cloisonnement des droits entre entités clientes — **déjà couvert pour les utilisateurs
    synchronisés LDAP** (`RuleRightBuilder`, Sprint 27) : chaque règle assigne l'utilisateur à
    l'entité *feuille* précise (pas la racine, pas récursif), ce qui suffit au cloisonnement natif
    GLPI par défaut (confirmé en relisant `RuleRightBuilder::createRule()` — aucune action
    `is_recursive` positionnée, donc non-récursif par défaut). Ne couvre pas les comptes créés à
    la main (hors LDAP) ni le compte `glpi`/Super-Admin natif — restreindre ce dernier
    automatiquement serait une action destructive risquant de verrouiller l'admin hors de son
    instance, hors de portée d'un wizard de configuration.

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
   L'affectation d'un profil global par fonction métier (ex. "Finance"/"DSI" → profil,
   indépendamment du site) — **fait (Sprint 36, 2026-08-12).** Intégrée à l'étape LDAP existante
   plutôt qu'une étape séparée (confirmé avec l'utilisateur) : liste répétable optionnelle de
   paires (groupe AD, profil), chaque paire devient une `RuleRight` sans action `entities_id`, qui
   s'accumule avec les règles par site ci-dessus plutôt que les remplacer (confirmé en base et dans
   le code source de `RuleRightCollection`). Reste hors périmètre : la gestion fine des droits par
   module au sein d'un même profil.

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
de se contenter de les *mapper* pour le suivi d'avancement des tâches. ✅ **Gabarits d'évènements
externes + Catégories d'évènements — fait (v0.25.0, 2026-08-12), nouveau `PlanningEventBuilder`.**
`PlanningEventCategory` a un champ natif `color` (distinct du mécanisme icône
`DropdownTranslation` utilisé partout ailleurs) — utilisé directement, la couleur étant ce qui
s'affiche réellement dans la grille de planning GLPI. `PlanningExternalEventTemplate` laisse
volontairement `rrule` (récurrence) à vide — un gabarit réutilisable (nom, description,
catégorie, durée plausible), pas une décision de récurrence à la place de l'admin.

**Général** (5 intitulés) — fait : Statuts des éléments (`StateBuilder`). **Fait (Sprint 31,
2026-08-11)** : Lieux (`LocationBuilder` — mirroir de l'arborescence d'entités de l'étape 2, pas
une liste inventée : un `Location` par entité, même nom, même imbrication, scopé à l'entité
réelle), Fabricants (`ManufacturerBuilder`, ~29 fabricants IT/bureautique courants). Pas prévu
(sécurité anti-spam, pas de bonne pratique universelle à préremplir) : Listes noires
(`Blacklist`), Contenu de mail interdit (`BlacklistedMailContent`).

**Outils** — **fait (Sprint 31, 2026-08-11)** : Catégories de la base de connaissances
(`KnowbaseCategoryBuilder`) — réutilise les 11 thèmes de `CategoryBuilder` (étape 5) plutôt
qu'une seconde taxonomie inventée, filtré sur les branches effectivement sélectionnées.

**Gestion** — ✅ **fait (v0.23.0, 2026-08-12).** `DocumentManagementBuilder` (nouveau) : Rubriques
des documents (`DocumentCategory`) suivant l'échelle standard de classification de l'information
ISO 27001 Annexe A.8.2 (Public/Interne/Confidentiel/Diffusion restreinte — aucun champ natif GLPI
équivalent sur `Document`, c'est le mécanisme le plus proche), Criticités (`BusinessCriticity`,
échelle d'impact métier à 4 niveaux, utilisée en réalité par les *actifs* via `Infocom`, pas les
documents — confirmé via `information_schema.COLUMNS`). Types de documents (`DocumentType`) —
**vérifié, déjà suffisant** : GLPI ships 73 types natifs (toutes les extensions courantes), rien à
construire, même conclusion que `RequestType`/`ProjectState` ailleurs dans ce document.

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

### Sixième audit — module Projets + points transverses (fait, 2026-08-12, v0.26.0)

Audit réel (code source GLPI + base de données, pas de suppositions) centré sur le module Projets à
la demande de l'utilisateur, plus quelques questions transverses posées dans la foulée.

**Projets** :
- Notifications projet (`New/Update/Delete Project`, `New/Update/Delete Project Task`) —
  **vérifié déjà actives par défaut** en base, contrairement au bug `auto_reminder` trouvé sur les
  tickets (Sprint 25). Rien à corriger.
- Statuts de projet (`ProjectState`) — re-confirmé : conclusion "déjà suffisant" du troisième audit
  tient toujours (`is_finished`/`color` déjà cohérents nativement sur les 3 statuts).
- Rôles d'équipe (`ProjectTeam`) — constante PHP figée (`Team::ROLE_MEMBER`...), pas une liste
  configurable, rien à construire.
- **Gap réel identifié — fait (v0.34.0, 2026-08-13).** GLPI permet de sauver un projet existant
  comme "modèle" (`is_template`) et d'en recréer un nouveau à partir de ce modèle — comme les
  gabarits de ticket, mais le plugin n'en fournissait aucun. `ProjectTemplateBuilder` construit
  deux modèles pré-structurés (« Déploiement standard », 6 tâches ; « Projet interne — cycle
  court », 3 tâches) en s'appuyant sur `Project::getCloneRelations()` (confirmé dans le code
  source de GLPI : inclut `ProjectTask::class`, donc le sélecteur de gabarit natif clone déjà les
  tâches automatiquement). Vérifié en réel via le vrai flux `project.form.php?...&withtemplate=2` :
  les 6 tâches apparaissent bien sur le nouveau projet, sans rien à construire côté UI. Détail
  complet dans `CHANGELOG.md` `[0.34.0]`.

**Variables/balises dans les gabarits (suivi/tâche/solution/ticket) — correction en cours d'audit.**
Première conclusion erronée : le système `##ticket.title##`
(`NotificationTemplateTranslation::showAvailableTags()`) est bien exclusif aux notifications, mais
en creusant `AbstractITILChildTemplate::getRenderedContent()` un second mécanisme, réel et distinct,
existe depuis la 10.0 : `Glpi\ContentTemplates\TemplateManager`, un moteur **Twig sandboxé**
(tags autorisés : `if`/`for`/`set`... ; filtres : `date`/`default`/`first`/`length`...) exposant les
champs du ticket/change/problem (`{{ ticket.name }}`, `{{ ticket.requesters.users }}`,
`{{ ticket.priority }}`...). **Fait (v0.27.0, 2026-08-12)** : `FollowupLibraryBuilder`/
`SolutionLibraryBuilder` (15 gabarits) utilisent désormais une salutation personnalisée
itemtype-aware (`{{ requesters|first.fullname }}`, repli sur "Bonjour," si vide) ; au passage, un
vrai bug latent trouvé et corrigé sur les 2 gabarits "Sécurité" qui référençaient déjà
`{{ ticket.solvedate }}` en dur alors que sélectionnables sur un Change. Vérifié en réel (ticket +
Change créés pour l'occasion) que les deux branches rendent correctement. `TaskTemplateBuilder`
(checklists techniciens, pas de salutation à personnaliser) laissé statique par nature du contenu.

**Catégories FAQ** (`KnowbaseCategoryBuilder`) — re-confirmé créées et délibérément alignées sur les
mêmes branches que les catégories ITIL/catalogue de services (filtrage identique), pas une seconde
taxonomie inventée.

**Documents** (`DocumentManagementBuilder`) — re-confirmé créés (v0.23.0), icônes + traductions
complètes vérifiées sur les 8 termes (4 classifications + 4 niveaux de criticité) dans
`Translations.php`, rien ne manque.

**Personnalisation HTML/CSS des e-mails — fait (v0.31.0, 2026-08-13).** Piste initiale
(`mailing_signature`) réévaluée en cours de route : ce champ ne supporte que du texte simple
d'après l'UI (`<textarea>`), et surtout — trouvé en creusant le vrai flux de rendu
(`NotificationTemplate::makeAllReplacements()`) — GLPI ne partage aucun habillage HTML commun entre
notifications : chaque évènement (nouveau ticket, mise à jour...) pointe en réalité vers le *même*
`NotificationTemplate` natif partagé (confirmé en base), pas des gabarits distincts. Un vrai jeu
d'e-mails HTML de production (4 évènements, balises `##ticket.xxx##` réelles) a servi de référence
de structure — `NotificationBrandingBuilder` (nouveau) crée un gabarit HTML dédié par évènement
(nouveau ticket/mise à jour/résolution/nouveau suivi) avec la couleur/logo déjà calculés par
`BrandingBuilder`, et réassigne l'évènement correspondant vers ce nouveau gabarit. Vérifié de bout
en bout : gabarits créés, évènements réassignés en base, idempotence confirmée, et un vrai ticket
créé pour confirmer que la notification réellement mise en file contient le HTML habillé avec les
balises correctement substituées. **Corrigé en v0.31.1** : régression trouvée en se relisant avec
un œil critique — une seule ligne `language=''` par gabarit aurait montré des libellés français à
tout destinataire, quelle que soit sa langue GLPI. Une ligne par langue désormais (5 langues).

**Contenu des gabarits de suivi/tâche/solution traduit — fait (v0.32.0, 2026-08-13).** Trouvé lors
de l'analyse critique post-v0.31.1 : même type de limite que le bug des notifications, contenu
traduisible en théorie (`AbstractITILChildTemplate::getRenderedContent()` appelle
`DropdownTranslation::getTranslatedValue(..., $_SESSION['glpilanguage'], ...)`) mais aucune ligne
`DropdownTranslation` n'existait pour leur `content`. D'abord laissé de côté ("moins grave, texte
de départ qu'un technicien édite"), puis l'utilisateur a explicitement demandé de traduire "tout
sans exception" — corrigé. `Translations::applyContent()` (nouveau, même mécanisme que
`applyIcon()` mais sur le champ `content`) : les 18 gabarits (5 suivis + 10 solutions + 3 tâches)
ont désormais leurs 4 traductions, y compris la salutation Twig ("Bonjour" → "Hello"/"Hallo"/
"Buongiorno"/"Hola", même prudence itemtype-aware que la v0.27.0). Vérifié en réel : une session
anglaise appliquant un gabarit de suivi reçoit bien "Hello glpi," et le corps traduit, rendu Twig
inclus.

**Filigrane PDF sur documents confidentiels** — position inchangée depuis la première discussion :
nature technique différente (traitement de fichier temps réel vs scaffolding one-shot), plugin
séparé recommandé plutôt qu'ajout ici.

**Fabricants — visibilité selon le type de produit, vérifié non supporté nativement (2026-08-12).**
L'utilisateur a fait remarquer qu'un fabricant comme Jabra (casques/audio) ne devrait pas apparaître
dans la liste déroulante fabricant lors de la création d'un ordinateur. Vérifié en base
(`DESCRIBE glpi_manufacturers`) : la table n'a aucun champ de portée par type d'actif (juste
`id`/`name`/`comment`/dates) — c'est une liste plate partagée par tous les types d'actifs GLPI
nativement, sans mécanisme de filtrage. Implémenter ce filtrage demanderait un moteur de règles
JS personnalisé (chantier non trivial, pas juste un champ à cocher). Laissé de côté sauf demande
explicite de le construire quand même.

**Fabricants — dictionnaire de normalisation — fait (v0.32.0, 2026-08-13).** Suite à la remarque
ci-dessus, l'utilisateur a proposé une piste différente et réellement construite : un dictionnaire
GLPI natif (`RuleDictionnaryManufacturer`, confirmé dans le code source — `getActions()` supporte
`assign` sur le champ `name`) pour normaliser les variantes de nom qu'un vrai inventaire remonte
(« Hewlett-Packard », « HP Inc. »… → « HP »). Contrairement aux dictionnaires logiciel/matériel
étudiés et rejetés plus tôt (variantes propres à l'inventaire réel de chaque organisation,
impossibles à deviner à l'avance), les variantes des plus grands fabricants sont documentées et
stables (chaînes `sys_vendor`/WMI `Manufacturer` connues) — pas besoin des données réelles d'une
organisation pour écrire ces règles. `ManufacturerDictionaryBuilder` (nouveau) : 15 règles (sur les
29 fabricants créés, ceux avec des variantes réellement documentées). Vérifié avec l'outil natif de
test de règle de GLPI (`front/rule.test.php`) : « Hewlett-Packard » → validé → fabricant assigné
« HP ».

**Idée cadrée, débloquée, pas encore construite (proposée par l'utilisateur, 2026-08-12 ; API
confirmée le 2026-08-13 après avoir repéré le sujet dans les vidéos de formation GLPI de Patrice
Vaillant, https://www.youtube.com/@patricevaillant — chaîne listée par GLPI lui-même dans
« Ils parlent de nous »)** : générer des « actifs personnalisés » GLPI en fonction des branches de
catégories sélectionnées à l'étape 5 — ex. la branche « Flotte Automobile » activée créerait un
type d'actif « Véhicule » avec des champs pertinents (immatriculation, type de carburant, date de
contrôle technique...), la branche « Bâtiment » un type « Local »/« Salle » ou équivalent, la
branche « IT & SI » un type « Serveur » distinct de l'actif natif `Computer` (champs propres :
position en baie, RAID, hyperviseur...).

**Blocage levé** : vérifié directement dans le code source de GLPI 11.0.8 réel (pas supposé) —
`Glpi\Asset\AssetDefinition` (`src/Glpi/Asset/AssetDefinition.php`) est un vrai `CommonDBTM`
(`extends AbstractDefinition`), pas seulement une manipulation via l'UI. Ce n'est PAS du GLPI 10+
comme noté précédemment : la généricité est native depuis la GLPI 11 seulement, migrée depuis
l'ancien plugin externe « Generic Object » (confirmé dans la doc officielle,
https://help.glpi-project.org/documentation/modules/configuration/asset-definitions). Champs clés
côté `add()`/`update()` : `system_name` (fige le nom, génère la classe
`GlpiCustomAsset<SystemName>Asset`), `capacities` (tableau JSON de « capacités » modulaires —
25+ disponibles dans `src/Glpi/Asset/Capacity/` : `HasNetworkPortCapacity`,
`HasVirtualMachineCapacity`, `HasInfocomCapacity`, `IsInventoriableCapacity`... — chaque type
d'actif active uniquement celles qui le concernent), `profiles`, `translations`, `fields_display`.
Reste à définir un jeu de champs/capacités par branche sans tomber dans le sur-mesure par
organisation (même principe généraliste que le reste du plugin). Pas de version cible — l'utilisateur
a choisi de documenter le déblocage plutôt que de lancer la construction immédiatement
(2026-08-13).

**Lieux — assistant d'adresse interactif — fait (v0.33.0, 2026-08-13).** Demandé explicitement par
l'utilisateur ("les adresse on a dit un truc interractif, comme pour les site internet ou tu
commence a taper ta rue il la sugère, idm tu met le code postal tu a la ville"), confirmant que la
ligne « assistant intelligent avec géocodage » retirée du README lors du nettoyage v0.31.0
correspondait bien à une vraie idée, pas du texte marketing oublié. Recherche faite sur les API
disponibles avant de construire :
- **Nominatim** (OpenStreetMap) et **Photon** (komoot) : couverture mondiale, gratuites, sans clé,
  CORS déjà activé sur leurs instances publiques — mais usage public strictement limité (~1 req/s
  sur Nominatim, pas de saisie assistée en rafale, sinon blocage 403/429) : auto-hébergement
  recommandé pour un usage réel, la démo publique suffit pour un usage ponctuel (admin, une seule
  fois, pendant l'assistant).
- **LocationIQ**/**OpenCage** : alternatives avec clé API, quota gratuit quotidien plus confortable,
  posture RGPD plus explicite (OpenCage).
- **Point RGPD réel traité** : chaque frappe envoie un bout d'adresse à un service tiers — opt-in
  réel (rien n'est envoyé tant que la case n'est pas cochée dans le navigateur de l'admin), seuil
  minimum de 3 caractères + debounce 400 ms, endpoint auto-hébergeable pour les organisations
  sensibles.

Construit comme prévu : Nominatim public par défaut avec `User-Agent` correct et debounce, endpoint
admin-configurable (`ajax/geocode.php`, proxy serveur — SSRF fermé, l'endpoint est toujours lu
depuis la config stockée, jamais depuis la requête client). Deux bugs réels trouvés et corrigés
avant mise en ligne (détail dans `CHANGELOG.md` `[0.33.0]`) : la persistance en base bloquait le
tout premier essai de l'assistant, et une recherche par code postal seul était ambiguë à l'échelle
mondiale sans un pays associé (« 69001 » = Lyon *ou* un quartier de Zaporijjia, Ukraine). Vérifié en
réel contre le vrai service Nominatim (pas de mock) via Playwright : suggestions de rue réelles,
ville correctement résolue depuis le code postal, données persistées sur le bon `Location`.

**Suite (v0.35.0, 2026-08-13)** — deux vrais problèmes remontés par l'utilisateur en testant la
fonctionnalité (capture d'écran à l'appui) : la recherche de rue en texte libre n'était pas non
plus restreinte à la ville/pays déjà saisis (même défaut que le code postal seul, pas encore
corrigé ici — même correctif étendu, recherche structurée Nominatim `street`+`city`+`country`), et
la liste de suggestions se superposait visuellement aux champs en dessous (`list-group` sans fond
opaque propre → `dropdown-menu` Bootstrap/Tabler). Un second bug latent trouvé pendant cette
correction, avant mise en ligne : `dropdown-menu` est masqué par sa propre règle CSS, jamais levée
sans le composant JS Bootstrap natif — la liste restait invisible même remplie de vrais résultats
tant que l'affichage forcé restait une chaîne vide au lieu de `'block'`. Coordonnées GPS
(`glpi_locations.latitude`/`longitude`) ajoutées au passage : déjà fournies par Nominatim, jamais
exploitées jusqu'ici. Détail complet dans `CHANGELOG.md` `[0.35.0]`.

**Refonte (v0.36.0, 2026-08-13)** — retour utilisateur direct après usage réel de l'assistant :
« tu met les entité dans les lieu, mais s'en ai pas, par contre faire en sorte que l'on puisse
saisir ou non une adresse pour chaque entité et sous entité, se serais bien ». Confirmait un vrai
défaut de conception : `LocationBuilder` mirrorait TOUTE l'arborescence d'entités en Lieux sans
condition (un département interne sans adresse propre devenait quand même un Lieu), avec adresse
saisissable seulement sur les nœuds racine. Reconstruit pour qu'un `Location` ne soit créé QUE là
où l'admin saisit effectivement quelque chose, à n'importe quel niveau de l'arbre — l'étape 15
affiche désormais l'arborescence complète avec un bouton « + Ajouter une adresse » repliable par
nœud. Deux demandes supplémentaires dans la foulée, toutes deux traitées dans la même version :
alias (`glpi_locations.alias`, jamais utilisé) et l'ensemble des champs natifs de `glpi_locations`
(code, commentaire, état/région, bâtiment, pièce, altitude — pas seulement adresse/code postal/
ville/pays). Un bug trouvé pendant cette refonte, avant mise en ligne : le validateur de
coordonnées rejetait toute altitude à 4 chiffres (limite à 3 chiffres avant la virgule, correcte
pour latitude/longitude mais pas pour une altitude de montagne). Vérifié en réel : une entité sans
donnée ne produit aucun Lieu, une sous-entité avec adresse+alias+tous les champs produit un
`Location` correctement scopé et rattaché à la racine (son parent n'ayant pas de Lieu). Détail
complet dans `CHANGELOG.md` `[0.36.0]`.

**Sources des demandes (`RequestType`) — traduction, fait (v0.38.0, 2026-08-13).** Revient sur la
conclusion "déjà suffisant" d'un audit précédent — cette conclusion portait sur le *contenu* (6
valeurs natives couvrent les cas d'usage), pas sur la *traduction*, une question orthogonale jamais
vérifiée à l'époque. Confirmé un vrai manque en lisant `install/empty_data.php` de GLPI : ces 6
valeurs sont des chaînes anglaises codées en dur, sans ligne `DropdownTranslation` (absence
confirmée en base) — toute session non anglaise les voit telles quelles. `RequestTypeTranslationBuilder`
(nouveau) traduit les 6 valeurs existantes dans les 5 langues du plugin, sans jamais les créer.

---

## 📋 Chantiers identifiés en marge d'une revue d'écran par l'utilisateur (2026-08-13, pas encore
cadrés — capturés tels quels, à trancher un par un avant de construire quoi que ce soit, même
méthode que les audits précédents)

L'utilisateur a parcouru plusieurs écrans natifs de GLPI (Intitulés, Entités) en configurant une
instance réelle et a soulevé sept pistes d'un coup. Consignées ici sans construire, sur sa propre
demande explicite ("ajout tout ce que je viens de te dire dans la liste des chantier a faire").

1. ✅ **Lieux — lieux enfants arbitraires avec arborescence éditable, comme les entités — fait
   (v0.39.0, 2026-08-13).** `LocationBuilder` créait au plus un `Location` par nœud de
   l'arborescence d'entités, avec un rattachement 1:1 forcé. `glpi_locations` a pourtant son propre
   arbre indépendant (`locations_id` auto-référencé) — un même site peut avoir plusieurs lieux
   imbriqués (Bâtiment > Étage > Salle) sans rapport avec la structure d'entités. Chaque panneau
   "Ajouter une adresse" propose désormais un éditeur récursif identique à celui de l'arborescence
   d'entités (réutilise directement les classes CSS de `_entity_structure_fields.html.twig` : nœuds
   `{name, fields, children}` sérialisés en JSON par entité, boutons `+`/`x` par nœud). Contrairement
   aux entités de l'étape 2, ajouter un lieu enfant le crée toujours (le geste explicite de l'ajouter
   est déjà la justification, pas de règle "aucune donnée = pas de lieu" ici). Vérifié en réel :
   arbre à 2 niveaux (Bâtiment A > Étage 1) créé avec le bon rattachement `locations_id`, y compris
   quand l'entité parente elle-même n'a pas de lieu propre (rattachement direct à la racine, même
   règle de compression que pour l'arbre d'entités).
2. ✅ **Catégories d'utilisateur (`UserCategory`) — fait (v0.41.0, 2026-08-13).** Vide nativement,
   l'utilisateur a demandé à quoi ça sert. Vérifié dans le code de GLPI : champ réel sur `User`
   (`usercategories_id`), importable depuis un attribut LDAP (`AuthLDAP::category_field`), utilisé
   comme critère de ciblage de notification (`NotificationTargetCommonITILObject`) et comme axe de
   statistiques (`Stat.php`) — indépendant des profils/droits GLPI. `UserCategoryBuilder` (nouveau) :
   6 catégories génériques (Employé, Prestataire externe, Stagiaire, Alternant, Intérimaire,
   Consultant). Vérifié en réel : les 6 lignes créées dans `glpi_usercategories`.
3. ✅ **Opérateurs téléphoniques (`LineOperator`) — fait (v0.49.0, 2026-08-13), défaut France
   posé comme pour les jours fériés.** `LineOperatorBuilder` (nouveau) : 4 grands opérateurs mobiles
   français (Orange, SFR, Bouygues Telecom, Free), avec MCC/MNC réels recoupés sur 3 sources
   indépendantes pour éviter d'inventer un numéro. **Bug réel trouvé pendant la vérification** :
   `glpi_lineoperators` a un index `UNIQUE(mcc, mnc)`, et GLPI met `0` par défaut (pas `NULL`) sur
   ces champs entiers non fournis — sans MCC/MNC explicites et distincts, seul le premier opérateur
   se créait, les 3 suivants étaient silencieusement rejetés par la contrainte d'unicité, sans
   aucune erreur visible ni dans le message de succès du wizard ni dans les logs GLPI. Repéré
   uniquement en comptant les lignes en base après soumission — pas en faisant confiance au message
   de succès. `NetworkPortFiberchannelType` (malgré son nom FR "Types de fibre") concerne en réalité
   le protocole de stockage SAN Fibre Channel (débits 1/2/4/8/16/32 Gb, FCoE...) — rien à voir avec
   la fibre internet résidentielle/entreprise, écarté du périmètre "opérateurs télécom" de ce point.
4. **Grande liste de dropdowns "Types" natifs vides** — repérée en parcourant l'écran Intitulés >
   Types. Vérifié par requête sur `information_schema` : ~25 tables natives à 0 ligne, parmi
   lesquelles des candidats plausibles à un contenu générique (types d'ordinateurs, de moniteurs,
   de matériels réseau, d'imprimantes, de périphériques, de téléphones, de boîtiers, de contrats,
   de contact, de fournisseurs, de certificats, de budgets, de baies, de PDU, de cartouches, de
   consommables, de câbles, de lignes, de capteurs, de batteries, de disques durs, de clusters,
   d'instances de base de données, de machines virtuelles, de licences logicielles) et d'autres
   probablement auto-gérées par GLPI lui-même plutôt qu'à seeder manuellement (types d'agent
   d'inventaire, types d'actifs liés au nouveau système `AssetDefinition`) — à vérifier au cas par
   cas avant de construire, trop volumineux pour être traité d'un bloc sans prioriser.
5. **Jours fériés par pays, selon le pays saisi sur un Lieu.** `CalendarBuilder` n'applique
   aujourd'hui que les jours fériés français, en dur. Demandé : dès qu'une adresse avec un pays est
   saisie sur un Lieu (assistant d'adresse v0.33.0+), proposer d'appliquer automatiquement les
   jours fériés propres à ce pays — mais explicitement **seulement pour les pays où on dispose
   réellement de la donnée** (pas de case à cocher qui ne ferait rien pour un pays non couvert).
   **Source de données réelle trouvée et vérifiée (2026-08-13)** : l'API publique gratuite
   Nager.Date (`https://date.nager.at/api/v3/PublicHolidays/{année}/{code pays ISO}`), testée en
   direct avec des jours fériés français réels et actuels — couvre ~100 pays
   (`/api/v3/AvailableCountries`). **Pas encore construit**, trois questions de conception restent
   ouvertes avant de s'y mettre : (1) le champ pays des Lieux est actuellement du texte libre ("
   France", "Allemagne"...), il faudrait une table de correspondance nom→code ISO 3166-1 alpha-2 au
   moins pour les pays européens/courants ; (2) GLPI's `Holiday.is_perpetual` ne gère qu'un
   mois/jour fixe répété chaque année (déjà la raison pour laquelle `CalendarBuilder` exclut les
   jours fériés mobiles français type Pâques/Ascension) — Nager.Date fournit des dates précises par
   année, pas une règle récurrente, donc soit se limiter aux jours fériés à date fixe par pays (même
   simplification que l'existant), soit créer des jours non-perpétuels nécessitant un rafraîchissement
   chaque année (recréer/mettre à jour à chaque nouvelle exécution du wizard, ce qui est cohérent
   avec la nature de cet outil de configuration réutilisable) ; (3) dépendance à un nouveau service
   externe non encore utilisé par ce plugin (contrairement à Nominatim déjà accepté pour l'assistant
   d'adresse), à valider avec l'utilisateur avant d'ajouter cet appel réseau supplémentaire au
   parcours du wizard.
6. ✅ **Unicité des champs (`FieldUnicity`) — fait (v0.43.0, 2026-08-13), scope réduit au cas
   universel.** Audité avant construction (`src/FieldUnicity.php` du cœur GLPI) : contrairement aux
   dropdowns de contenu, `FieldUnicity` définit bien des *règles* de contrainte, plus proche des
   règles métier volontairement laissées de côté ailleurs dans ce plugin (`RuleTicket`...) —
   mais parmi les 20 itemtypes éligibles (`$CFG_GLPI['unicity_types']`), un seul cas est
   suffisamment universel pour un défaut sans jugement métier par organisation : le numéro de série
   ne doit pas être dupliqué sur les six types d'actifs matériel sérialisables (Ordinateurs, Écrans,
   Matériel réseau, Périphériques, Téléphones, Imprimantes) — recommandation ITAM standard,
   indépendante du secteur. `action_refuse` (bloque) plutôt que `action_notify` (nécessiterait un
   gabarit de notification lié, même écueil que les gabarits liés de `WaitReasonBuilder`). Vérifié
   dans `CommonDBTM::checkUnicity()` : un numéro de série vide n'est jamais traité comme un
   doublon, donc aucun risque de bloquer la création de plusieurs actifs sans série renseignée.
   Vérifié en réel : les 6 règles créées avec les bons champs, resoumission sans doublon.

   **Extension à 12 règles — fait (v0.47.0, 2026-08-13).** Sur question directe de l'utilisateur
   ("pouvons-nous faire un truc ?"), réaudité les 20 itemtypes éligibles avec le même filtre
   (colonne `serial` réellement présente, confirmé par `information_schema` sur une instance réelle
   — `Cluster` en est dépourvu malgré son éligibilité). Six candidats supplémentaires passaient le
   même test d'universalité que les six premiers : Racks/Châssis/PDU (infrastructure physique, même
   raisonnement), Licences logicielles (le `serial` y est la clé de licence — un doublon signifie
   presque toujours une double saisie), Certificats (numéro de série X.509), Cartes SIM (ICCID).
   `User` écarté explicitement : pas de colonne e-mail directe sur `glpi_users` (l'e-mail vit dans
   `glpi_useremails`, relation 1-N), donc pas exploitable par ce mécanisme sans le détourner.
7. ✅ **Adresse native de l'Entité (`Entity`), distincte du Lieu — fait (v0.40.0, 2026-08-13).**
   Repéré sur l'onglet "Adresse" natif d'une entité (`front/entity.form.php`) : `glpi_entities` a
   ses propres champs téléphone/fax/site web/e-mail/code postal/ville/état/pays/adresse/latitude/
   longitude/altitude, avec sa propre carte Leaflet — un mécanisme entièrement distinct de
   `glpi_locations`. Tranché en faveur d'une extension du même assistant plutôt qu'un canal
   indépendant : `EntityAddressBuilder` (nouveau) réutilise l'adresse déjà saisie dans le panneau
   "Lieux" de chaque entité (aucune retypée), et n'ajoute que téléphone/fax/site web/e-mail — les
   seuls champs sans équivalent sur `Location`. Toggle dédié, désactivé par défaut. Vérifié en réel :
   adresse + les 4 nouveaux champs correctement persistés sur la fiche entité.

**Réordonnancement des 18 étapes du wizard — fait (v0.42.0, 2026-08-13).** Retour utilisateur
direct après avoir suivi tout le parcours en conditions réelles : « on ne m'a pas demandé les
lieux » (l'étape Lieux était noyée dans un des huit interrupteurs de l'étape "Général & Outils",
en position 15 sur 17 — après la personnalisation graphique, les gabarits de tickets, les droits
LDAP...) et une demande explicite de revoir l'ordre pour un parcours plus logique, la partie CSS
("Personnalisation graphique") reléguée en tout dernier car jugée non prioritaire. Lieux extrait de
"Général & Outils" en une étape autonome, placée juste après "Entités" (étape 3) puisqu'une adresse
dépend directement de la structure d'entités qui vient d'être construite ; Personnalisation
graphique déplacée de l'étape 9 à l'étape 17 (avant-dernière, juste avant le Récapitulatif). Les 16
autres étapes réordonnées en conséquence pour rester un parcours cohérent (structure → temps/SLA →
contenu métier ITIL → droits/réglages → bibliothèques transverses → esthétique → validation finale).
Titres d'étape découplés du numéro (`Étape {{ N }} : {{ Sujet }}`, le numéro en Twig brut) pour que
tout futur réordonnancement n'exige plus de nouvelle traduction par étape déplacée. Restructuration
mécanique du template (3000+ lignes) faite via un script PHP à vérification stricte des bornes
plutôt qu'à la main, pour éliminer le risque d'erreur de copier-coller. Vérifié en réel : parcours
complet des 18 étapes, déclenchement JS des deux panneaux dont la condition dépendait du numéro
d'étape (Lieux, Personnalisation par client), soumission complète avec les données persistées en
base, rendu correct en session anglaise. `docs/TUTORIAL.md` et ses 18 captures d'écran repris à
l'identique du nouveau parcours.

**Icônes cochées par défaut — fait (v0.44.0, 2026-08-13).** Retour utilisateur direct après capture
d'écran de l'étape 3 : les 17 cases "Ajouter des icônes" du wizard étaient décochées par défaut
(opt-in), obligeant à les activer une par une malgré un coût nul et un bénéfice systématique.
Basculées en cochées par défaut (opt-out) dans `Config::getDefaults()` — aucun préréglage de
`ConfigurationProfile::getSuggestedDefaults()` ne les force à `false`, donc le changement s'applique
à tous les profils sans exception. Vérifié en réel sur une instance neuve (config remise à zéro) :
les 17 cases confirmées pré-cochées au premier chargement du wizard.

**Assistant d'adresse activé par défaut — fait (v0.45.0, 2026-08-13).** Question initiale de
l'utilisateur sur une capture d'écran ("il est pas intelligent") clarifiée : la case "Assistant
d'adresse" était simplement décochée dans son test, pas un bug — mais sur sa demande explicite
("tu fais le reste"), basculée en activée par défaut, même raisonnement que les icônes juste
au-dessus (réversible, bénéfice systématique). Seule nuance par rapport aux icônes : chaque frappe
part vers un service externe (OpenStreetMap Nominatim par défaut) — texte d'aide mis à jour pour
expliquer clairement pourquoi/comment la désactiver si besoin, pas juste "à activer
volontairement" comme avant.

**Presque tout activé par défaut — fait (v0.46.0, 2026-08-13).** Généralisation explicite de
l'utilisateur après les deux points ci-dessus ("il faudrait limite tout activé par défaut et
l'utilisateur choisis ce qu'il ne veut pas"). Étendu `Config::getDefaults()` (le point de départ
brut, utilisé par le profil "Personnalisé") pour qu'il corresponde à ce que
`ConfigurationProfile::getSuggestedDefaults()` considérait déjà comme "bonne pratique universelle"
pour les profils préréglés — pas une nouvelle liste inventée, juste le même socle déjà validé
appliqué aussi au point de départ brut. `FieldUnicityBuilder` (v0.43.0, pas encore dans ce socle)
inclus aussi. Seule exception délibérée conservée : la personnalisation graphique (couleur/logo/
palette/e-mails, étape 17/18) — recolorer toute l'instance sans qu'un admin ait choisi une couleur
reste différent d'ajouter du contenu à des listes vides, et cette étape est déjà explicitement
dépriorisée depuis le réordonnancement (v0.42.0). Vérifié en réel : soumission complète sur le
profil "Personnalisé" (le cas le plus strict, sans préréglage de profil), tout le contenu attendu
correctement créé en base sans erreur.

**Dictionnaire de fabricants — deuxième passage, fait (v0.46.0, 2026-08-13).** Suite à la demande
utilisateur de vérifier les variantes manquantes, confirmées via un vrai export `Manufacturer` d'une
instance GLPI réelle peuplée par glpi-agent (pas une recherche générique) : 3 fabricants déjà
couverts avaient des variantes réelles manquantes (Acer, Cisco, Samsung), et 9 des 29 fabricants
canoniques n'avaient encore aucune règle du tout (Fortinet, Logitech, Oracle, Red Hat, HPE Aruba,
Ubiquiti, Netgear, Canon, Brother, QNAP, Jabra, Poly, APC, Eaton) — les 29 ont maintenant une règle.
`createRule()` ajoute aussi désormais les critères manquants à une règle déjà existante au lieu de
l'ignorer entièrement, pour qu'un admin qui remet à jour le plugin en bénéficie aussi. Non traité,
noté pour un chantier séparé (changement de portée, pas juste une correction de variantes) : le
même export révèle plusieurs fabricants matériel réels et récurrents absents des 29 canoniques
(Intel, Kingston, Toshiba, Western Digital, Sony, Seagate, Micron, SanDisk, Realtek, TP-Link,
Transcend, NVIDIA, Broadcom, SK hynix) — décider lesquels ajouter à `ManufacturerBuilder` lui-même
(avec icône, catégorie) est une décision de contenu différente de la normalisation de doublons déjà
décidés.

**Flux RSS CERT-FR — fait (v0.47.0, 2026-08-13).** Demandé explicitement par l'utilisateur
("notamment le CERT-FR pour les français"). `RSSFeedBuilder` (nouveau) ajoute le flux natif GLPI
(Outils > Flux RSS, vide par défaut) des avis de sécurité CERT-FR/ANSSI. URL vérifiée en direct
(`https://www.cert.ssi.gouv.fr/feed/`, un vrai flux actif avec du contenu réel au moment du test)
plutôt que devinée. Visibilité instance-wide via `Entity_RSSFeed` (entité racine + récursif) —
sans ça, un flux `RSSFeed` n'est visible que par son créateur. Nom/description récupérés par GLPI
lui-même au moment de l'ajout (fetch live du flux par `RSSFeed::prepareInputForAdd()`), pas codés
en dur ici. Scope volontairement limité à ce seul flux (pas un mécanisme générique "ajouter
n'importe quel flux") — France-spécifique mais assumé, même logique que les jours fériés déjà
France-first ailleurs dans ce plugin.

**Étape "Marketplace & plugins recommandés" — fait (v0.48.0, 2026-08-13).** Demandée par
l'utilisateur, cadrée le même jour après déblocage réel du marketplace natif sur l'instance Docker
de test (l'utilisateur y a renseigné une vraie clé d'enregistrement GLPI Network) — les faits
ci-dessous vérifiés en conditions réelles (recherche live dans Configuration > Plugins > Marketplace
> Découvrir + inspection du DOM), pas juste via GitHub.
1. **Clé d'enregistrement GLPI Network.** Champ ajouté à l'étape "Réglages généraux GLPI", écrit
   directement dans la config native GLPI (`\Config::setConfigurationValues('core', [...])`,
   exactement le mécanisme de la page native "Enregistrement") — jamais dupliqué dans la table de ce
   plugin, qui n'a aucun chiffrement au niveau champ contrairement au stockage natif via `GLPIKey`.
   Pré-rempli à chaque chargement depuis `GLPINetwork::getRegistrationKey()` (même comportement que
   la page native). Vérifié : une resoumission avec le champ inchangé donne un chiffré différent en
   base (IV aléatoire à chaque chiffrement, normal) mais le marketplace continue de s'authentifier
   correctement ensuite — pas de corruption.
2. **Liste de plugins recommandés (informationnelle, pas d'installation automatique)** — 3 plugins
   vérifiés en direct sur le marketplace natif (clé, note, licence, auteur, bouton d'installation
   réel inspectés) :
   - **remise-glpi** (https://github.com/parime/remise-glpi, plugin de l'utilisateur — mise en avant
     explicite). Gestion de feuilles de prêt/retour/vente/don de matériel pour la traçabilité,
     centralisation des documents associés dans GLPI. **Absent du marketplace natif** (recherche
     "remise" → aucun résultat) — lien GitHub direct plutôt qu'un renvoi vers le marketplace.
   - **Escalade** — clé confirmée `escalade` (attribut `data-key` du marketplace lui-même, pas juste
     le nom du dépôt GitHub), licence GPL v2+, auteurs Alexandre Delaunay/TECLIB', v2.10.6, 3,5★,
     gratuit (aucun badge "GLPI Network"/offre payante). Description native : « simplifie l'escalade
     de ticket vers des groupes différents ».
   - **One-Time Secret** — clé confirmée `onetimesecret` (sans tiret, contrairement au nom du dépôt
     `ticgal/one-timesecret` — bien vérifié en direct plutôt que déduit), licence AGPL v3+, auteur
     TICgal, v3.0.0, 4,5★, gratuit. Description native : « Share your passwords securely on GLPI ».
   - Installation volontairement laissée à l'admin (bouton
     `<button data-action="download_plugin">` du marketplace natif, un clic, aucune redirection
     externe) plutôt qu'automatisée depuis ce wizard — télécharger/exécuter du code tiers est une
     catégorie de risque différente du reste de ce plugin (qui ne fait que créer du contenu dans les
     propres tables de GLPI).

**Plan retenu avec l'utilisateur pour la suite immédiate (par ordre de priorité)** :
1. **Fait.** Tests réels des gabarits (suivi/tâche/solution) appliqués sur un vrai ticket via l'UI.
2. **Fait (v0.29.0, 2026-08-12).** Documentation GitHub avec captures d'écran de chaque étape,
   `docs/TUTORIAL.md`.
3. **Fait (v0.28.0, 2026-08-12).** Étoffement du catalogue de services : 23 → 50 services, les 4
   branches sans aucun service comblées (Administratif, Communication, Qualité, Maintenance), les
   branches trop réduites étoffées. Vérifié en base après resoumission du wizard.

**Chantiers additionnels ouverts pendant cet audit** :
- Variables Twig dans les gabarits — **fait (v0.27.0)**.
- Prérequis de publication marketplace pour les deux dépôts sœurs — **fait** : `remise-glpi`
  corrigé (PR #66 : manifeste déplacé à la racine, langue `<fr_FR>`→`<fr>`, version obsolète mise à
  jour) ; `glpi-vulnerability-manager` audité mais volontairement pas touché — le plugin est encore
  en 0.8.0, le fichier `plugin.json` documente lui-même que la publication est prévue à la v1.0,
  pas avant.
- Traductions `.po`/`.mo` de l'interface du wizard en en_GB/de_DE/it_IT/es_ES — **fait (v0.30.0,
  2026-08-12)**. 318 chaînes traduites, vérifié en réel dans les 4 langues (aucune régression,
  aucun problème d'encodage).
- `<state>` du manifeste (`dev` → `stable`) — **fait (2026-08-13)**, décision utilisateur : 36
  versions livrées, suite qualité verte à chaque livraison, vérifié en réel sur GLPI 11.0.8 à
  chaque fonctionnalité. Dernier prérequis marketplace pour ce dépôt — tous cochés.

---

## 📮 Propositions issues du quatrième audit — à trancher avec l'utilisateur

Aucune de ces pistes n'est implémentée — même méthode que les audits précédents, pas de décision de
priorité unilatérale.

1. ✅ **Séparation stricte des droits par défaut (axe sécurité/ISO 27001) — fait (v0.22.0,
   2026-08-12).** Diffé `glpi_profilerights` Admin vs Super-Admin sur un GLPI 11.0.8 réel plutôt que
   deviner : Admin n'a déjà ni le droit `profile` en écriture (ne peut pas éditer les profils, donc
   pas s'en attribuer plus), ni `rule_ldap`/`rule_import` (ne peut pas réécrire les règles de
   synchronisation), ni `config` — exactement les vecteurs d'auto-élévation identifiés en recherche,
   sans inventer un nouveau jeu de droits sur mesure. `ldap_rights_profile` (réglage "Profil
   attribué" de `RuleRightBuilder`/étape 12) passe de `Technician` à `Admin` par défaut, avec
   l'explication affichée dans le wizard — reste un simple menu déroulant, n'importe quel profil
   natif reste sélectionnable.
2. ❌ **Diagnostic LDAP pas-à-pas — écarté (décision utilisateur, 2026-08-12).** Le point de
   friction le plus fréquent trouvé en recherche, mais chaque annuaire d'entreprise a ses propres
   filtres, bind DN, schéma de groupes — un assistant de diagnostic générique se heurte à du
   cas-par-cas qui ne rentre pas dans la philosophie du plugin (des builders universels,
   applicables à n'importe quelle organisation, pas des outils ad hoc par annuaire). `RuleRightBuilder`
   continue de couvrir la partie universelle (affectation post-synchronisation) ; fiabiliser la
   connexion LDAP elle-même reste hors périmètre.
3. ✅ **Notifications : tâches automatiques d'alerte manquantes — fait (v0.20.3, 2026-08-12).** Voir
   la section "Cinquième passage" ci-dessus.
4. ✅ **Mode "express" du wizard — fait (v0.24.0, 2026-08-12), inspiré de Freshservice.** Découvert
   en concevant la fonctionnalité que la mécanique nécessaire existait déjà : choisir un profil à
   l'étape 1 déclenche déjà `applyProfileDefaults()`, qui préremplit *tous* les champs des 17 étapes
   (calendrier, SLA, catégories, réglages généraux...) via `ConfigurationProfile::
   getSuggestedDefaults()`. Le seul vrai manque était la navigation : rien ne permettait de terminer
   sans cliquer "Suivant" 16 fois pour relire chaque écran déjà rempli. Ajouté un second bouton
   "Terminer avec les réglages recommandés" directement sous les choix de profil (étape 1), qui
   soumet le même formulaire unique avec `name="finish"` — aucune nouvelle logique serveur, aucun
   nouveau champ de config, juste un raccourci de navigation. Le plus pertinent en mode mono-entité
   (rien d'autre à décider) ; en mode multi-site/MSP, l'arborescence réelle (étape 2) reste à
   construire séparément ensuite, sans quoi tout s'applique à l'entité racine seule — précisé dans
   le texte du bouton et la confirmation.
5. ✅ **Bibliothèque de "profils métier" prêts à l'emploi — fait (v0.22.0, 2026-08-12), portée
   réduite par rapport à la piste initiale.** Contrairement au SLA IT (`Config::DEFAULT_SLA_TIERS`,
   sourcé sur une vraie pratique ITIL), il n'existe aucune pratique RH/Facilities équivalente à citer
   — inventer des gabarits/contenus complets par verticale aurait été le même risque que les règles
   métier GLPI volontairement laissées de côté ("inventer serait pire que rien", cf. section
   "Règles" plus haut). Implémenté à la place comme un préréglage 1-clic purement client (JS), sans
   nouveau champ serveur : 4 boutons (IT pur / RH & Support interne / Bâtiment & Moyens généraux /
   Multi-services) qui précochent les branches de catégories déjà conçues (étape 5) et remplissent
   le tableau SLA (étape 4) avec le rythme IT existant ×2 (Bâtiment, intervention physique) ou ×4
   (RH, rarement classe "panne"), plutôt que des valeurs indépendamment inventées — cohérent avec le
   fait que ces multiplicateurs sont assumés comme un point de départ, pas une norme.
6. ✅ **`BrandingBuilder` — couvrir les 6 variables de logo et la vraie palette de couleurs Tabler —
   fait (v0.21.0, 2026-08-12).** Voir CHANGELOG.md.
   ✅ **Palette `.scss` custom — fait (v0.23.0, 2026-08-12), nouveau `PaletteBuilder`.** Mécanisme
   distinct et complémentaire de `BrandingBuilder` (confirmé dans `Glpi\UI\ThemeManager` : un fichier
   dans `files/_themes/` devient une palette sélectionnable par tout utilisateur dans ses propres
   préférences, `\Config::setConfigurationValues('core', ['palette' => ...])` en fait le choix par
   défaut — pas un forçage par entité comme `custom_css_code`). **Piège GLPI core trouvé en testant** :
   `Theme::getPath()` suppose toujours l'extension `.scss`, même pour un fichier `.css` pourtant
   accepté par la détection — un fichier `.css` fait planter *tout* le site (500 partout, y compris
   la page de login) car `ThemeManager::getCustomThemesPaths()` tourne sur chaque requête. Réutilise
   la même couleur que la case au-dessus, pas un second sélecteur.
   ✅ **Palettes natives GLPI sélectionnables dans le wizard — fait (v0.25.0, 2026-08-12).** Menu
   déroulant listant les 18 palettes natives (`Glpi\UI\ThemeManager::getCoreThemes()`, noms/état
   sombre lus dynamiquement depuis GLPI plutôt que dupliqués en dur dans le wizard), alternative
   mutuellement exclusive à la palette personnalisée dans l'UI — `PaletteBuilder::apply()` pointe
   simplement `core.palette` sur la clé native choisie, aucun fichier généré.
7. ✅ **Contrôle de prérequis serveur en tout début de wizard — fait (v0.23.0, 2026-08-12), portée
   réduite à ce qui est réellement pertinent.** GLPI lui-même a déjà validé PHP/MySQL au moment de
   sa propre installation — revalider ces prérequis aurait été redondant. Recentré sur ce que ce
   plugin a spécifiquement besoin (droits d'écriture sur `files/_themes` pour la palette custom,
   sur `GLPI_CACHE_DIR` pour GLPI en général — un vrai souci de permissions rencontré cette session
   après une manipulation hors wizard, confirmant la pertinence du contrôle) : bandeau
   informatif au-dessus de l'étape 1, jamais bloquant, visible seulement s'il y a un point
   d'attention réel.

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
