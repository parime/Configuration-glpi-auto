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
> 11.0.8.
>
> **Mise à jour (2026-08-17)** : les 10 items ci-dessous sont désormais tous livrés : les cases
> étaient simplement restées non cochées au fil des sprints suivants (74 versions livrées depuis,
> voir CHANGELOG.md), pas un signe d'avancement réel. Le plugin a largement dépassé ce périmètre
> initial (52 fichiers `*Builder` dans `src/`, bien au-delà des 10 items d'origine).

**Premières releases réelles** : `v0.1.0` (2026-08-10, Sprints 1-2), version actuelle `v0.65.0`
(2026-08-19), voir [Releases GitHub](https://github.com/parime/Configuration-glpi-auto/releases)
et [CHANGELOG.md](CHANGELOG.md).

**Fonctionnalités prévues** :
- ✅ Catalogue de profils de configuration (CRUD, Sprint 1)
- ✅ Réglages de structure d'entités (mono/multi/MSP), aperçu en temps réel (Sprint 2)
- ✅ Assistant graphique (Wizard) : 18 étapes, `front/wizard.php`
- ✅ Moteur de déploiement (application effective d'un profil sur une instance) : `Config.php`
- ✅ Calendriers intelligents : horaires par jour, coupure déjeuner, jours fériés automatiques
  selon le pays détecté (France par défaut, v0.64.0)
- ✅ SLA et OLA prédéfinis : escalade N1/N2/N3
- ✅ Branding et personnalisation graphique : couleur, logo, palettes natives GLPI (19 au choix),
  templates de notification habillés
- ✅ Templates pour tickets, problèmes, changements
- ✅ Catalogue de services complet : 23 services / 7 branches
- ✅ Gestion des profils utilisateurs, 37 règles LDAP

**Limites identifiées à corriger (Sprint 11, 2026-08-10)**, remontées en testant le wizard, pas
encore implémentées :

- ✅ **Calendrier : horaires par jour + coupure déjeuner, fait (v0.57.0, 2026-08-14), sur demande
  explicite de l'utilisateur.** `CalendarBuilder` ne construisait qu'une seule plage horaire
  uniforme pour tous les jours cochés ; `Config::getCalendarDayHours()` (nouveau) permet
  désormais un horaire propre par jour (ex. vendredi 9h-12h seulement), et une coupure déjeuner
  optionnelle scinde en deux segments tout jour dont les horaires la couvrent réellement. Même
  capacité côté calendrier par client/site (mode MSP). Voir CHANGELOG pour le détail technique et
  la vérification en réel.

- ✅ **Multi-entité "même entreprise" vs "MSP" : plus purement cosmétique, note obsolète mise à
  jour (2026-08-12).** Écrite tôt dans le projet, avant que les sprints suivants n'aient
  concrètement traité chacun des trois points listés à l'époque :
  - calendrier et SLA propres à chaque client/site : **fait (Sprint 13)**, étendu à tout mode
    multi-entité (pas seulement MSP) ;
  - logo/couleur de personnalisation différents par client : logo déjà par-client depuis
    longtemps (`entity_logo_N`, un fichier par nœud de premier niveau) ; **couleur : fait
    (v0.25.0, 2026-08-12)**, nouveau réglage "Couleur différente par client/site" dans
    `BrandingBuilder::applyPerClientColors()`, même schéma de panneau par entité que le logo ;
  - cloisonnement des droits entre entités clientes : **déjà couvert pour les utilisateurs
    synchronisés LDAP** (`RuleRightBuilder`, Sprint 27) : chaque règle assigne l'utilisateur à
    l'entité *feuille* précise (pas la racine, pas récursif), ce qui suffit au cloisonnement natif
    GLPI par défaut (confirmé en relisant `RuleRightBuilder::createRule()` : aucune action
    `is_recursive` positionnée, donc non-récursif par défaut). Ne couvre pas les comptes créés à
    la main (hors LDAP) ni le compte `glpi`/Super-Admin natif : restreindre ce dernier
    automatiquement serait une action destructive risquant de verrouiller l'admin hors de son
    instance, hors de portée d'un wizard de configuration.

- ✅ **SLA plat (un seul TTO/TTR) au lieu de SLA par priorité, confirmé par recherche
  (2026-08-10), fait en Sprint 14.** Vérifié par recherche web (voir sources dans l'historique de conversation,
  ITIL 4 priority matrix, GLPI Service Levels documentation officielle) : en pratique ITSM
  réelle, les SLA sont quasi-systématiquement définis **par niveau de priorité** (P1 Critique →
  P4 Faible), pas un seul couple prise-en-charge/résolution pour tous les tickets : un P1 peut
  avoir 15 min de prise en charge quand un P4 en a 1 jour ouvré. GLPI calcule déjà nativement une
  Priorité par ticket (matrice Urgence × Impact configurable, Setup > Général > Assistance), et
  sa façon documentée d'assigner un SLA est justement une règle métier qui matche sur `priority`,
  exactement le mécanisme `RuleTicket`/`RuleCriteria`/`RuleAction` que `SlaBuilder.php` utilise
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
décoration, ce n'est pas prioritaire ; quand on y reviendra, prévoir un aperçu en direct plutôt
qu'une simple case couleur. En attendant, prioriser : les intitulés/libellés du wizard, les
catégories de tickets ITIL (incident/demande/problème/changement), et les templates de tickets.

**Audit de complétude : bonnes pratiques ITIL/ISO27001/GLPI (2026-08-10)**

Suite à la demande explicite de l'utilisateur ("je veux une configuration complète de GLPI dans le
respect des bonnes pratiques, dis-moi ce qui manque") : recherche sur les pratiques recommandées
pour la configuration initiale d'un outil ITSM comme GLPI, ITIL 4 et ISO 27001. Ce que couvre déjà
le wizard vs ce qui manque, avec le pourquoi de chaque manque, pas de jugement de priorité fait
unilatéralement, à trancher avec l'utilisateur.

*Déjà couvert par le wizard* : structure d'entités (mono/multi-site/MSP), calendrier (partagé ou
par site/client), SLA par niveau de priorité (partagé ou par site/client, Sprint 14), branding
basique, profils de démarrage.

*Manques identifiés, par ordre approximatif d'impact ITIL* :

1. **OLA (Operational Level Agreement) : fait (Sprint 14, 2026-08-10).**
   Engagement interne entre le helpdesk et les équipes support, qui vient épauler le SLA externe
   (ex : SLA "résolution sous 4h" au client ⇒ OLA interne "niveau 1 trie sous 30 min, niveau 2
   diagnostique sous 2h"). Implémenté dans `SlaBuilder` (même classe que le SLA externe, avec
   `sla_astreinte` pour la couverture 24/7), quasi symétrique à SLA côté GLPI (`OLA` étend la
   même classe `LevelAgreement` que `SLA`, `glpi_olas`, `olas_id_tto`/`olas_id_ttr` sur les
   tickets, même moteur `RuleTicket`).

2. **Catégories de tickets + types ITIL (Incident/Demande/Problème/Changement) : fait (Sprint 17,
   2026-08-10).** ITIL 4 distingue 4 types de ticket avec des pratiques de gestion différentes
   (Incident Management, Request Management, Problem Management, Change Management), GLPI a
   nativement `ITILCategory` et un champ `type` sur les tickets. `CategoryBuilder` construit une
   arborescence thématique réelle (IT, Bâtiment, Flotte, RH...) plutôt qu'une catégorie par type
   ITIL (le type Incident/Demande est déjà géré nativement par GLPI, une catégorie par type
   n'apportait rien, voir Sprint 17 dans le CHANGELOG).

3. **Templates de tickets : fait (Sprint 19, 2026-08-10).** Pas un template par catégorie au
   final (`TicketTemplateBuilder`) : la pratique ITSM courante réserve ça au catalogue de services
   (point 5, pas encore fait) ; à la place, deux templates par audience : un simplifié
   (titre+description) pour les profils sans droits élevés (Self-Service, Read-Only), un complet
   (catégorie+urgence obligatoires, rien de masqué) pour le reste, câblés via
   `glpi_profiles.tickettemplates_id`, un mécanisme natif GLPI par profil.

4. **Niveaux d'escalade SLA/OLA (`SlaLevel`/`OlaLevel`) : fait (Sprint 28, 2026-08-11 ; complété
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

5. **Catalogue de services : fait (Sprint 23, 2026-08-11).** `ServiceCatalogBuilder`, sur le
   système natif de formulaires de GLPI 11 (`Glpi\Form\Form`) : 23 services sur 7 branches, chacun
   ne demandant que titre + description, routé automatiquement vers la bonne catégorie de ticket
   sans que l'utilisateur ait à la choisir. Validé de bout en bout avec un vrai compte Self-Service.

6. **Droits/profils GLPI par entité (cloisonnement) : fait partiellement (Sprint 26, 2026-08-11).**
   `RuleRightBuilder` scaffolde une `RuleRight` par site (feuille de l'arborescence) : GLPI
   affecte automatiquement l'entité + un profil fixe à un utilisateur d'après son groupe AD/LDAP
   lors de la synchronisation : mécanisme confirmé sur un vrai export de production (37 règles
   `RuleRight` réelles), généralisé via un gabarit de nom de groupe configurable plutôt que les
   noms d'AD réels de l'export. Ne sert que si une synchronisation LDAP est prévue par
   l'organisation ; sans ça, cette étape ne crée rien d'utile, ce qui est le comportement attendu.
   L'affectation d'un profil global par fonction métier (ex. "Finance"/"DSI" → profil,
   indépendamment du site) : **fait (Sprint 36, 2026-08-12).** Intégrée à l'étape LDAP existante
   plutôt qu'une étape séparée (confirmé avec l'utilisateur) : liste répétable optionnelle de
   paires (groupe AD, profil), chaque paire devient une `RuleRight` sans action `entities_id`, qui
   s'accumule avec les règles par site ci-dessus plutôt que les remplacer (confirmé en base et dans
   le code source de `RuleRightCollection`). Reste hors périmètre : la gestion fine des droits par
   module au sein d'un même profil.

7. **Modèles de notifications : fait (Sprint 25, 2026-08-11).** GLPI a déjà de bons modèles par
   défaut ; le vrai manque était que plusieurs notifications de cycle de vie du ticket sont
   `is_active=0` d'origine, dont `Ticket`/`auto_reminder`, exactement celle que déclenchent les
   relances automatiques du Sprint 24 (bug réel corrigé au passage : les relances étaient créées
   mais jamais notifiées au demandeur).

8. **Workflow de validation (approbation) : fait (Sprint 25 puis v0.56.0, 2026-08-14).** Ajout
   d'une étape "Validation comité (2/3)" en plus de la "Validation" (100%) native, pour les
   décisions collégiales. ✅ **Routage automatique vers le supérieur hiérarchique (N+1) : fait
   (v0.56.0, 2026-08-14), sur demande explicite de l'utilisateur.** `ValidationRoutingBuilder`
   (nouveau) : mécanisme 100% natif GLPI (`RuleTicket` + action `responsible_id_validate`, lit
   `glpi_users.users_id_supervisor`), voir CHANGELOG pour le détail technique. Dépend toujours de
   ce champ étant renseigné par organisation (LDAP ou saisie manuelle) : vérifié en réel que
   l'absence de superviseur ne casse rien (aucune validation créée plutôt qu'une ligne invalide).

9. **Enquêtes de satisfaction post-résolution : fait (Sprint 25, 2026-08-11).** L'enquête native
   GLPI (1 à 5 étoiles + commentaire) était techniquement "activée" mais avec un taux
   d'échantillonnage à 0%, traité par GLPI comme entièrement désactivé. Activée à 100%. Une
   enquête multi-questions plus riche nécessiterait un outil externe (`inquest_config` = externe +
   URL), hors périmètre car dépendant de l'outil choisi par chaque organisation.

---

## 🔎 Troisième audit : inventaire complet des "Intitulés" (2026-08-11)

Après les deux audits précédents (liste ci-dessus, et l'export de production Sprints 22-28), un
troisième passage plus systématique : la page GLPI Configuration > Intitulés recense *tous* les
types d'objets pré-paramétrables, catégorie par catégorie. Extrait directement de l'instance de
test réelle (pas deviné), pour ne rien manquer. État par catégorie GLPI (pas de jugement de
priorité, à trancher avec l'utilisateur comme les audits précédents) :

**Assistance** (19 intitulés), déjà fait : Gabarits de tickets (`TicketTemplateBuilder`),
Catégories ITIL (`CategoryBuilder`), Raisons d'attente (`WaitReasonBuilder`), Catégories du
catalogue de services (`ServiceCatalogBuilder`), Étapes de validation (`GeneralSettingsBuilder`).
**Fait (Sprint 29, 2026-08-11)** : Gabarits de changement/problème (`ChangeProblemTemplateBuilder`,
un modèle standard chacun, assigné à tous les profils ; pas de split base/support comme les
tickets, Self-Service n'a par défaut aucun droit sur Change/Problem), Catégories de tâches
(`TaskCategoryBuilder`, 14 catégories), Gabarits de tâche (`TaskTemplateBuilder`, 3 checklists
réutilisables), Types de solutions + vraie bibliothèque de gabarits de solution
(`SolutionLibraryBuilder`, 5 types × 2 gabarits, taxonomie de clôture ITIL générique), vraie
bibliothèque de gabarits de suivis (`FollowupLibraryBuilder`, 5 gabarits, distincts par nom de
ceux liés aux raisons d'attente), Gabarits de validation (`ValidationTemplateBuilder`, 5
gabarits). Sources des demandes (`RequestType`) : **vérifié, déjà suffisant** : GLPI ships 6
valeurs par défaut (Helpdesk/E-Mail/Phone/Direct/Written/Other), rien à construire. **Fait
(Sprint 30, 2026-08-11)** : Types de projet + types de tâche de projet
(`ProjectTaxonomyBuilder`, 5 + 8, généralisés sur la pratique PM standard ; l'export de production
n'avait pas personnalisé ce point, seulement les statuts natifs), Gabarits de tâches de projets
(`ProjectTaskTemplateBuilder`, 3 checklists réutilisables). Statuts de projet
(`ProjectState`) : **vérifié, déjà suffisant** : GLPI ships les 3 statuts natifs
(New/Processing/Closed), non personnalisés non plus dans l'export de production audité, pas de
bonne pratique universelle identifiée pour en ajouter d'autres, `GeneralSettingsBuilder` continue
de se contenter de les *mapper* pour le suivi d'avancement des tâches. ✅ **Gabarits d'évènements
externes + Catégories d'évènements : fait (v0.25.0, 2026-08-12), nouveau `PlanningEventBuilder`.**
`PlanningEventCategory` a un champ natif `color` (distinct du mécanisme icône
`DropdownTranslation` utilisé partout ailleurs), utilisé directement, la couleur étant ce qui
s'affiche réellement dans la grille de planning GLPI. `PlanningExternalEventTemplate` laisse
volontairement `rrule` (récurrence) à vide : un gabarit réutilisable (nom, description,
catégorie, durée plausible), pas une décision de récurrence à la place de l'admin.

**Général** (5 intitulés), fait : Statuts des éléments (`StateBuilder`). **Fait (Sprint 31,
2026-08-11)** : Lieux (`LocationBuilder`, mirroir de l'arborescence d'entités de l'étape 2, pas
une liste inventée : un `Location` par entité, même nom, même imbrication, scopé à l'entité
réelle), Fabricants (`ManufacturerBuilder`, ~29 fabricants IT/bureautique courants). Pas prévu
(sécurité anti-spam, pas de bonne pratique universelle à préremplir) : Listes noires
(`Blacklist`), Contenu de mail interdit (`BlacklistedMailContent`).

**Outils**, **fait (Sprint 31, 2026-08-11)** : Catégories de la base de connaissances
(`KnowbaseCategoryBuilder`) : réutilise les 11 thèmes de `CategoryBuilder` (étape 5) plutôt
qu'une seconde taxonomie inventée, filtré sur les branches effectivement sélectionnées.

**Gestion**, ✅ **fait (v0.23.0, 2026-08-12).** `DocumentManagementBuilder` (nouveau) : Rubriques
des documents (`DocumentCategory`) suivant l'échelle standard de classification de l'information
ISO 27001 Annexe A.8.2 (Public/Interne/Confidentiel/Diffusion restreinte, aucun champ natif GLPI
équivalent sur `Document`, c'est le mécanisme le plus proche), Criticités (`BusinessCriticity`,
échelle d'impact métier à 4 niveaux, utilisée en réalité par les *actifs* via `Infocom`, pas les
documents, confirmé via `information_schema.COLUMNS`). Types de documents (`DocumentType`) :
**vérifié, déjà suffisant** : GLPI ships 73 types natifs (toutes les extensions courantes), rien à
construire, même conclusion que `RequestType`/`ProjectState` ailleurs dans ce document.

**Règles** (`Configuration > Règles`), fait : Règles d'affectation d'habilitations à un
utilisateur (`RuleRight`, Sprint 27). Étudié et volontairement pas fait : règles métier
tickets/changements/problèmes (`RuleTicket`/`RuleChange`/`RuleProblem`) : logique de routage/
priorisation propre à chaque organisation, inventer des règles arbitraires serait pire que ne
rien faire (même raisonnement que le "niveau 2" laissé de côté aux Sprints 27/28) ; règles
d'affectation de catégorie aux logiciels (`RuleSoftwareCategory`) : gestion d'actifs, hors
périmètre Assistance.

**Dictionnaires**, étudié et volontairement pas fait : couvrent exclusivement la normalisation
de données d'inventaire déjà importées (logiciels, fabricants, modèles/types de matériel,
systèmes d'exploitation, confirmé en listant la page réelle, aucun lien avec l'Assistance).
Sur un GLPI neuf sans inventaire, il n'y a rien à normaliser, et sans données réelles désordonnées
à calibrer, générer des règles regex de départ reviendrait à deviner : risque de mal normaliser
les vraies données plus tard. Format confirmé dans `RuleAction.php` (groupes de capture
référencés `#0`, `#1`... dans le champ de remplacement) si le sujet revient avec de vrais
exemples à traiter.

**Décision utilisateur (2026-08-11)** : traiter tout le bloc Assistance + Général/Outils listés
comme "pas fait" ci-dessus. Découpé en plusieurs sprints vu le volume, voir CHANGELOG.md pour
l'avancement réel sprint par sprint (ce document décrit l'état au moment de l'audit, pas l'état
courant). Sprint 29 (cycle de vie ticket/tâche/changement/problème), Sprint 31 (Général/Outils)
et Sprint 30 (Projets) sont faits : **la troisième vague d'audit est close**. Restent en attente,
non cadrés techniquement : les intitulés basse-priorité explicitement laissés de côté ci-dessus
(gabarits/catégories d'évènements planning), et le logo par entité (voir plus bas).

**Bug corrigé au passage (Sprint 31, 2026-08-11)** : `Config::prepareInput()`'s traitement de
`category_branches` castait `(array)` une chaîne JSON au lieu de la décoder, sur une instance
vraiment neuve (jamais soumise via le formulaire), `getDefaults()` fournit `category_branches`
sous forme de chaîne JSON, pas de tableau PHP, donc chaque nouvelle installation démarrait
silencieusement avec 0 branche sélectionnée à l'étape 5 au lieu des 11 documentées. Resté invisible
tant que l'instance de test accumulait des soumissions réelles (le tableau PHP écrase alors la
chaîne), révélé par la remise à zéro complète de l'environnement demandée cette session. Corrigé :
`prepareInput()` décode maintenant explicitement si la valeur reçue est une chaîne.

**Demande complémentaire : fait (2026-08-11)** : personnalisation graphique par entité : un logo
uploadable par client/site (en plus de la couleur principale plugin-wide de `BrandingBuilder`),
visible sur l'entité correspondante. Confirmé en source qu'aucun champ logo natif n'existe sur
`Entity` : le mécanisme retenu (après confirmation avec l'utilisateur) réutilise
`custom_css_code` (déjà natif par entité, déjà utilisé par `BrandingBuilder` pour la couleur) :
le fichier uploadé est encodé en `data:` URI et injecté dans la variable CSS `--glpi-logo`
(confirmée dans le SCSS source de GLPI, `.glpi-logo { background: var(--glpi-logo) no-repeat; }`),
pas d'upload `Document` séparé à maintenir, pas de sélecteur DOM fragile. `BrandingBuilder`
délimite chaque bloc CSS qu'il écrit par un marqueur de commentaire (`mergeCssBlock()`) pour que
couleur et logo coexistent sans s'écraser l'un l'autre lors des ré-exécutions.

**Demande complémentaire : fait (2026-08-11)** : `StateBuilder` (Sprint 16) créait ses 14 statuts
sans granularité (tout ou rien). Passé en cases à cocher individuelles, chacune activable/
désactivable indépendamment, même principe que `category_branches`. Cinq statuts ("En stock",
"Attribué", "Donné", "Vendu", "Attente restitution") marqués « recommandé » dans l'interface : ce
sont ceux utilisés par le plugin `remise-glpi` (https://github.com/parime/remise-glpi) pour
déclencher automatiquement son propre workflow de remise/don/vente/restitution sur changement
d'État : les décocher ne casse rien (remise-glpi référence un État par ID configuré dans ses
propres réglages, pas par nom exact), mais réduit l'interopérabilité entre les deux plugins si
l'organisation utilise `remise-glpi`. **Bug trouvé et corrigé au passage** : les noms de statuts
accentués ("Attribué", "Obsolète"...) étaient corrompus par la migration `addField()` : l'échappement
JSON `\uXXXX` perdait son antislash en traversant la clause SQL `DEFAULT`, transformant "Attribué"
en "Attribuu00e9". Corrigé en encodant le JSON par défaut avec `JSON_UNESCAPED_UNICODE` (caractères
UTF-8 bruts, aucun antislash à perdre).

---

## 🔍 Quatrième audit : vérification de complétude + recherche marché (fait, 2026-08-12)

**Contexte** : le Sprint 34 (icônes/traductions) puis sa correction v0.20.0 (gabarits de ticket/
changement/problème/solution/tâche/suivi/validation, oubliés puis mal exclus à tort) ont montré que
relire le code ne suffit pas à garantir qu'une fonctionnalité marche réellement : un
`DropdownTranslation` peut exister en base sans jamais s'afficher nulle part (`ITILTemplate` n'a
pas d'onglet Traductions), et inversement une classe peut sembler ne pas supporter un mécanisme
alors qu'elle le supporte bel et bien (`AbstractITILChildTemplate`). Audit mené en deux temps :
recherche web (2 agents en parallèle) + un audit de complétude en base, plus rapide et plus
exhaustif qu'un audit Playwright écran par écran pour ce cas précis (comparer le nombre de lignes
réellement traduites au nombre total de lignes par itemtype révèle un trou aussi sûrement qu'une
capture d'écran, sans le coût d'une visite par intitulé).

### Bugs trouvés et corrigés (v0.20.2)

Méthode : requête SQL comparant, pour chaque itemtype à icônes, le nombre total de lignes créées
au nombre de lignes ayant réellement une traduction dans `glpi_dropdowntranslations` : tout écart
est soit un item natif GLPI jamais touché par le plugin (attendu, ex. le gabarit "Default" natif de
`TicketTemplate`), soit un vrai trou. Trois vrais trous trouvés :

1. **`WaitReasonBuilder` ne traduisait jamais ses gabarits liés.** Chaque `PendingReason` peut
   créer son propre `ITILFollowupTemplate`/`SolutionTemplate` dédié (ex. "Attente de retour
   utilisateur" génère un gabarit de suivi ET un gabarit de solution du même nom), la raison
   d'attente elle-même recevait bien son icône, mais ses deux gabarits liés, jamais. Aggravé par un
   second bug dans le correctif lui-même : la branche "déjà existant" de `getOrCreateReason()`
   retournait avant d'atteindre le nouveau code, donc réexécuter le wizard sur une instance déjà
   configurée ne rattrapait rien : corrigé en relisant les IDs de gabarits liés directement depuis
   la `PendingReason` existante.
2. **`ProjectTaskTemplateBuilder` n'avait jamais eu d'icônes du tout** : angle mort du même genre
   que celui corrigé en v0.20.0 pour `TicketTemplate`/`ChangeTemplate`/`ProblemTemplate` : la classe
   (`ProjectTaskTemplate extends CommonDropdown`) n'a simplement jamais été incluse dans la liste
   des itemtypes à traiter à l'époque. Nouveau réglage `project_task_template_icons_enabled` ajouté,
   3 icônes choisies (🎯 Cadrage initial, 📍 Point d'avancement, 🏁 Revue de clôture).
3. **`CategoryBuilder` : 37 sous-catégories de niveau 3 (sur 103 `ITILCategory` au total) n'avaient
   jamais de traduction**, alors que les 5 langues existaient déjà dans `Translations.php` pour
   chacune d'elles (travail déjà fait au Sprint "traductions", jamais branché), la cause exacte :
   `Translations::applyIcon()` n'était appelée que si le nœud avait une icône (`isset($node['icon'])`),
   et les feuilles de l'arbre n'en ont volontairement jamais eu (choix de design du Sprint 16, pas
   remis en cause). Corrigé en découplant les deux : `applyIcon()` accepte maintenant une icône
   vide (`trim()` sur le résultat pour éviter un espace résiduel), et `CategoryBuilder` traduit
   désormais chaque nœud dès que `category_icons_enabled` est actif, icône ou pas. Résultat pratique
   pour l'utilisateur : une session en anglais/allemand/italien/espagnol voyait jusqu'ici les ~37
   catégories les plus fines (ex. "Ordinateur fixe", "Wifi") rester en français au milieu d'une
   arborescence sinon traduite ; plus le cas.

Les trois corrections vérifiées de bout en bout sur l'instance de test réelle (pas seulement en
base) : soumission complète du wizard, recomptage SQL avant/après (66→103 pour `ITILCategory`,
0→3 pour `ProjectTaskTemplate`, 5→7 et 10→11 pour les gabarits liés de `WaitReasonBuilder`).
**Piège opérationnel rencontré en testant** : l'opcache PHP du conteneur GLPI n'a pas repris le
correctif du bug n°1 tout de suite malgré `opcache.validate_timestamps=On`, un redémarrage du
conteneur a été nécessaire. À garder en tête pour la prochaine session : si un correctif ne semble
"pas prendre" en test alors que le code est correct, redémarrer le conteneur avant de creuser plus
loin une fausse piste.

Passé en revue tous les builders restants pour le même genre de trou (création d'un itemtype à
icônes sans appel `applyIcon()` à proximité), aucun autre trouvé à ce jour.

### Cinquième passage : audit navigateur réel (fait, 2026-08-12, v0.20.3)

L'audit ci-dessus était base de données + code, pas une navigation réelle dans l'interface :
l'utilisateur l'a fait remarquer, à raison (c'est précisément la leçon de la correction v0.20.0).
Deux choses vérifiées en parcourant l'admin GLPI par Playwright :

- **Configuration > Intitulés** : les captures d'écran/textes des pages déjà auditées confirment ce
  que la base disait : pas d'écart trouvé entre les deux méthodes sur les intitulés déjà couverts.
- **Configuration > Actions automatiques** : a révélé un vrai trou du même genre que
  `auto_reminder` (Sprint 25) : les tâches automatiques `cartridge`/`consumable`/`software`
  (alertes cartouches, consommables, expiration de licences) ships `Désactivé` par défaut, alors
  que leurs `Notification` correspondantes sont déjà `is_active = 1` : la notification a l'air
  configurée mais ne se déclenche jamais, faute de tâche pour la déclencher. Corrigé dans
  `GeneralSettingsBuilder::applyNotifications()` (même toggle "Notifications" que le reste, cohérent
  avec ce que la case promet). Vérifié en base (`state` 0→1) et dans l'admin réel (« Désactivé » →
  « Programmée »).
- ❌ **Configuration > Authentification** (LDAP/SMTP) et **> Collecteurs** : confirmés non couverts,
  mais explicitement écartés par l'utilisateur (2026-08-14) : trop dépendant de l'annuaire propre à
  chaque organisation pour être généralisable, ne sera pas construit.

### Recherche web : points de friction GLPI réels (résumé, détail complet demandé à l'utilisateur si besoin)

Recherché sur le forum GLPI officiel, les issues GitHub `glpi-project/glpi`, et le web général :
structure d'entités mal comprise dès le départ (aucune vue d'impact avant de choisir) ; confusion
SLA/OLA persistante malgré le preset déjà livré ; LDAP : connexion OK mais import KO, filtres
fragiles, messages d'erreur peu clairs (très fréquent, non couvert par le plugin aujourd'hui) ;
catégories de tickets mal filtrées par entité (mi-bug core, mi-config) ; droits/profils : risque
réel d'auto-élévation faute de séparation stricte par défaut (axe ISO 27001 direct, pas encore
traité) ; notifications email désactivées par défaut + pièges SMTP (très fréquent, bloquant, pas
couvert) ; prérequis serveur mal anticipés avant même d'atteindre la configuration applicative (hors
périmètre wizard, mais un contrôle de prérequis en tout début de parcours serait utile).

