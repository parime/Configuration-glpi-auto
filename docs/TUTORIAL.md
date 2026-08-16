# Tutoriel — Utilisation de l'assistant
*Tutorial — Using the wizard*

Ce tutoriel parcourt les 18 étapes de l'assistant de configuration, avec une capture d'écran de
chacune. Les captures ci-dessous montrent le profil **« Plusieurs sites ou services »** — les
autres profils (Installation simple, Plusieurs entreprises clientes/MSP, Personnalisé)
pré-remplissent les mêmes étapes différemment, mais la structure du parcours est identique.

*This tutorial walks through the configuration wizard's 18 steps, with a screenshot of each. The
screenshots below show the **"Multiple sites or departments"** profile — the other profiles
(Simple install, Multiple client companies/MSP, Custom) pre-fill the same steps differently, but
the flow's structure is identical.*

À tout moment, vous pouvez revenir en arrière avec **Précédent** sans perdre vos réponses : rien
n'est enregistré dans GLPI avant l'étape 18 (Récapitulatif).

*At any point, you can go back with **Previous** without losing your answers: nothing is saved to
GLPI before step 18 (Summary).*

## Étape 1 — Profil
*Step 1 — Profile*

Le point de départ : quatre profils prédéfinis (PME/ETI mono-site, plusieurs sites ou services,
plusieurs entreprises clientes/MSP, ou personnalisé). Le choix pré-remplit la structure d'entités,
le calendrier et les SLA des étapes suivantes avec des valeurs adaptées — tout reste ajustable
ensuite. Un bouton **mode express** permet aussi de valider directement avec les valeurs par
défaut du profil choisi, sans parcourir les 18 étapes une à une.

*The starting point: four predefined profiles (single-site SMB, multiple sites or departments,
multiple client companies/MSP, or custom). The choice pre-fills the entity structure, calendar and
SLAs of the following steps with values suited to it — everything remains adjustable afterwards.
An **express mode** button also lets you submit directly with the chosen profile's default values,
without going through all 18 steps one by one.*

![Étape 1 — Choix du profil](screenshots/01-profil.png)

## Étape 2 — Entités
*Step 2 — Entities*

Construit l'arborescence d'entités : mono-entité, multi-site (une entreprise, plusieurs
sites/équipes), ou MSP (plusieurs entreprises clientes distinctes). Un nœud par site, profondeur
libre — l'aperçu à droite se met à jour en direct.

*Builds the entity tree: single entity, multi-site (one company, several sites/teams), or MSP
(several distinct client companies). One node per site, unlimited depth — the preview on the right
updates live.*

![Étape 2 — Structure des entités](screenshots/02-entites.png)

## Étape 3 — Lieux
*Step 3 — Locations*

Optionnel : associez une adresse (rue, ville, coordonnées GPS, alias, téléphone...) à n'importe
quelle entité de l'arborescence construite à l'étape précédente, à n'importe quelle profondeur.
Aucune adresse saisie, aucun lieu créé — comme sur une installation GLPI neuve. Un assistant
d'adresse optionnel (Nominatim/OpenStreetMap) propose des suggestions de rue pendant la saisie une
fois la ville et le pays renseignés. Chaque entité peut aussi avoir des lieux enfants (bâtiment,
étage, salle...), et sa propre fiche adresse native GLPI (Entités > Adresse) peut être complétée en
même temps avec les mêmes coordonnées, plus téléphone/fax/site web/e-mail.

*Optional: attach an address (street, city, GPS coordinates, alias, phone...) to any entity in the
tree built in the previous step, at any depth. No address entered, no location created — same as
on a fresh GLPI install. An optional address helper (Nominatim/OpenStreetMap) suggests streets as
you type once the city and country are filled in. Each entity can also have child locations
(building, floor, room...), and its own native GLPI address record (Entities > Address) can be
filled in at the same time with the same details, plus phone/fax/website/e-mail.*

![Étape 3 — Lieux](screenshots/03-lieux.png)

