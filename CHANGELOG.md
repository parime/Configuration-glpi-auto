# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

## [0.39.0] - 2026-08-13

### Added
- Lieux enfants arbitraires : `glpi_locations` a son propre arbre indépendant de l'arborescence
  d'entités (`locations_id` auto-référencé) — un même site peut avoir plusieurs niveaux imbriqués
  (Bâtiment > Étage > Salle) sans rapport avec la structure d'entités. Chaque panneau "Ajouter une
  adresse" (étape 15) propose désormais un éditeur récursif identique à celui de l'arborescence
  d'entités (étape 2) : ajout/retrait de lieux enfants à profondeur arbitraire, chacun avec son
  propre nom + adresse/code postal/ville/région/pays. `LocationBuilder` les crée toujours (contra-
  irement aux entités : ajouter un lieu enfant est déjà le geste explicite qui justifie sa création),
  rattachés à l'entité du panneau parent, imbriqués sous le lieu de cette entité si elle en a un, ou
  directement sous la racine sinon — même règle de compression déjà en place pour l'arbre d'entités.

## [0.38.0] - 2026-08-13

### Added
- `RequestTypeTranslationBuilder` (nouveau) : traduit les 6 « Sources des demandes » natives de
  GLPI (`RequestType`) dans les 5 langues du plugin. Confirmé un vrai manque en lisant l'installeur
  de GLPI lui-même (`install/empty_data.php`) : ces 6 valeurs (`Helpdesk`/`E-Mail`/`Phone`/`Direct`/
  `Written`/`Other`) sont des chaînes anglaises codées en dur, jamais passées par `__()`, identiques
  quelle que soit la langue choisie à l'installation — et sans ligne `DropdownTranslation`
  (confirmée absente), toute session non anglaise les voit telles quelles. Ne crée jamais ces 6
  lignes (déjà natives, confirmées suffisantes dans un audit précédent) — traduit uniquement ce qui
  existe déjà, trouvé par recherche de nom.

## [0.37.0] - 2026-08-13

### Changed
- Manifeste (`configurationglpiauto.xml`) : `<state>` passe de `dev` à `stable` — décision
  utilisateur, dernier prérequis marketplace pour ce dépôt (36 versions livrées, suite qualité
  verte à chaque livraison, vérifié en réel sur GLPI 11.0.8 à chaque fonctionnalité).

### Documentation
- ROADMAP.md : idée « actifs personnalisés » (`Glpi\Asset\AssetDefinition`) confirmée et débloquée
  après vérification directe dans le code source de GLPI 11.0.8 réel — aucun changement de code.

## [0.36.0] - 2026-08-13

Refonte de l'étape Lieux suite à un retour utilisateur direct sur v0.33.0/v0.35.0 en usage réel
("tu met les entité dans les lieu, mais s'en ai pas, par contre faire en sorte que l'on puisse
saisir ou non une adresse pour chaque entité et sous entité" ; "pour les lieux on pourrais leur
proposer de mettre un alias" ; "faut que l'on puisse remplir tout les champs natif de glpi").

### Changed
- **`LocationBuilder` ne mirror plus systématiquement toute l'arborescence d'entités.** Avant : un
  `Location` était créé pour CHAQUE entité/sous-entité de l'arbre, avec adresse possible seulement
  sur les nœuds racine — même les départements internes sans adresse propre finissaient en Lieux.
  Maintenant : un `Location` n'est créé QUE là où l'admin a effectivement saisi quelque chose, à
  N'IMPORTE quel niveau de l'arbre (pas seulement les nœuds racine). Un nœud sans donnée n'a aucun
  Lieu — ses descendants, s'ils en ont, se rattachent au plus proche ancêtre qui a un Lieu (ou à la
  racine s'il n'y en a aucun), sans niveau vide intercalé.
- L'étape 15 (Lieux) affiche désormais l'arborescence complète (tous niveaux, pas seulement les
  entités racine), avec un bouton « + Ajouter une adresse » repliable par nœud plutôt que des
  panneaux fixes uniquement sur les clients/sites de premier niveau.

### Added
- Tous les champs natifs de `glpi_locations` sont désormais saisissables, pas seulement adresse/
  code postal/ville/pays : `code`, `alias`, `commentaire`, `état/région` (distinct du pays),
  `bâtiment`, `pièce`, et `altitude`. Nominatim peut suggérer adresse/code postal/ville/état/pays/
  latitude/longitude ; le reste (code, alias, commentaire, bâtiment, pièce, altitude — aucun
  service de géocodage ne fournit l'altitude) reste toujours en saisie manuelle, comme sur le
  formulaire natif de GLPI.

### Fixed
- Bug trouvé pendant cette refonte, avant mise en ligne : le validateur de coordonnées GPS
  (`sanitizeCoordinate()`) limitait le nombre de chiffres avant la virgule à 3 — correct pour
  latitude/longitude, mais rejetait toute altitude à 4 chiffres (un site de montagne à 2000m,
  par exemple). Corrigé en ne bornant que par la plage (`$max`), pas par la forme de la chaîne.

## [0.35.0] - 2026-08-13

Suite de l'assistant d'adresse (v0.33.0) : coordonnées GPS + deux vrais bugs remontés par
l'utilisateur en testant la fonctionnalité en réel.

### Added
- Coordonnées GPS (`glpi_locations.latitude`/`longitude`, colonnes natives GLPI jamais utilisées
  jusqu'ici) : récupérées automatiquement depuis Nominatim (une suggestion de rue choisie les
  écrase toujours ; le centroïde d'un code postal ne les remplit que si rien n'est déjà saisi),
  champs restant éditables à la main sinon.

### Fixed
- **"si je tape une adresse il complete ou sugère... il me propose des chose qui ne sont pas a
  paris"** — la recherche de rue en texte libre ("avenue") n'était jamais restreinte à la
  ville/pays déjà saisis, remontant des résultats partout en France (même bug déjà corrigé pour le
  code postal seul en v0.33.0, pas encore appliqué ici). Corrigé en passant `town`/`country` à
  `ajax/geocode.php`, qui bascule alors sur le mode de recherche *structurée* de Nominatim
  (`street`+`city`+`country` séparés) plutôt qu'une recherche libre sur tout le pays.
- **"pas hyper lisible"** — la liste de suggestions utilisait `list-group`, sans fond opaque
  propre : le texte se superposait visuellement aux champs code postal/ville/pays et aux bascules
  en dessous (confirmé sur une vraie capture d'écran). Corrigé en réutilisant `dropdown-menu`
  (Bootstrap/Tabler, fond/bordure/ombre déjà corrects nativement) à la place.
- **Bug latent trouvé pendant cette même correction, avant toute mise en ligne** : `dropdown-menu`
  est masqué par sa propre règle CSS (`display: none`, normalement levée par le composant JS
  Bootstrap natif, non utilisé ici) — `suggestions.style.display = ''` retombait donc sur cette
  règle et gardait la liste invisible même une fois remplie de vrais résultats. Corrigé en forçant
  `'block'` plutôt qu'une chaîne vide. Trouvé en vérifiant l'état réel du DOM après une recherche,
  pas juste en relisant le code.

## [0.34.0] - 2026-08-13

Modèles de projet pré-structurés — dernier chantier ouvert du sixième audit (v0.26.0) : GLPI
permet de sauver un projet comme "modèle" et d'en recréer un nouveau à partir de lui, mais le
plugin n'en fournissait aucun.

### Added
- `ProjectTemplateBuilder` (nouveau) : deux vrais `Project` marqués `is_template=1` avec leurs
  `ProjectTask` déjà rattachées — « Déploiement standard » (6 étapes, cadrage → clôture) et
  « Projet interne — cycle court » (3 étapes, réutilise les mêmes jalons que la bibliothèque
  `ProjectTaskTemplateBuilder`). Confirmé en lisant le code source de GLPI plutôt que supposé :
  `Project` utilise le mécanisme de gabarit générique `CommonDBTM`
  (`is_template`/`template_name`), et `Project::getCloneRelations()` inclut explicitement
  `ProjectTask::class` — le sélecteur de gabarit que `CommonDBTM` affiche déjà nativement sur
  "+ Nouveau" clone donc automatiquement les tâches liées, sans rien à construire côté UI.
- Pas de bascule "icônes" ici contrairement à la plupart des autres constructeurs de ce plugin :
  vérifié dans le code de GLPI que `Project` n'est pas un `CommonDropdown` et que
  `DropdownTranslation::getTranslatedValue()` n'est jamais appelé ni pour son nom ni pour son
  sélecteur de gabarit (qui affiche `template_name` directement) — un appel à
  `Translations::applyIcon()` aurait écrit des lignes que rien ne lit jamais. L'icône est donc
  simplement intégrée au nom du modèle.

### Verified
- Vérifié en réel (Playwright, pas de mock) contre le vrai flux natif de GLPI : ouverture de
  `project.form.php?id=<modèle>&withtemplate=2`, formulaire pré-rempli depuis le modèle,
  enregistrement d'un nouveau projet réel — les 6 tâches du modèle « Déploiement standard » sont
  apparues automatiquement sur le nouveau projet, aucune action supplémentaire nécessaire.

## [0.33.0] - 2026-08-13

Assistant d'adresse interactif pour l'étape Lieux — demandé explicitement par l'utilisateur
("les adresse on a dit un truc interractif, comme pour les site internet ou tu commence a taper
ta rue il la sugère, idm tu met le code postal tu a la ville"), cadré dans la v0.32.0 (recherche
API dans ROADMAP.md), construit ici.

### Added
- `ajax/geocode.php` (nouveau) : proxy serveur vers un service compatible Nominatim (OpenStreetMap
  Nominatim public par défaut, endpoint admin-configurable pour une instance auto-hébergée ou
  LocationIQ/OpenCage). Proxifié plutôt qu'un appel direct depuis le navigateur pour deux raisons
  réelles : un `fetch()` navigateur ne peut pas fixer de `User-Agent` (exigé par la politique
  d'usage de Nominatim), et l'endpoint cible est lu exclusivement depuis la config stockée
  côté serveur (jamais depuis la requête du client) pour fermer tout SSRF. Utilise
  `Toolbox::getGuzzleClient()` (cœur GLPI) pour respecter le proxy sortant déjà configuré par
  l'admin GLPI.
- `Config::location_geocoding_enabled`/`location_geocoding_endpoint` (nouveaux réglages) : bascule
  d'activation (opt-in réel, rien n'est envoyé au service tiers tant qu'elle n'est pas cochée dans
  le navigateur de l'admin) et endpoint avancé, validé côté serveur (`https://` uniquement).
- `LocationBuilder::build()` accepte désormais des données d'adresse par entité de premier niveau
  (rue/code postal/ville/pays), écrites sur le `Location` racine de chaque site — jamais propagées
  aux lieux enfants, une adresse décrit un site, pas chaque service qui y est nichée.
- Étape 15 (Lieux) : panneau par site avec autocomplétion de rue (saisie → suggestions Nominatim,
  débouncée 400 ms) et remplissage automatique de la ville à partir du code postal (déclenché à la
  sortie du champ, jamais s'il y a déjà une ville saisie à la main).

### Fixed
- Deux problèmes réels trouvés lors de la vérification en direct (Playwright + vraie instance
  Nominatim), avant toute mise en ligne :
  - Le proxy refusait toute requête tant que `location_geocoding_enabled` n'était pas déjà
    enregistré en base — ce qui bloquait systématiquement le tout premier essai de l'assistant
    pendant l'assistant lui-même (rien n'est encore enregistré avant de cliquer sur "Terminer").
    L'opt-in réel est la case cochée dans le navigateur à l'instant T (seule condition qui
    déclenche réellement un appel côté JS) ; la persistance en base n'apportait aucune protection
    supplémentaire au-delà du droit déjà vérifié par `Session::checkRight()`.
  - Une recherche par code postal seul est ambiguë à l'échelle mondiale (« 69001 » correspond à la
    fois à Lyon et à un quartier de Zaporijjia, Ukraine — confirmé en réel) et ce résultat écrasait
    la ville sans relecture possible par l'admin. Corrigé en associant systématiquement un pays à
    la recherche : celui déjà saisi par l'admin, ou « France » par défaut (même logique que les
    jours fériés français déjà codés en dur ailleurs dans le plugin).

## [0.32.0] - 2026-08-13

Traduction complète du contenu des gabarits (demandé explicitement : "tout sans exception") et
dictionnaire de normalisation des fabricants.

### Added
- `Translations::applyContent()` (nouveau) : même mécanisme `DropdownTranslation` que
  `applyIcon()`, sur le champ `content` cette fois. Les 18 gabarits de suivi/tâche/solution
  (`FollowupLibraryBuilder`/`SolutionLibraryBuilder`/`TaskTemplateBuilder`) ont désormais leurs 4
  traductions, salutation Twig incluse ("Bonjour" → "Hello"/"Hallo"/"Buongiorno"/"Hola",
  itemtype-aware comme la v0.27.0). Vérifié en réel : une session anglaise reçoit "Hello glpi," et
  le corps traduit en appliquant un gabarit à un vrai ticket.
- `ManufacturerDictionaryBuilder` (nouveau) : 15 règles `RuleDictionnaryManufacturer` normalisant
  les variantes de nom des plus grands fabricants (« Hewlett-Packard », « HP Inc. » → « HP »...).
  Ne contredit pas le rejet précédent des dictionnaires logiciel/matériel (variantes propres à
  chaque organisation, indevinables) : les variantes des grands fabricants sont documentées et
  stables, écrites sans avoir besoin de l'inventaire réel d'une organisation. Vérifié avec l'outil
  natif de test de règle de GLPI (`front/rule.test.php`).

## [0.31.1] - 2026-08-13

### Fixed
- **Régression réelle introduite par la v0.31.0**, trouvée en relisant le code avec un œil
  critique juste après l'avoir livré (pas signalée par l'utilisateur cette fois) :
  `NotificationBrandingBuilder` n'écrivait qu'une ligne `language=''` par gabarit — GLPI résout
  pourtant le contenu par la langue du *destinataire*
  (`NotificationTemplate::getByLanguage()`, `WHERE language IN ($langue_destinataire, '')`), donc
  tous les destinataires auraient reçu les libellés en français codés en dur, quelle que soit leur
  langue GLPI — contredisait directement le travail de traduction 5 langues livré juste avant.
  Corrigé : une ligne par langue (fr_FR/en_GB/de_DE/it_IT/es_ES), `''` servant à la fois de repli
  universel et de contenu français (même convention que `locales/fr_FR.po`). Vérifié en base :
  les 4 gabarits ont chacun leurs 5 lignes avec un contenu réellement différent par langue.

## [0.31.0] - 2026-08-13

Habillage HTML des e-mails de notification, à partir d'un vrai jeu d'e-mails de production
audité (couleur/logo déjà calculés par `BrandingBuilder`, balises GLPI natives vérifiées une par
une dans le code source plutôt que devinées).