### Recherche web : patterns d'onboarding des concurrents ITSM (résumé)

ServiceNow (Guided Setup séquencé par dépendances, étapes verrouillées tant qu'un prérequis n'est
pas actif) ; Freshservice (onboarding minimal 3 étapes, approfondissement plus tard, idée de "mode
express" distinct du mode complet actuel) ; Jira Service Management (templates de projet métier
pré-configurés en un clic : IT/RH/Facilities avec workflows+SLA assortis, fort potentiel de
transposition) ; Zendesk (préparation du contenu self-service en amont plutôt qu'en fin de
parcours).

### Recherche : variables CSS GLPI 11 pour un branding exhaustif (résumé)

`BrandingBuilder` ne surchargeait initialement que `--glpi-logo` (une seule des 6 variantes réelles :
`--glpi-logo-light/-light-reduced/-dark/-dark-reduced/-light-login/-dark-login`, le mode réduit, le
mode sombre et l'écran de login gardaient donc le logo natif GLPI) : **fait entre-temps** (v0.64.0,
2026-08-17, corrigé au passage du bug de duplication ×8 du logo en base64) : les 8 variables réelles
sont toutes surchargées via une seule propriété CSS personnalisée partagée (`--cga-logo-url`,
référencée par `var()`), le mode réduit/sombre/login affichent désormais tous le bon logo. Couleur
primaire approximée (alors que `--tblr-primary`/`--tblr-primary-fg`/`--tblr-primary-darken` etc. existent, dérivées
nativement par `color-mix`). GLPI 11 expose aussi `--glpi-mainmenu-*` (menu principal), des
variables de portail helpdesk calculées depuis le menu, et surtout un **mécanisme officiel plus
propre que `custom_css_code`** apparu en 11.0 : déposer un `.scss` dans `files/_themes`, auto-
détecté comme palette sélectionnable : évite les soucis de spécificité (`!important` contre Tabler)
et couvre nativement le dark mode, deux limites confirmées de l'approche actuelle. GLPI ships aussi
des palettes natives (`auror`, `dark`, `midnight`, `teclib`...), **fait entre-temps** (le fichier
`.scss` déposé dans `files/_themes` mentionné ci-dessus, `PaletteBuilder`/`native_palette`) : le
wizard propose bien un sélecteur listant dynamiquement toutes les palettes natives de l'instance
(`Glpi\UI\ThemeManager::getCoreThemes()`, jamais codé en dur), confirmé en réel : 19 options
(18 palettes + "Aucune") sur cette instance GLPI 11.0.8. Cette note de recherche datait d'avant
cette implémentation et n'avait jamais été mise à jour.

