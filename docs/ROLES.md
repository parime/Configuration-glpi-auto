[🇫🇷 Français](ROLES.md) | [🇬🇧 English](ROLES.en.md)

# Parcours par rôle

Chaque parcours tient en quelques étapes : ce que fait la personne, où elle clique et ce qu'elle
obtient. Les chemins sont ceux de l'interface en français. Le plugin a une seule entrée de menu,
**Configuration > Profils de configuration**, qui ouvre l'assistant ; ses autres pages (Audit de
configuration, Plan d'adressage, Importer depuis Jira, Importer des tickets (CSV)) sont des
sous-pages de cette entrée. Pour le détail des étapes, avec captures, voir le
[tutoriel](TUTORIAL.md) ; pour la logique ITIL de chaque étape, le [guide ITIL](ITIL_GUIDE.md).

Les exemples utilisent des données fictives : une société « Exemple SA » avec deux sites, Lyon et
Nantes, et une boîte de support `support@example.org`.

| Rôle | Ce qu'il fait avec Configuration GLPI Auto | Parcours |
|---|---|---|
| Administrateur GLPI | Installe le plugin et lance l'assistant | [Administrateur](#administrateur-glpi) |
| Responsable du parc | Statuts, lieux, types d'actifs, dictionnaires, contrôles du parc | [Parc](#responsable-du-parc) |
| Responsable du helpdesk | Catégories, catalogue de formulaires, SLA, réception par e-mail | [Helpdesk](#responsable-du-helpdesk) |
| Auditeur de configuration | Vérifie l'instance et suit les écarts | [Auditeur](#auditeur-de-configuration) |

---

## Administrateur GLPI

Objectif : une instance GLPI neuve configurée en une séance, sans rien écrire dans GLPI avant
d'avoir relu le résultat. Première configuration en dix étapes :

1. **Installer** : extraire l'archive de release dans `plugins/` (voir
   [Installation](../README.md#installation)), puis **Configuration > Plugins**, ligne
   « Configuration GLPI Auto », **Installer** puis **Activer**.
2. **Vérifier les droits** : le profil Super-Admin reçoit les droits du plugin à l'installation.
   Pour un autre profil : **Administration > Profils**, ouvrir le profil, onglet **Configuration GLPI
   Auto** : droits **Profils de configuration** (les modèles de l'assistant) et **Configuration**
   (appliquer l'assistant, audit, imports).
3. **Ouvrir l'assistant** : **Configuration > Profils de configuration**. En tête de page, les
   encadrés « Vérification de l'environnement » et « Droits de l'opérateur actuel » signalent ce
   qui limiterait une fonctionnalité ; on peut continuer quand même.
4. **Étape 1, Profil** : choisir le profil le plus proche (Installation simple, Plusieurs sites ou
   services, Plusieurs entreprises clientes, Personnalisé). Pour une petite structure, le bouton
   **Configuration express** applique tout de suite les réglages recommandés du profil.
5. **Étape 2, Structure des entités** : par exemple, une entité par site (Lyon, Nantes) ; l'aperçu
   à droite se met à jour en direct.
6. **Étapes 3 à 5** : lieux et adresses, calendrier (jours fériés du pays), niveaux de service.
7. **Étapes 6 à 19** : la plupart sont facultatives ; le responsable du parc et celui du helpdesk
   peuvent les relire avec vous (voir leurs parcours ci-dessous).
8. **Récapitulatif** (dernière étape, 20 ou 21 selon les plugins actifs) : relire, puis **Terminer**. C'est le seul moment où l'assistant écrit
   dans GLPI. Ce qui existe déjà est réutilisé, rien n'est supprimé, et une sauvegarde de la
   configuration est enregistrée à chaque application.
9. **Contrôler** : ouvrir **Configuration > Profils de configuration > Audit de configuration** et
   traiter les constats les plus graves (voir [Auditeur](#auditeur-de-configuration)).
10. **Relancer plus tard** : l'assistant se relance à tout moment (nouveau site, SLA ajusté) ; seuls
    les éléments manquants sont créés.

## Responsable du parc

Objectif : un inventaire exploitable, où chaque matériel a un statut, un lieu et un nom de
logiciel normalisé.

1. **Étape 3, Lieux** de l'assistant : adresse de chaque site, et lieux enfants (bâtiment, étage,
   salle). Exemple : « Lyon > Bâtiment A > Salle serveurs ».
2. **Étape 8, Statuts des éléments** : cocher les statuts à créer (En stock, Attribué, En panne...).
   Si le plugin assetsign est actif, cocher **Plugin assetsign détecté : relier ses déclencheurs de
   fiches à ces statuts** : un matériel qui passe dans l'un de ces statuts déclenche la fiche à faire
   signer ; un déclencheur déjà réglé dans assetsign n'est jamais modifié.
3. **Étape 6, Catégories de tickets** : les branches dédiées proposent aussi des types d'actifs
   facultatifs (véhicule, serveur, local, sécurité incendie et premiers secours, sécurité physique),
   chacun avec ses propres champs (dates de vérification, garantie...).
4. **Étape 14, Réglages généraux GLPI** : activer, si besoin, l'inventaire natif de GLPI (agents
   GLPI Agent).
5. **Étape 15, Général & Outils** : fabricants et dictionnaires (fabricants, logiciels, systèmes
   d'exploitation), pour qu'un même logiciel n'apparaisse pas sous dix noms différents.
6. **Plan d'adressage** (**Configuration > Profils de configuration > Plan d'adressage**) : coller
   depuis un tableur les réseaux IP et VLAN de chaque site (par exemple `192.0.2.0/24`, VLAN 10,
   site Lyon), vérifier l'aperçu, puis créer les lignes valides. Relancer l'import met à jour au
   lieu de dupliquer.
   Si le plugin Inventaire Docker est actif, une étape **Inventaire Docker** de l'assistant déclare
   ses connexions (Portainer, WUD, Harbor...) ; chacune se teste avant l'enregistrement et rien
   n'est importé tant que vous ne lancez pas le premier import depuis le plugin.
7. **Suivre le parc** : **Audit de configuration**, section **Contrôles du parc** : régler les seuils
   « Matériel qui ne remonte plus à l'inventaire depuis (jours) » et « Garanties qui expirent dans
   (jours) » (0 désactive le contrôle). Dans les constats « Parc », **Créer les tickets** ouvre des
   tickets liés au matériel concerné, **Fusionner** regroupe les fiches de logiciel en double,
   **Classer** range les logiciels sans catégorie. Le matériel lui-même n'est jamais modifié.

## Responsable du helpdesk

Objectif : des demandes bien classées dès leur arrivée, par le portail comme par e-mail.

1. **Étape 6, Catégories de tickets** : choisir les branches utiles (poste de travail, comptes,
   sécurité...), jusqu'à trois niveaux.
2. **Étape 7, Catalogue de services** : l'assistant crée des formulaires natifs GLPI à partir de ces
   catégories. L'utilisateur décrit son besoin en langage courant, le ticket arrive dans la bonne
   catégorie, avec l'urgence, le lieu, l'appareil, les observateurs et l'approbation repris des
   réponses. Les formulaires se retrouvent ensuite dans GLPI (Administration > Formulaires) ; un
   formulaire que vous avez modifié n'est plus jamais touché par l'assistant.
3. **Étapes 5, 9 et 10** : niveaux de service avec escalade entre niveaux de support, raisons
   d'attente avec relance automatique du demandeur, modèles de tickets (simplifié pour les
   demandeurs, complet pour les techniciens).
4. **Étapes 11 et 12** : bibliothèques de tâches, solutions, suivis et validations, prêtes à insérer.
5. **Étape 18, Configuration SMTP** : réglages d'envoi des e-mails de GLPI. Le test d'envoi se fait
   après l'assistant, depuis **Configuration > Notifications**.
6. **Étape 19, Réception des tickets par e-mail** : pour chaque boîte (exemple :
   `support@example.org`, serveur `imap.example.org`, IMAP), choisir l'entité, la catégorie et le
   groupe en charge. Garder cochée l'option de refus des réponses automatiques, pour éviter les
   boucles. La boîte devient un collecteur GLPI (**Configuration > Collecteurs**) **créé désactivé**
   tant que **Activer la relève dès maintenant** n'est pas cochée.
7. Après l'assistant, revenir à l'étape 19 : chaque collecteur enregistré a un bouton **Tester la
   connexion** (GLPI compte les messages sans les relever).
8. **Reprendre l'historique** d'un autre outil : **Importer depuis Jira** (export CSV ou API) ou
   **Importer des tickets (CSV)**, dans **Configuration > Profils de configuration**. Chaque import
   montre un aperçu à blanc avant d'écrire, et un réimport ne crée pas de doublon.

## Auditeur de configuration

Objectif : savoir si l'instance suit les bonnes pratiques, sans risque de la modifier par erreur.

1. **Droits en lecture seule** : sur le profil de l'auditeur, droit **Configuration** du plugin en
   lecture (onglet **Configuration GLPI Auto** du profil) et lecture de la configuration de GLPI.
   Sans droit de modification, la page s'affiche sans aucun bouton de correction.
2. Ouvrir **Configuration > Profils de configuration > Audit de configuration**. L'analyse porte
   sur l'état réel de l'instance, que l'assistant ait servi ou non : comptes par défaut, politique
   de mots de passe, notifications, calendriers, SLA, statuts, catégories, entités sans adresse,
   collecteurs de courriels, réseau, parc.
3. Lire les constats par gravité (**Critique**, **À surveiller**, **Information**), chacun avec sa
   recommandation. Un bouton **Corriger** n'apparaît que lorsque le bon réglage ne fait aucun doute
   et que l'utilisateur a le droit de modifier la configuration.
4. **Rapport périodique par e-mail** : choisir la fréquence (**Hebdomadaire** ou **Mensuel**), puis
   **Modifier le modèle et les destinataires** (notification GLPI) ou **Enregistrer et envoyer
   maintenant**. Le rapport donne le nombre de contrôles réussis, en alerte et en échec, et
   l'évolution depuis le précédent ; il ne contient aucun mot de passe ni secret.
5. Pour un audit ISO 27001 plus large (risques, SoA, audits internes), voir le plugin
   [GRC Manager](https://github.com/parime/glpi-iso27001-management).
