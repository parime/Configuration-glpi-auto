[🇫🇷 Français](#-français) · [🇬🇧 English](#-english)

## 🇫🇷 Français

**Guide : les pratiques ITIL 4 derrière l'assistant**

Ce guide explique, pratique par pratique, *pourquoi* l'assistant de configuration propose ce qu'il
propose, et *où* dans les 19 étapes chaque pratique ITIL 4 prend forme concrètement. Il ne
remplace pas le [tutoriel pas-à-pas](TUTORIAL.md) (qui montre chaque étape en détail, capture
d'écran à l'appui) : celui-ci répond à « comment utiliser l'assistant », ce guide répond à
« pourquoi ces réglages plutôt que d'autres ». Rien de ce qui suit n'est propre à ce plugin — ce
sont des pratiques ITIL 4 standard ; l'assistant se contente de les traduire en réglages GLPI
concrets plutôt que de laisser un administrateur les redécouvrir à la main.

### Gestion des incidents et des demandes de service

ITIL 4 distingue un **incident** (un service qui ne fonctionne pas comme prévu) d'une **demande de
service** (une demande planifiée, sans panne associée) — deux pratiques différentes, avec des
attentes différentes en termes de délai. GLPI porte cette distinction nativement sur chaque ticket
(champ *Type*).

- **Étape 6 (Catégories)** construit une arborescence de catégories thématiques (Informatique,
  Bâtiment, Flotte automobile, RH...), pas une catégorie par type ITIL : le type Incident/Demande
  est déjà natif sur le ticket, dupliquer cette distinction dans les catégories n'apporterait rien.
- **Étape 7 (Catalogue de services)** route chaque demande de service vers la bonne catégorie
  automatiquement, sans que l'utilisateur final ait à la choisir lui-même — il décrit son besoin
  en langage courant ("Accès à un logiciel", "Nouveau poste de travail"...), l'assistant a déjà
  fait la correspondance.
- **Étape 8 (Statuts)** et **étape 9 (Raisons d'attente)** couvrent le cycle de vie complet d'un
  ticket, y compris la relance et la clôture automatiques quand un ticket reste sans réponse du
  demandeur — une pratique de gestion des incidents courante, pas un simple statut de plus.

### Gestion des niveaux de service (SLA/OLA)

Un **SLA** (Service Level Agreement) est un engagement de délai envers le client final ; un
**OLA** (Operational Level Agreement) est l'engagement interne, entre équipes, qui permet de tenir
le SLA (ex. SLA client "résolu sous 4h" ⇒ OLA interne "niveau 1 trie sous 30 min, niveau 2
diagnostique sous 2h"). ITIL 4 recommande de définir ces deux délais **par niveau de priorité**
(P1 Critique → P4 Mineur), pas un seul délai identique pour tous les tickets.

- **Étape 4 (Calendrier)** définit les horaires ouvrés sur lesquels les délais SLA/OLA sont
  calculés — un ticket ouvert un dimanche ne "consomme" pas de délai avant le lundi matin.
- **Étape 5 (SLA)** définit un couple délai de prise en charge/délai de résolution par niveau de
  priorité, pour le SLA externe et, si activé, l'OLA interne — jamais un seul délai plat pour tous
  les tickets. Une option d'astreinte 24h/24, 7j/7 est disponible pour les organisations qui s'y
  engagent contractuellement (typique de l'infogérance/MSP).
- **Escalade automatique** : si un ticket approche de son échéance (75% du délai écoulé par
  défaut) sans être résolu, il est automatiquement réaffecté au niveau de support suivant (N1 →
  N2 → N3, groupes créés à cette même étape) — une pratique ITSM standard, pas une invention
  propre à ce plugin. Le moteur natif GLPI (`SlaLevel`/`OlaLevel`) s'en charge une fois les
  niveaux créés.

### Gestion des changements et des problèmes

Un **problème** est la cause sous-jacente d'un ou plusieurs incidents ; un **changement** est une
modification planifiée d'un service, avec son propre circuit d'approbation. Les deux ont des
besoins de documentation différents d'un ticket d'incident classique.

- **Étape 11 (Tâches & solutions)** fournit des gabarits de changement et de problème distincts,
  assignés à tous les profils sauf Libre-Service (qui n'a par défaut aucun droit sur ces deux
  types), et une vraie bibliothèque de solutions organisée par taxonomie de clôture ITIL générique
  (résolu / contourné / non reproductible...), pas un champ texte libre.

### Workflow d'approbation (validation)

ITIL 4 distingue une approbation individuelle standard d'une décision collégiale, et recommande
que l'approbation remonte automatiquement à la bonne personne plutôt que d'être assignée à la
main à chaque fois.

- **Étape 12 (Suivis & validations)** ajoute une étape de validation "Comité (2/3)" en plus de
  l'approbation standard (100%), pour les décisions qui nécessitent un accord collégial plutôt
  qu'une seule signature.
- **Étape 14 (Réglages généraux)**, case "Validation automatique par le supérieur hiérarchique"
  route automatiquement une demande vers le supérieur du demandeur (champ natif GLPI
  *Superviseur*) — activée seulement pour les tickets de type Demande, jamais pour les incidents,
  et sans effet si ce champ n'est pas renseigné pour l'utilisateur concerné (aucune validation
  bloquante n'est créée dans ce cas).

### Gestion de la connaissance

ITIL 4 traite la base de connaissances comme un actif à organiser, pas comme une liste plate
d'articles.

- **Étape 15 (Général & Outils)** propose une arborescence de catégories de base de connaissances
  et deux articles de FAQ prêts à l'emploi (catalogue de services, incident vs. demande) — un
  point de départ structuré plutôt qu'une base vide.
- Pour les organisations soumises à ISO 27001 : cette même étape propose des rubriques
  documentaires et des niveaux de criticité pour classer la documentation selon sa sensibilité,
  dès la configuration initiale plutôt qu'ajoutés après coup.

### Enquêtes de satisfaction et amélioration continue

ITIL 4 fait de l'amélioration continue une pratique à part entière, pas une réflexion ponctuelle —
ce qui suppose de mesurer avant de pouvoir s'améliorer.

- **Étape 14 (Réglages généraux)** active l'enquête de satisfaction native de GLPI (1 à 5 étoiles,
  sur chaque ticket clos) — techniquement présente sur une installation neuve, mais avec un taux
  d'échantillonnage à 0% par défaut (donc en pratique jamais envoyée) tant qu'elle n'est pas
  ajustée.
- Cette même étape, case "Tableau de bord ITIL", crée un tableau de bord GLPI natif dédié qui met
  en avant deux indicateurs de conformité SLA que GLPI fournit nativement mais n'affiche sur aucun
  tableau de bord par défaut (tickets en retard, par technicien et par groupe de techniciens), en
  complément du tableau de bord "Assistance" déjà fourni par GLPI. Consultable depuis *Tableaux de
  bord > Tableau de bord ITIL* une fois l'assistant terminé. N'est créé qu'une seule fois : si vous
  personnalisez ensuite ce tableau de bord (widgets déplacés, ajoutés...), relancer l'assistant ne
  l'écrase jamais.

### Ce qui reste hors périmètre

Certaines pratiques ITIL 4 dépendent de choix propres à chaque organisation que l'assistant ne
peut pas deviner à sa place — la gestion de la disponibilité et de la capacité (dépend de
l'infrastructure réelle), la gestion des événements/supervision (dépend de l'outil de supervision
choisi), et une enquête de satisfaction multi-questions plus riche que l'enquête native GLPI
(dépend de l'outil externe choisi, si vous en utilisez un). Ce ne sont pas des oublis : les
inclure demanderait de deviner une réponse à la place de l'administrateur, ce que ce plugin évite
délibérément partout ailleurs aussi.

---

## 🇬🇧 English

**Guide: the ITIL 4 practices behind the wizard**

This guide explains, practice by practice, *why* the configuration wizard suggests what it
suggests, and *where* in the 19 steps each ITIL 4 practice takes concrete shape. It doesn't
replace the [step-by-step tutorial](TUTORIAL.md#-english) (which shows every step in detail, with
a screenshot): that one answers "how to use the wizard", this guide answers "why these settings
rather than others". Nothing below is specific to this plugin — these are standard ITIL 4
practices; the wizard simply translates them into concrete GLPI settings instead of leaving an
administrator to rediscover them by hand.

### Incident and service request management

ITIL 4 distinguishes an **incident** (a service not working as expected) from a **service
request** (a planned request, with no associated failure) — two different practices, with
different delay expectations. GLPI carries this distinction natively on every ticket (the *Type*
field).

- **Step 6 (Categories)** builds a tree of topical categories (IT, Facilities, Fleet, HR...), not
  one category per ITIL type: the Incident/Request distinction is already native on the ticket,
  duplicating it in categories would add nothing.
- **Step 7 (Service Catalogue)** routes every service request to the right category
  automatically, without the end user having to pick it themselves — they describe their need in
  plain language ("Software access", "New workstation"...), the wizard already made the mapping.
- **Step 8 (Statuses)** and **step 9 (Wait reasons)** cover the full ticket lifecycle, including
  automatic follow-up and closure when a ticket sits unanswered by the requester — a common
  incident-management practice, not just another status.

### Service level management (SLA/OLA)

An **SLA** (Service Level Agreement) is a delay commitment to the end customer; an **OLA**
(Operational Level Agreement) is the internal commitment, between teams, that makes the SLA
achievable (e.g. customer SLA "resolved within 4h" ⇒ internal OLA "tier 1 triages within 30 min,
tier 2 diagnoses within 2h"). ITIL 4 recommends defining both delays **per priority level** (P1
Critical → P4 Minor), not a single identical delay for every ticket.

- **Step 4 (Calendar)** defines the business hours SLA/OLA delays are computed against — a ticket
  opened on a Sunday doesn't "consume" any delay until Monday morning.
- **Step 5 (SLA)** defines a response/resolution delay pair per priority level, for the external
  SLA and, if enabled, the internal OLA — never a single flat delay for every ticket. A 24/7
  on-call option is available for organisations contractually committed to it (typical of managed
  service providers).
- **Automatic escalation**: if a ticket approaches its deadline (75% of the delay elapsed by
  default) without being resolved, it's automatically reassigned to the next support tier (N1 →
  N2 → N3, groups created in this same step) — a standard ITSM practice, not something invented
  by this plugin. GLPI's native engine (`SlaLevel`/`OlaLevel`) handles it once the tiers exist.

### Change and problem management

A **problem** is the underlying cause of one or more incidents; a **change** is a planned
modification to a service, with its own approval workflow. Both have documentation needs
different from a plain incident ticket.

- **Step 11 (Tasks & solutions)** provides distinct change and problem templates, assigned to
  every profile except Self-Service (which has no rights on either type by default), and a real
  solution library organised by a generic ITIL closure taxonomy (resolved / worked around / not
  reproducible...), not a free-text field.

### Approval workflow (validation)

ITIL 4 distinguishes a standard individual approval from a collegial decision, and recommends
that approval automatically routes to the right person rather than being assigned by hand every
time.

- **Step 12 (Follow-ups & validations)** adds a "Committee (2/3)" validation step on top of the
  standard (100%) approval, for decisions that need a collegial agreement rather than a single
  signature.
- **Step 14 (General settings)**, "Automatic approval by the requester's line manager" toggle,
  automatically routes a request to the requester's manager (native GLPI *Supervisor* field) —
  only enabled for Request-type tickets, never incidents, and has no effect if that field isn't
  filled in for the user concerned (no blocking approval is created in that case).

### Knowledge management

ITIL 4 treats the knowledge base as an asset to organise, not a flat list of articles.

- **Step 15 (General & Tools)** proposes a knowledge base category tree and two ready-to-use FAQ
  articles (service catalogue, incident vs. request) — a structured starting point rather than an
  empty base.
- For organisations under ISO 27001: this same step proposes documentary sections and criticality
  levels to classify documentation by sensitivity from the initial setup, rather than added as an
  afterthought.

### Satisfaction surveys and continual improvement

ITIL 4 treats continual improvement as a practice in its own right, not an occasional afterthought
— which means measuring before you can improve.

- **Step 14 (General settings)** activates GLPI's native satisfaction survey (1 to 5 stars, on
  every closed ticket) — technically present on a fresh install, but with a 0% sampling rate by
  default (so effectively never sent) until adjusted.
- The same step's "ITIL Dashboard" checkbox creates a dedicated native GLPI dashboard surfacing
  two SLA-compliance indicators GLPI ships natively but shows on no default dashboard (late
  tickets, by technician and by technician group), alongside the "Assistance" dashboard GLPI
  already provides. Available from *Dashboards > ITIL Dashboard* once the wizard is finished.
  Only ever created once: if you later customise this dashboard (widgets moved, added...),
  re-running the wizard never overwrites it.

### What's deliberately out of scope

Some ITIL 4 practices depend on choices specific to each organisation that the wizard can't guess
on your behalf — availability and capacity management (depends on real infrastructure), event
management/monitoring (depends on the monitoring tool chosen), and a richer multi-question
satisfaction survey than GLPI's native one (depends on whichever external tool you use, if any).
These aren't oversights: including them would mean guessing an answer in the administrator's
place, which this plugin deliberately avoids everywhere else too.