---

### Sixième audit : module Projets + points transverses (fait, 2026-08-12, v0.26.0)

Audit réel (code source GLPI + base de données, pas de suppositions) centré sur le module Projets à
la demande de l'utilisateur, plus quelques questions transverses posées dans la foulée.

**Projets** :
- Notifications projet (`New/Update/Delete Project`, `New/Update/Delete Project Task`) :
  **vérifié déjà actives par défaut** en base, contrairement au bug `auto_reminder` trouvé sur les
  tickets (Sprint 25). Rien à corriger.
- Statuts de projet (`ProjectState`), re-confirmé : conclusion "déjà suffisant" du troisième audit
  tient toujours (`is_finished`/`color` déjà cohérents nativement sur les 3 statuts).
- Rôles d'équipe (`ProjectTeam`) : constante PHP figée (`Team::ROLE_MEMBER`...), pas une liste
  configurable, rien à construire.
- **Gap réel identifié : fait (v0.34.0, 2026-08-13).** GLPI permet de sauver un projet existant
  comme "modèle" (`is_template`) et d'en recréer un nouveau à partir de ce modèle, comme les
  gabarits de ticket, mais le plugin n'en fournissait aucun. `ProjectTemplateBuilder` construit
  deux modèles pré-structurés (« Déploiement standard », 6 tâches ; « Projet interne : cycle
  court », 3 tâches) en s'appuyant sur `Project::getCloneRelations()` (confirmé dans le code
  source de GLPI : inclut `ProjectTask::class`, donc le sélecteur de gabarit natif clone déjà les
  tâches automatiquement). Vérifié en réel via le vrai flux `project.form.php?...&withtemplate=2` :
  les 6 tâches apparaissent bien sur le nouveau projet, sans rien à construire côté UI. Détail
  complet dans `CHANGELOG.md` `[0.34.0]`.

**Variables/balises dans les gabarits (suivi/tâche/solution/ticket) : correction en cours d'audit.**
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

**Catégories FAQ** (`KnowbaseCategoryBuilder`) : re-confirmé créées et délibérément alignées sur les
mêmes branches que les catégories ITIL/catalogue de services (filtrage identique), pas une seconde
taxonomie inventée.

**Documents** (`DocumentManagementBuilder`) : re-confirmé créés (v0.23.0), icônes + traductions
complètes vérifiées sur les 8 termes (4 classifications + 4 niveaux de criticité) dans
`Translations.php`, rien ne manque.

**Personnalisation HTML/CSS des e-mails : fait (v0.31.0, 2026-08-13).** Piste initiale
(`mailing_signature`) réévaluée en cours de route : ce champ ne supporte que du texte simple
d'après l'UI (`<textarea>`), et surtout, trouvé en creusant le vrai flux de rendu
(`NotificationTemplate::makeAllReplacements()`), GLPI ne partage aucun habillage HTML commun entre
notifications : chaque évènement (nouveau ticket, mise à jour...) pointe en réalité vers le *même*
`NotificationTemplate` natif partagé (confirmé en base), pas des gabarits distincts. Un vrai jeu
d'e-mails HTML de production (4 évènements, balises `##ticket.xxx##` réelles) a servi de référence
de structure : `NotificationBrandingBuilder` (nouveau) crée un gabarit HTML dédié par évènement
(nouveau ticket/mise à jour/résolution/nouveau suivi) avec la couleur/logo déjà calculés par
`BrandingBuilder`, et réassigne l'évènement correspondant vers ce nouveau gabarit. Vérifié de bout
en bout : gabarits créés, évènements réassignés en base, idempotence confirmée, et un vrai ticket
créé pour confirmer que la notification réellement mise en file contient le HTML habillé avec les
balises correctement substituées. **Corrigé en v0.31.1** : régression trouvée en se relisant avec
un œil critique : une seule ligne `language=''` par gabarit aurait montré des libellés français à
tout destinataire, quelle que soit sa langue GLPI. Une ligne par langue désormais (5 langues).

**Contenu des gabarits de suivi/tâche/solution traduit, fait (v0.32.0, 2026-08-13).** Trouvé lors
de l'analyse critique post-v0.31.1 : même type de limite que le bug des notifications, contenu
traduisible en théorie (`AbstractITILChildTemplate::getRenderedContent()` appelle
`DropdownTranslation::getTranslatedValue(..., $_SESSION['glpilanguage'], ...)`) mais aucune ligne
`DropdownTranslation` n'existait pour leur `content`. D'abord laissé de côté ("moins grave, texte
de départ qu'un technicien édite"), puis l'utilisateur a explicitement demandé de traduire "tout
sans exception". Corrigé. `Translations::applyContent()` (nouveau, même mécanisme que
`applyIcon()` mais sur le champ `content`) : les 18 gabarits (5 suivis + 10 solutions + 3 tâches)
ont désormais leurs 4 traductions, y compris la salutation Twig ("Bonjour" → "Hello"/"Hallo"/
"Buongiorno"/"Hola", même prudence itemtype-aware que la v0.27.0). Vérifié en réel : une session
anglaise appliquant un gabarit de suivi reçoit bien "Hello glpi," et le corps traduit, rendu Twig
inclus.

**Filigrane PDF sur documents confidentiels**, position inchangée depuis la première discussion :
nature technique différente (traitement de fichier temps réel vs scaffolding one-shot), plugin
séparé recommandé plutôt qu'ajout ici.