## Étape 4 — Calendrier
*Step 4 — Calendar*

Horaires d'ouverture et jours fériés français, utilisés ensuite par les SLA pour calculer les
délais réels (hors horaires non ouvrés).

*Business hours and French public holidays, used afterwards by the SLAs to compute real deadlines
(excluding non-working hours).*

![Étape 4 — Calendrier](screenshots/04-calendrier.png)

## Étape 5 — SLA
*Step 5 — SLA*

Seuils de résolution par priorité (SLA côté demandeur) et engagements internes (OLA), avec
escalade automatique N1 → N2 → N3 configurable.

*Resolution thresholds per priority (requester-side SLA) and internal commitments (OLA), with
configurable automatic L1 → L2 → L3 escalation.*

![Étape 5 — SLA](screenshots/05-sla.png)

## Étape 6 — Catégories
*Step 6 — Categories*

Arborescence de catégories de tickets sur 3 niveaux, organisée par thème métier (IT, Bâtiment,
RH...). Chaque branche de premier niveau est sélectionnable indépendamment — une organisation sans
flotte automobile ou maintenance industrielle n'a pas à les garder.

*A 3-level ticket category tree, organized by business topic (IT, Facilities, HR...). Each
top-level branch can be selected independently — an organization with no vehicle fleet or
industrial maintenance doesn't have to keep those.*

![Étape 6 — Catégories](screenshots/06-categories.png)

## Étape 7 — Catalogue de services
*Step 7 — Service catalog*

Génère un catalogue en libre-service (formulaires GLPI natifs) à partir des catégories
sélectionnées à l'étape précédente : chaque service route automatiquement vers la bonne catégorie,
sans que l'utilisateur final ait à la choisir lui-même.

*Generates a self-service catalog (native GLPI forms) from the categories selected in the previous
step: each service automatically routes to the right category, with no need for the end user to
choose it themselves.*

![Étape 7 — Catalogue de services](screenshots/07-catalogue-services.png)

## Étape 8 — Statuts
*Step 8 — Statuses*

Statuts d'éléments du parc (En stock, Affecté, En panne...), avec sélection individuelle de ceux à
créer.

*Asset statuses (In stock, Assigned, Out of order...), with individual selection of which ones to
create.*

![Étape 8 — Statuts](screenshots/08-statuts.png)

## Étape 9 — Raisons d'attente
*Step 9 — Pending reasons*

Raisons de mise en attente d'un ticket, chacune pouvant déclencher automatiquement un modèle de
suivi et une fréquence de relance.

*Reasons for putting a ticket on hold, each able to automatically trigger a follow-up template and
a reminder frequency.*