### Added
- `NotificationBrandingBuilder` (nouveau) : remplace le modèle de notification GLPI par défaut
  (partagé nativement par tous les évènements ticket — confirmé en base : `new`/`update`/`solved`/
  `add_followup` pointent tous vers le même template id) par un modèle HTML dédié et coloré pour
  chacun de ces 4 évènements, avec le logo de l'entité racine s'il existe. Idempotent via un
  marqueur HTML (pas le pattern habituel "la ligne existe déjà, ne rien faire" : ceci *modifie* une
  ligne native, donc un e-mail modifié à la main ensuite n'est plus jamais réécrit par une
  réexécution de l'assistant).
- Manifeste marketplace (`configurationglpiauto.xml`) : `<screenshots>` renseigné (6 captures),
  différé de la v0.29.0 pour ne pas casser la vérification CI officielle GLPI (les captures
  n'existaient pas encore sur `main` au moment de cette PR).

### Fixed
- `README.md` : bandeau et liste "Fonctionnalités" jamais mis à jour depuis la toute première
  version du projet (2026-08-10, avant le vrai travail) — signalé par l'utilisateur, qui a
  remarqué l'absence d'une fonctionnalité ("assistant intelligent de création des lieux avec
  géocodage") jamais réellement construite (`LocationBuilder` fait un miroir simple de
  l'arborescence d'entités, par choix — jamais un assistant de géocodage). En creusant, deux
  autres items listés ("Mode Audit", "Blueprints") sont aussi de purs items `[ ]` non cochés du
  ROADMAP, jamais livrés. Liste réécrite pour refléter ce qui est réellement livré, ancre
  "Utilisation" du sommaire réparée (menait nulle part, la section n'avait jamais été écrite).
- `Installer.php` : la migration d'ajout de colonne pour `notification_branding_enabled` manquait
  sur le chemin de mise à jour (ajoutée seulement au `CREATE TABLE` d'une install neuve) — trouvé
  en testant en réel (colonne absente après réinstallation), même classe de bug que celle déjà
  documentée pour d'autres colonnes plus tôt dans le projet.

Vérifié de bout en bout en conditions réelles : gabarits créés, évènements réassignés en base,
idempotence confirmée (resoumission → 0 modèle réécrit, hash de contenu identique), et un vrai
ticket créé pour confirmer que la notification en file d'attente contient bien le HTML habillé
avec les balises GLPI correctement substituées (titre, contenu — aucune balise `##ticket.xxx##`
laissée non résolue).

## [0.30.1] - 2026-08-12

### Fixed
- **Régression réelle introduite par la v0.30.0**, signalée par l'utilisateur (capture d'écran
  montrant un mélange de langue) : l'interface du wizard s'affichait en anglais pour les
  utilisateurs francophones. Cause : `Plugin::loadLang()` (core GLPI) ne se rabat pas sur le texte
  brut du `msgid` quand aucun `.mo` n'existe pour la langue de session — il descend jusqu'à
  `en_GB.mo` en dernier recours. Comme `locales/fr_FR.mo` n'existait pas (hypothèse de départ
  erronée : "le français est déjà le texte source, inutile de le compiler"), toute session en
  `fr_FR` recevait silencieusement la traduction anglaise. Corrigé en ajoutant
  `locales/fr_FR.{po,mo}` (mapping identité, `msgid` = `msgstr`, même pattern que remise-glpi).
  Revérifié en réel dans les 5 langues (fr_FR inclus) après correctif — plus de mélange.

## [0.30.0] - 2026-08-12

Traduction complète de l'interface du wizard (en_GB/de_DE/it_IT/es_ES), dernier prérequis avant
soumission à plugins.glpi-project.org.

### Added
- `locales/{en_GB,de_DE,it_IT,es_ES}.{po,mo}` (nouveau) : les 318 chaînes `__()`/`_n()` du domaine
  `configurationglpiauto` (templates, builders, `front/`, `setup.php`) traduites dans les 4 langues
  déjà utilisées pour les traductions de données. Aucun changement de code nécessaire — GLPI charge
  automatiquement un dossier `locales/` par convention (`Plugin.php`, confirmé en lisant le core).
- Manifeste : langues de_DE/it_IT/es_ES déclarées (ajoutées v0.26.0) désormais réellement honorées
  par l'interface, pas seulement les traductions de données.

Vérifié en conditions réelles sur l'instance de test : bascule de langue testée pour les 4 langues,
aucune régression, aucun caractère mal encodé. Limite connue, hors périmètre gettext : les noms et
descriptions des 4 profils prédéfinis (`ConfigurationProfile`) restent en français — ce sont des
données en base au moment de l'installation, pas des chaînes `__()`.

### Removed
- `.github/workflows/locales-sync.yml` : infrastructure vestige jamais fonctionnelle (dépend de
  Transifex, jamais configuré — pas de secret `TRANSIFEX_TOKEN` — et référence un `hook.php` qui
  n'existe pas dans ce dépôt). Filtrée sur `locales/**`, elle ne s'était jamais déclenchée avant
  que ce dossier existe ; désormais qu'il existe, elle échouait sur chaque PR touchant les
  traductions sans que ça n'ait de sens de la corriger — pas l'infrastructure réellement utilisée
  ici (`.po`/`.mo` gérés manuellement).

## [0.29.0] - 2026-08-12

Documentation utilisateur : tutoriel avec capture d'écran de chaque étape de l'assistant.

### Added
- `docs/TUTORIAL.md` (nouveau) : parcours des 17 étapes de l'assistant, une capture d'écran réelle
  par étape (profil "Plusieurs sites ou services"), lié depuis le README.

Le `<screenshots>` du manifeste marketplace sera renseigné dans un correctif séparé une fois ces
images réellement présentes sur `main` — la CI officielle GLPI (`plugin-ci-workflows`) vérifie que
chaque URL de capture résout, contrairement à `download_url` qu'elle tolère en 404 tant que la
release n'est pas encore publiée ; référencer des captures qui n'existent pas encore sur `main`
aurait fait échouer la CI de cette PR.

## [0.28.0] - 2026-08-12

Catalogue de services étoffé (suite du sixième audit).

### Added
- `ServiceCatalogBuilder` : 27 nouveaux services (23 → 50), tous ancrés sur des sous-catégories
  réelles de `CategoryBuilder` (aucun chemin inventé, vérifié en base après resoumission — 50
  services créés, aucun ignoré silencieusement pour cause de chemin introuvable). Couvre
  notamment les 4 branches qui n'avaient encore aucun service (Administratif/Juridique/Finance,
  Communication & Marketing, Qualité/QHSE/Conformité, Maintenance Industrielle & Technique) et
  étoffe les branches existantes trop réduites (Bâtiment, Flotte, RH, Achats, Sécurité, Services
  Généraux : 1 à 3 services chacune auparavant).

## [0.27.0] - 2026-08-12

Sixième audit (module Projets + points transverses) : préparation à la publication sur
plugins.glpi-project.org, et variables Twig dans les gabarits de suivi/solution. Voir
`ROADMAP.md` pour le détail complet de l'audit.

### Added
- `FollowupLibraryBuilder`/`SolutionLibraryBuilder` : les 15 gabarits utilisent désormais le
  moteur Twig sandboxé natif de GLPI (`Glpi\ContentTemplates\TemplateManager`, disponible depuis
  la 10.0 sur les gabarits de suivi/tâche/solution) pour une salutation personnalisée
  (`Bonjour <nom>,` via `{{ requesters|first.fullname }}`, avec repli sur "Bonjour," si aucun
  demandeur) — compatible Ticket/Change/Problem (le nom de variable racine change selon le type,
  géré via `itemtype`). Vérifié en conditions réelles (ticket réel + Change réel créés pour
  l'occasion) que le rendu est correct dans les deux cas.
- Manifeste (`configurationglpiauto.xml`) : ajout de `<download_url>` par version (absent
  jusqu'ici, la marketplace n'avait rien à proposer en téléchargement), déclaration de 3 langues
  supplémentaires (de_DE/it_IT/es_ES) en plus de fr_FR/en_GB.

### Fixed
- `SolutionLibraryBuilder` : les 2 gabarits "Sécurité" référençaient déjà `{{ ticket.solvedate }}`
  en dur alors que leur `SolutionType` est aussi sélectionnable sur un Change
  (`is_change=1`) — où `ticket` n'existe pas, et le filtre Twig `date` traite un input
  indéfini comme "maintenant", affichant silencieusement la date du jour au lieu de la vraie date
  de résolution. Rendu itemtype-aware comme le reste de ce changement.

## [0.26.0] - 2026-08-12

Règles de droits LDAP par fonction/département, en complément de la règle par site existante.
Cadrage confirmé avec l'utilisateur : intégré à l'étape 12 existante (« Droits LDAP »), pas une
nouvelle étape séparée.

### Added
- Étape 12 (Droits LDAP) : liste répétable optionnelle de règles fonction/département — un nom de
  groupe AD/LDAP et un profil natif, indépendant du site de l'utilisateur (ex. le groupe "Finance"
  reçoit toujours tel profil, quel que soit son site). Chaque paire devient une `RuleRight` GLPI
  distincte qui n'agit que sur le profil (aucune action `entities_id`) — confirmée en base comme
  s'accumulant avec les règles par site déjà générées plutôt que les remplacer (GLPI ne s'arrête
  pas à la première règle qui matche).
- `RuleRightBuilder::buildFunctionRights()` (nouveau) et `Config::getLdapFunctionRights()`/
  `sanitizeLdapFunctionRights()` (validation : groupe non vide, profil parmi les profils natifs
  existants).
- Colonne `ldap_function_rights` (texte JSON) sur la table de config du plugin.

## [0.25.0] - 2026-08-12

Quatre dernières pistes basse priorité du ROADMAP : sélection des palettes natives GLPI,
évènements de planning, couleur par client, et mise à jour d'une note obsolète sur le
cloisonnement des droits MSP. Voir `ROADMAP.md` pour le détail complet.

### Added
- Sélection d'une des 18 palettes natives GLPI (`auror`, `dark`, `midnight`...) comme choix par
  défaut, alternative à la palette personnalisée (`PaletteBuilder`) — liste lue dynamiquement
  depuis `Glpi\UI\ThemeManager::getCoreThemes()`, mutuellement exclusive avec la palette
  personnalisée dans l'UI.
- `PlanningEventBuilder` (nouveau) : Catégories d'évènements (`PlanningEventCategory`, avec
  couleur native — c'est elle qui s'affiche réellement dans la grille de planning, pas le
  mécanisme icône habituel) et Gabarits d'évènements externes (`PlanningExternalEventTemplate`,
  récurrence volontairement laissée à l'admin).
- `branding_per_client_enabled` : couleur indépendante par client/site (au lieu d'une seule
  couleur partagée pour toutes les entités créées) — même schéma de panneau par entité que le
  logo par entité déjà existant. `BrandingBuilder::applyPerClientColors()` (nouveau).

### Changed
- `ROADMAP.md` : note obsolète sur le cloisonnement des droits MSP mise à jour — `RuleRightBuilder`
  (déjà livré) couvre déjà ce point pour les utilisateurs synchronisés LDAP (assignation à
  l'entité feuille précise, jamais récursive).

## [0.24.0] - 2026-08-12

Dernière piste du ROADMAP côté wizard : le mode "express". Voir `ROADMAP.md` pour le détail.

### Added
- Bouton "Terminer avec les réglages recommandés" à l'étape 1 (Profil), visible dès qu'un profil
  est sélectionné — soumet immédiatement le wizard avec les valeurs déjà préremplies par
  `applyProfileDefaults()`/`ConfigurationProfile::getSuggestedDefaults()`, sans passer en revue
  les 17 étapes. Purement une navigation raccourcie (même formulaire, même bouton `name="finish"`)
  : aucun nouveau champ de configuration, aucune nouvelle logique serveur.

## [0.23.0] - 2026-08-12

Trois pistes du ROADMAP : palette GLPI personnalisée, contrôle de prérequis, gestion documentaire.
Voir `ROADMAP.md` pour le détail complet.

### Added
- `PaletteBuilder` (nouveau) : génère une palette GLPI native (Configuration > Générale >
  Apparence, `files/_themes/`), sélectionnable par tout utilisateur dans ses préférences et
  définie par défaut pour l'instance (`\Config::setConfigurationValues('core', ['palette' => ...])`)
  — mécanisme distinct et complémentaire de `BrandingBuilder` (celui-ci force la couleur par
  entité, celui-là ajoute un choix par défaut que chacun peut changer). Réutilise la couleur déjà
  choisie à l'étape 9, nouvelle case "Créer une palette GLPI personnalisée".
- `DocumentManagementBuilder` (nouveau) : Rubriques des documents (`DocumentCategory`, échelle de
  classification ISO 27001 — Public/Interne/Confidentiel/Diffusion restreinte) et Criticités
  (`BusinessCriticity`, impact métier des actifs — Critique/Élevée/Moyenne/Faible). `DocumentType`
  non touché : GLPI ships déjà 73 types natifs, suffisant.
- Bandeau informatif de vérification d'environnement en tout début de wizard (droits d'écriture
  sur `files/_themes`/`GLPI_CACHE_DIR`, extension GD/Imagick) — jamais bloquant, visible seulement
  s'il y a un point d'attention réel.

### Fixed (upstream GLPI, worked around here)
- `Glpi\UI\ThemeManager`/`Theme::getPath()` suppose toujours l'extension `.scss` pour une palette
  personnalisée, même si `*.css` est accepté par la détection — un fichier `.css` fait planter
  *tout* le site (500 partout, y compris la page de login, `ThemeManager::getCustomThemesPaths()`
  tournant sur chaque requête). `PaletteBuilder` écrit toujours en `.scss`, contenu CSS classique.

## [0.22.1] - 2026-08-12