**Fabricants : visibilité selon le type de produit, vérifié non supporté nativement (2026-08-12).**
L'utilisateur a fait remarquer qu'un fabricant comme Jabra (casques/audio) ne devrait pas apparaître
dans la liste déroulante fabricant lors de la création d'un ordinateur. Vérifié en base
(`DESCRIBE glpi_manufacturers`) : la table n'a aucun champ de portée par type d'actif (juste
`id`/`name`/`comment`/dates), c'est une liste plate partagée par tous les types d'actifs GLPI
nativement, sans mécanisme de filtrage. Implémenter ce filtrage demanderait un moteur de règles
JS personnalisé (chantier non trivial, pas juste un champ à cocher). Laissé de côté sauf demande
explicite de le construire quand même.

**Fabricants : dictionnaire de normalisation, fait (v0.32.0, 2026-08-13).** Suite à la remarque
ci-dessus, l'utilisateur a proposé une piste différente et réellement construite : un dictionnaire
GLPI natif (`RuleDictionnaryManufacturer`, confirmé dans le code source, `getActions()` supporte
`assign` sur le champ `name`) pour normaliser les variantes de nom qu'un vrai inventaire remonte
(« Hewlett-Packard », « HP Inc. »… → « HP »). Contrairement aux dictionnaires logiciel/matériel
étudiés et rejetés plus tôt (variantes propres à l'inventaire réel de chaque organisation,
impossibles à deviner à l'avance), les variantes des plus grands fabricants sont documentées et
stables (chaînes `sys_vendor`/WMI `Manufacturer` connues) : pas besoin des données réelles d'une
organisation pour écrire ces règles. `ManufacturerDictionaryBuilder` (nouveau) : 15 règles (sur les
29 fabricants créés, ceux avec des variantes réellement documentées). Vérifié avec l'outil natif de
test de règle de GLPI (`front/rule.test.php`) : « Hewlett-Packard » → validé → fabricant assigné
« HP ».

**Idée cadrée, débloquée (2026-08-12/13) ; partie automobile construite (v0.58.0, 2026-08-14, sur
demande explicite "lance la partie automobile")** : générer des « actifs personnalisés » GLPI en
fonction des branches de catégories sélectionnées à l'étape 5 : ex. la branche « Flotte Automobile »
activée créerait un type d'actif « Véhicule » avec des champs pertinents (immatriculation, type de
carburant, date de contrôle technique...), la branche « Bâtiment » un type « Local »/« Salle » ou
équivalent, la branche « IT & SI » un type « Serveur » distinct de l'actif natif `Computer` (champs
propres : position en baie, RAID, hyperviseur...).