![Étape 9 — Raisons d'attente](screenshots/09-raisons-attente.png)

## Étape 10 — Modèles de tickets
*Step 10 — Ticket templates*

Deux modèles de ticket assignés automatiquement selon le profil GLPI de l'utilisateur : un
simplifié (titre + description) pour les profils de base, un complet (catégorie et urgence
obligatoires) pour le personnel technique.

*Two ticket templates automatically assigned based on the user's GLPI profile: a simplified one
(title + description) for basic profiles, a full one (category and urgency mandatory) for
technical staff.*

![Étape 10 — Modèles de tickets](screenshots/10-modeles-tickets.png)

## Étape 11 — Tâches & solutions
*Step 11 — Tasks & solutions*

Bibliothèque de gabarits de tâches et de solutions réutilisables, classés par type (assistance,
résolution technique, sécurité, gestion des accès...), avec une salutation personnalisée générée
dynamiquement à partir du demandeur réel du ticket.

*A library of reusable task and solution templates, classified by type (support, technical
resolution, security, access management...), with a personalized greeting dynamically generated
from the ticket's actual requester.*

![Étape 11 — Tâches & solutions](screenshots/11-taches-solutions.png)

## Étape 12 — Suivis, validations & modèles
*Step 12 — Follow-ups, validations & templates*

Gabarits de suivis (messages de relance/notification réutilisables), étapes de validation
(hiérarchique, technique, comité...), et modèles de changement/problème.

*Follow-up templates (reusable reminder/notification messages), validation steps (hierarchical,
technical, committee...), and change/problem templates.*

![Étape 12 — Suivis, validations & modèles](screenshots/12-suivis-validations.png)

## Étape 13 — Droits LDAP par entité
*Step 13 — LDAP rights per entity*

Optionnel : règles d'affectation automatique entité + profil par site lors d'une synchronisation
LDAP/AD, plus des règles par fonction/département indépendantes du site (ex. le groupe AD
« Finance » reçoit toujours tel profil, quel que soit le site de l'utilisateur).

*Optional: rules for automatically assigning an entity + profile per site during an LDAP/AD sync,
plus site-independent rules per role/department (e.g. the AD "Finance" group always gets a given
profile, regardless of the user's site).*

![Étape 13 — Droits LDAP par entité](screenshots/13-droits-ldap.png)

## Étape 14 — Réglages généraux GLPI
*Step 14 — General GLPI settings*

Bascule des paramètres GLPI natifs recommandés : notifications, tâches automatiques, champs
masqués sur les formulaires en libre-service...

*Toggling of recommended native GLPI settings: notifications, automatic actions, fields hidden on
self-service forms...*

![Étape 14 — Réglages généraux GLPI](screenshots/14-reglages-generaux.png)

## Étape 15 — Général & Outils
*Step 15 — General & Tools*

Intitulés transverses : fabricants, sources de demande, catégories d'utilisateurs, catégories de
la base de connaissances, rubriques documentaires (classification ISO 27001), évènements de
planning.

*Cross-cutting dropdowns: manufacturers, request sources, user categories, knowledge base
categories, document topics (ISO 27001 classification), calendar events.*

![Étape 15 — Général & Outils](screenshots/15-general-outils.png)

## Étape 16 — Projets
*Step 16 — Projects*

Types de projet et de tâche de projet, gabarits de tâches de projet, modèles de projet, catégories
et gabarits d'évènements de planning.

*Project and project task types, project task templates, project templates, calendar event
categories and templates.*

![Étape 16 — Projets](screenshots/16-projets.png)

## Étape 17 — Personnalisation graphique
*Step 17 — Visual customization*

Couleur principale et logo, appliqués à l'interface GLPI — partagés pour toute l'instance ou
différenciés par client/site en mode multi-entité. Aperçu en direct de l'en-tête GLPI pendant le
réglage. Placée en dernier dans le parcours : c'est la partie la moins importante, purement
esthétique, qui ne doit pas retarder les réglages fonctionnels qui précèdent.

*Primary color and logo, applied to the GLPI interface — shared across the whole instance or
differentiated per client/site in multi-entity mode. Live preview of the GLPI header while
adjusting it. Placed last in the flow: this is the least important part, purely cosmetic, and it
shouldn't delay the functional settings that come before it.*

![Étape 17 — Personnalisation graphique](screenshots/17-personnalisation.png)

## Étape 18 — Récapitulatif
*Step 18 — Summary*

Dernière étape avant la création réelle : relit tous les choix des 17 étapes précédentes. Rien
n'est créé dans GLPI avant de valider ici.

*The last step before actual creation: reviews every choice made in the previous 17 steps. Nothing
is created in GLPI before confirming here.*

![Étape 18 — Récapitulatif](screenshots/18-recapitulatif.png)

---

Pour le détail technique de chaque fonctionnalité (ce qui est généré, pourquoi, et les décisions
prises en cours de route), voir [CHANGELOG.md](../CHANGELOG.md) et [ROADMAP.md](../ROADMAP.md).

*For the technical detail of each feature (what gets generated, why, and the decisions made along
the way), see [CHANGELOG.md](../CHANGELOG.md) and [ROADMAP.md](../ROADMAP.md).*
