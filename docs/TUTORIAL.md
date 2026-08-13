# Tutoriel — Utilisation de l'assistant

Ce tutoriel parcourt les 18 étapes de l'assistant de configuration, avec une capture d'écran de
chacune. Les captures ci-dessous montrent le profil **« Plusieurs sites ou services »** — les
autres profils (Installation simple, Plusieurs entreprises clientes/MSP, Personnalisé)
pré-remplissent les mêmes étapes différemment, mais la structure du parcours est identique.

À tout moment, vous pouvez revenir en arrière avec **Précédent** sans perdre vos réponses : rien
n'est enregistré dans GLPI avant l'étape 18 (Récapitulatif).

## Étape 1 — Profil

Le point de départ : quatre profils prédéfinis (PME/ETI mono-site, plusieurs sites ou services,
plusieurs entreprises clientes/MSP, ou personnalisé). Le choix pré-remplit la structure d'entités,
le calendrier et les SLA des étapes suivantes avec des valeurs adaptées — tout reste ajustable
ensuite. Un bouton **mode express** permet aussi de valider directement avec les valeurs par
défaut du profil choisi, sans parcourir les 18 étapes une à une.

![Étape 1 — Choix du profil](screenshots/01-profil.png)

## Étape 2 — Entités

Construit l'arborescence d'entités : mono-entité, multi-site (une entreprise, plusieurs
sites/équipes), ou MSP (plusieurs entreprises clientes distinctes). Un nœud par site, profondeur
libre — l'aperçu à droite se met à jour en direct.

![Étape 2 — Structure des entités](screenshots/02-entites.png)

## Étape 3 — Lieux

Optionnel : associez une adresse (rue, ville, coordonnées GPS, alias, téléphone...) à n'importe
quelle entité de l'arborescence construite à l'étape précédente, à n'importe quelle profondeur.
Aucune adresse saisie, aucun lieu créé — comme sur une installation GLPI neuve. Un assistant
d'adresse optionnel (Nominatim/OpenStreetMap) propose des suggestions de rue pendant la saisie une
fois la ville et le pays renseignés. Chaque entité peut aussi avoir des lieux enfants (bâtiment,
étage, salle...), et sa propre fiche adresse native GLPI (Entités > Adresse) peut être complétée en
même temps avec les mêmes coordonnées, plus téléphone/fax/site web/e-mail.

![Étape 3 — Lieux](screenshots/03-lieux.png)

## Étape 4 — Calendrier

Horaires d'ouverture et jours fériés français, utilisés ensuite par les SLA pour calculer les
délais réels (hors horaires non ouvrés).

![Étape 4 — Calendrier](screenshots/04-calendrier.png)

## Étape 5 — SLA

Seuils de résolution par priorité (SLA côté demandeur) et engagements internes (OLA), avec
escalade automatique N1 → N2 → N3 configurable.

![Étape 5 — SLA](screenshots/05-sla.png)

## Étape 6 — Catégories

Arborescence de catégories de tickets sur 3 niveaux, organisée par thème métier (IT, Bâtiment,
RH...). Chaque branche de premier niveau est sélectionnable indépendamment — une organisation sans
flotte automobile ou maintenance industrielle n'a pas à les garder.

![Étape 6 — Catégories](screenshots/06-categories.png)

## Étape 7 — Catalogue de services

Génère un catalogue en libre-service (formulaires GLPI natifs) à partir des catégories
sélectionnées à l'étape précédente : chaque service route automatiquement vers la bonne catégorie,
sans que l'utilisateur final ait à la choisir lui-même.

![Étape 7 — Catalogue de services](screenshots/07-catalogue-services.png)

## Étape 8 — Statuts

Statuts d'éléments du parc (En stock, Affecté, En panne...), avec sélection individuelle de ceux à
créer.

![Étape 8 — Statuts](screenshots/08-statuts.png)

## Étape 9 — Raisons d'attente

Raisons de mise en attente d'un ticket, chacune pouvant déclencher automatiquement un modèle de
suivi et une fréquence de relance.

![Étape 9 — Raisons d'attente](screenshots/09-raisons-attente.png)

## Étape 10 — Modèles de tickets

Deux modèles de ticket assignés automatiquement selon le profil GLPI de l'utilisateur : un
simplifié (titre + description) pour les profils de base, un complet (catégorie et urgence
obligatoires) pour le personnel technique.

![Étape 10 — Modèles de tickets](screenshots/10-modeles-tickets.png)

## Étape 11 — Tâches & solutions

Bibliothèque de gabarits de tâches et de solutions réutilisables, classés par type (assistance,
résolution technique, sécurité, gestion des accès...), avec une salutation personnalisée générée
dynamiquement à partir du demandeur réel du ticket.

![Étape 11 — Tâches & solutions](screenshots/11-taches-solutions.png)

## Étape 12 — Suivis, validations & modèles

Gabarits de suivis (messages de relance/notification réutilisables), étapes de validation
(hiérarchique, technique, comité...), et modèles de changement/problème.

![Étape 12 — Suivis, validations & modèles](screenshots/12-suivis-validations.png)

## Étape 13 — Droits LDAP par entité

Optionnel : règles d'affectation automatique entité + profil par site lors d'une synchronisation
LDAP/AD, plus des règles par fonction/département indépendantes du site (ex. le groupe AD
« Finance » reçoit toujours tel profil, quel que soit le site de l'utilisateur).

![Étape 13 — Droits LDAP par entité](screenshots/13-droits-ldap.png)

## Étape 14 — Réglages généraux GLPI

Bascule des paramètres GLPI natifs recommandés : notifications, tâches automatiques, champs
masqués sur les formulaires en libre-service...

![Étape 14 — Réglages généraux GLPI](screenshots/14-reglages-generaux.png)

## Étape 15 — Général & Outils

Intitulés transverses : fabricants, sources de demande, catégories d'utilisateurs, catégories de
la base de connaissances, rubriques documentaires (classification ISO 27001), évènements de
planning.

![Étape 15 — Général & Outils](screenshots/15-general-outils.png)

## Étape 16 — Projets

Types de projet et de tâche de projet, gabarits de tâches de projet, modèles de projet, catégories
et gabarits d'évènements de planning.

![Étape 16 — Projets](screenshots/16-projets.png)

## Étape 17 — Personnalisation graphique

Couleur principale et logo, appliqués à l'interface GLPI — partagés pour toute l'instance ou
différenciés par client/site en mode multi-entité. Aperçu en direct de l'en-tête GLPI pendant le
réglage. Placée en dernier dans le parcours : c'est la partie la moins importante, purement
esthétique, qui ne doit pas retarder les réglages fonctionnels qui précèdent.

![Étape 17 — Personnalisation graphique](screenshots/17-personnalisation.png)

## Étape 18 — Récapitulatif

Dernière étape avant la création réelle : relit tous les choix des 17 étapes précédentes. Rien
n'est créé dans GLPI avant de valider ici.

![Étape 18 — Récapitulatif](screenshots/18-recapitulatif.png)

---

Pour le détail technique de chaque fonctionnalité (ce qui est généré, pourquoi, et les décisions
prises en cours de route), voir [CHANGELOG.md](../CHANGELOG.md) et [ROADMAP.md](../ROADMAP.md).