**Blocage levé** : vérifié directement dans le code source de GLPI 11.0.8 réel (pas supposé) :
`Glpi\Asset\AssetDefinition` (`src/Glpi/Asset/AssetDefinition.php`) est un vrai `CommonDBTM`
(`extends AbstractDefinition`), pas seulement une manipulation via l'UI. Ce n'est PAS du GLPI 10+
comme noté précédemment : la généricité est native depuis la GLPI 11 seulement, migrée depuis
l'ancien plugin externe « Generic Object » (confirmé dans la doc officielle,
https://help.glpi-project.org/documentation/modules/configuration/asset-definitions). Champs clés
côté `add()`/`update()` : `system_name` (fige le nom, génère la classe
`GlpiCustomAsset<SystemName>Asset`), `capacities` (tableau JSON de « capacités » modulaires :
25+ disponibles dans `src/Glpi/Asset/Capacity/` : `HasNetworkPortCapacity`,
`HasVirtualMachineCapacity`, `HasInfocomCapacity`, `IsInventoriableCapacity`..., chaque type
d'actif active uniquement celles qui le concernent), `profiles`, `translations`, `fields_display`.

✅ **Véhicule (Flotte Automobile) : fait (v0.58.0, 2026-08-14).** `VehicleAssetBuilder` : déclenché
directement par la case de branche "Flotte Automobile & Mobilité" déjà existante (aucune case
dédiée), API vérifiée en créant un vrai actif à la main via l'interface admin réelle avant d'écrire
le code (pas supposée depuis la doc). 8 capacités (financier, contrats, documents, historique,
notes, liens, recherche globale, réservable), 5 champs personnalisés (immatriculation, carburant,
mise en circulation, contrôle technique, assurance), droits par défaut Super-Admin/Admin
uniquement. Voir CHANGELOG pour le détail complet de la vérification en réel (y compris un vrai
piège de nettoyage de données de test découvert et corrigé avant l'envoi).

✅ **IT & SI/Serveur et Bâtiment/Local : fait (v0.60.0, 2026-08-14), sur demande explicite ("fait
serveur et batiment").** `ServerAssetBuilder` : actif "Serveur" distinct de l'actif natif
`Ordinateur`, déclenché par la branche "IT & SI", 17 capacités (proches d'un `Ordinateur` natif plus
montable en baie/VM hébergées/administration à distance/certificats/instances BDD), 3 champs texte
libre (position en baie, RAID, hyperviseur). `BuildingAssetBuilder` : actif "Local", déclenché par
la branche "Bâtiment", pensé comme complément du `Location` natif (qui dit où est un actif, sans
capacités propres) plutôt qu'un doublon : un "Local" peut avoir un loyer, des contrats, des
documents, être réservable (salle de réunion). 8 capacités, 3 champs (surface, capacité en
personnes, type de local). Les deux vérifiés en réel de bout en bout, voir CHANGELOG pour le détail.

**Lieux : assistant d'adresse interactif, fait (v0.33.0, 2026-08-13).** Demandé explicitement par
l'utilisateur ("les adresse on a dit un truc interractif, comme pour les site internet ou tu
commence a taper ta rue il la sugère, idm tu met le code postal tu a la ville"), confirmant que la
ligne « assistant intelligent avec géocodage » retirée du README lors du nettoyage v0.31.0
correspondait bien à une vraie idée, pas du texte marketing oublié. Recherche faite sur les API
disponibles avant de construire :
- **Nominatim** (OpenStreetMap) et **Photon** (komoot) : couverture mondiale, gratuites, sans clé,
  CORS déjà activé sur leurs instances publiques ; mais usage public strictement limité (~1 req/s
  sur Nominatim, pas de saisie assistée en rafale, sinon blocage 403/429) : auto-hébergement
  recommandé pour un usage réel, la démo publique suffit pour un usage ponctuel (admin, une seule
  fois, pendant l'assistant).
- **LocationIQ**/**OpenCage** : alternatives avec clé API, quota gratuit quotidien plus confortable,
  posture RGPD plus explicite (OpenCage).
- **Point RGPD réel traité** : chaque frappe envoie un bout d'adresse à un service tiers, opt-in
  réel (rien n'est envoyé tant que la case n'est pas cochée dans le navigateur de l'admin), seuil
  minimum de 3 caractères + debounce 400 ms, endpoint auto-hébergeable pour les organisations
  sensibles.

Construit comme prévu : Nominatim public par défaut avec `User-Agent` correct et debounce, endpoint
admin-configurable (`ajax/geocode.php`, proxy serveur, SSRF fermé, l'endpoint est toujours lu
depuis la config stockée, jamais depuis la requête client). Deux bugs réels trouvés et corrigés
avant mise en ligne (détail dans `CHANGELOG.md` `[0.33.0]`) : la persistance en base bloquait le
tout premier essai de l'assistant, et une recherche par code postal seul était ambiguë à l'échelle
mondiale sans un pays associé (« 69001 » = Lyon *ou* un quartier de Zaporijjia, Ukraine). Vérifié en
réel contre le vrai service Nominatim (pas de mock) via Playwright : suggestions de rue réelles,
ville correctement résolue depuis le code postal, données persistées sur le bon `Location`.

**Suite (v0.35.0, 2026-08-13)** : deux vrais problèmes remontés par l'utilisateur en testant la
fonctionnalité (capture d'écran à l'appui) : la recherche de rue en texte libre n'était pas non
plus restreinte à la ville/pays déjà saisis (même défaut que le code postal seul, pas encore
corrigé ici, même correctif étendu, recherche structurée Nominatim `street`+`city`+`country`), et
la liste de suggestions se superposait visuellement aux champs en dessous (`list-group` sans fond
opaque propre → `dropdown-menu` Bootstrap/Tabler). Un second bug latent trouvé pendant cette
correction, avant mise en ligne : `dropdown-menu` est masqué par sa propre règle CSS, jamais levée
sans le composant JS Bootstrap natif : la liste restait invisible même remplie de vrais résultats
tant que l'affichage forcé restait une chaîne vide au lieu de `'block'`. Coordonnées GPS
(`glpi_locations.latitude`/`longitude`) ajoutées au passage : déjà fournies par Nominatim, jamais
exploitées jusqu'ici. Détail complet dans `CHANGELOG.md` `[0.35.0]`.

**Refonte (v0.36.0, 2026-08-13)**, retour utilisateur direct après usage réel de l'assistant :
« tu met les entité dans les lieu, mais s'en ai pas, par contre faire en sorte que l'on puisse
saisir ou non une adresse pour chaque entité et sous entité, se serais bien ». Confirmait un vrai
défaut de conception : `LocationBuilder` mirrorait TOUTE l'arborescence d'entités en Lieux sans
condition (un département interne sans adresse propre devenait quand même un Lieu), avec adresse
saisissable seulement sur les nœuds racine. Reconstruit pour qu'un `Location` ne soit créé QUE là
où l'admin saisit effectivement quelque chose, à n'importe quel niveau de l'arbre : l'étape 15
affiche désormais l'arborescence complète avec un bouton « + Ajouter une adresse » repliable par
nœud. Deux demandes supplémentaires dans la foulée, toutes deux traitées dans la même version :
alias (`glpi_locations.alias`, jamais utilisé) et l'ensemble des champs natifs de `glpi_locations`
(code, commentaire, état/région, bâtiment, pièce, altitude, pas seulement adresse/code postal/
ville/pays). Un bug trouvé pendant cette refonte, avant mise en ligne : le validateur de
coordonnées rejetait toute altitude à 4 chiffres (limite à 3 chiffres avant la virgule, correcte
pour latitude/longitude mais pas pour une altitude de montagne). Vérifié en réel : une entité sans
donnée ne produit aucun Lieu, une sous-entité avec adresse+alias+tous les champs produit un
`Location` correctement scopé et rattaché à la racine (son parent n'ayant pas de Lieu). Détail
complet dans `CHANGELOG.md` `[0.36.0]`.

**Sources des demandes (`RequestType`) : traduction, fait (v0.38.0, 2026-08-13).** Revient sur la
conclusion "déjà suffisant" d'un audit précédent, cette conclusion portait sur le *contenu* (6
valeurs natives couvrent les cas d'usage), pas sur la *traduction*, une question orthogonale jamais
vérifiée à l'époque. Confirmé un vrai manque en lisant `install/empty_data.php` de GLPI : ces 6
valeurs sont des chaînes anglaises codées en dur, sans ligne `DropdownTranslation` (absence
confirmée en base) : toute session non anglaise les voit telles quelles. `RequestTypeTranslationBuilder`
(nouveau) traduit les 6 valeurs existantes dans les 5 langues du plugin, sans jamais les créer.

---

## 📋 Chantiers identifiés en marge d'une revue d'écran par l'utilisateur (2026-08-13, pas encore
cadrés, capturés tels quels, à trancher un par un avant de construire quoi que ce soit, même
méthode que les audits précédents)

L'utilisateur a parcouru plusieurs écrans natifs de GLPI (Intitulés, Entités) en configurant une
instance réelle et a soulevé sept pistes d'un coup. Consignées ici sans construire, sur sa propre
demande explicite ("ajout tout ce que je viens de te dire dans la liste des chantier a faire").

1. ✅ **Lieux : lieux enfants arbitraires avec arborescence éditable, comme les entités, fait
   (v0.39.0, 2026-08-13).** `LocationBuilder` créait au plus un `Location` par nœud de
   l'arborescence d'entités, avec un rattachement 1:1 forcé. `glpi_locations` a pourtant son propre
   arbre indépendant (`locations_id` auto-référencé) : un même site peut avoir plusieurs lieux
   imbriqués (Bâtiment > Étage > Salle) sans rapport avec la structure d'entités. Chaque panneau
   "Ajouter une adresse" propose désormais un éditeur récursif identique à celui de l'arborescence
   d'entités (réutilise directement les classes CSS de `_entity_structure_fields.html.twig` : nœuds
   `{name, fields, children}` sérialisés en JSON par entité, boutons `+`/`x` par nœud). Contrairement
   aux entités de l'étape 2, ajouter un lieu enfant le crée toujours (le geste explicite de l'ajouter
   est déjà la justification, pas de règle "aucune donnée = pas de lieu" ici). Vérifié en réel :
   arbre à 2 niveaux (Bâtiment A > Étage 1) créé avec le bon rattachement `locations_id`, y compris
   quand l'entité parente elle-même n'a pas de lieu propre (rattachement direct à la racine, même
   règle de compression que pour l'arbre d'entités).
2. ✅ **Catégories d'utilisateur (`UserCategory`) : fait (v0.41.0, 2026-08-13).** Vide nativement,
   l'utilisateur a demandé à quoi ça sert. Vérifié dans le code de GLPI : champ réel sur `User`
   (`usercategories_id`), importable depuis un attribut LDAP (`AuthLDAP::category_field`), utilisé
   comme critère de ciblage de notification (`NotificationTargetCommonITILObject`) et comme axe de
   statistiques (`Stat.php`), indépendant des profils/droits GLPI. `UserCategoryBuilder` (nouveau) :
   6 catégories génériques (Employé, Prestataire externe, Stagiaire, Alternant, Intérimaire,
   Consultant). Vérifié en réel : les 6 lignes créées dans `glpi_usercategories`.
3. ✅ **Opérateurs téléphoniques (`LineOperator`) : fait (v0.49.0, 2026-08-13), défaut France
   posé comme pour les jours fériés.** `LineOperatorBuilder` (nouveau) : 4 grands opérateurs mobiles
   français (Orange, SFR, Bouygues Telecom, Free), avec MCC/MNC réels recoupés sur 3 sources
   indépendantes pour éviter d'inventer un numéro. **Bug réel trouvé pendant la vérification** :
   `glpi_lineoperators` a un index `UNIQUE(mcc, mnc)`, et GLPI met `0` par défaut (pas `NULL`) sur
   ces champs entiers non fournis, sans MCC/MNC explicites et distincts, seul le premier opérateur
   se créait, les 3 suivants étaient silencieusement rejetés par la contrainte d'unicité, sans
   aucune erreur visible ni dans le message de succès du wizard ni dans les logs GLPI. Repéré
   uniquement en comptant les lignes en base après soumission, pas en faisant confiance au message
   de succès. `NetworkPortFiberchannelType` (malgré son nom FR "Types de fibre") concerne en réalité
   le protocole de stockage SAN Fibre Channel (débits 1/2/4/8/16/32 Gb, FCoE...), rien à voir avec
   la fibre internet résidentielle/entreprise, écarté du périmètre "opérateurs télécom" de ce point.
4. **Grande liste de dropdowns "Types" natifs vides : trois tranches faites (v0.50.0, v0.51.0,
   v0.61.0), quasiment clos.** Requête `information_schema` réexécutée : 33 tables natives
   `glpi_*types` à 0 ligne (plus large que l'estimation initiale de ~25).
   ✅ **Traité, 26 catégories, 130 types au total** : Ordinateurs/Écrans/Matériel réseau/
   Périphériques/Téléphones/Imprimantes (v0.50.0) puis Racks/PDU/Certificats/Disques durs/
   Batteries/Boîtiers/Câbles/Cartouches d'impression (v0.51.0) puis Appliances/Budgets/Cluster/
   Consommables/Contacts/Contrats/Domaines/Lignes/Équipements passifs datacenter/Fournisseurs/
   Machines virtuelles/Capteurs (v0.61.0, 2026-08-14, poursuite autonome sur "continu"),
   `AssetTypeBuilder`, types standards du secteur, icônes optionnelles. Racks/PDU/Certificats/
   Appliances/Domaines/Cluster scopés par entité (confirmé via `DESCRIBE`). Contenu de
   `ConsumableItemType` délibérément distinct de `CartridgeItemType` (déjà traité) : objets GLPI
   différents. `VirtualMachineType` sans rapport avec le champ texte libre "hyperviseur" de
   `ServerAssetBuilder`.
   ❌ **Explicitement écartés** : `AgentType` (auto-créé par `Agent::handleAgent()` à la première
   connexion d'un agent d'inventaire réel, le seeder serait redondant), `Assets_AssetType`
   (dépend d'une définition d'actif personnalisée à créer d'abord, pas un dropdown global autonome),
   `Enclosure`/Châssis (confirmé sans table `Type` du tout dans GLPI :
   `glpi_enclosuretypes` n'existe pas), `DeviceGeneric` (trop générique pour un contenu
   significatif), `NetworkPortFiberchannelType` (protocole SAN générique et stable, déjà écarté du
   périmètre "opérateurs télécom" ailleurs dans ce document).
   ✅ **`SoftwareLicenseType` : fait (v0.64.0, 2026-08-17), sur demande explicite.** Seule table
   restée différée (c'est un `CommonTreeDropdown`, pas un dropdown plat comme les 26 autres de
   `AssetTypeBuilder`, nécessitant le même soin que la construction de l'arbre de
   `CategoryBuilder`) : `SoftwareLicenseTypeBuilder` (nouveau), 10 catégories réelles de gestion de
   licences (OEM, Volume, Boîte, Abonnement SaaS, Open Source, Essai, Site, Concurrente, Nommée,
   Don/Occasion), en liste plate (une licence se catégorise sur un seul axe, pas une hiérarchie).
   ✅ **`DatabaseInstanceType` : fait (v0.65.1, 2026-08-20).** Documenté comme livré dès v0.64.0
   (raisonnement toujours valable : initialement écarté par crainte de dériver vers une liste de
   produits DBMS façon fabricants, résolu en catégorisant par *nature du moteur* (relationnelle,
   document, clé-valeur, colonne large, graphe, séries temporelles, recherche/index) plutôt que par
   produit, confirmé distinct de `manufacturers_id` et de `databaseinstancecategories_id` déjà
   natifs sur `DatabaseInstance`) mais jamais réellement exécuté : la classe était importée et le
   docblock d'`AssetTypeBuilder` décrivait ce contenu, mais aucune entrée `DatabaseInstanceType::class`
   n'existait dans la constante `TYPES` réellement parcourue par `build()` ; régression de
   documentation confirmée en relisant le code, corrigée ici.
   ✅ **Types natifs des actifs personnalisés Véhicule/Serveur/Local : fait (v0.64.0, 2026-08-17),
   sur demande explicite.** Chaque définition d'actif personnalisé reçoit automatiquement de GLPI
   son propre dropdown "type" (`Glpi\CustomAsset\<X>AssetType`), jusqu'ici jamais peuplé :
   `VehicleAssetBuilder`/`ServerAssetBuilder`/`BuildingAssetBuilder` le peuplent désormais chacun
   avec des catégories réelles. Le libellé "Véhicule types" (au lieu de "Types de véhicule") généré
   par GLPI lui-même reste non traduit en `fr_FR`, confirmé être un manque de traduction du cœur
   GLPI 11 (`Glpi\Asset\AssetType::getTypeName()`), hors de portée d'un plugin.
5. ✅ **Jours fériés par pays, France incluse : fait (v0.52.0, étendu v0.64.0, 2026-08-17).**
   `CountryHolidayBuilder`, étape "Lieux" : crée les jours fériés natifs GLPI des pays saisis sur
   les adresses, source Nager.Date déjà vérifiée en direct. **Étendu (v0.64.0)** sur demande
   explicite ("gérer les fermetures de manière automatique selon le pays") : la France, jusqu'ici
   couverte séparément par une case à cocher indépendante du pays réellement détecté dans
   `CalendarBuilder` (8 jours codés en dur, attachés même à un site non-français si la case était
   cochée), passe par ce même mécanisme, chaque site/client sans pays saisi est par défaut la
   France. `CalendarBuilder::attachFrenchHolidays()`/`calendar_holidays_enabled` supprimés.
   Couverture pays aussi étendue à l'intégralité de l'Union européenne plus
   Royaume-Uni/Suisse/Norvège/Islande (10 pays ajoutés : Bulgarie, Croatie, Chypre, Estonie,
   Lettonie, Lituanie, Malte, Slovaquie, Slovénie, Islande), confirmé un par un contre les 204 pays
   réels de Nager.Date. Les trois questions de conception d'origine ont été tranchées : (1)
   table de correspondance nom→code ISO pour désormais l'intégralité de l'UE + voisins européens
   proches, plus quelques pays hors Europe courants (français/anglais),
   un pays non reconnu est simplement ignoré ; (2) limitation `is_perpetual` de GLPI résolue en
   déterminant empiriquement les jours fériés à date fixe (comparaison de deux années
   consécutives, même simplification que pour la France) plutôt que de gérer un rafraîchissement
   annuel, pas de jours mobiles créés ; (3) nouvelle dépendance externe acceptée (même
   raisonnement que Nominatim pour l'assistant d'adresse). **Bug de qualité réel trouvé et corrigé
   pendant la vérification, avant tout envoi** : Nager.Date retourne aussi des jours fériés
   *régionaux* mélangés aux jours fériés nationaux (champ `global` de l'API) : confirmé en direct
   pour l'Allemagne, où "Heilige Drei Könige"/"Mariä Himmelfahrt"/"Weltkindertag" ne sont fériés
   que dans certains Länder, pas dans tout le pays. Sans filtrer sur `global: true`, ces jours
   auraient été créés comme si toute l'Allemagne les observait, corrigé avant la première
   soumission testée. **Note obsolète retirée (v0.64.1)** : cette section disait initialement
   "volontairement pas rattaché automatiquement à un calendrier, à faire par l'admin", devenu
   faux depuis l'extension v0.64.1 ci-dessus, qui rattache désormais chaque pays à un calendrier
   dédié automatiquement.
6. ✅ **Unicité des champs (`FieldUnicity`) : fait (v0.43.0, 2026-08-13), scope réduit au cas
   universel.** Audité avant construction (`src/FieldUnicity.php` du cœur GLPI) : contrairement aux
   dropdowns de contenu, `FieldUnicity` définit bien des *règles* de contrainte, plus proche des
   règles métier volontairement laissées de côté ailleurs dans ce plugin (`RuleTicket`...),
   mais parmi les 20 itemtypes éligibles (`$CFG_GLPI['unicity_types']`), un seul cas est
   suffisamment universel pour un défaut sans jugement métier par organisation : le numéro de série
   ne doit pas être dupliqué sur les six types d'actifs matériel sérialisables (Ordinateurs, Écrans,
   Matériel réseau, Périphériques, Téléphones, Imprimantes) : recommandation ITAM standard,
   indépendante du secteur. `action_refuse` (bloque) plutôt que `action_notify` (nécessiterait un
   gabarit de notification lié, même écueil que les gabarits liés de `WaitReasonBuilder`). Vérifié
   dans `CommonDBTM::checkUnicity()` : un numéro de série vide n'est jamais traité comme un
   doublon, donc aucun risque de bloquer la création de plusieurs actifs sans série renseignée.
   Vérifié en réel : les 6 règles créées avec les bons champs, resoumission sans doublon.

   **Extension à 12 règles : fait (v0.47.0, 2026-08-13).** Sur question directe de l'utilisateur
   ("pouvons-nous faire un truc ?"), réaudité les 20 itemtypes éligibles avec le même filtre
   (colonne `serial` réellement présente, confirmé par `information_schema` sur une instance réelle,
   `Cluster` en est dépourvu malgré son éligibilité). Six candidats supplémentaires passaient le
   même test d'universalité que les six premiers : Racks/Châssis/PDU (infrastructure physique, même
   raisonnement), Licences logicielles (le `serial` y est la clé de licence, un doublon signifie
   presque toujours une double saisie), Certificats (numéro de série X.509), Cartes SIM (ICCID).
   `User` écarté explicitement : pas de colonne e-mail directe sur `glpi_users` (l'e-mail vit dans
   `glpi_useremails`, relation 1-N), donc pas exploitable par ce mécanisme sans le détourner.
7. ✅ **Adresse native de l'Entité (`Entity`), distincte du Lieu : fait (v0.40.0, 2026-08-13).**
   Repéré sur l'onglet "Adresse" natif d'une entité (`front/entity.form.php`) : `glpi_entities` a
   ses propres champs téléphone/fax/site web/e-mail/code postal/ville/état/pays/adresse/latitude/
   longitude/altitude, avec sa propre carte Leaflet : un mécanisme entièrement distinct de
   `glpi_locations`. Tranché en faveur d'une extension du même assistant plutôt qu'un canal
   indépendant : `EntityAddressBuilder` (nouveau) réutilise l'adresse déjà saisie dans le panneau
   "Lieux" de chaque entité (aucune retypée), et n'ajoute que téléphone/fax/site web/e-mail : les
   seuls champs sans équivalent sur `Location`. Toggle dédié, désactivé par défaut. Vérifié en réel :
   adresse + les 4 nouveaux champs correctement persistés sur la fiche entité.

**Réordonnancement des 18 étapes du wizard : fait (v0.42.0, 2026-08-13).** Retour utilisateur
direct après avoir suivi tout le parcours en conditions réelles : « on ne m'a pas demandé les
lieux » (l'étape Lieux était noyée dans un des huit interrupteurs de l'étape "Général & Outils",
en position 15 sur 17, après la personnalisation graphique, les gabarits de tickets, les droits
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

**Icônes cochées par défaut : fait (v0.44.0, 2026-08-13).** Retour utilisateur direct après capture
d'écran de l'étape 3 : les 17 cases "Ajouter des icônes" du wizard étaient décochées par défaut
(opt-in), obligeant à les activer une par une malgré un coût nul et un bénéfice systématique.
Basculées en cochées par défaut (opt-out) dans `Config::getDefaults()` : aucun préréglage de
`ConfigurationProfile::getSuggestedDefaults()` ne les force à `false`, donc le changement s'applique
à tous les profils sans exception. Vérifié en réel sur une instance neuve (config remise à zéro) :
les 17 cases confirmées pré-cochées au premier chargement du wizard.

**Assistant d'adresse activé par défaut : fait (v0.45.0, 2026-08-13).** Question initiale de
l'utilisateur sur une capture d'écran ("il est pas intelligent") clarifiée : la case "Assistant
d'adresse" était simplement décochée dans son test, pas un bug ; mais sur sa demande explicite
("tu fais le reste"), basculée en activée par défaut, même raisonnement que les icônes juste
au-dessus (réversible, bénéfice systématique). Seule nuance par rapport aux icônes : chaque frappe
part vers un service externe (OpenStreetMap Nominatim par défaut) : texte d'aide mis à jour pour
expliquer clairement pourquoi/comment la désactiver si besoin, pas juste "à activer
volontairement" comme avant.

**Presque tout activé par défaut : fait (v0.46.0, 2026-08-13).** Généralisation explicite de
l'utilisateur après les deux points ci-dessus ("il faudrait limite tout activé par défaut et
l'utilisateur choisis ce qu'il ne veut pas"). Étendu `Config::getDefaults()` (le point de départ
brut, utilisé par le profil "Personnalisé") pour qu'il corresponde à ce que
`ConfigurationProfile::getSuggestedDefaults()` considérait déjà comme "bonne pratique universelle"
pour les profils préréglés, pas une nouvelle liste inventée, juste le même socle déjà validé
appliqué aussi au point de départ brut. `FieldUnicityBuilder` (v0.43.0, pas encore dans ce socle)
inclus aussi. Seule exception délibérée conservée : la personnalisation graphique (couleur/logo/
palette/e-mails, étape 17/18), recolorer toute l'instance sans qu'un admin ait choisi une couleur
reste différent d'ajouter du contenu à des listes vides, et cette étape est déjà explicitement
dépriorisée depuis le réordonnancement (v0.42.0). Vérifié en réel : soumission complète sur le
profil "Personnalisé" (le cas le plus strict, sans préréglage de profil), tout le contenu attendu
correctement créé en base sans erreur.

**Dictionnaire de fabricants : deuxième passage, fait (v0.46.0, 2026-08-13).** Suite à la demande
utilisateur de vérifier les variantes manquantes, confirmées via un vrai export `Manufacturer` d'une
instance GLPI réelle peuplée par glpi-agent (pas une recherche générique) : 3 fabricants déjà
couverts avaient des variantes réelles manquantes (Acer, Cisco, Samsung), et 9 des 29 fabricants
canoniques n'avaient encore aucune règle du tout (Fortinet, Logitech, Oracle, Red Hat, HPE Aruba,
Ubiquiti, Netgear, Canon, Brother, QNAP, Jabra, Poly, APC, Eaton), les 29 ont maintenant une règle.
`createRule()` ajoute aussi désormais les critères manquants à une règle déjà existante au lieu de
l'ignorer entièrement, pour qu'un admin qui remet à jour le plugin en bénéficie aussi. Non traité,
noté pour un chantier séparé (changement de portée, pas juste une correction de variantes) : le
même export révèle plusieurs fabricants matériel réels et récurrents absents des 29 canoniques
(Intel, Kingston, Toshiba, Western Digital, Sony, Seagate, Micron, SanDisk, Realtek, TP-Link,
Transcend, NVIDIA, Broadcom, SK hynix) : décider lesquels ajouter à `ManufacturerBuilder` lui-même
(avec icône, catégorie) est une décision de contenu différente de la normalisation de doublons déjà
décidés.

**Flux RSS CERT-FR : fait (v0.47.0, 2026-08-13).** Demandé explicitement par l'utilisateur
("notamment le CERT-FR pour les français"). `RSSFeedBuilder` (nouveau) ajoute le flux natif GLPI
(Outils > Flux RSS, vide par défaut) des avis de sécurité CERT-FR/ANSSI. URL vérifiée en direct
(`https://www.cert.ssi.gouv.fr/feed/`, un vrai flux actif avec du contenu réel au moment du test)
plutôt que devinée. Visibilité instance-wide via `Entity_RSSFeed` (entité racine + récursif) :
sans ça, un flux `RSSFeed` n'est visible que par son créateur. Nom/description récupérés par GLPI
lui-même au moment de l'ajout (fetch live du flux par `RSSFeed::prepareInputForAdd()`), pas codés
en dur ici. Scope volontairement limité à ce seul flux (pas un mécanisme générique "ajouter
n'importe quel flux"), France-spécifique mais assumé, même logique que les jours fériés déjà
France-first ailleurs dans ce plugin.

**Étape "Marketplace & plugins recommandés" : fait (v0.48.0, 2026-08-13).** Demandée par
l'utilisateur, cadrée le même jour après déblocage réel du marketplace natif sur l'instance Docker
de test (l'utilisateur y a renseigné une vraie clé d'enregistrement GLPI Network) : les faits
ci-dessous vérifiés en conditions réelles (recherche live dans Configuration > Plugins > Marketplace
> Découvrir + inspection du DOM), pas juste via GitHub.
1. **Clé d'enregistrement GLPI Network.** Champ ajouté à l'étape "Réglages généraux GLPI", écrit
   directement dans la config native GLPI (`\Config::setConfigurationValues('core', [...])`,
   exactement le mécanisme de la page native "Enregistrement") : jamais dupliqué dans la table de ce
   plugin, qui n'a aucun chiffrement au niveau champ contrairement au stockage natif via `GLPIKey`.
   Pré-rempli à chaque chargement depuis `GLPINetwork::getRegistrationKey()` (même comportement que
   la page native). Vérifié : une resoumission avec le champ inchangé donne un chiffré différent en
   base (IV aléatoire à chaque chiffrement, normal) mais le marketplace continue de s'authentifier
   correctement ensuite, pas de corruption.
2. **Liste de plugins recommandés (informationnelle, pas d'installation automatique)** : 3 plugins
   vérifiés en direct sur le marketplace natif (clé, note, licence, auteur, bouton d'installation
   réel inspectés) :
   - **remise-glpi** (https://github.com/parime/remise-glpi, plugin de l'utilisateur, mise en avant
     explicite). Gestion de feuilles de prêt/retour/vente/don de matériel pour la traçabilité,
     centralisation des documents associés dans GLPI. **Absent du marketplace natif** (recherche
     "remise" → aucun résultat) : lien GitHub direct plutôt qu'un renvoi vers le marketplace.
   - **Escalade** : clé confirmée `escalade` (attribut `data-key` du marketplace lui-même, pas juste
     le nom du dépôt GitHub), licence GPL v2+, auteurs Alexandre Delaunay/TECLIB', v2.10.6, 3,5★,
     gratuit (aucun badge "GLPI Network"/offre payante). Description native : « simplifie l'escalade
     de ticket vers des groupes différents ».
   - **One-Time Secret** : clé confirmée `onetimesecret` (sans tiret, contrairement au nom du dépôt
     `ticgal/one-timesecret`, bien vérifié en direct plutôt que déduit), licence AGPL v3+, auteur
     TICgal, v3.0.0, 4,5★, gratuit. Description native : « Share your passwords securely on GLPI ».
   - Installation volontairement laissée à l'admin (bouton
     `<button data-action="download_plugin">` du marketplace natif, un clic, aucune redirection
     externe) plutôt qu'automatisée depuis ce wizard, télécharger/exécuter du code tiers est une
     catégorie de risque différente du reste de ce plugin (qui ne fait que créer du contenu dans les
     propres tables de GLPI).

**Intégration conditionnelle de plugins tiers au wizard : liste proposée par l'utilisateur
(2026-08-14), vérifiée en direct sur le marketplace natif désormais débloqué (mêmes
clés/descriptions/auteurs/licences confirmés qu'au point précédent, pas de supposition) :**
- **PDF** (`pdf`, Teclib/Remi Collet/Nelly Mahu-Lasson) : export PDF d'une fiche d'inventaire.
- **Used items export** (`useditemsexport`, TECLIB') : export PDF de la liste du matériel affecté
  à un utilisateur.
- ✅ **More satisfaction** (`satisfaction`, Infotel) : fait (v0.53.0, 2026-08-14), premier plugin
  tiers traité, sur choix explicite de l'utilisateur ("le mieux c'est satisfaction"). Plugin
  installé et activé en réel sur l'instance de test (marketplace débloqué), schéma de
  `glpi_plugin_satisfaction_surveys`/`surveyquestions` lu directement via `DESCRIBE`. 3 types de
  question confirmés exhaustifs (`note`/`yesno`/`textarea`, `SurveyQuestion::getQuestionTypeList()`)
  : `SatisfactionSurveyBuilder` crée une enquête toute faite (note sur 5, résolution oui/non,
  remarques libres) écrite directement dans les tables du plugin tiers via `$DB` (pas de dépendance
  dure vers ses classes PHP, hors du périmètre PHPStan de ce dépôt). Vérifié en réel : section du
  wizard confirmée absente quand le plugin est désactivé, présente et fonctionnelle une fois
  réactivé : exactement le comportement demandé.
- ✅ **VIP** (`vip`, Infotel/PROBESYS et al.) : fait (v0.54.0, 2026-08-14), sur choix explicite de
  l'utilisateur ("qr code, y a rien a faire pour moi, par contre tu peux faire vip"). Plugin installé
  et activé en réel sur l'instance de test, schéma de `glpi_plugin_vip_groups` lu directement via
  `DESCRIBE` avant d'écrire le builder : clé primaire `id` qui reflète directement `glpi_groups.id`
  sans auto-incrément (confirmé dans le code source du plugin tiers), une ligne singleton `id=0`
  pré-existante dès l'installation. `VipBuilder` crée un groupe natif "VIP", **pas** un groupe de
  techniciens, le plugin sert à signaler des personnes/groupes prioritaires côté demandeur (direction,
  actionnaires...), et le marque `isvip=1`, écrit directement dans les tables du plugin tiers via
  `$DB` (même raisonnement que `SatisfactionSurveyBuilder`, pas de dépendance dure). Son moteur de
  règles (`RuleVip`, affectation
  depuis des critères LDAP) volontairement pas automatisé, dépend de la structure AD/LDAP propre à
  chaque organisation, même raisonnement que l'exclusion des diagnostics LDAP. Vérifié en réel :
  section absente quand le plugin est désactivé, groupe natif + ligne `isvip=1` créés à la soumission,
  resoumission idempotente (pas de doublon).
- **Oauth IMAP** (`oauthimap`, TECLIB'), authentification OAuth pour les collecteurs de mail
  (Microsoft 365/Google imposent de plus en plus OAuth, l'authentification IMAP simple devient
  obsolète chez ces fournisseurs).
- **Data Injection** (`datainjection`), import CSV en masse, plugin de référence bien établi dans
  l'écosystème GLPI.
- **Carbon** (`carbon`, TECLIB'), évaluation d'impact environnemental du parc, cohérent avec l'axe
  ISO27001/bonnes pratiques déjà porté par ce plugin.
- ✅ **Tag** (`tag`, TECLIB') : fait (v0.55.0, 2026-08-14). **Correction sur "TAG" demandé par
  l'utilisateur** : le plugin natif "Tag" (`tag`, TECLIB') est un système de tags génériques sur
  n'importe quel objet GLPI (mots-clés de classification), **pas** de l'impression d'étiquettes
  physiques, confondu un temps avec ça, corrigé après que l'utilisateur a confirmé via une capture
  d'écran du marketplace qu'il s'agissait bien du plugin `pluginsGLPI/tag` (2.14.6, TECLIB'). Le vrai
  candidat pour l'impression d'étiquettes est **"QR Code Label"** (`qrcodelabel`, Etienne Gaillard) :
  explicitement écarté par l'utilisateur ("y a rien a faire pour moi"). **Vérifié dans remise-glpi
  (README/ARCHITECTURE via `gh api`, 2026-08-14) : aucune fonctionnalité de tag là-dedans**, pas de
  doublon avec le plugin sœur de l'utilisateur, contrairement à sa propre inquiétude initiale. Schéma
  de `glpi_plugin_tag_tags` (`PluginTagTag extends CommonDropdown`) lu en direct : table vide sur
  install fraîche, `type_menu` (JSON d'itemtypes) vide/NULL = tag utilisable sur tout objet
  (`PluginTagTag::canItemtype()`). `TagBuilder` crée 6 tags génériques (Prioritaire, Urgent, À
  vérifier, Obsolète, Garantie active, Confidentiel), chacun avec une couleur distincte, écrits
  directement via `$DB`. Volontairement pas d'automatisation sur l'affectation réelle des tags
  (`glpi_plugin_tag_tagitems`) : dépend de chaque organisation. Vérifié en réel : section absente
  si le plugin est désactivé, 6 tags créés à la soumission, resoumission idempotente.
- **Mécanisme technique confirmé faisable et déjà utilisé** : `Plugin::isPluginActive('clé')`
  (natif GLPI) permet de détecter si un plugin tiers est installé et actif, et donc de n'afficher
  une section du wizard que dans ce cas (sinon rien, on passe à la suite, exactement la demande de
  l'utilisateur, confirmé en réel sur More Satisfaction, VIP et Tag ci-dessus). QR Code Label
  explicitement écarté par l'utilisateur ("y a rien a faire pour moi") : les trois plugins tiers
  demandés (More Satisfaction, VIP, Tag) sont maintenant traités ; PDF/Used items export/Oauth
  IMAP/Data Injection/Carbon restent uniquement documentés ci-dessus, aucune demande de construction
  reçue.

**Maintenance du dépôt : nettoyage fait (2026-08-14).**
- ✅ **Audit fiable des 45 classes `src/*.php`** : `git grep` par nom de classe sur l'ensemble du
  dépôt (`*.php`/`*.twig`), en excluant le fichier de définition lui-même, puis vérification manuelle
  de chaque résultat à faible occurrence (pas juste un comptage brut, la première tentative avait
  échoué exactement sur ce point, en confondant "peu référencé" et "non référencé"). **Aucune classe
  morte** : les 45 sont bien instanciées/appelées depuis `front/wizard.php` ou incluses depuis
  `templates/wizard.html.twig`.
- ✅ **Audit des méthodes privées** (mêmes 45 fichiers + `Installer.php`) : recherche de chaque
  `private function` référencée une seule fois dans son propre fichier (sa déclaration) : **aucune
  méthode privée morte**.
- ✅ **Audit des méthodes statiques publiques** : plusieurs faux positifs confirmés (`getTable()`,
  `getTypeName()`, `getIcon()`..., dispatchées par le cœur GLPI via `CommonDBTM`/`CommonGLPI`,
  jamais appelées par un nom littéral dans ce dépôt, donc invisibles à un grep), même piège que la
  première tentative, cette fois identifié et écarté à la main plutôt que rapporté comme un vrai
  résultat. **Un vrai cas trouvé** : `ManufacturerDictionaryBuilder::getPreview()`, écrite mais
  jamais câblée dans le wizard (contrairement à ses équivalents `getOperatorsPreview()`/
  `getTiersPreview()`/`getPreview()` sur d'autres builders, tous bien utilisés), supprimée.
- ✅ **Fichiers non suivis par aucune référence** :
  - `ROADMAP_original.md` (racine) : doublon figé du 2026-08-07 (527 lignes), jamais retouché
    depuis, totalement supplanté par `ROADMAP.md` (1254 lignes, activement maintenu), supprimé.
  - `.tx/config` : configuration Transifex, vestige de la même infrastructure jamais fonctionnelle
    que `.github/workflows/locales-sync.yml` (supprimé en v0.30.0) : référence le même `hook.php`
    inexistant, aucun `TRANSIFEX_TOKEN`, zéro mention dans `CONTRIBUTING.md`/`README.md`/CI : la
    traduction réelle de ce dépôt reste le pipeline manuel `.po`/`.mo` documenté ailleurs. Supprimé
    (le dossier `.tx/` disparaît de lui-même, vide).
  - `logo.png` (racine) et `misc/logos/logo.png` : **faux positif vérifié, pas supprimés** : fichiers
    identiques (même MD5) mais chacun sert un usage distinct et réel (badge liste de plugins via
    `Glpi\Marketplace\View::getPluginIcon()`, qui exige un `logo.png` littéral à la racine du
    plugin ; URL `<logo>` du manifeste pour la page marketplace publique) : déjà documenté dans
    CHANGELOG au moment où le premier avait été retiré par erreur (2026-08-11) puis restauré.
  - `tools/HEADER` : **faux positif vérifié, pas supprimé** : zéro référence directe dans ce dépôt,
    mais chargé par convention (chemin `tools/HEADER` en dur) par la commande
    `licence-headers-check` du paquet dev `glpi-project/tools`, confirmé en lisant son code source
    dans `vendor/`.
- ✅ **Captures d'écran** (`docs/screenshots/*.png`) : les 18 sont référencées dans
  `docs/TUTORIAL.md`, aucune orpheline.
- **Revue des pull requests en cours**, faite le jour même : 3 PR Dependabot ouvertes (mises à jour
  de SHA d'actions GitHub épinglées, aucun changement fonctionnel vérifié dans chaque diff). PR #39
  (codecov-action) approuvée et mergée. PR #40 (shivammathur/setup-php) et #41
  (aquasecurity/trivy-action) approuvées mais **pas mergées** : le jeton `gh` utilisé dans cette
  session n'a pas le scope OAuth `workflow` requis par GitHub pour merger une PR qui modifie
  `.github/workflows/*.yml`, à merger manuellement par l'utilisateur, ou réautoriser `gh auth
  login` avec ce scope.

**Plan retenu avec l'utilisateur pour la suite immédiate (par ordre de priorité)** :
1. **Fait.** Tests réels des gabarits (suivi/tâche/solution) appliqués sur un vrai ticket via l'UI.
2. **Fait (v0.29.0, 2026-08-12).** Documentation GitHub avec captures d'écran de chaque étape,
   `docs/TUTORIAL.md`.
3. **Fait (v0.28.0, 2026-08-12).** Étoffement du catalogue de services : 23 → 50 services, les 4
   branches sans aucun service comblées (Administratif, Communication, Qualité, Maintenance), les
   branches trop réduites étoffées. Vérifié en base après resoumission du wizard.

**Chantiers additionnels ouverts pendant cet audit** :
- Variables Twig dans les gabarits : **fait (v0.27.0)**.
- Prérequis de publication marketplace pour les deux dépôts sœurs, **fait** : `remise-glpi`
  corrigé (PR #66 : manifeste déplacé à la racine, langue `<fr_FR>`→`<fr>`, version obsolète mise à
  jour) ; `glpi-vulnerability-manager` audité mais volontairement pas touché, le plugin est encore
  en 0.8.0, le fichier `plugin.json` documente lui-même que la publication est prévue à la v1.0,
  pas avant.
- Traductions `.po`/`.mo` de l'interface du wizard en en_GB/de_DE/it_IT/es_ES : **fait (v0.30.0,
  2026-08-12)**. 318 chaînes traduites, vérifié en réel dans les 4 langues (aucune régression,
  aucun problème d'encodage).
- `<state>` du manifeste (`dev` → `stable`), **fait (2026-08-13)**, décision utilisateur : 36
  versions livrées, suite qualité verte à chaque livraison, vérifié en réel sur GLPI 11.0.8 à
  chaque fonctionnalité. Dernier prérequis marketplace pour ce dépôt : tous cochés.

---

## 📮 Propositions issues du quatrième audit : à trancher avec l'utilisateur

Aucune de ces pistes n'est implémentée, même méthode que les audits précédents, pas de décision de
priorité unilatérale.

1. ✅ **Séparation stricte des droits par défaut (axe sécurité/ISO 27001) : fait (v0.22.0,
   2026-08-12).** Diffé `glpi_profilerights` Admin vs Super-Admin sur un GLPI 11.0.8 réel plutôt que
   deviner : Admin n'a déjà ni le droit `profile` en écriture (ne peut pas éditer les profils, donc
   pas s'en attribuer plus), ni `rule_ldap`/`rule_import` (ne peut pas réécrire les règles de
   synchronisation), ni `config` : exactement les vecteurs d'auto-élévation identifiés en recherche,
   sans inventer un nouveau jeu de droits sur mesure. `ldap_rights_profile` (réglage "Profil
   attribué" de `RuleRightBuilder`/étape 12) passe de `Technician` à `Admin` par défaut, avec
   l'explication affichée dans le wizard, reste un simple menu déroulant, n'importe quel profil
   natif reste sélectionnable.
2. ❌ **Diagnostic LDAP pas-à-pas : écarté (décision utilisateur, 2026-08-12).** Le point de
   friction le plus fréquent trouvé en recherche, mais chaque annuaire d'entreprise a ses propres
   filtres, bind DN, schéma de groupes : un assistant de diagnostic générique se heurte à du
   cas-par-cas qui ne rentre pas dans la philosophie du plugin (des builders universels,
   applicables à n'importe quelle organisation, pas des outils ad hoc par annuaire). `RuleRightBuilder`
   continue de couvrir la partie universelle (affectation post-synchronisation) ; fiabiliser la
   connexion LDAP elle-même reste hors périmètre.
3. ✅ **Notifications : tâches automatiques d'alerte manquantes, fait (v0.20.3, 2026-08-12).** Voir
   la section "Cinquième passage" ci-dessus.
4. ✅ **Mode "express" du wizard : fait (v0.24.0, 2026-08-12), inspiré de Freshservice.** Découvert
   en concevant la fonctionnalité que la mécanique nécessaire existait déjà : choisir un profil à
   l'étape 1 déclenche déjà `applyProfileDefaults()`, qui préremplit *tous* les champs des 17 étapes
   (calendrier, SLA, catégories, réglages généraux...) via `ConfigurationProfile::
   getSuggestedDefaults()`. Le seul vrai manque était la navigation : rien ne permettait de terminer
   sans cliquer "Suivant" 16 fois pour relire chaque écran déjà rempli. Ajouté un second bouton
   "Terminer avec les réglages recommandés" directement sous les choix de profil (étape 1), qui
   soumet le même formulaire unique avec `name="finish"`, aucune nouvelle logique serveur, aucun
   nouveau champ de config, juste un raccourci de navigation. Le plus pertinent en mode mono-entité
   (rien d'autre à décider) ; en mode multi-site/MSP, l'arborescence réelle (étape 2) reste à
   construire séparément ensuite, sans quoi tout s'applique à l'entité racine seule, précisé dans
   le texte du bouton et la confirmation.
5. ✅ **Bibliothèque de "profils métier" prêts à l'emploi, fait (v0.22.0, 2026-08-12), portée
   réduite par rapport à la piste initiale.** Contrairement au SLA IT (`Config::DEFAULT_SLA_TIERS`,
   sourcé sur une vraie pratique ITIL), il n'existe aucune pratique RH/Facilities équivalente à citer
  , inventer des gabarits/contenus complets par verticale aurait été le même risque que les règles
   métier GLPI volontairement laissées de côté ("inventer serait pire que rien", cf. section
   "Règles" plus haut). Implémenté à la place comme un préréglage 1-clic purement client (JS), sans
   nouveau champ serveur : 4 boutons (IT pur / RH & Support interne / Bâtiment & Moyens généraux /
   Multi-services) qui précochent les branches de catégories déjà conçues (étape 5) et remplissent
   le tableau SLA (étape 4) avec le rythme IT existant ×2 (Bâtiment, intervention physique) ou ×4
   (RH, rarement classe "panne"), plutôt que des valeurs indépendamment inventées, cohérent avec le
   fait que ces multiplicateurs sont assumés comme un point de départ, pas une norme.
6. ✅ **`BrandingBuilder`, couvrir les 6 variables de logo et la vraie palette de couleurs Tabler ,
   fait (v0.21.0, 2026-08-12).** Voir CHANGELOG.md.
   ✅ **Palette `.scss` custom, fait (v0.23.0, 2026-08-12), nouveau `PaletteBuilder`.** Mécanisme
   distinct et complémentaire de `BrandingBuilder` (confirmé dans `Glpi\UI\ThemeManager` : un fichier
   dans `files/_themes/` devient une palette sélectionnable par tout utilisateur dans ses propres
   préférences, `\Config::setConfigurationValues('core', ['palette' => ...])` en fait le choix par
   défaut, pas un forçage par entité comme `custom_css_code`). **Piège GLPI core trouvé en testant** :
   `Theme::getPath()` suppose toujours l'extension `.scss`, même pour un fichier `.css` pourtant
   accepté par la détection, un fichier `.css` fait planter *tout* le site (500 partout, y compris
   la page de login) car `ThemeManager::getCustomThemesPaths()` tourne sur chaque requête. Réutilise
   la même couleur que la case au-dessus, pas un second sélecteur.
   ✅ **Palettes natives GLPI sélectionnables dans le wizard, fait (v0.25.0, 2026-08-12).** Menu
   déroulant listant les 18 palettes natives (`Glpi\UI\ThemeManager::getCoreThemes()`, noms/état
   sombre lus dynamiquement depuis GLPI plutôt que dupliqués en dur dans le wizard), alternative
   mutuellement exclusive à la palette personnalisée dans l'UI, `PaletteBuilder::apply()` pointe
   simplement `core.palette` sur la clé native choisie, aucun fichier généré.
   ✅ **Retour à la palette native impossible après coche "palette personnalisée"/choix natif : bug
   confirmé et corrigé (v0.65.1, 2026-08-20).** Signalé par l'utilisateur ("quand j'ai coché le menu
   en bleu, je ne peux plus revenir en arrière"). Cause réelle : `PaletteBuilder::apply()` ne faisait
   que `return false` sans rien écrire dès que ni `custom_palette_enabled` ni `native_palette`
   n'étaient actifs ; décocher/choisir "Aucune" ne faisait donc que *ne pas réappliquer*, sans jamais
   annuler ce qu'une exécution précédente avait écrit dans `core.palette`, confirmé en reproduisant en
   direct (coche : `core.palette = 'cga_custom'`, décoche : toujours `'cga_custom'`). Corrigé en
   réécrivant activement `core.palette` sur `''` (vraie valeur native GLPI, "pas de surcharge") dans
   ce cas, et en supprimant le `.scss` orphelin de `GLPI_THEMES_DIR` laissé par une exécution
   précédente.
7. ✅ **Contrôle de prérequis serveur en tout début de wizard, fait (v0.23.0, 2026-08-12), portée
   réduite à ce qui est réellement pertinent.** GLPI lui-même a déjà validé PHP/MySQL au moment de
   sa propre installation, revalider ces prérequis aurait été redondant. Recentré sur ce que ce
   plugin a spécifiquement besoin (droits d'écriture sur `files/_themes` pour la palette custom,
   sur `GLPI_CACHE_DIR` pour GLPI en général, un vrai souci de permissions rencontré cette session
   après une manipulation hors wizard, confirmant la pertinence du contrôle) : bandeau
   informatif au-dessus de l'étape 1, jamais bloquant, visible seulement s'il y a un point
   d'attention réel.

---

## 💬 Pistes remontées par le forum GLPI officiel (2026-08-18)

Même méthode que les audits précédents : consignées ici après vérification technique, aucune
décision de priorité unilatérale, sujets soumis par l'utilisateur, à trancher avant de construire
quoi que ce soit. Les deux items ci-dessous ont depuis été tranchés et livrés en v0.65.0
(2026-08-19).

1. ✅ **ITSM → ESM : actifs personnalisés réglementés, fait partiellement (v0.65.0, 2026-08-19).**
   Topic https://forum.glpi-project.org/viewtopic.php?id=293900. Un utilisateur (Perreip) demande si
   GLPI peut être détourné d'un outil ITSM (parc informatique) vers un usage ESM (Enterprise Service
   Management) : gérer aussi des actifs non-IT réglementés, ascenseurs, extincteurs, véhicules de
   service, locaux, avec export filtré (exemple cité : « extincteurs avec dates de validité »).
   Réponse du modérateur cconard96, vérifiée cohérente avec le code déjà audité dans ce projet
   (section « actifs personnalisés » plus haut, v0.58.0-v0.60.0) : GLPI 11 permet nativement ce
   genre de type d'actif via `Glpi\Asset\AssetDefinition` + capacités modulaires (remplaçant
   l'ancien plugin externe « Generic Object »), et le moteur de recherche natif couvre déjà le
   filtrage/export (CSV/PDF/ODS/XLSX, recherches sauvegardées, alertes), rien à construire côté
   export. Suivi : issue GitHub #132.

   **`FireSafetyAssetBuilder`** (nouveau), « Sécurité incendie & premiers secours » : extincteurs,
   RIA, désenfumage, détecteur de fumée/alarme incendie, éclairage de sécurité/issue de secours, et
   défibrillateur automatisé externe (DAE), ajouté en cours de sprint sur constat que le DAE
   partage exactement le même besoin (champ « date de vérification périodique ») que les autres,
   donc regroupé dans le même type d'actif plutôt qu'un second builder dédié. Déclenché par un
   nouveau réglage dédié (`fire_safety_assets_enabled`), affiché dans le panneau de la branche
   « Bâtiment & Moyens Généraux » à l'étape Catégories, aux côtés de la case de branche elle-même
   (comme envisagé ci-dessus), mais en case à cocher **séparée**, pas automatique : toute
   organisation qui coche « Bâtiment » ne souhaite pas forcément suivre ses extincteurs comme des
   actifs GLPI, contrairement à `BuildingAssetBuilder`/« Local » qui reste, lui, purement
   automatique.

   **`PhysicalSecurityAssetBuilder`** (nouveau), « Sécurité physique » : caméra de
   vidéosurveillance, centrale d'alarme intrusion, détecteur de mouvement, contrôle d'accès/lecteur
   de badge, serrure électronique, interphone/vidéophone. Ajouté en cours de sprint sur demande
   explicite de l'utilisateur (« tout ce qui touche la sécurité physique du bâtiment, [...] par
   exemple les caméras »), au-delà des deux candidats initiaux ci-dessus, explicitement grounded
   dans ISO/IEC 27001:2022 Annexe A.7 « Mesures physiques » (chaque sous-type cite sa clause A.7.x
   dans le code), cohérent avec le positionnement ITIL4/ISO27001 déjà affiché par ce plugin.
   Déclenchement revu par rapport à l'idée initiale : pas la branche « Bâtiment », mais « Sécurité &
   Protection des Personnes » (`securite`), confirmé en relisant `CategoryBuilder::CATEGORIES` que
   cette branche a déjà pour enfants « Contrôle d'Accès & Badges » et « Vidéosurveillance & Alarmes »,
   un rattachement thématique direct plutôt que le choix arbitraire envisagé au départ. Même schéma
   de case à cocher dédiée (`physical_security_assets_enabled`).

   **Équipement de levage (ascenseurs/monte-charges), non retenu.** Deuxième candidat évalué
   ci-dessus, écarté en cours de sprint : ne suit pas le forum ni l'issue #132 (ni l'un ni l'autre ne
   le mentionnent explicitement, contrairement à la sécurité incendie et aux véhicules/serveurs/
   locaux déjà couverts), resterait à construire sur demande explicite si le besoin se confirme,
   même patron `AssetDefinition` déjà en place pour les deux builders ci-dessus.

   **Mobilier, évalué, non retenu.** Piste complémentaire envisagée en cours de sprint (la branche
   « Bâtiment » a déjà une catégorie de service « Mobilier & Aménagement »/`ServiceCatalogBuilder`),
   mais ne passe pas le même filtre « generalist scope » que les deux builders ci-dessus : pas de
   champ de conformité réglementaire universel comparable à la vérification incendie ou au contrôle
   d'accès, la valeur serait essentiellement de l'inventaire générique, déjà raisonnablement couvert
   par les mécanismes natifs GLPI, sans bonne pratique universelle identifiée à préremplir (même
   conclusion « rien à construire » que `RequestType`/`ProjectState`/`DocumentType` ailleurs dans ce
   document). Laissé de côté sauf demande explicite de le construire quand même.

   Architecture : builders dédiés par verticale (patron déjà utilisé par
   `VehicleAssetBuilder`/`ServerAssetBuilder`/`BuildingAssetBuilder`), pas un assistant générique
   « créer votre propre type d'actif », confirmé rester le bon niveau de granularité, le besoin n'a
   pas dépassé ce que quatre builders dédiés couvrent proprement. Chaque sous-type des deux nouveaux
   builders traduit dans les 5 langues du plugin via `Translations::applyIcon()` (mécanisme
   `DropdownTranslation` déjà prouvé sur ~20 autres builders), à la différence des trois builders
   d'actifs personnalisés précédents (`VehicleAssetBuilder`/`ServerAssetBuilder`/
   `BuildingAssetBuilder`), qui ne traduisent pas leurs types seedés (lacune préexistante, non
   corrigée ici, hors du périmètre de ce sprint). Détail complet de la vérification (suite
   d'intégration PHPUnit dédiée + soumission réelle du wizard par HTTP) dans `CHANGELOG.md` `[0.65.0]`.

2. ✅ **Gabarit de solution « Demande incomplète », fait (v0.65.0, 2026-08-19).** Topic
   https://forum.glpi-project.org/viewtopic.php?id=294630. Un utilisateur (alecomte) demande un
   statut/bouton dédié pour rejeter un ticket mal formulé ou incomplet. Réponse du contributeur
   LaDenrée : pas besoin de toucher au workflow natif, un gabarit de solution avec un texte type
   « votre demande est incomplète » suffit, mécanisme déjà utilisé par ce plugin
   (`SolutionLibraryBuilder`, 5 catégories/10 gabarits existants, voir plus haut). Aucun gabarit
   équivalent n'existait dans la catégorie « Informationnel » (qui couvrait « Fonctionnement normal
   constaté » et « Ticket doublon », pas « informations manquantes »). 11e gabarit ajouté dans cette
   même catégorie, aucun changement de mécanisme (même structure Twig itemtype-aware, mêmes 5
   traductions, même icône). Vérifié en réel : gabarit créé sous le bon type via une vraie
   soumission du wizard, appliqué à un vrai ticket créé pour l'occasion, rendu Twig correct.

3. ✅ **Formulaires de catalogue de services « intelligents », pilote (issues #207/#208).** #207
   demande deux capacités natives à GLPI 11 (questions conditionnelles + titre de ticket calculé)
   pour rendre les ~50 services de `ServiceCatalogBuilder` plus riches qu'un simple couple
   nom/catégorie ; #208 en propose un premier exemple concret pilote à valider avant généralisation.
   Pilote livré : nouveau constructeur `AbroadMissionFormBuilder`, service « Demande de droit
   d'accès / mission à l'étranger » (branche Ressources Humaines) avec de vraies questions typées
   (pays, date de début, date de fin, motif) et un titre de ticket calculé à la soumission via le
   système natif de balises de formulaire (`Glpi\Form\Destination\CommonITILField\TitleField` +
   `Glpi\Form\Tag\FormTagsManager`), confirmé en lisant directement le cœur GLPI 11 (pas supposé) et
   vérifié en réel par une vraie soumission de formulaire donnant un vrai ticket au titre calculé
   correct. Pas de question conditionnelle dans ce pilote (les 3 champs sont toujours pertinents
   ensemble, comme dans l'exemple réel du porteur du plugin) : `HelpdeskFormBuilder` reste la seule
   utilisation actuelle de `Question::visibility_strategy`/`conditions` dans ce dépôt.
   **Reste pour la généralisation complète de #207** (issue laissée ouverte) : identifier, parmi les
   ~50 autres services existants, lesquels bénéficieraient réellement d'un titre calculé et/ou de
   questions conditionnelles (VISIBLE_IF), et étendre au cas par cas plutôt que d'appliquer ça
   partout par principe.

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