### Added
- `ROADMAP.md` : le diagnostic LDAP pas-à-pas (piste #2 des propositions du cinquième audit) est
  explicitement écarté — décision utilisateur, chaque annuaire d'entreprise a ses propres filtres/
  bind DN/schéma de groupes, un assistant générique se heurterait à du cas-par-cas contraire à la
  philosophie du plugin (des builders universels, pas des outils ad hoc par organisation). Aucun
  changement de comportement du plugin.

## [0.22.0] - 2026-08-12

Deux pistes du cinquième audit (recherche marché) : séparation stricte des droits par défaut, et
profils métier prêts à l'emploi. Voir `ROADMAP.md` pour le raisonnement complet et les limites
assumées de chacune.

### Changed
- `ldap_rights_profile` (réglage "Profil attribué" de `RuleRightBuilder`, étape 12 du wizard) passe
  de `Technician` à `Admin` par défaut. Confirmé en diffant `glpi_profilerights` sur un GLPI 11.0.8
  réel : Admin n'a déjà ni le droit `profile` en écriture, ni `rule_ldap`/`rule_import`, ni
  `config` — les vecteurs d'auto-élévation identifiés comme le point de friction GLPI le plus
  sévère en recherche — sans avoir à fabriquer un nouveau profil sur mesure. Toujours un simple
  menu déroulant, tout profil natif reste sélectionnable.

### Added
- Étape 5 (Catégories) : 4 boutons "Profil métier" (IT pur / RH & Support interne / Bâtiment &
  Moyens généraux / Multi-services) préremplissant en un clic les branches de catégories et le
  tableau SLA de l'étape 4 — purement client (JavaScript), aucun nouveau champ serveur : réutilise
  les branches et le mécanisme SLA déjà existants plutôt que d'ajouter du contenu métier par
  verticale. RH et Bâtiment utilisent le rythme SLA IT existant multiplié par 4 et 2
  respectivement (assumé comme point de départ, pas une pratique métier sourcée comme l'est le SLA
  IT).

## [0.21.0] - 2026-08-12

`BrandingBuilder` réécrit pour couvrir exhaustivement les variables CSS de personnalisation
exposées par GLPI 11 (`css/includes/_base.scss`, confirmé directement dans le code source de
l'instance, pas seulement recherché) au lieu d'en deviner une par une à chaque nouvelle demande.
Voir `ROADMAP.md` pour le détail complet.

### Changed
- Logo : couvre maintenant les 6 variables réelles (`--glpi-logo-light`/`-light-reduced`/`-dark`/
  `-dark-reduced`/`-light-login`/`-dark-login`) au lieu de seulement l'alias `--glpi-logo`/
  `-reduced` — le logo d'un admin n'apparaissait jusqu'ici jamais sur l'écran de connexion
  (variables login jamais touchées) ni sur certaines règles CSS qui référencent directement la
  variante "dark" plutôt que l'alias.
- Couleur : la couleur principale du wizard s'applique désormais aussi à `--glpi-mainmenu-bg`
  (fond de la barre latérale), pas seulement `--tblr-primary` (boutons/liens) — l'aperçu en direct
  du wizard simulait déjà la barre latérale prenant cette couleur, l'implémentation ne le faisait
  pas. Couleur de texte (`--tblr-primary-fg`/`--glpi-mainmenu-fg`) recalculée par contraste
  (luminance perceptuelle) plutôt que laissée aux valeurs par défaut de GLPI, pour rester lisible
  quelle que soit la couleur choisie.
- L'écran de connexion (page non authentifiée) reçoit maintenant la couleur choisie en mode
  mono-entité et multi-entité "même entreprise" — confirmé dans le code source de GLPI que la page
  de connexion utilise le CSS personnalisé de l'entité racine en repli
  (`FrontEndAssetsExtension::customCss()`), jamais exploité jusqu'ici. Toujours exclu en mode MSP :
  une page de connexion non authentifiée ne doit pas laisser fuiter la couleur d'un client en
  particulier.

Vérifié de bout en bout sur l'instance réelle : styles calculés du navigateur (pas seulement le
contenu de la base) confirmant que la barre latérale, le texte et le logo affichent bien les
valeurs choisies après une vraie soumission du wizard et un vrai changement d'entité active.

## [0.20.3] - 2026-08-12

Cinquième passage d'audit — navigation réelle dans l'admin GLPI (Playwright), pas seulement base de
données/code cette fois. Voir `ROADMAP.md` pour le détail.

### Fixed
- `GeneralSettingsBuilder` : les tâches automatiques `cartridge`/`consumable`/`software` (alertes
  cartouches, consommables, expiration de licences) ships désactivées par défaut, alors que leurs
  notifications correspondantes sont déjà actives — la notification semblait configurée mais ne se
  déclenchait jamais. Activées par le même toggle "Notifications" que le reste (`notifications_enabled`).

## [0.20.2] - 2026-08-12

Quatrième audit de complétude (comparaison SQL du nombre total de lignes vs lignes réellement
traduites, par itemtype) — trois trous réels trouvés et corrigés. Voir `ROADMAP.md` pour le détail
complet, la recherche marché associée (douleurs de configuration GLPI, benchmark ITSM concurrent),
et les 7 propositions soumises à l'utilisateur.

### Fixed
- `WaitReasonBuilder` : les gabarits de suivi/solution auto-créés par une raison d'attente
  (`ITILFollowupTemplate`/`SolutionTemplate`) ne recevaient jamais d'icône ni de traduction, y
  compris en réexécutant le wizard sur une instance déjà configurée (bug dans la branche
  "déjà existant" du correctif lui-même, corrigé dans la même passe).
- `CategoryBuilder` : 37 sous-catégories de niveau 3 sur 103 n'étaient jamais traduites alors que
  les 5 langues existaient déjà dans `Translations.php` — `Translations::applyIcon()` n'était
  appelée que si le nœud avait une icône, jamais pour les feuilles (qui n'en ont volontairement
  aucune). `applyIcon()` accepte maintenant une icône vide.

### Added
- `ProjectTaskTemplateBuilder` : nouveau réglage "Ajouter des icônes"
  (`project_task_template_icons_enabled`) — angle mort du même type que celui corrigé en v0.20.0
  pour les gabarits de ticket/changement/problème, jamais construit à l'époque.

## [0.20.1] - 2026-08-12

### Added
- `ROADMAP.md` : nouvelle section "Quatrième audit" cadrant le travail à reprendre en priorité —
  audit de complétude par navigation Playwright réelle (pas une relecture de code, leçon tirée de
  la correction v0.20.0), inventaire des réglages GLPI encore non couverts, pistes d'automatisation
  (plugin + moteur de règles natif GLPI), passage de l'aperçu CSS/branding de la déduction au
  systématique, et recherche marché (douleurs de configuration GLPI courantes + benchmark des
  wizards d'onboarding des principaux concurrents ITSM). Aucun changement de comportement du
  plugin.

## [0.20.0] - 2026-08-12

Icônes + traductions EN/DE/IT/ES sur les gabarits ("Gabarits de ticket/changement/problème/
solution/tâche/suivi/validation") — jusqu'ici seuls les statuts, catégories, types etc.
en bénéficiaient. Correction d'une erreur de conception du sprint précédent : `TicketTemplate`/
`ChangeTemplate`/`ProblemTemplate` (`ITILTemplate extends CommonDropdown`) n'avaient jamais reçu
d'icônes du tout (angle mort, jamais construit), et `SolutionTemplate`/`TaskTemplate`/
`ITILFollowupTemplate`/`ITILValidationTemplate` (`AbstractITILChildTemplate`) avaient été
explicitement écartés sur la base d'une lecture de code incorrecte — vérifié empiriquement que
GLPI accepte bien une traduction sur le champ `name` de ces gabarits via leur propre onglet
« Traductions ». Aucune rupture de compatibilité.

### Added
- 6 nouveaux réglages « Ajouter des icônes » (un par famille de gabarit) : gabarits de ticket,
  de changement/problème, de tâche, de suivi, de validation, de solution — chacun indépendant du
  réglage existant qui crée déjà les gabarits eux-mêmes.
- `TicketTemplateBuilder`/`ChangeProblemTemplateBuilder`/`TaskTemplateBuilder`/
  `FollowupLibraryBuilder`/`ValidationTemplateBuilder`/`SolutionLibraryBuilder` : appellent
  désormais `Translations::applyIcon()` sur chaque gabarit créé, avec les mêmes 5 langues
  (fr_FR/en_GB/de_DE/it_IT/es_ES) que le reste du plugin.
- ~30 nouveaux termes ajoutés à la table de correspondance de `src/Translations.php`.

### Fixed
- `TicketTemplate`/`ChangeTemplate`/`ProblemTemplate` : n'avaient jamais eu d'icônes, angle mort
  du balayage d'icônes initial (`ITILTemplate` n'avait pas été identifié comme une des classes
  concernées).

Vérifié en conditions réelles, pas seulement en base : traduction `en_GB` visible avec sa session
active dans le sélecteur natif "Modèle de ticket par défaut" (Profils > Assistance), et dans
l'onglet « Traductions » des gabarits de solution/validation. La liste d'administration des
intitulés (Configuration > Intitulés) n'affiche jamais l'icône, par conception de GLPI — seul
l'endroit où la valeur est effectivement utilisée (sélecteur, ticket...) la montre.

## [0.19.0] - 2026-08-12

### Added

- CI : job `semgrep` (SAST) — absent jusqu'ici, alors que le plugin jumeau
  [remise-glpi](https://github.com/parime/remise-glpi) l'a depuis le début. Pas de
  `continue-on-error` (leçon tirée directement de remise-glpi le même jour : y avoir mis ça a
  rendu son statut CI "vert" quoi que semgrep trouve, pendant un temps).
- CI (Trivy) : scan étendu à `secret,misconfig` en plus de `vuln` — ce dépôt ne détectait aucun
  secret commité ni mauvaise configuration jusqu'ici, seulement des vulnérabilités connues de
  dépendances (déjà couvertes par ailleurs via `composer audit`).
- `.github/dependabot.yml` : cooldown de 7 jours avant de proposer une version tout juste publiée
  (composer + github-actions), aligné sur remise-glpi.

### Fixed

- CI : toutes les références d'actions GitHub non épinglées (`actions/checkout@v7`,
  `shivammathur/setup-php@v2`, `codecov/codecov-action@v7`, `aquasecurity/trivy-action@master`)
  épinglées au SHA complet — un tag ou une branche peuvent être redirigés silencieusement par le
  propriétaire de l'action (supply-chain), `@master` en particulier pour trivy-action est
  remplacé par un tag de version réel (`v0.36.0`) plutôt qu'une branche mouvante.

Rapprochement CI/CD avec le plugin jumeau remise-glpi, dans l'autre sens cette fois : ce dépôt
avait plus de couverture que remise-glpi sur certains points (matrice multi-versions GLPI,
coverage PHPUnit/Codecov, validation JSON/YAML/XML) mais moins sur la sécurité (pas de scan de
secrets, pas de SAST, actions non épinglées). Aucun changement de comportement du plugin
lui-même.

## [0.18.0] - 2026-08-12

Traductions anglais/allemand/italien/espagnol pour tous les intitulés générés qui supportent déjà
des icônes — jusqu'ici, le mécanisme n'ajoutait qu'une icône, toujours en français, jamais une
vraie traduction. Pas de rupture de compatibilité.

### Added
- `src/Translations.php` (nouveau) : table de correspondance centralisée (~154 termes français →
  en_GB/de_DE/it_IT/es_ES) + une méthode statique unique `applyIcon()` qui remplace les 9 méthodes
  privées quasi-identiques auparavant dupliquées dans `StateBuilder`, `CategoryBuilder`,
  `TaskCategoryBuilder`, `KnowbaseCategoryBuilder`, `ManufacturerBuilder`, `WaitReasonBuilder`,
  `ProjectTaxonomyBuilder`, `SolutionLibraryBuilder`, `SupportTierBuilder`. Chaque toggle « Ajouter
  des icônes » existant crée désormais les 5 langues d'un coup au lieu du seul français — aucune
  nouvelle case à cocher.
- Les noms de fabricants (`ManufacturerBuilder`) n'ont pas d'entrée dans la table de correspondance
  (noms de marque, identiques dans toutes les langues) : `applyIcon()` réutilise le texte français
  pour les 4 autres langues afin que l'icône reste visible quelle que soit la session.
- **Portée volontairement limitée aux données générées** (noms de statuts, catégories...) — la
  traduction de l'interface propre de l'assistant (labels, aide, boutons) resterait un chantier
  distinct : confirmé qu'aucun fichier `.po`/`.mo` n'existe dans ce dépôt pour l'assistant
  lui-même, `en_GB` déclaré dans le manifeste n'a en réalité jamais été honoré.

### Fixed
- Refactor en passant : élimine la duplication de code (9 méthodes `addIcon()`/logique inline
  quasi-identiques) au profit d'un point d'entrée unique.

## [0.17.1] - 2026-08-12

Complément au Sprint 34 : icônes sur les groupes de support N1/N2/N3, angle mort du sweep icônes
(`SupportTierBuilder` a été construit après ce lot, jamais repris). Pas de rupture de compatibilité.

### Added
- `SupportTierBuilder` : icônes optionnelles (`support_tier_icons_enabled`) sur "Support N1/N2/N3"
  via `DropdownTranslation` — même mécanisme que les autres intitulés, `Group extends
  CommonTreeDropdown` confirmé éligible. Progression 🟢/🟡/🔴 (aucune légende nécessaire pour
  repérer le niveau de sévérité d'un coup d'œil dans une liste d'affectation).

## [0.17.0] - 2026-08-12

Sprint 34 : correctif CSRF/ergonomie du wizard, icônes sur les intitulés, escalade N1→N2→N3 entre
niveaux de support — et un bug critique trouvé et corrigé en cours de route (soumission du wizard
cassée par le tout premier correctif de ce même sprint). Pas de rupture de compatibilité.

### Sprint 34 (partie 3/3) — escalade N1→N2→N3 + correctif critique du bouton Terminer (2026-08-12)

#### Fixed — [CRITIQUE] régression introduite par le correctif CSRF de la partie 1/3

Le correctif anti-double-clic du Sprint 34 (1/3) désactivait `#cga-wizard-finish` **de façon
synchrone à l'intérieur du handler `submit`**. Conséquence, propre au fonctionnement des
formulaires HTML : un bouton désactivé n'est jamais inclus dans les données envoyées, y compris
quand la désactivation survient pendant l'événement `submit` lui-même, avant l'envoi réseau — le
couple `name="finish"`/valeur du bouton disparaissait donc de la requête POST. Côté serveur,
`isset($_POST['finish'])` devenait faux à **chaque** soumission, silencieusement : la page se
ré-affichait normalement (aucune erreur visible, aucun message), sans qu'aucun des ~25 builders ne
s'exécute. **Toute soumission de l'assistant était cassée depuis le merge de la PR #33**, pas
seulement l'escalade en cours de test.

Trouvé en testant l'escalade N1-N3 : `escalation_enabled` restait obstinément à 0 en base malgré
une case cochée confirmée jusqu'au clic sur Terminer. Diagnostic confirmé étape par étape (jeton
CSRF, corps de requête multipart, réponse brute du serveur) jusqu'à isoler la cause exacte.
**Corrigé** en repoussant uniquement la désactivation de `finishBtn` d'un tick
(`setTimeout(fn, 0)`), après que le navigateur a déjà capturé les données du formulaire pour cette
soumission — `prevBtn`/`nextBtn` (de simples `type="button"`, jamais soumis) restent désactivés
immédiatement. Revalidé de bout en bout sur une base vierge : soumission réelle, tous les builders
s'exécutent, DB vérifiée.

#### Added — Escalade entre niveaux de support (N1 → N2 → N3)

Recherche web (2026-08-12, InvGate/Giva/TOPdesk/Buchanan...) : 3 niveaux (N1/N2/N3) est la
convention ITSM la plus répandue ; N0 (Tier 0) est une couche additionnelle de libre-service sans
équipe humaine, déjà couverte par le catalogue de services/formulaire d'accueil de ce plugin.

- `SupportTierBuilder` (nouveau) : crée 3 groupes techniciens globaux ("Support N1/N2/N3",
  `is_assign=1`) si `escalation_enabled`.
- `SlaBuilder` étendu : réutilise le mécanisme d'escalade SlaLevel/OlaLevel du Sprint 28 (confirmé
  dans le code source GLPI que `SlaLevel`/`OlaLevel` héritent de `RuleTicket` et supportent donc
  l'action `_groups_id_assign`, pas seulement `priority`). Tout ticket créé démarre au groupe N1 ;
  N1→N2 se déclenche au même seuil que l'escalade de priorité existante (`escalation_auto_n1_n2`) ;
  N2→N3 se déclenche à l'échéance elle-même si le ticket n'est toujours pas résolu
  (`escalation_auto_n2_n3`). Chaque hop est un toggle indépendant, y compris de la priorité
  (`sla_escalation_enabled`, Sprint 28).
- Réglable par client/site (étape 4, même panneau que le SLA par client) : un client peut
  désactiver l'escalade ou choisir d'autres hops automatiques sans affecter les autres.
- Case « Inclure le niveau N0 » : purement informative (N0 = libre-service déjà configuré ailleurs
  dans l'assistant), aucun groupe N0 n'est créé — pas d'équipe humaine derrière ce niveau.

### Sprint 34 (partie 2/3) — icônes sur les intitulés (2026-08-12)

Objectif : que le technicien retrouve rapidement ce qu'il cherche, même principe que les icônes
déjà en place sur les statuts et catégories ITIL, étendu partout où GLPI le permet techniquement.

#### Changed
- `ManufacturerBuilder`, `WaitReasonBuilder`, `ProjectTaxonomyBuilder`, `SolutionLibraryBuilder` :
  icônes optionnelles (`DropdownTranslation`, fr_FR) sur `Manufacturer`, `PendingReason`,
  `ProjectType`/`ProjectTaskType`, `SolutionType` — 4 nouveaux toggles indépendants
  (`manufacturer_icons_enabled`, `wait_reason_icons_enabled`, `project_taxonomy_icons_enabled`,
  `solution_type_icons_enabled`).
- Fabricants : icônes groupées par catégorie de produit (💻 informatique, 🌐 réseau, 🖨️
  impression...) plutôt qu'une icône par marque — pas de convention émoji par marque établie,
  l'inventer aurait été arbitraire.
- **Non couvert, pour une raison technique vérifiée dans le code source GLPI** :
  `SolutionTemplate`/`TaskTemplate`/`ITILFollowupTemplate`/`ITILValidationTemplate` étendent
  `AbstractITILChildTemplate`, pas `CommonDropdown` — le mécanisme d'icône par traduction ne s'y
  applique pas. L'icône va sur `SolutionType` (le regroupement choisi en premier par le
  technicien) à la place.

#### Fixed
- Bug introduit puis corrigé dans la même session, avant merge : le changement de forme des
  données de `ManufacturerBuilder::getManufacturersPreview()` (chaînes → tableaux `{name, icon}`)
  avait cassé le rendu Twig de l'étape 15 (`|join(', ')` sur des tableaux → avertissements PHP
  « Array to string conversion » répétés, signalés par capture d'écran). Corrigé en adaptant la
  boucle Twig ; revérifié en navigant les 17 étapes sans aucune erreur PHP.

### Sprint 34 (partie 1/3) — correctif CSRF, ergonomie du wizard (2026-08-11)

Premier lot de Sprint 34 (le reste — icônes sur les intitulés, escalade N1→N2→N3 — suit dans un
commit séparé). Déclenché par un vrai bug signalé par l'utilisateur en cours de test.

#### Fixed
- **CSRF `AccessDeniedHttpException` intermittent sur `front/wizard.php`** : le bouton « Terminer »
  n'avait aucune protection contre un double clic. Le traitement serveur (~25 builders séquentiels)
  prend plusieurs secondes ; un second clic pendant ce délai repart avec le même jeton CSRF, déjà
  consommé par GLPI (`Session::validateCSRF()` le retire de la session dès sa première validation
  réussie) → rejet par le pare-feu du noyau avant même que ce plugin ne s'exécute. Corrigé en
  désactivant le bouton (et en affichant « Création en cours... ») dès le premier `submit` réel.
  Confirmé par une entrée réelle dans `files/_log/access-errors.log` au moment du signalement.
- **Logo d'entité qui déborde de l'en-tête GLPI** : la règle CSS native `.page .glpi-logo` (boîte
  fixe 100×55px) n'a pas de `background-size`, contrairement à la variante réduite (sidebar
  repliée) qui l'a déjà — un logo uploadé de dimensions arbitraires débordait donc visuellement
  dans le menu au lieu de s'adapter. `BrandingBuilder::buildLogoCss()` force désormais
  `background-size: contain !important` sur cette règle. Texte d'aide ajouté sur le ratio
  recommandé (~100×55px) à l'étape Personnalisation.

#### Changed
- Étape Statuts (7) : ajout d'une phrase d'intro sur le rôle des statuts + le commentaire de
  chaque statut affiché en sous-texte sous son nom (donnée déjà disponible côté serveur, seul
  l'affichage manquait) — pour que l'admin choisisse en connaissance de cause.
- Étape Personnalisation (9) : le bouton « Aperçu » (simple pastille de couleur) est remplacé par
  un mini bloc simulant l'en-tête GLPI (couleur + logo), mis à jour en direct en JS pendant la
  saisie/le choix de fichier, sans soumettre le formulaire.

## [0.16.0] - 2026-08-11

Sprint 33: individually selectable element states, plus a real encoding bug fix found while
validating it. No breaking changes.

### Sprint 33 — selectable element states + remise-glpi interoperability (2026-08-11)

User request: `StateBuilder`'s 14 states (Sprint 16) were all-or-nothing — add checkboxes so an
admin can pick which ones to create, and flag a recommended minimum for interoperability with the
user's other plugin, `remise-glpi` (https://github.com/parime/remise-glpi).

#### Changed
- `StateBuilder::build()` now creates only the states selected in `Config.state_names` (new field,
  same whitelist-intersect pattern as `category_branches`) instead of all 14 unconditionally.
- Five states — "En stock", "Attribué", "Donné", "Vendu", "Attente restitution" — are flagged
  "recommandé" in the wizard UI: confirmed in `remise-glpi`'s own `ARCHITECTURE.md` that its
  `handleStateBasedTrigger()` auto-launches a handover/donation/sale/return workflow off a `State`
  change, matched by *state ID* configured in its own settings (not a hardcoded name) — so
  unchecking these doesn't break anything, but keeps the two plugins usable together out of the
  box for an admin running both.
- All 14 remain selected by default (matches the previous all-or-nothing behavior exactly) — the
  admin opts out per-state, not in.

#### Fixed
- **A real encoding bug**, found while validating the migration on the freshly-reset test
  instance: several state names carry accents ("Attribué", "Obsolète", "Donné"...), and
  `json_encode()`'s default `\uXXXX`-escaped output lost its backslash when embedded in the SQL
  `DEFAULT` clause `Installer.php`'s migration writes for upgrading installs — corrupting
  "Attribué" into "Attribuu00e9" in the database. Fixed everywhere this field is encoded
  (`Config::getDefaults()`, `Config::prepareInput()`, the migration itself) by using
  `JSON_UNESCAPED_UNICODE`, storing the raw UTF-8 character instead of a backslash-escape sequence
  that had nowhere safe to survive.

Validated against the real GLPI 11.0.8 test instance: confirmed the migration seeds all 14 states
correctly (both a fresh-install and an upgrading-install path re-tested after the encoding fix,
byte-for-byte correct accented names in the DB and in the resulting `glpi_states` rows), the wizard
step renders all 14 with the 5 recommended ones badged, and unchecking 3 states results in exactly
11 `State` rows created — not the unchecked ones. Local suite green (phpunit 10/10, phpstan clean,
php-cs-fixer clean).

## [0.15.0] - 2026-08-11

Sprint 32: per-entity logo upload — a client/site-specific logo alongside `BrandingBuilder`'s
existing plugin-wide primary color. No breaking changes.

### Sprint 32 — per-entity logo upload (2026-08-11)

User request: each client/site entity should be able to have its own logo, uploadable through the
wizard. Confirmed in GLPI source that `Entity` has no dedicated logo field — the closest native
mechanisms are `custom_css_code` (already used by `BrandingBuilder` for the primary color) and the
`custom_helpdesk_home_scene_left/right` illustration fields (decorative Helpdesk-home artwork, not
a client-identity logo). Scope confirmed with the user via `AskUserQuestion` before building: real
file upload + CSS injection, over upload-only or skipping the feature.

#### Added
- `BrandingBuilder::applyLogos()`: each uploaded logo is embedded as a `data:` URI and written into
  the target entity's `custom_css_code`, overriding `--glpi-logo`/`--glpi-logo-reduced` — confirmed
  in GLPI's own `_base.scss`/`_global-menu.scss` that the header/sidebar logo (`.glpi-logo`) is
  entirely CSS-custom-property-driven, so this is the same "override a themeable variable, not an
  arbitrary DOM selector" approach already used for the primary color, not a new class of risk.
  Both writers now delimit their own CSS with a comment marker (`mergeCssBlock()`) so color and
  logo — or any future CSS-writing feature, or an admin's own manual additions — coexist safely
  across reruns instead of overwriting the whole field.
- New file input per top-level entity (client/site) on the existing Branding wizard step, only
  shown once `entity_logos_enabled` is on — the wizard form now submits as
  `multipart/form-data`. Server-side validation (`front/wizard.php`): `getimagesize()` confirms the
  upload is a genuine image rather than trusting the browser-supplied MIME type, 1&nbsp;MB size cap
  (the file ends up base64-encoded, ~33% larger, inside a plain-text DB column), and SVG is
  deliberately excluded from the allow-list (PNG/JPEG/WebP/GIF only) to avoid any embedded-script
  question entirely rather than relying on background-image context not executing it.
- Deliberately *not* added to `ConfigurationProfile`'s suggested defaults, same reasoning as
  `ldap_rights_enabled`: there's no default file to suggest, so a pre-checked toggle with nothing
  uploaded would do nothing — opt-in only, matching the reality that only the admin has the logo.

Validated against the real GLPI 11.0.8 test instance: built a 2-client entity tree, uploaded a
distinct 1×1 PNG to each via Playwright's real file-input handling, confirmed in DB that each
entity's `custom_css_code` contains the exact byte-for-byte base64 of the file uploaded *for that
entity* (not swapped or mixed between the two), correctly delimited and with `enable_custom_css`
set. Local suite green (phpunit 10/10, phpstan clean, php-cs-fixer clean).

## [0.14.0] - 2026-08-11

Sprint 30: Projets intitulés (types de projet, types de tâche de projet, gabarits de tâches de
projets) — the last sprint from the third audit. No breaking changes.

### Sprint 30 — Projets intitulés (2026-08-11)

Closes the third audit (see ROADMAP.md). This sprint covers the Projets block: `ProjectType`,
`ProjectTaskType`, and `ProjectTaskTemplate`, none of which GLPI ships by default.

#### Added
- `ProjectTaxonomyBuilder`: 5 `ProjectType` rows (Interne, Client/Prestation, Infrastructure,
  Déploiement/Migration, R&D/Innovation) + 8 `ProjectTaskType` rows (Analyse & Cadrage,
  Conception, Développement, Tests & Recette, Déploiement, Documentation, Réunion & Pilotage,
  Formation). Unlike most other builders in this plugin, the audited production export had no
  customization to draw from here (its own project-related export only had GLPI's 3 native
  `ProjectState` rows, unmodified) — generalized from standard PM practice instead, cross-checked
  against GLPI's own project-management documentation.
- `ProjectTaskTemplateBuilder`: 3 reusable `ProjectTaskTemplate` rows (cadrage initial, point
  d'avancement, revue de clôture), each resolving its `ProjectTaskType` by name against whatever
  `ProjectTaxonomyBuilder` created — same independent-resolution pattern used throughout this
  plugin (`TaskTemplateBuilder` against `TaskCategoryBuilder`, etc.).
- New wizard step 16 ("Projets"), two independently-gated toggles.

#### Verified, not built
- `ProjectState`: GLPI ships 3 native rows (New/Processing/Closed) — confirmed unmodified in the
  audited production export too, no universal good-practice case found for adding more.
  `GeneralSettingsBuilder` continues to only *map* these 3 to the unstarted/in-progress/completed
  buckets used for progress tracking.

Validated against the real GLPI 11.0.8 test instance: confirmed in DB the exact 5 project types,
8 task types, and 3 templates with correct `projecttasktypes_id` linkage. Local suite green
(phpunit 10/10, phpstan clean, php-cs-fixer clean).

## [0.13.0] - 2026-08-11

Sprint 31: Général/Outils intitulés (Lieux, Fabricants, Catégories de la base de connaissances)
from the third audit, plus a real fix to a long-standing bug in `category_branches`' default
value handling, caught only after resetting the test environment to a clean slate. No breaking
changes.

### Sprint 31 — Général/Outils intitulés (2026-08-11)

Continues the third audit (see ROADMAP.md). This sprint covers the Général/Outils block: physical
locations, hardware manufacturers, and knowledge-base categories.

#### Added
- `LocationBuilder`: mirrors the entity tree built in step 2 into a matching `Location` tree — one
  location per entity node, same name, same nesting, scoped to that entity's real ID (not
  root+recursive like most of this plugin's builders, since a `Location` is real per-site/client
  data an MSP client shouldn't see another client's). Unlike `TaskCategoryBuilder`, there's no
  invented generic list — connects the dots on data the admin already entered once, rather than
  asking for the same site names a second time. No-op on an empty entity tree (mono-entité).
- `ManufacturerBuilder`: ~29 common IT/office manufacturers (Dell, HP, Cisco, Microsoft...) — a
  starting point, not every organization's real supplier list.
- `KnowbaseCategoryBuilder`: reuses `CategoryBuilder`'s 11 top-level branch names/icons instead of
  a second invented taxonomy, filtered to the branches actually selected in step 5 — a requester
  browsing the knowledge base sees the same themes as when filing a ticket.
- New wizard step 15 ("Général & Outils"), three independently-gated toggles.

#### Fixed
- **A real, long-standing bug** in `Config::prepareInput()`'s `category_branches` handling: it cast
  the incoming value with `(array)`, which only works for a real PHP array (submitted from the
  wizard's checkboxes) — `Config::getDefaults()` provides the same field as a JSON-encoded
  *string*, and `(array) $jsonString` wraps the whole string as one bogus element instead of
  decoding it, so `array_intersect()` against the 11 valid branch keys always came back empty. Net
  effect: every genuinely fresh install of this plugin silently started step 5 with zero category
  branches selected instead of the 11 documented in `getDefaults()`'s own comment and in
  `ConfigurationProfile`'s suggested defaults. Invisible on the long-lived test instance (a real
  form submission always overwrites the string with a proper array, masking it after the first
  save) — only surfaced after resetting the docker test stack to a clean slate this session, which
  is exactly why that reset was worth doing. Fixed by explicitly `json_decode`-ing when the
  incoming value is a string.

Validated against the real GLPI 11.0.8 test instance, freshly reset for this session: built a small
multi-entity tree (2 clients, one with a child site) via the wizard, confirmed in DB the resulting
`Location` rows exactly mirror it (correct names, nesting, and `entities_id` scoping), 29
manufacturers created, and 11 KB categories matching the 11 selected category branches. Also
specifically re-verified the `category_branches` fix by deleting the config row and confirming a
freshly-recreated one now correctly seeds all 11 branches. Local suite green (phpunit 10/10,
phpstan clean, php-cs-fixer clean).

## [0.12.0] - 2026-08-11

Sprint 29: closes most of a third, more systematic audit (every dropdown type under Configuration
> Intitulés, not just the two prior real-export-driven audits) — the ticket/task/change/problem
lifecycle intitulés GLPI ships with none of by default. New wizard steps, no breaking changes.

### Sprint 29 — ticket/task/change/problem lifecycle intitulés (2026-08-11)

Third audit pass (see ROADMAP.md's new "Troisième audit" section for the full inventory this was
extracted from — every category on GLPI's own Configuration > Intitulés page, not guessed). User
scope decision: tackle the whole Assistance + relevant Général/Outils block, split into sprints for
size. This sprint covers the ticket/task/change/problem lifecycle group specifically — the two
gaps the user pointed at directly (task categories, solution templates) plus everything else in
that cluster.

#### Added
- `TaskCategoryBuilder`: 14 flat `TaskCategory` rows (what *kind* of technician work — diagnostic,
  install, escalation... — independent of what the *ticket* is about). Same icon mechanism as
  `CategoryBuilder`/`StateBuilder` (`DropdownTranslation`, never HTML in `name`).
- `SolutionLibraryBuilder`: 5 `SolutionType` rows with per-ITIL-type visibility flags
  (`is_incident`/`is_request`/`is_problem`/`is_change`) + 2 `SolutionTemplate` each (10 total) — a
  standard ITSM closure-code taxonomy (resolution vs. workaround vs. informational vs. security vs.
  access management), modeled on a real production GLPI export's own 5-type scheme, generalized and
  cross-checked against standard ITIL/ServiceNow closure-code practice.
- `FollowupLibraryBuilder`: 5 general-purpose `ITILFollowupTemplate` rows (deliberately different
  names from `WaitReasonBuilder`'s own reason-specific ones, so the two libraries never collide).
- `ValidationTemplateBuilder`: 5 `ITILValidationTemplate` rows — one linked to the "Validation
  comité (2/3)" `ValidationStep` if it exists (Sprint 25/26), by name lookup, never guessed.
- `TaskTemplateBuilder`: 3 reusable checklist `TaskTemplate` rows (onboarding, offboarding,
  preventive maintenance), each resolving its `TaskCategory` by name against whatever
  `TaskCategoryBuilder` created — same independent-resolution pattern `ServiceCatalogBuilder` uses
  against `CategoryBuilder`.
- `ChangeProblemTemplateBuilder`: one default `ChangeTemplate` (`content` + `impact` mandatory —
  risk assessment before approval is Change Management's defining ITIL practice) and one default
  `ProblemTemplate` (`content` mandatory), assigned to every profile via
  `glpi_profiles.changetemplates_id`/`problemtemplates_id`. Unlike `TicketTemplateBuilder`'s
  simplified/complete split, only one template each — confirmed in `glpi_profilerights` that
  GLPI's own Self-Service profile has zero rights on Change/Problem by default, so the "base user
  vs. staff" split doesn't apply.
- Two new wizard steps ("Tâches & solutions", "Suivis, validations & modèles") with live previews,
  each toggle independently controllable — no bundling into a shared switch (Sprint 26's lesson).

#### Verified, not built
- Sources des demandes (`RequestType`): GLPI ships 6 sensible defaults out of the box
  (Helpdesk/E-Mail/Phone/Direct/Written/Other) — confirmed on a fresh install, nothing to add.

#### Studied, deliberately not built
- Règles métier tickets/changements/problèmes (`RuleTicket`/`RuleChange`/`RuleProblem`) and
  `RuleSoftwareCategory`: routing/prioritization logic is inherently organization-specific;
  inventing arbitrary rules would be worse than not building them (same reasoning as the "level 2"
  reassignment left out of Sprints 27/28).
- Dictionnaires (`RuleDictionary*`): confirmed by listing the real page that they cover exclusively
  asset/inventory normalization (software, manufacturers, models/types, OS), nothing Assistance-
  related. Nothing to normalize on a fresh GLPI with no inventory yet, and no real messy data to
  calibrate starter regex rules against — would be guessing. Replacement-field syntax confirmed in
  `RuleAction.php` (`#0`, `#1`... for capture groups) if the topic returns with real examples.

Validated against the real GLPI 11.0.8 test instance: confirmed in DB the exact counts (14 task
categories, 3 task templates correctly linked to their categories, 5 solution types with the exact
designed visibility flags, 10+1 solution templates — the +1 being `WaitReasonBuilder`'s pre-existing
one, no collision —, 5+2 followup templates same reasoning, 5 validation templates, 1 Change + 1
Problem template each alongside GLPI's own native "Default" row, all 8 profiles correctly pointing
at the new templates rather than the native one). Local suite green (phpunit 10/10, phpstan clean,
php-cs-fixer clean).

## [0.11.0] - 2026-08-11

Sprint 28: SLA/OLA escalation levels — a configurable-threshold priority escalation before a
TTO/TTR deadline is breached, closing ROADMAP item 4 (partially — see scope note below). No
breaking changes.

### Sprint 28 — SLA/OLA escalation levels before breach (2026-08-11)

Closes ROADMAP item 4 (partially — see scope note), the escalation-level engine `SlaBuilder.php`'s
own docblock flagged from the start as "a distinct, considerably heavier feature to build later if
actually needed." Confirmed via GLPI source this sprint that it's real ITIL standard practice, not
a luxury: warn/escalate before a TTO/TTR deadline is breached, not just record that it happened.

#### Added
- `SlaBuilder` now creates one `SlaLevel` per priority tier's resolution (TTR) SLA — and, if OLA is
  enabled, one matching `OlaLevel` on the OLA TTR — firing a configurable percentage of the delay
  before the deadline (`sla_escalation_threshold_percent`, default 75%) and raising the ticket's
  priority one step. The already-highest priority tier gets no escalation level (nothing higher to
  escalate to). Confirmed in GLPI source: `Ticket.php` automatically queues the first level
  (`SlaLevel::getFirstSlaLevel()` / `(new SLA)->addLevelToDo()`) whenever an SLA/OLA is assigned to
  a ticket, and the native `slaticket`/`olaticket` CronTasks that process due levels are active by
  default — no extra wiring needed, same "just works once created" pattern as this class's own
  `RuleTicket` rows.
- New "Escalade automatique avant échéance" control on the existing SLA wizard step (step 4) —
  deliberately placed outside both the shared-SLA and per-client-SLA sections so it stays visible
  and applies either way, rather than only in one of the two modes.
- Deliberately out of scope, same reasoning as Sprint 27's `RuleRightBuilder`: reassigning the
  ticket to a "level 2" support group — this wizard has no way to know an org's real support-tier
  group names, and inventing fictional ones would be worse than not building it. Only the priority
  is escalated.

Validated against the real GLPI 11.0.8 test instance with an existing 3-SLM setup (1 shared + 2
per-client, from earlier sprints' test data): confirmed in DB 15 `SlaLevel` + 15 `OlaLevel` rows
(5 priority tiers × 3 SLMs, skipping the highest tier each time), each with the exact
`execution_time` expected from its own TTR/OLA-TTR hours at the chosen threshold, and each
`SlaLevelAction`/`OlaLevelAction` correctly assigning the next priority up. Confirmed the new
control renders and stays functional in both shared and per-client SLA modes. Local suite green
(phpunit 10/10, phpstan clean, php-cs-fixer clean).

## [0.10.0] - 2026-08-11

Sprint 27: scaffolds LDAP/AD-driven entity+profile assignment (`RuleRight`) per site, closing the
last unaddressed item from the real-GLPI-export audit (ROADMAP item 6, partially — see scope note
in the entry below). New wizard step, no breaking changes.

### Sprint 27 — LDAP/AD rights scaffolding per entity (2026-08-11)

Closes ROADMAP item 6 (partially — see scope note below), the last unaddressed item from the
real-GLPI-export audit. Confirmed in the export: 37 `RuleRight` rows, GLPI's native mechanism for
auto-assigning a user's entity + profile from their AD/LDAP group membership at import/sync time
(`RuleRightCollection`, fires automatically on every LDAP import/update — no extra wiring needed
once the rows exist, same "just works once created" behavior as `SlaBuilder`'s `RuleTicket` rows).
Two patterns were present in the export: (1) one rule per physical site (leaf entity) assigning a
fixed profile, and (2) an org-wide function-based profile (e.g. "Finance"/"DSI" AD groups → a
global profile regardless of site). Scoped to pattern (1) only, per explicit user confirmation —
the AD group names are never reused from the export, only the *shape* of the rule.

#### Added
- `RuleRightBuilder`: creates one `RuleRight` per leaf entity in the tree (not every node at every
  depth — matches the real pattern of one rule per physical site, the leaves of a Client > Site
  tree). Criteria match both `_groups_id` and `memberof` (`OR`-ed, `PATTERN_CONTAIN`) against an
  admin-supplied naming template containing the literal placeholder `{ENTITY}` (default
  `GLPI_{ENTITY}`) — same belt-and-braces matching as the audited export, so the rule still fires
  on a user's very first LDAP sync before GLPI's own group sync has caught up. The assigned
  profile is a single admin-picked choice (`ldap_rights_profile`, validated against GLPI 11's 8
  native profile names) applied to every generated rule.
- New wizard step 12 ("Droits LDAP"): master toggle, group-name template input, profile dropdown,
  and a live preview (JS, walking the same `window.cgaTree` state the entity-tree editor in step 2
  already mutates) listing every leaf entity next to its resulting AD group name — updates as the
  admin edits the template or the tree.
- No-op entirely without a multi-site entity tree (mono-entité) — nothing to scaffold without at
  least one site. Deliberately *not* added to `ConfigurationProfile`'s suggested-defaults baseline
  (unlike every other Sprint 14-25 toggle): LDAP usage isn't a universal good practice independent
  of org size, it depends on infrastructure the wizard has no way to know about.

Validated against the real GLPI 11.0.8 test instance against an existing 2-client/4-site entity
tree: wizard preview correctly listed all 4 leaf sites (not the 2 intermediate client nodes) with
the live-edited template substituted; confirmed in DB 4 `RuleRight` rows with the correct
`entities_id`/`profiles_id` actions and `_groups_id`/`memberof` `OR`-matched criteria against the
custom group names. Local suite green (phpunit 10/10 — added `RuleRightBuilderTest` covering the
pure `preview()` helper, phpstan clean, php-cs-fixer clean).

## [0.9.0] - 2026-08-11

Sprint 26: splits the "Réglages généraux" step's single all-or-nothing toggle into 6 independently
configurable groups, following direct user feedback that the bundled design didn't actually let the
admin choose what to enable. No new features — a configurability/UX fix on top of Sprint 18-25's
existing settings. Includes a DB migration (existing installs keep their prior on/off state).

### Sprint 26 — split "Réglages généraux" into 6 independently-configurable groups (2026-08-11)

User feedback after seeing the actual wizard UI for step 10: Sprint 18 through Sprint 25 had all
been folded into a *single* `general_settings_enabled` toggle, so an admin who wanted the
satisfaction survey but not the committee validation step (or any other such combination) had no
way to say so — accept the whole bundle or reject the whole bundle. Confirmed by screenshot: one
checkbox controlling 10 unrelated settings, with a flat bullet list underneath and no way to opt
out of any single item.

#### Changed
- `general_settings_enabled` replaced by 6 independently-gated groups, each its own checkbox in
  step 10 with its own description list: **Interface & ergonomie** (button layout, search
  form/pagination position, homepage tickets widget), **Notifications** (master activation +
  the 5 ticket-lifecycle events from Sprint 25, including the `auto_reminder` fix),
  **Informations financières** (`auto_create_infocoms`), **Statuts des tâches de projet**
  (`ProjectState` mapping), **Enquête de satisfaction**, **Validation comité**. A "Tout
  sélectionner" convenience checkbox (indeterminate when only some groups are checked) replaces
  the old single master toggle without bringing back the all-or-nothing behavior.
- `GeneralSettingsBuilder::apply()` now applies each group only if its own `Config` field is on,
  instead of one `general_settings_enabled` gate around everything.
- `ConfigurationProfile::getSuggestedDefaults()` suggests all 6 groups on for every non-minimal
  profile (same "universal good practice" reasoning as before) — the admin can still uncheck
  individual ones in step 10.

#### Migration
- Existing installs: the 6 new `glpi_plugin_configurationglpiauto_configs` columns are backfilled
  from the old `general_settings_enabled` value (read before the column is dropped) so an instance
  that had it on keeps every group on rather than silently losing them.

Validated against the real GLPI 11.0.8 test instance: reinstalled to run the migration, confirmed
in DB the old column's value (1) backfilled onto all 6 new columns, then confirmed the old column
was actually dropped. Playwright: step 10 renders all 6 groups checked with "Tout sélectionner"
checked; unchecking two groups makes it indeterminate; the recap step's summary line reflects the
partial count. End-to-end: reset `auto_create_infocoms`/`use_notifications`/`show_search_form` to
0 and deleted the "Validation comité" row, ran the wizard with only Informations financières and
Validation comité unchecked, confirmed those two stayed off/absent in DB while the other 4 groups'
settings were (re)applied — proving the groups are genuinely independent, not just relabeled. Local
suite green (phpunit 5/5, phpstan clean, php-cs-fixer clean).

## [0.8.0] - 2026-08-11

Sprint 25: notification, satisfaction survey, and validation good-practice defaults — including a
real fix to Sprint 24's automatic follow-ups, which were silently never emailed to the requester.
Closes the real-GLPI-export audit's full priority list. No breaking changes.

### Sprint 25 — notifications, satisfaction survey, committee validation (2026-08-11)

Last item out of the real-GLPI-export audit priority order, bundling the ROADMAP's three remaining
items (7: notification templates, 8: validation workflow, 10: satisfaction survey). All three
turned out to be small "flip GLPI's own good-practice defaults" fixes — like Sprint 18's general
settings — rather than needing new object trees, so folded into `GeneralSettingsBuilder` under the
existing `general_settings_enabled` toggle instead of adding new wizard steps for genuinely minor
settings.

#### Fixed
- **A real bug in Sprint 24's own automation**: `glpi_notifications` (`Ticket`, `auto_reminder`)
  ships `is_active = 0` — confirmed in source that this is the *exact* notification
  `PendingReasonCron` fires for `WaitReasonBuilder`'s automatic follow-ups
  (`NotificationEvent::raiseEvent('auto_reminder', ...)`). Without it, those follow-ups were being
  added to the ticket internally but the requester was never actually emailed — silently defeating
  half the point of the feature. Also enables `Ticket`/`update`, `Ticket`/`add_document`,
  `Change`/`add_document`, `Problem`/`add_document` (all `is_active = 0` by default) —
  `KnowbaseItem` new/update/delete notifications deliberately left alone (a content-management
  concern, not core ticket-lifecycle communication).

#### Changed
- Native satisfaction survey (`Entity.inquest_config`/`inquest_rate`) enabled at the root entity for
  both Ticket and Change: confirmed `inquest_rate = 0` by default makes GLPI treat it as fully
  disabled regardless of `inquest_config`'s value. Only the built-in single-question survey (1-5
  stars + optional comment) is turned on — a richer multi-question survey (like the one in the
  audited production export) needs an external tool via `inquest_config = TYPE_EXTERNAL` +
  `inquest_URL`, which depends on which third-party tool the org uses, so left out of scope.
- New "Validation comité (2/3)" `ValidationStep` (67% required) alongside the single "Validation"
  (100%) row GLPI ships by default — for multi-approver committee decisions, a real pattern
  confirmed in the audited production export.

Validated against the real GLPI 11.0.8 test instance: ran the wizard, confirmed in DB all 5 target
notifications flipped to `is_active = 1`, the root entity's satisfaction survey fields, and both
validation steps present with the correct percentages. Local suite green (phpunit 5/5, phpstan
clean, php-cs-fixer clean).

## [0.7.0] - 2026-08-11

Sprint 24: wait reasons with automatic follow-up and resolution (`PendingReason`), closing the
third item out of the real-GLPI-export audit. No breaking changes.

### Sprint 24 — wait reasons with auto-followup and auto-resolve (2026-08-11)

Third item out of the real-GLPI-export audit priority order (after French holidays and the Service
Catalog): `glpi_pendingreasons` ships empty on a fresh install, so tickets put "on hold" never get
an automatic reminder or auto-close — they can sit forever waiting on a user who never replies.

#### Changed
- New `WaitReasonBuilder`: creates 4 `PendingReason` rows. Only "Attente de retour utilisateur"
  gets full automation — a follow-up every 2 weeks (`ITILFollowupTemplate`), auto-resolved after 3
  unanswered ones (`SolutionTemplate`). The other 3 ("Attente livraison fournisseur", "Intervention
  planifiée", "Validation interne en attente") stay reminder-only or fully manual — confirmed
  against a real production reference that auto-closing while waiting on a supplier or an internal
  approval would be inappropriate (the org doesn't control that timeline), only the requester-wait
  case is safe to auto-resolve.
- Confirmed GLPI's automation engine (`PendingReasonCron`, crontask
  `pendingreason_autobump_autosolve`) ships **active by default** (`state = 1`, every 30 minutes) —
  nothing else needed to enable it, this sprint only had to create the reasons themselves.
- `followup_frequency` built from GLPI's own `WEEK_TIMESTAMP` global constant (2/1 weeks), matching
  the fixed day/week enum `PendingReason`'s own admin UI restricts this field to
  (`PendingReason::getFollowupFrequencyValues()`) — not an arbitrary seconds value.
- New `wait_reasons_enabled` toggle, new wizard step 8 (right after Statuts) — `STEP_COUNT` 11→12,
  all later steps renumbered.

Validated against the real GLPI 11.0.8 test instance: ran the wizard, confirmed in DB all 4
`PendingReason` rows with the correct `followup_frequency`/`followups_before_resolution`/template
links (and the reverse link from the follow-up template back to its `PendingReason`), and visually
on Configuration > Intitulés > Raisons d'attente. Local suite green (phpunit 5/5, phpstan clean,
php-cs-fixer clean).

## [0.6.0] - 2026-08-11

Sprints 21-23: Urgency/Observers/Location hidden on GLPI's native self-service forms, French
public holidays seeded on the calendar, and a 23-form Service Catalog on GLPI 11's native Form
system (ROADMAP item 5, closing a long-standing gap) — plus a plugin logo and a vendor-neutral
naming/icon follow-up on the catalog. No breaking changes.

### Fixed — Service Catalog: vendor-neutral naming and real icons (2026-08-11)

Direct follow-up to Sprint 23, from user feedback on the real catalog screenshot: one service name
and one category-tree branch name referenced Microsoft (Teams/SharePoint/Microsoft 365) — not every
organization is on Microsoft 365. Separately, every rubric shared the same generic default icon.

#### Changed
- "Microsoft 365 / Workspace" (an `ITILCategory` branch, part of the tree since Sprint 17) renamed
  to "Messagerie & Collaboration"; "Demande d'accès à un espace Teams / SharePoint" (Service
  Catalog) renamed to "Demande d'accès à un espace collaboratif d'équipe". Both are plugin-created
  objects matched by exact name elsewhere in the code, so an already-created row has to be renamed
  in the DB too (`Installer.php` migration), not just in the source constants, or the next wizard
  run would create a duplicate instead of reusing it. Also fixes up the `DropdownTranslation` rows
  for this category — both `name` (only set once at creation) and `completename` (GLPI
  auto-derives this second breadcrumb-path translation from `name`, so it needed its own explicit
  `DropdownTranslation::regenerateAllCompletenameTranslationsFor()` call, not just an UPDATE).
- Each of the 11 Service Catalog rubrics — and every service form within it — now gets a real
  identifying icon (computer for IT & SI, car for Flotte, building for Bâtiment, shield for
  Sécurité, people for RH...) instead of GLPI's generic default. Uses GLPI's own bundled
  illustration catalog (`Glpi\UI\IllustrationManager`, `public/lib/glpi-project/illustrations/
  icons.json`) — no custom SVG import needed. Backfilled via migration for rubrics/forms created
  before this fix, guarded to skip anything already customized by hand.

Validated against the real GLPI 11.0.8 test instance with the real `post-only` (Self-Service)
account: `/ServiceCatalog` screenshot confirms distinct, recognizable icons per rubric and the
vendor-neutral names; DB confirms the renamed `ITILCategory`'s `name` and `completename`
translations both read correctly. Local suite green (phpunit 5/5, phpstan clean, php-cs-fixer
clean).

### Sprint 23 — Service Catalog (ROADMAP item 5) (2026-08-11)

Second item out of the real-GLPI-export audit and the biggest remaining ROADMAP gap: a self-service
catalog, "never done" since the original roadmap. GLPI 11 has an entirely native, dedicated system
for this (`Glpi\Form\Form`/`Question`/`Category`, `/Form/Render/N` — same subsystem
`HelpdeskFormBuilder`, Sprint 21, works with, not the classic `ITILTemplate`).

#### Changed
- New `ServiceCatalogBuilder`: for each selected `category_branches` branch, creates/reuses a
  `Glpi\Form\Category` catalog rubric (same icon/name as `CategoryBuilder`'s branch), then 23
  service forms across 7 branches (IT & SI, Bâtiment, Flotte, RH, Achats, Sécurité, Services
  Généraux) — adapted from the production export, generalized rather than copied verbatim (dropped
  company-specific ones like "télétravail depuis l'étranger").
- Each service form has only Title + Description (`Question`, same minimal philosophy as
  `HelpdeskFormBuilder`) — **no category field shown to the user**. The resulting ticket's category
  is fixed per service via `FormDestinationTicket`'s `ITILCategoryFieldConfig` with the
  `SPECIFIC_VALUE` strategy, pointing at the matching `ITILCategory` `CategoryBuilder` already
  built (resolved by name at build time, not hardcoded IDs — a service whose target category can't
  be resolved, e.g. its branch was deselected, is skipped rather than created without one).
  Confirmed via source (`Glpi\Form\Destination\CommonITILField\ITILCategoryFieldStrategy`) that the
  alternative `LAST_VALID_ANSWER` strategy would instead read from a "Category"-type question —
  deliberately not used, since picking a specific *service* already implies the category.
- `AbstractConfigField::getKey()` (`Toolbox::slugify(static::class)`) used to build the destination
  `config` JSON key at runtime instead of hardcoding the string — matches exactly what GLPI's own
  admin UI produces.
- `Form::add()` already creates a first `Section` and a default `FormDestination` on its own
  (confirmed in `Form::post_addItem()`) — reused instead of hand-building them.
- New `service_catalog_enabled` toggle, a new wizard step 6 (right after Catégories, since it
  depends on that tree already existing) — `STEP_COUNT` 10→11, all later steps renumbered.

Validated against the real GLPI 11.0.8 test instance: ran the wizard, confirmed in DB 23
`glpi_forms_forms` rows across 11 `glpi_forms_categories` rubrics, and each
`glpi_forms_destinations_formdestinations.config` pointing at the correct `specific_itilcategory_id`
(spot-checked 4). **End-to-end test with the real `post-only` (Self-Service) account**: opened
`/Form/Render/7` ("Réinitialisation de mot de passe"), confirmed only Title + Description are shown
(screenshot), submitted it, and confirmed in DB the resulting `Ticket` row landed with
`itilcategories_id` correctly set to "Mot de passe & Réinitialisation" (id 136) — automatically,
with no category ever shown to the user. Local suite green (phpunit 5/5, phpstan clean,
php-cs-fixer clean).

### Sprint 22 — French public holidays on the calendar (2026-08-11)

First item out of the real-GLPI-export audit: `glpi_holidays` ships empty on a fresh install
(confirmed), so SLA/OLA due dates keep counting through a public holiday until an admin adds them
by hand. Seeds the 8 fixed-date French public holidays.

#### Changed
- `CalendarBuilder` now optionally attaches 8 `Holiday` rows (Jour de l'An, Fête du Travail,
  Victoire 1945, Fête nationale, Assomption, Toussaint, Armistice 1918, Noël) to whichever calendar
  it builds — the shared one and any per-client MSP override — via the native `Calendar_Holiday`
  relation. `Holiday.is_perpetual = 1` makes `Calendar::isHoliday()` compare month/day only, so the
  reference year in `begin_date`/`end_date` is arbitrary.
- Deliberately excludes the 3 movable holidays tied to Easter (Lundi de Pâques, Ascension, Lundi de
  Pentecôte) — GLPI's `Holiday` model has no "recompute from Easter" mechanism, only fixed
  month/day recurrence, so a movable date seeded once would silently go stale every year. Documented
  in the wizard rather than seeded and forgotten.
- New `calendar_holidays_enabled` toggle, a sub-option under the existing Calendar step (step 3),
  not a new step. Suggested `true` for every non-minimal profile.

Validated against the real GLPI 11.0.8 test instance: ran the wizard with the toggle on, confirmed
in DB all 8 holidays created with correct `begin_date`/`end_date`/`is_perpetual = 1`, and linked via
`glpi_calendars_holidays` to all 3 calendars present (the shared one plus 2 per-client MSP
overrides) — confirming the per-client path also received holidays. Local suite green (phpunit
5/5, phpstan clean, php-cs-fixer clean).

### Fixed — plugin-list logo (2026-08-11)

The Marketplace plugin-list badge ("CG" initials) isn't driven by `configurationglpiauto.xml`'s
`<logo>` at all — confirmed in `Glpi\Marketplace\View::getPluginIcon()`: GLPI looks for a literal
`logo.png` at the plugin's own root directory and serves it via `/Plugin/{key}/Logo`, regardless of
the manifest. Restored `logo.png` at the plugin root (`misc/logos/logo.png`, added in Sprint 21,
only satisfies the manifest's marketing URL) — both now present. Verified visually on the real test
instance: the real logo now replaces the "CG" badge.

### Sprint 21 — hide Urgency/Observers/Location on GLPI's native self-service forms (2026-08-11)

Real-world testing (a genuine `post-only` Self-Service login, not the Super-Admin preview tab)
caught a gap Sprint 19/20 missed entirely: GLPI 11's actual "Report an issue"/"Request a service"
self-service portal pages don't go through `TicketTemplate` at all — they're built on a separate,
newer form engine (`Glpi\Form\Form`/`Question`, `/Helpdesk`, `/Form/Render/N`). Every
`TicketTemplateHiddenField` configured in Sprint 19/20 had zero effect there, however it was set.

#### Changed
- New `HelpdeskFormBuilder`, a distinct class from `TicketTemplateBuilder` for this distinct GLPI
  subsystem: hides Urgency, Observers, and Location on both native forms (ids matched by name —
  "Report an issue"/"Request a service" — not hardcoded, same defensive convention used
  throughout). ITIL rationale for Urgency specifically: a base user has no visibility into real
  business impact and reliably rates their own issue as urgent — a documented ITSM anti-pattern;
  better decided by the service desk during triage (or derived from category) than self-reported.
  Location and Observers are staff/triage concerns, not something a base user needs to pick.
- `Glpi\Form\Question` has no "always hidden" visibility strategy, only `ALWAYS_VISIBLE`/
  `VISIBLE_IF`/`HIDDEN_IF` (`Glpi\Form\Condition\VisibilityStrategy`). Confirmed in
  `Engine::computeConditions()` that an empty condition list evaluates to `false`; paired with
  `VISIBLE_IF` (which returns that result as-is), an empty condition list is therefore permanently
  hidden — no dummy/always-false condition needed.
- New `helpdesk_form_hide_fields` toggle, a sub-option under step 9 (Modèles de tickets) rather than
  a new step — same screen, since it serves the same "simplify what a base user sees" goal, just
  through a different GLPI mechanism. Suggested `true` for every non-minimal profile.

Also fixed in passing: `configurationglpiauto.xml`'s `<logo>`/`<screenshots>` pointed at
`misc/logos/`/`misc/screenshots/` files that were never added to the repo — 404 since the manifest
was first written, caught by the official `glpi-project/plugin-ci-workflows` manifest validation
(pull_request-only, which is why it never showed up on a plain `dev` push). Removed the broken
screenshot entries; added a real logo (resized 1254×1254 → 512×512, 1.5MB → 297KB) at
`misc/logos/logo.png` and restored the `<logo>` entry.

Validated against the real GLPI 11.0.8 test instance: confirmed via a genuine `post-only`
(Self-Service profile) Playwright login — not the admin preview tab, which doesn't reflect real
self-service rendering — that Urgency/Observers/Location are gone from both
`/Form/Render/1` and `/Form/Render/2`, leaving only Category, User devices, Title, Description,
Attachments. DB confirmed all 6 expected question rows (3 fields × 2 forms) switched to
`visible_if` with empty conditions. Local suite green (phpunit 5/5, phpstan clean, php-cs-fixer
clean).

## [0.5.0] - 2026-08-10

Sprints 5 through 20: arbitrary per-node entity tree, calendar, SLA/OLA (flat and per-priority),
per-client/site overrides, graphic branding, a real topical ticket-category tree (11 selectable
top-level branches, up to 3 levels), element states, GLPI core general settings, and ticket
templates split by profile (minimal for base users, full qualification for staff). No breaking
changes — every addition is an opt-in toggle in the wizard, defaulting off except where a
configuration profile suggests it on.

### Sprint 20 — simplified template refinements: parent-only categories, real SLA/OLA hiding (2026-08-10)

Direct follow-up to Sprint 19, based on user testing of the real hidden-fields admin tab: the
simplified template should keep category access (just restricted to the 11 top-level branches, not
the ~92 leaf categories meant for staff triage) instead of hiding it outright, and "niveaux de
service" (SLA/OLA due dates) needed to actually be hidden — which an earlier version of
`TicketTemplateBuilder` silently failed to do.

#### Fixed
- `TicketTemplateBuilder` previously hand-resolved SearchOption IDs via `Ticket::
  getSearchOptionIDByField()`, and concluded SLA/OLA fields (`slas_id_tto`/`_ttr`, `olas_id_tto`/
  `_ttr`, `time_to_own`, `internal_time_to_own`/`_resolve`) weren't hideable — wrong: they're
  defined in `TicketTemplate::getExtraAllowedFields()` (a different method, on the template class,
  not the ticket class), which a real screenshot of GLPI's own "Champs masqués" tab caught. Rewrote
  to resolve every field through `TicketTemplate::getAllowedFields(true)` instead — the exact same
  authoritative map GLPI's own admin tab is built from — rather than re-deriving lookups by hand.

#### Changed
- Simplified template no longer hides `itilcategories_id` — category stays visible, but
  `CategoryBuilder` now sets `is_helpdeskvisible` only on the 11 top-level branches (`0` on every
  child), which is what GLPI's own ticket-creation category search option filters on for the
  Self-Service interface specifically (`CommonITILObject::rawSearchOptions()`, condition added only
  when `Session::getCurrentInterface() == 'helpdesk'`). A base user now picks a broad theme, not one
  of the ~90 leaf categories.
  - Existing installs: an explicit `ensureNotHidden()` cleanup call removes the now-stale
    `itilcategories_id` hidden-field row a Sprint 19 run would have already created.
- Simplified template now genuinely hides the "Niveaux de service" panel — all 7 SLA/OLA fields —
  in addition to the fields already hidden in Sprint 19.

Also investigated and explained rather than built: restricting which entity a Self-Service user can
select at ticket creation isn't controllable via the ticket-template mechanism — `entities_id` isn't
part of `ITILTemplate`'s hideable-field set at all (confirmed reading `fields_panel.html.twig`: the
entity dropdown is gated only by `is_multi_entities_mode()`, and lists every entity the user's
account has access to). That's an account/entity-assignment concern — ROADMAP item 6
("Droits/profils GLPI par entité"), not yet built.

Validated against the real GLPI 11.0.8 test instance via Playwright: wiped the previously-created
category tree and re-ran the wizard fresh — confirmed in DB all 11 top-level branches have
`is_helpdeskvisible = 1` and all 92 children have `0`; confirmed the simplified template (id 2) has
exactly 20 hidden-field rows (all previously-missing SLA/OLA/duration fields now present, resolved
to the correct SearchOption `num`s) and no `itilcategories_id` entry; profile assignment unchanged
(Self-Service/Read-Only → simplified, all others → complete). Local suite green (phpunit 5/5,
phpstan clean, php-cs-fixer clean).

### Sprint 19 — ticket templates split by profile, project task state mapping (2026-08-10)

Two follow-up items requested right after Sprint 18. First: GLPI ships exactly 3 native
`ProjectState` rows ("New"/"Processing"/"Closed") but leaves the "Statuts des tâches"
unstarted/in-progress/completed bucket mapping unset, so project task progress tracking silently
does nothing — folded into `GeneralSettingsBuilder` as requested ("à ajouter avec les réglages
automatique déjà existant"), not a new step. Second: the ROADMAP's "Templates de tickets" gap,
now unblocked by Sprint 17's categories — user's explicit split: base users (Self-Service,
Read-Only) enter the least possible (title + description), every other profile gets the full
qualification interface (category, urgency, impact, priority, status...).

#### Changed
- `GeneralSettingsBuilder::projectTaskStateMapping()`: matches GLPI's 3 native `ProjectState` rows
  by exact name (not hardcoded IDs — an admin could have reordered/recreated them) and sets
  `projecttask_unstarted_states_id`/`_inprogress_states_id`/`_completed_states_id`. Skipped
  entirely if any of the 3 native names isn't found, rather than writing a guessed ID.
- New `TicketTemplateBuilder`: creates two `TicketTemplate` rows and wires them to GLPI's native
  per-profile override (`glpi_profiles.tickettemplates_id`, confirmed in `Profile.php`) — a
  mechanism independent from, and requested instead of, per-category templates (industry practice
  research: per-category templates are usually a service-catalog concern, not a raw-category one;
  recommended sticking to one template per audience instead, which the user confirmed).
  - "Ticket simplifié (libre-service)": only `content` (Description) mandatory; `itilcategories_id`,
    `urgency`, `impact`, `priority`, `status`, `locations_id`, the 3 date/duration fields, and all
    assignment/observer actor fields are hidden. Assigned to `Self-Service` and `Read-Only`.
  - "Ticket complet (support)": `content`, `itilcategories_id`, `urgency` mandatory; nothing
    hidden. Assigned to every other existing profile (Observer, Admin, Super-Admin, Hotliner,
    Technician, Supervisor by default, but driven by "every profile not in the simplified list",
    not a hardcoded whitelist — a custom/renamed profile still gets the complete template).
  - Field `num`s are GLPI SearchOption IDs, resolved via `getSearchOptionIDByField()` (the same
    method `ITILTemplate::getAllowedFields()` itself uses) rather than hardcoded, except for the
    handful of actor pseudo-fields GLPI's own core hardcodes the same way (`_users_id_assign` etc.).
- New `ticket_template_enabled` toggle, step 9 of the wizard (`STEP_COUNT` 9→10).

Also resolved in passing: a user report of missing icons on a real "État" dropdown turned out not
to be a bug — `$_SESSION['glpi_dropdowntranslations']` is cached at login, so a session started
before the wizard created the translations doesn't see them until the next login. Confirmed via a
fresh Playwright session against the real Computer creation form (`states_id` select2 dropdown):
icons render correctly once the session is fresh.

Validated against the real GLPI 11.0.8 test instance via Playwright: reset the 3
`projecttask_*_states_id` keys to 0, reran the wizard, confirmed they resolve to the correct
native `ProjectState` IDs. Ticket templates: confirmed in DB the exact expected SearchOption `num`s
on both templates (14 hidden fields on the simplified one, 3 mandatory on each), profile
assignment (`Self-Service`→2, `Read-Only`→2, all others→3), and — on the real central ticket
creation form as Super-Admin — Description/Catégorie/Urgence show as mandatory (red asterisk) with
nothing hidden, matching the "complete" template. Local suite green (phpunit 5/5, phpstan clean,
php-cs-fixer clean).

### Sprint 18 — GLPI core general settings (2026-08-10)

User feedback with screenshots on GLPI's own general-settings pages: several core defaults are
unhelpful out of the box (notifications off entirely, merged action buttons, search/pagination
below results instead of above, no financial info by default, no new-ticket homepage widget). All
6 screenshot-shown settings implemented (2 mentioned explicitly in text plus 4 others visible in
the screenshots — notifications counts as 3 distinct `glpi_configs` keys, for 8 total).

#### Changed
- New `GeneralSettingsBuilder`: writes straight through GLPI core's own
  `\Config::setConfigurationValues('core', [...])` (not raw SQL) so the write goes through GLPI's
  normal cache/session invalidation. Referenced with a leading `\` throughout — this plugin has its
  own `Config` class in the same namespace, so a bare `Config::` call here would resolve to the
  wrong class.
- 8 `glpi_configs` (context `core`) keys set: `use_notifications`, `notifications_mailing`,
  `notifications_ajax` (the 3 notification sub-toggles), `timeline_action_btn_layout` →
  `Config::TIMELINE_ACTION_BTN_SPLITTED` (split Répondre/Observation/Solution buttons instead of
  merged), `show_search_form`, `search_pagination_on_top`, `show_jobs_at_login`,
  `auto_create_infocoms`. Keys and their matching French labels confirmed by grepping GLPI's own
  Twig setup templates (`setup_notifications.html.twig`, `preferences_setup.html.twig`,
  `assets_setup.html.twig`), not guessed.
- New `general_settings_enabled` toggle (`Config`), instance-wide, not per-entity/per-client —
  unlike calendar/SLA these settings don't vary by org size, same reasoning already applied to
  categories/states, so it's suggested `true` for every non-minimal profile.
- Step 8 (Réglages généraux) added to the wizard: single master toggle plus a read-only list of
  what gets changed (all-or-nothing, since `GeneralSettingsBuilder::apply()` itself is
  all-or-nothing — no granular sub-toggles for 8 keys that all point the same direction).
  `STEP_COUNT` 8→9, Récapitulatif renumbered 8→9 with a new "Réglages généraux" recap line.

Validated against the real GLPI 11.0.8 test instance via Playwright: confirmed all 8 keys read `0`
before running the wizard, ran the wizard with the new step's toggle checked, confirmed all 8 keys
read `1` afterward via direct DB query — including `timeline_action_btn_layout = 1`, matching
`Config::TIMELINE_ACTION_BTN_SPLITTED`. Flash message includes "Réglages généraux GLPI appliqués."
Local suite green (phpunit 5/5, phpstan clean, php-cs-fixer clean).

### Sprint 17 — real topical category tree, replacing the ITIL-type one (2026-08-10)

Sprint 16's "one category per ITIL type" (Incidents/Demandes/Problèmes/Changements) turned out to
be pointless: `Ticket` already has a native `type` field for Incident/Demande, and Problem/Change
are already their own GLPI object types, not ticket sub-types — a category per type duplicated a
distinction GLPI already makes elsewhere. Replaced with what the user actually needed: a real
topical category tree (IT & SI, Bâtiment & Moyens Généraux, Flotte Automobile, RH, Achats,
Sécurité, Services Généraux, Administratif, Communication, Qualité, Maintenance Industrielle), up
to 3 levels deep, ~92-115 categories depending on which of the 11 top-level branches are selected.

#### Changed
- `CategoryBuilder` rewritten: recursive tree builder (`itilcategories_id` already supports
  arbitrary parent/child nesting, same mechanism as `Entity` — reused `EntityBuilder`'s recursion
  pattern) instead of 4 flat rows. Every category gets all 4 `is_incident`/`is_request`/
  `is_problem`/`is_change` flags — the category doesn't decide which ticket type it's usable for,
  that's the orthogonal, native concern the old version wrongly conflated with categorization.
- `category_branches` (JSON, new `Config` field): each of the 11 top-level branches is
  independently selectable — an organization without a vehicle fleet or industrial maintenance
  doesn't have to end up with those branches. All 11 selected by default on a fresh install/
  profile suggestion (trim what doesn't apply, easier than re-checking from nothing).
- Icons (`category_icons_enabled`, same mechanism as `State` from Sprint 16 — a
  `DropdownTranslation` on `fr_FR`/`name`, since GLPI renders that value as escaped plain text,
  never HTML) only on the two levels the user actually gave an emoji for; leaf bullet items never
  had one in the original list.
- Confirmed with the user: parenthetical text in the original list (e.g. "Accessoires (Dock USB-C,
  Webcam, Casque, Clavier, Souris, Batterie)") is example/guidance text for the admin, not a 4th
  tree level — becomes each node's `comment` field instead, keeping the tree at the stated 3
  levels (N1/N2/N3).
- Step 5 (Catégories) gets one checkbox per top-level branch plus an icon toggle, both feeding a
  read-only recursive preview (new Twig macro, `_self.category_tree()`) — same "point of entry,
  not final" philosophy as every other step in this wizard.
- Migration hides (`is_helpdeskvisible = 0`, not deleted — `ITILCategory` has no soft-disable
  flag, and these are real objects that could already have tickets attached) the 4 old root
  categories from Sprint 16 on upgrade, restricted to exact-name matches at the root so nothing an
  admin created themselves gets touched.

Validated against the real GLPI 11.0.8 test instance via Playwright: 11 branch checkboxes render,
unchecking a branch hides its preview subtree live, icon toggle shows/hides preview icons; on
submit with 2 of 11 branches unchecked, exactly 92 categories were created (none from the excluded
branches) with the correct 3-level `itilcategories_id` hierarchy — "Accessoires" confirmed at
level 3 with its parenthetical content correctly landing in `comment`, not a level-4 category.
Screenshot of Configuration > Intitulés > Catégories ITIL compared directly against the requested
structure. Local suite green (phpunit 5/5, phpstan clean, php-cs-fixer clean).

### Sprint 16 — ticket categories (ITIL types) and element states (2026-08-10)

Two more items from the completeness audit, requested explicitly with a precise reference list for
the second one: ITIL-typed ticket categories, and the 14 asset/element states GLPI ships with
*none* of by default (confirmed: `glpi_states` is empty on a fresh 11.0.8 install — a genuine gap,
not a cosmetic one). Neither is a per-entity/per-client concept like calendar/SLA — a category
tree or a status list means the same thing across the whole instance, so both are instance-wide
(`entities_id => 0`, `is_recursive => 1`), with no per-client wizard panel needed.

#### Added
- `CategoryBuilder`: 4 starting `ITILCategory` rows (Incidents/Demandes/Problèmes/Changements),
  one per GLPI's native `is_incident`/`is_request`/`is_problem`/`is_change` flags — a defensible
  universal starting point (unlike the entity tree, ITIL's 4 base types aren't business-specific),
  admin renames/extends natively in GLPI afterward.
- `StateBuilder`: the 14 states, each with the exact name/comment provided, plus:
  - **Visibility** (`DropdownVisibility` rows) — confirmed by reading `State::post_getFromDB()`
    that a visibility field defaults to *not visible* unless an explicit row says otherwise (the
    all-visible default only applies to GLPI's own blank "add new state" form), so only the ~9
    itemtypes that should show "Oui" (Computer, Phone, SoftwareLicense, Line, Contract, Unmanaged,
    Monitor, Peripheral, Printer) need a row — not the full ~30-itemtype list with "Non".
  - **Icons**, gated behind a "state_icons_enabled" checkbox, stored as a `DropdownTranslation`
    (fr_FR, field `name`) — never on the `name` field itself, per instruction. Caught during
    validation: the translation `value` renders as *escaped plain text* in GLPI's UI, not HTML —
    an `<i class="ti ...">` tag showed up literally instead of rendering, so the icon had to
    become a plain Unicode emoji prepended to the name instead, not markup. Fixed before this
    reached the user.
- Wizard grows two steps (5 "Catégories", 6 "Statuts des éléments"; Personnalisation/Récapitulatif
  shift to 7/8), each with a read-only preview (categories: name + ITIL type; states: name, with
  the icon shown/hidden live as the icon checkbox is toggled) before anything is created.
- `ConfigurationProfile::getSuggestedDefaults()`: both features suggested on for every non-minimal
  profile — unlike calendar/SLA they don't vary by org size or business model.

Validated against the real GLPI 11.0.8 test instance via Playwright: both preview lists render
correctly (4/14 rows), finish flash message confirms creation, and in the database: 4
`glpi_itilcategories` with the right flags, 14 `glpi_states` with the exact names/comments, 126
`glpi_dropdownvisibilities` rows (9 × 14, matching the intended "Oui" grid) and 28
`glpi_dropdowntranslations` rows (GLPI auto-mirrors `name` into `completename`). Screenshot of
Configuration > Intitulés > Statuts des éléments compared directly against the reference — emoji
icons render correctly next to plain-text names, matching what was asked. Local suite green
(phpunit 5/5, phpstan clean, php-cs-fixer clean).

### Sprint 15 — OLA, the internal commitment behind the SLA (2026-08-10)

Following a completeness audit against ITIL/ISO27001/GLPI best practices (requested by the user —
see ROADMAP.md "Audit de complétude"): OLA (Operational Level Agreement) is the internal
commitment between the helpdesk and support teams that has to be met *before* the external SLA
deadline for the SLA to actually be kept (e.g. SLA "resolve within 4h" to the customer ⇒ internal
OLA "tier 1 triage within 30min, tier 2 diagnosis within 2h"). Confirmed in GLPI core: `OLA`
extends the same `LevelAgreement` base class as `SLA`, `glpi_olas` has the identical schema to
`glpi_slas`, and — the key finding — OLA attaches to the *same* `SLM` container as SLA (same
`slms_id`), so one "Niveau de service" naturally carries both. `RuleTicket` already had
`olas_id_tto`/`olas_id_ttr` as valid actions alongside `slas_id_tto`/`slas_id_ttr`.

#### Added
- `Config` gains `ola_enabled`/`ola_tiers` (same 6-priority-level shape as `sla_tiers`), plus a
  tighter `DEFAULT_OLA_TIERS` starting point (OLA has to land before its paired SLA). Per-client
  override (`settings.sla`, Sprint 13/14) gains `ola_enabled`/`ola_tiers` as sibling keys rather
  than a separate `settings.ola` object — an OLA only ever exists attached to its client's SLA/SLM,
  so nesting it separately would just be two objects that always have to agree on which client
  they belong to.
- Step 4's shared section gets an "Ajouter des engagements internes (OLA)" toggle under the SLA
  table, revealing a second 6-row table (only shown/meaningful when SLA itself is enabled — OLA
  doesn't stand alone in this plugin's model). The per-client SLA panel gets the same, inside its
  "custom" block.
- `SlaBuilder::buildSlm()` now creates OLA rows in the *same* SLM as SLA when enabled — no second
  container class needed. `getOrCreateSla()` generalized to `getOrCreateLevelAgreement(string
  $class, ...)`, serving both `SLA::class` and `OLA::class` (identical schema, only the class
  differs). `assignOne()` adds `olas_id_tto`/`olas_id_ttr` `RuleAction`s onto the *same* rule that
  already assigns the SLA for that priority/entity — still 6 rules per entity, not 12.

Validated end-to-end against the real GLPI 11.0.8 test instance via Playwright: enabled shared
SLA+OLA with distinct values, confirmed the 6-row OLA table renders and the finish flash message
mentions both; in the database, the same `slms_id` carries both SLA and OLA rows, and each of the
6 rules for the test entity carries all 4 actions (`slas_id_tto`, `slas_id_ttr`, `olas_id_tto`,
`olas_id_ttr`). Created a real ticket with priority=Majeure via the entity's helpdesk form and
confirmed it received both the correct SLA ids *and* the correct OLA ids — the first sprint to
verify SLA and OLA together against an actual ticket, not just the database rows the wizard
produces. Local suite green (phpunit 5/5, phpstan clean, php-cs-fixer clean).

### Sprint 14 — SLA per priority level, not one flat delay for everything (2026-08-10)

Confirmed by research and the user (Sprint 13): real ITSM practice defines SLAs per ticket
priority, not one flat "prise en charge/résolution" pair for every ticket regardless of severity.
GLPI itself has 6 native priority levels (`CommonITILObject::getPriorityName()`, Très basse=1
through Majeure=6, computed from an instance-wide urgency×impact matrix) and documents assigning
SLAs via a `RuleTicket` matching on `priority` — the same mechanism `SlaBuilder` already used to
match on `entities_id`.

#### Changed
- `sla_tto_hours`/`sla_ttr_hours` (flat ints) replaced by `sla_tiers` (JSON, one
  `{tto_hours, ttr_hours}` pair per GLPI priority level 1-6). Migration reads the old singleton's
  flat value first and seeds all 6 levels with it, so upgrading doesn't silently lose the existing
  setting.
- Step 4's shared section is now a 6-row table (one per priority, labelled via GLPI's own
  `getPriorityName()` so it respects the instance's language) instead of two number fields.
- Per-client SLA panel (Sprint 13) gains a "Utiliser le SLA par défaut" checkbox, checked by
  default: checked → this client follows the shared table, nothing stored for it (same
  no-override-means-shared principle as Sprint 13). Unchecked → its own 6-row table, pre-filled
  from the shared table's current values as a starting point rather than blank fields.
- `SlaBuilder` now builds 12 `glpi_slas` rows per SLM (6 levels × TTO/TTR) and one `RuleTicket`
  per (entity × priority level) instead of one flat pair and one rule per entity —
  `getOrCreateSla()`'s uniqueness key gained `name` since 6 TTO rows now share the same
  `slms_id`+`type`.

#### Fixed
- **SLA assignment rules never actually fired on a real ticket, since this plugin started
  creating them (Sprint 6/7) — not something introduced this sprint.** `SlaBuilder::assignOne()`
  created each `RuleTicket` without `is_recursive => 1`; GLPI's `RuleCollection` only evaluates a
  rule for its own `entities_id` (root, 0 by default) unless it's marked recursive, so it was
  silently skipped for every ticket created in any sub-entity — the only kind of entity this
  plugin ever assigns an SLA to. Every previous sprint's validation checked that the
  `RuleTicket`/`RuleCriteria`/`RuleAction` rows existed in the database with the right values, but
  never actually created a real ticket to confirm GLPI's rule engine assigned anything — this
  sprint's end-to-end Playwright check (create a ticket with a known priority, read back
  `slas_id_tto`/`slas_id_ttr`) is what caught it. Existing rule rows from earlier sprints are
  fixed by a migration (`$DB->update` on `is_recursive`), new ones are created correctly.

Validated against the real GLPI 11.0.8 test instance via Playwright: shared table renders 6 rows
with correct priority labels; a client with "Utiliser le SLA par défaut" unchecked correctly
pre-fills from the shared table and its own override for one level (Majeure, set to 99h/199h)
lands in the database exactly as entered (12 distinct `glpi_slas` rows, 6 `RuleTicket` rows with
`entities_id` + `priority` criteria); a ticket created directly via the entity's helpdesk form
with priority=Majeure correctly received `slas_id_tto`/`slas_id_ttr` matching that override — the
first time this plugin's SLA assignment has been verified against a real ticket rather than just
the database rows the wizard produces. Local suite green (phpunit 5/5, phpstan clean, php-cs-fixer
clean).

### Sprint 13 — calendar and SLA can differ per site/client (2026-08-10)

Documented as a known gap in ROADMAP.md since Sprint 11: multi-entity mode built exactly one
shared calendar and one shared SLA for the whole tree, whether the admin picked "plusieurs sites
d'une même entreprise" or "plusieurs entreprises clientes" — in reality every client/site tends to
have its own opening hours and its own service commitment.

#### Added
- Steps 3 (Calendrier) and 4 (SLA) each get a "différent par site ou client" toggle, shown for
  any multi-entity mode (not just MSP — widened from the original plan after user feedback: a
  same-company multi-site org can just as reasonably want per-site hours). OFF (default): exactly
  today's shared behavior, untouched. ON: one panel per top-level tree node (client/site), each
  with its own enabled/days/hours (calendar) or enabled/TTO/TTR/astreinte (SLA) — a site with no
  override falls back to the shared calendar/SLA, unchanged from before this sprint.
- No new database column: the override lives inside the existing `entity_tree` JSON column, as an
  optional `settings.calendar`/`settings.sla` object on top-level nodes only (see
  `Config::sanitizeTree()`) — reuses the tree's existing single-hidden-field sync mechanism
  instead of a second data channel that would need to stay aligned with the tree by index.
  `_entity_structure_fields.html.twig` exposes `window.cgaTree` (live reference) and a
  `cga:tree-changed` DOM event so steps 3/4 can react when a client is added/renamed/removed in
  step 2 without a tighter coupling between the two scripts.
- `CalendarBuilder`/`SlaBuilder` gain `buildFromOverride()` (same logic as `build()`, from a
  per-client settings array instead of `$config->fields`, named after the client) and `assignMap()`
  (a different calendar/SLA per entity instead of one for all of them) — the existing `build()`/
  `assignToEntities()` are untouched, still the shared-path default.
- `front/wizard.php`'s finish handler now pairs each `EntityBuilder::build()` result with its
  matching top-level tree node (same index) to pick override vs. shared per client — the shared
  calendar/SLA is still built eagerly up front (not lazily), so which pairing every non-overridden
  client falls back to no longer depends on iteration order.

Validated against the real GLPI 11.0.8 test instance via Playwright (ephemeral
`mcr.microsoft.com/playwright` container on the `docker-compose.test.yml` network, no browser
installed on the host): built a 2-client MSP tree, gave each client its own calendar hours and SLA
hours, left a third client on the shared settings, submitted, and confirmed in the database that
each overridden client got its own `glpi_calendars`/`glpi_slms` row with the right hours and the
right entity → calendar linkage, while the non-overridden client correctly pointed at the shared
"Horaires standard" calendar.

Also researched (web search, sources in ROADMAP.md) and confirmed with the user: real ITSM
practice defines SLAs per ticket priority (P1-P4), not one flat delay for everything, and GLPI's
own documented mechanism for this — a `RuleTicket` matching on `priority` — is the same primitive
`SlaBuilder` already uses to match on `entities_id`. Deliberately not built in this sprint (would
mean redesigning the SLA data model mid-sprint); captured as the next one in ROADMAP.md instead,
along with a "SLA par défaut vs personnalisé par client" design sketch from the user.

### Sprint 12 — plain-language profiles, no acronyms (2026-08-10)

Testing Sprint 11 surfaced two more issues, both fixed here:

1. Step 1 still listed PME/ETI/MSP as unexplained acronyms — not usable by someone who doesn't
   already know GLPI/business jargon, and the plugin's whole point is to make GLPI configuration
   approachable for novices, not just professionals.
2. Once framework-vs-size was untangled in Sprint 11, PME/ETI/Grande entreprise started returning
   *byte-for-byte identical* suggested defaults — three acronym-labeled options that silently did
   the same thing is worse than clutter, it's actively misleading.

#### Changed
- `ConfigurationProfile::getTypes()` down to 4 plain-French options, no acronyms: "Installation
  simple", "Plusieurs sites ou services (une seule entreprise)", "Plusieurs entreprises clientes
  (infogérance)", "Personnalisé". Each now has a short `description` shown under its label in the
  wizard (e.g. "Un seul site, pas de sous-structure").
- Install/upgrade migration deactivates the old `sme`/`eti`/`enterprise` rows (same
  deactivate-don't-delete approach as Sprint 11's `iso27001`/`itil` cleanup) and renames/inserts
  rows for the surviving 4 types.
- Removed `front/config.php` + `templates/config_form.html.twig`: a second, older single-page
  settings screen (entity mode + tree only, no calendar/SLA/branding/profile) that predates the
  wizard and was still wired to the "configure" wrench icon on Configuration > Plugins — landing
  there instead of the wizard is what "je n'ai qu'un truc dans le paramétrage du plugin" was about.
  `Hooks::CONFIG_PAGE` in `setup.php` now points at `front/wizard.php`, same as the main menu
  entry — a single coherent entry point regardless of how the admin gets there.

#### Fixed
- **`ConfigurationProfile::getSuggestedDefaults('msp')` never actually applied its own SLA
  override.** `['entity_mode' => ...] + $goodPracticeBaseline + ['sla_tto_hours' => 1, ...]` — PHP's
  `+` operator keeps the *left* array's value on a key collision (unlike `array_merge()`), so once
  `$goodPracticeBaseline` had already set `sla_tto_hours` to 4, the trailing `+ [...]` override was
  silently discarded. MSP was suggesting the generic 4h/48h SLA with astreinte off instead of its
  intended 1h/8h with astreinte on. Caught by the Playwright validation added this sprint (see
  below) — switched to `array_merge()`.

Validated with a Playwright script run via an ephemeral `mcr.microsoft.com/playwright` container
joined to the `docker-compose.test.yml` network (no browser installed on the host) against the
real GLPI 11.0.8 test instance: logged in, confirmed the plugin's main menu entry and the
"configure" wrench icon on Configuration > Plugins both open the wizard directly, confirmed the 4
profile labels/descriptions render as expected, confirmed picking "Plusieurs entreprises clientes"
sets `entity_mode=multi_msp` + astreinte checked + SLA 1h/8h, and picking "Plusieurs sites ou
services" sets `entity_mode=multi_same_company` + astreinte unchecked + SLA 4h/48h. Local suite
green (phpunit 5/5, phpstan clean, php-cs-fixer 0 files).

### Sprint 11 — profiles are a size choice, not a framework choice (2026-08-10)

Sprint 10 (below) made profile choice pre-fill later steps, but conflated two different
questions: `getSuggestedDefaults()` treated ITIL and ISO 27001 as if they were org sizes on the
same footing as PME/ETI/Grande entreprise/MSP. Proof it never made sense: `'itil'` and
`'enterprise'` returned *exactly* the same values (same entity mode, same calendar, same SLA
2h/24h) — a coincidence that only happens when a distinction was never really implemented. ITIL
and ISO 27001 are practice frameworks any organization can follow regardless of size, not a size
category — a small company can be ISO 27001 certified, a large one might follow no formal
framework at all.

#### Changed
- `ConfigurationProfile::getTypes()` drops `'iso27001'`/`'itil'` — back to 6 profiles (minimal,
  sme, eti, enterprise, msp, custom). A calendar-scoped SLA *is* the ITIL/ISO27001 baseline, so
  every non-minimal profile now suggests one by default instead of only the "advanced" ones.
- Install/upgrade migration deactivates (`is_active = 0`, not deleted) any existing `iso27001`/
  `itil` profile rows so they stop appearing in the wizard without losing data.
- `ConfigurationProfile::getSearchURL()` now points the plugin's main admin menu entry straight
  at the wizard instead of the generic profile CRUD list (`front/profile.php`) — that list wasn't
  useful as a landing page, the wizard is the actual point of entry.

#### Added
- New `sla_astreinte` setting (wizard step 4): on-call/standby coverage outside opening hours.
  GLPI treats `SLM.calendars_id = 0` as "no calendar" = 24/7 countdown (confirmed in core
  `SLM.php`) — the same mechanism the codebase already used by accident when no calendar existed;
  `SlaBuilder` now uses it deliberately when astreinte is enabled, instead of the built business
  calendar. MSP profile suggests astreinte on by default (round-the-clock contractual coverage is
  characteristic of that business model, not of being "bigger") — every other profile suggests it
  off.
- `ROADMAP.md`: documented two follow-up gaps found while testing this — calendar hours are a
  single begin/end pair applied to every checked day (no per-day hours, no lunch-break split), and
  "multi-entité même entreprise" vs "MSP" have zero behavioral difference today (verified nothing
  in `EntityBuilder`/`CalendarBuilder`/`SlaBuilder`/`BrandingBuilder` reads `entity_mode` besides
  which wizard radio pre-checks) — a real MSP distinction needs per-client calendar/SLA/branding
  and entity rights isolation.

Validated: local suite green (phpunit 5/5, phpstan clean, php-cs-fixer 0 files, `php -l` clean).
Migration verified against the real GLPI 11.0.8 test instance — `iso27001`/`itil` rows correctly
deactivated, `sla_astreinte` column present, plugin reactivates with no errors in
`files/_log/php-errors.log`. Full click-through validated via Playwright in Sprint 12 below (which
is also where that pass caught the MSP astreinte bug this sprint had actually shipped).

### Sprint 10 — Profile choice actually does something (2026-08-10)

Step 1 of the wizard ("Quel profil correspond le mieux à votre organisation ?") has always said
picking a profile would "pré-remplir les prochaines étapes" — until now that was aspirational
text, the choice was only stored, nothing downstream read it.

#### Added
- `ConfigurationProfile::getSuggestedDefaults(string $type): array` — per profile type, a
  starting point for entity mode + calendar + SLA (e.g. "Installation minimale" suggests
  mono-entité and nothing else; "MSP" suggests multi-entité MSP with a tight 1h/8h SLA;
  "ISO 27001" suggests the same org shape as ETI/Enterprise but a much tighter 1h/4h SLA, since
  fast incident acknowledgement is the point of that profile). Deliberately never touches
  `entity_tree` itself — no realistic way to guess real client/site names — only the mode, so
  the admin still builds their own tree in step 2. "Personnalisé" returns no suggestions.
- Picking a profile radio in the wizard now live-applies its suggestions to steps 2-5's fields
  (entity mode, calendar toggle/days/hours, SLA toggle/hours) — a starting point, not a lock-in;
  every field stays a normal input the admin can still change in later steps.

Validated with Playwright against a real GLPI 11.0.8 instance: picking "MSP" set entity_mode to
multi_msp, enabled the calendar, and set SLA to 1h/8h as expected; switching to "Installation
minimale" afterward correctly switched entity_mode back to mono.

## [0.4.0] - 2026-08-10

The arbitrary entity tree editor, plus repo hygiene: branch protection on `main`, and the CI
pipeline actually passing green for the first time (see Sprint 8's entry below for the fixes) —
including reconciling with three Dependabot dependency-update PRs opened once
`.github/dependabot.yml` started working (`actions/checkout` v4→v7, `codecov/codecov-action`
v3→v7, `phpstan/phpstan` ^1.10→^2.2, `squizlabs/php_codesniffer` ^3.7→^4.0), all verified
locally before merging rather than accepted blind.

### Sprint 9 — Arbitrary entity tree, not just uniform levels (2026-08-10)

The entity-structure step's data model changed from "N levels, same shape repeated under every
top-level name" to a genuinely arbitrary tree: any node can have any number of children, at any
depth, independent of its siblings — e.g. "Client A" has 6 children and one of those has 3
children of its own while another has 2, and "Client B" has none.

#### Added
- `Config::getEntityTree()`/`entity_tree` column (JSON, replaces `entity_levels`/`level_labels`/
  `top_level_names`): an array of `{name, children}` nodes, recursively. `prepareInput()`
  sanitizes the whole tree server-side (trims names, drops empty-named nodes, caps depth at
  `MAX_LEVELS`) regardless of what the client sends.
- `_entity_structure_fields.html.twig` rewritten as a real recursive tree editor: each node is a
  row (name input, "+" add-child button, "×" remove button) with its own children indented
  beneath; a top-level "+" adds another root node. The whole tree is serialized to one hidden
  `entity_tree_json` field on every change (rather than kept in sync via indexed input names,
  which would need renumbering siblings on every add/remove at an arbitrary depth). The live
  preview now renders the *exact* tree directly — no more "A"/"B" illustrative approximation,
  since the real shape is always fully known now.
- `EntityBuilder::build()` rewritten to walk the tree recursively; `describe()`/`topEntityIds()`
  updated to the new per-top-level-node result shape (`{name, entities_id, count}`).

Validated by building the exact asymmetric structure from the feature request — one client with
two sub-entities, only one of which has further sub-sub-entities — confirmed correct in
`glpi_entities` (`Entité racine > client 1 > sous test 1-1 > {sous sous test 1-1-1, sous sous
test 1-1-2}`, sibling `sous teste 1-2` with none, siblings `client 2`/`client 3` with none).

### Sprint 8 — SLA step, and CI was never actually green (2026-08-10)

#### Added
- New wizard step "Niveaux de service (SLA)" (now 6 steps, between Calendrier and
  Personnalisation): optional toggle + time-to-own/time-to-resolve delays (hours), creating a
  real GLPI SLM ("SLA standard") with two SLA entries under it.
- `SlaBuilder` (`src/SlaBuilder.php`). Unlike Calendar (a direct `Entity::calendars_id` field),
  GLPI has no per-entity "default SLA" field — confirmed by reading `Entity.php`: the
  `slas_id_tto`/`slas_id_ttr` fields live on `glpi_tickets`, only ever set by the business-rules
  engine. So `assignToEntities()` creates a real `RuleTicket` per entity ("entity is X" →
  "assign these SLAs on ticket creation") instead of an (impossible) Entity update — an initial
  version wrongly assumed a direct Entity field, caught before shipping by actually reading the
  core source rather than guessing from the Calendar precedent.
- Validated as strongly as this plugin's features get: not just a DB read of the created
  `RuleTicket`/`RuleCriteria`/`RuleAction` rows, but creating a **real `Ticket`** in the target
  entity and confirming GLPI's own rules engine auto-populated `slas_id_tto`/`slas_id_ttr` with
  the exact SLA IDs `SlaBuilder` created.

#### Fixed — CI trigger, and the licence-header check's own reference file
- `continuous-integration.yml`/`locales-sync.yml` watched a branch named `develop` in their
  `push`/`pull_request` filters; this repo's actual working branch (established Sprint 1) is
  `dev` — CI had never triggered on a single `dev` push before this, only caught by pushing and
  finding no run at all in `gh run list`, rather than a failed one.
- `tools/HEADER` (used by the reusable GLPI CI workflow's licence-header check, not a check of
  my own) was a fully-formatted PHP `/** ... */` comment block — but the tool that reads it
  (`glpi-project/tools`' `licence-headers-check`) treats the file as **plain text** and wraps it
  itself per file type (`/** */` for PHP, `{# #}` for Twig, `#` for YAML), matching a stripped
  line-by-line comparison against each file's actual header. A fully-formatted reference file
  made every single file compare as "outdated" against itself, and `--fix` (before this was
  understood) wrapped the existing header inside a *second* one, corrupting several files with
  an unterminated comment (caught by `php -l` before it was committed, reverted, redone
  correctly). Root cause confirmed by reading the tool's own comparison source, not guessed.

#### Fixed — the CI pipeline had never actually run successfully, on any commit
Every push (including every release tag so far) failed CI; nothing had surfaced this because
this plugin's actual releases are validated by the separate, real `release.yml` workflow, not
by `continuous-integration.yml`. Root causes, all inherited from the original fictional
scaffold and never exercised before now:
- `composer.json`'s dev-only `vimeo/psalm`/`rector/rector`/`phpmd/phpmd` were never actually
  used by anything (only `phpstan`/`php-cs-fixer` are real, working checks) — worse,
  `vimeo/psalm` pulls in `amphp/amp` as a transitive dependency, which **fatally conflicts**
  with GLPI core's own `amphp/amp` copy the moment both get autoloaded in the same PHP process
  (`Cannot redeclare Amp\delay()`), breaking the reusable `glpi-project/plugin-ci-workflows`
  job outright — every other job in the workflow depends on it, so nothing downstream ever ran.
  Removed all three; `.github/workflows/continuous-integration.yml`'s `psalm`/`rector` jobs
  removed to match.
- `phpstan.neon` had `paths: []` (from an earlier fix that scoped out all GLPI-dependent code
  without leaving anything in) — PHPStan itself errors on an empty `paths` list rather than
  analysing nothing. Added `tests/Unit` (see below) as a real, non-empty, analysable path.
- `.php-cs-fixer.php`'s large hand-written rule list referenced half a dozen renamed/nonexistent
  PHP-CS-Fixer option and rule names (e.g. `trailing_comma_in_singleline`,
  `native_type_declaration_spacing`, `braces.position_after_functions`,
  `cast_spaces.spacing`, `function_declaration.closure_fn_spacer`) — every one a hard error, not
  a style violation. Also had `array_syntax`/`list_syntax` set to `'long'`, which would have
  silently rewritten this entire codebase's `[]` arrays to `array()` the first time anyone ran
  `--fix` instead of `--dry-run`. Replaced with a much smaller, verified-correct rule set.
- The `validation` job's "Check for duplicates" step ran `composer dups`, not a real Composer
  command (removed); its `dependabot` job ran `composer audit` with no prior `composer install`
  step, which needs a lockfile/installed packages to audit against (added the missing install
  step); its YAML validation used yamllint's strict defaults (80-char lines, mandatory `---`,
  no `on:` truthy keys) against real GitHub Actions files that violate all three by convention
  — added `.yamllint.yml` relaxing exactly those three, which is standard practice for
  repos with Actions workflows, not a weakening of real checks. `.github/dependabot.yml` had a
  fictional `npm` ecosystem entry (no `package.json`/JS anywhere in this repo) and two invalid
  keys (`pr-priority`, `milestone: 0`) — removed.
- `tests/Unit/EntityBuilderTest.php` + `phpunit.xml.dist`: the `phpunit` CI job had nothing to
  run against (zero GLPI-independent code existed). Added real tests for `EntityBuilder`'s two
  pure static helpers (`describe()`, `topEntityIds()`) — confirmed these genuinely run without
  a GLPI bootstrap, unlike everything else in this plugin.

## [0.3.0] - 2026-08-10

The wizard's branding step (real primary-color customization).

### Sprint 7 — Branding step (2026-08-10)

#### Added
- New wizard step "Personnalisation graphique" (now 5 steps, between Calendrier and
  Récapitulatif): optional toggle + color picker to apply a primary color to the created
  entities' interface, using GLPI's own built-in `Entity::enable_custom_css`/`custom_css_code`
  mechanism — no file writes, no touching GLPI's static assets.
- `BrandingBuilder` (`src/BrandingBuilder.php`): generates a `:root { --tblr-primary: ...;
  --tblr-primary-rgb: ...; }` override and sets it as the target entities' custom CSS.
- `Config` gained `branding_enabled`/`branding_primary_color`, migrated in for existing
  installs.

Validated against a real GLPI 11.0.8 instance, including a visual check (not just a DB read):
after applying a red (`#ff0000`) primary color via the wizard, `.btn-primary`'s actual computed
`background-color` was confirmed `rgb(255, 0, 0)`, and a screenshot shows the "Ajouter" button,
active-menu highlight, and user avatar all rendering in red.

## [0.2.0] - 2026-08-10

Real entity creation, the setup wizard, and the calendar step — see below for the sprint-by-sprint
detail.

### Sprint 6 — Calendar step (2026-08-10)

#### Added
- New wizard step "Calendrier" (now 4 steps: Profil → Entités → Calendrier → Récapitulatif):
  optional toggle to create a real GLPI `Calendar` with one `CalendarSegment` per selected
  weekday (Lun-Ven 08:00-18:00 by default), assigned to every top-level entity the wizard
  created (or to the root entity in mono-entité mode).
- `CalendarBuilder` (`src/CalendarBuilder.php`): idempotent (reuses a calendar of the same
  name, skips a segment that already exists at that day/time).
- `EntityBuilder::build()` return shape changed from a flat name list per branch to
  `['names' => [...], 'entities_id' => int]` so the wizard can hang the calendar off the right
  entity; `EntityBuilder::topEntityIds()` added for that lookup, `describe()` updated to match.
- `Config` gained `calendar_enabled`/`calendar_name`/`calendar_days`/`calendar_begin`/
  `calendar_end`, migrated in for existing installs.

Validated against a real GLPI 11.0.8 instance: enabling the calendar step with Lun/Mar/Mer
09:00-17:00 produced a real `Calendar` row named "Horaires Bureau" with exactly those three
`CalendarSegment` rows, and the mono-entité root entity's `calendars_id` pointing at it (GLPI
normalizes `calendars_strategy` to `0` — "see calendars_id" — for any non-inherited, non-24/7
value; confirmed by reading `Entity::getSpecificValueToDisplay()`'s own resolution logic).

### Sprint 5 — Real, named entity branches (2026-08-10)

#### Added
- Optional "real names" field on the entity-structure step (wizard and standalone settings
  screen): a dynamic add/remove list — client names in MSP mode, first-level entity names in
  same-company mode (e.g. real site names). `EntityBuilder` now creates one full branch per
  name instead of a single generic-labelled template branch; still idempotent (re-applying
  after adding a name only creates what's missing). Leaving the list empty keeps the previous
  behaviour (one generic template branch) unchanged.
- The live preview now renders the *exact* real tree (one line per real name) once names are
  given, instead of the illustrative "A"/"B" two-example approximation — which is now only
  shown while no real names have been entered yet.
- `Config.top_level_names`, migrated in for existing installs.

Validated against a real GLPI 11.0.8 instance: entering three client names in the wizard
(Entreprise Dupont/Martin/Petit) produced exactly three full branches in `glpi_entities`; leaving
the field empty still produces the single generic-template branch as before.

### Sprint 4 — Setup wizard (2026-08-10)

#### Added
- `front/wizard.php` + `templates/wizard.html.twig`: the actual "assistant graphique" from the
  plugin's vision, a 3-step JS-driven wizard (progress bar, Précédent/Suivant, no page reload
  between steps) — Profil (pick a `ConfigurationProfile`) → Entités (the mode/levels/labels
  live-preview screen, reused as-is) → Récapitulatif (summary of both, "Terminer" creates the
  entities for real). Reachable from a new "Lancer l'assistant" button on the profiles list.
- `templates/_entity_structure_fields.html.twig`: the entity-structure fields + live preview
  extracted out of `config_form.html.twig` into a shared partial so the wizard and the
  standalone settings screen (kept for quick later adjustments, per explicit request — the
  wizard isn't the only way in) render and behave identically with one copy of the logic.
- `Config.configurationprofiles_id`: records which profile the wizard's step 1 selected,
  migrated in for existing installs via `Migration::addField()`.
- Validated end-to-end with Playwright against a real GLPI 11.0.8 instance: full 3-step
  navigation, profile pick, MSP mode with 2 custom level labels, summary correctly reflecting
  every choice, and "Terminer" producing exactly `Client > Site > Departement` in
  `glpi_entities` with `configurationprofiles_id` saved.

#### Fixed
- `ConfigurationProfile::find()` in `front/wizard.php` was called with `['sort_order' => 'ASC']`
  as the order argument — `CommonDBTM::find()` passes `$order` straight through as GLPI query
  builder `ORDERBY` criteria, which expects a list of `"field ASC"` strings, not an associative
  array. The associative form silently ordered by the literal column name `ASC` (which doesn't
  exist), 500ing with `Unknown column 'ASC' in 'ORDER BY'`. Fixed to `['sort_order ASC']`.
- GLPI caches compiled Twig templates under `files/_cache` — edits to `.html.twig` files are not
  picked up automatically on the test image, which briefly made it look like the extracted
  `_entity_structure_fields.html.twig` partial wasn't being included. Documented in
  `docker-compose.test.yml`: clear `files/_cache` after any template change.

### Sprint 3 — Apply the entity structure for real (2026-08-10)

#### Added
- `EntityBuilder` (`src/EntityBuilder.php`): turns a saved `Config` into real GLPI `Entity`
  records, matching the settings screen's live preview shape exactly — mono-entité creates
  nothing (the GLPI root entity already is the single entity), multi-entité (same company)
  creates one template chain (one entity per configured level), multi-entité (MSP) nests that
  same chain under a "Client" placeholder entity. Idempotent: re-applying after tweaking a
  level's label reuses existing entities instead of duplicating them.
- "Enregistrer et créer les entités" button on the settings screen (`front/config.php`), next
  to the existing "Enregistrer" (save-only), with a confirmation prompt since it creates real
  data. Validated against a real GLPI 11.0.8 instance: applying `multi_same_company` with
  levels `Site`/`Service` created exactly `Entité racine > Site > Service` in
  `glpi_entities`, confirmed a second identical apply created zero duplicates.

## [0.1.0] - 2026-08-10

First real release. Nothing before this tag ever installed — see the historical note below.

### Sprint 2 — Entity structure settings (2026-08-10)

#### Added
- `Config` (`src/Config.php`): plugin-wide settings screen (Configuration > Plugins > wrench
  icon), a single settings row. First setting: the entity structure the future entity-creation
  wizard will build — mono-entité, multi-entité (same company), or multi-entité (MSP managing
  several client companies) — with a configurable number of sub-entity levels (up to 5) and a
  label per level. Does not create any `Entity` yet; only records the shape for the wizard.
- `front/config.php` + `templates/config_form.html.twig`: settings form (Bootstrap
  card/form-check style, same visual language as remise-glpi/glpi-vulnerability-manager) with a
  live, client-side tree preview that re-renders on every change (mode switch, level count,
  level labels) with no page reload or server round-trip — validated interactively with
  Playwright against a real GLPI 11.0.8 instance.
- `Profile::RIGHT_CONFIG` (`plugin_configurationglpiauto_config`): dedicated right for this
  settings screen, granted to Super-Admin by default, same registration pattern as
  `RIGHT_PROFILE`.

### Note on the [1.0.0] entry below

The `[1.0.0] - 2026-08-07` entry that used to be here described a fully-featured release. It did
not correspond to the actual state of the repository: on 2026-08-10 the codebase consisted almost
entirely of documentation and CI scaffolding — `composer.json` referenced non-existent packages,
`phpstan.neon` pointed at files that didn't exist, and every class referenced by `setup.php` /
`hook.php` beyond a handful was never written, making the plugin impossible to install. Sprint 1
(below) rebuilds real, validated foundations from that point. The entry is kept below, relabeled,
for history rather than deleted outright.

### Sprint 1 — Infra & first real entity (2026-08-10)

#### Added
- Real plugin bootstrap (`setup.php`/`hook.php`) following the modern GLPI 11 plugin functions
  convention (`plugin_init_*`, `plugin_version_*`, `plugin_*_install/uninstall`,
  `plugin_*_check_prerequisites/check_config`), validated against a real GLPI 11.0.8 instance.
- `ConfigurationProfile` (`src/ConfigurationProfile.php`): the first real, working entity — a
  catalog of predefined configuration profiles (Installation minimale, PME, ETI, Grande
  entreprise, MSP, ISO 27001, ITIL, Personnalisé), with full CRUD (list, add, edit, delete) via
  `front/profile.php` / `front/profile.form.php`.
- `Installer` (`src/Install/Installer.php`): creates the plugin's schema and seeds the eight
  default profiles on install; drops it cleanly on uninstall.
- `Profile` (`src/Profile.php`): registers a dedicated `plugin_configurationglpiauto_profile`
  right in GLPI's standard profile matrix, granted to Super-Admin by default.
- `docker-compose.test.yml`: local GLPI + MariaDB stack for manual/CI-independent validation.

#### Fixed
- `composer.json` referenced `glpi-project/glpi` (not a real Packagist package — GLPI core is
  provided by the host instance, not a Composer dependency) and mis-named/irrelevant dev tooling
  (`rectorphp/rector`, `php-compatibility/php-compatibility`, `nunomaduro/larastan` — a Laravel
  tool with no relevance to a GLPI plugin). Fixed to only require what actually resolves.
- `phpstan.neon` included a non-existent stub file and a non-existent
  `vendor/glpi-project/tools/phpstan/glpi.php`. Rescoped to GLPI-independent code only (none
  exists yet, same constraint documented on the sibling glpi-vulnerability-manager plugin), and
  the CI workflow's PHPStan step updated to match.
- Namespacing the profile entity under `...\Entity\ConfigurationProfile` triggered GLPI's
  automatic table-name derivation to treat the `Entity` segment as GLPI's own core `Entity`
  class, producing a bogus many-to-many relation table name
  (`glpi_plugin_configurationglpiauto_entities_configurationprofiles`) instead of
  `..._profiles`, and fatally breaking install. Fixed by flattening the namespace and overriding
  `getTable()` explicitly — same lesson already documented on glpi-vulnerability-manager.
- Reusing GLPI's core `config` right for the profile CRUD screens 403'd on "Ajouter" for the
  built-in super-admin: `config` only ever grants READ/UPDATE (it models a per-entity singleton),
  never CREATE/PURGE. Fixed by introducing a dedicated plugin right (see `Profile` above).
- The `itemlink` search-option datatype on the profile's `name` column resolves its target
  itemtype via a reverse table-name lookup (`getItemTypeForTable()`), which fails for any class
  with a manually-overridden `getTable()` (see above) — surfaced as a 500
  (`Class name must be a valid object or a string` in `SQLProvider::giveItem()`). Fixed by
  declaring `itemtype` explicitly in the search option instead of relying on the reverse lookup.

---

## [1.0.0] - 2026-08-07 (historical — description did not match the actual code, see note above)

### Added
- First stable release of Configuration GLPI Auto plugin
- All core features as described in the original README
- Complete wizard interface
- All deployment profiles (PME, ETI, Enterprise, MSP, ISO 27001, ITIL)
- All modules (Configuration, Calendars, SLA, Entities, Service Catalog, Templates, etc.)
- Audit mode for existing instances
- Blueprint export/import functionality
- Intelligent locations assistant with geocoding
- Comprehensive security features (dry run, backup, rollback)
- Detailed reporting system

### Technical Features
- Full PSR-12 compliance
- SOLID architecture principles
- Service-oriented design
- Repository pattern for data access
- DTO pattern for data transfer
- Dependency injection
- Centralized configuration
- Complete test coverage
- Internationalization support (French, English)

---

## Template Sections for Future Releases

---

### [Added]
- New features
- New modules
- New profiles
- New integrations

### [Changed]
- Breaking changes
- Behavior changes
- API changes
- Performance improvements

### [Fixed]
- Bug fixes
- Security fixes
- Performance fixes

### [Removed]
- Deprecated features
- Removed functionality
- Breaking changes

### [Security]
- Security vulnerabilities fixed
- Security improvements

### [Deprecated]
- Features that will be removed in future versions

---

## Notes

- **Breaking Changes** are marked with `BREAKING CHANGE:` prefix in commit messages
- **Security Fixes** are marked with `SECURITY:` prefix in commit messages
- **Deprecations** are marked with `DEPRECATED:` prefix in commit messages

---

[Unreleased]: https://github.com/parime/Configuration-glpi-auto/compare/v0.19.0...HEAD
[0.19.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.19.0
[0.18.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.18.0
[0.17.1]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.17.1
[0.17.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.17.0
[0.16.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.16.0
[0.15.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.15.0
[0.14.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.14.0
[0.13.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.13.0
[0.12.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.12.0
[0.11.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.11.0
[0.10.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.10.0
[0.9.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.9.0
[0.8.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.8.0
[0.7.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.7.0
[0.6.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.6.0
[0.5.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.5.0
[0.4.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.4.0
[0.3.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.3.0
[0.2.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.2.0
[0.1.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.1.0
