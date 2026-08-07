# a creer

Voici une version ultra-propre, alignée et prête à l'emploi en Markdown, avec tous les éléments demandés, organisés en tableaux clairs et lisibles.

📋 1. GABARITS DE TICKETS
markdown
Copier

  **Nom**                          | **Description**                                                                                     | **Catégorie ITIL**                     | **Type**       | **Priorité** | **Groupe Destinataire** |
 |----------------------------------|-----------------------------------------------------------------------------------------------------|----------------------------------------|----------------|--------------|-------------------------|
 | Demande de nouvel ordinateur    | Commande d’un poste fixe/portable (spécifications, budget, délai).                                | Poste de travail > Portable            | Installation   | Moyenne      | IT & SI / Achats         |
 | Incident imprimante (bourrage)   | Signalement d’un bourrage papier ou problème de toner.                                           | Impression > Incidents matériel        | Incident       | Haute        | IT & SI / Support N1     |
 | Réinitialisation mot de passe    | Réinitialisation de mot de passe (utilisateur, motif).                                            | Comptes & Identités > Mot de passe    | Incident       | Urgente      | IT & SI / Support N1     |
 | Demande d’accès VPN              | Demande d’accès distant (justificatif, durée).                                                    | Réseau > Accès Distant                 | Installation   | Moyenne      | IT & SI / Réseau         |
 | Demande de création de compte    | Onboarding (nom, rôle, accès nécessaires).                                                        | Comptes & Identités > Onboarding      | Installation   | Moyenne      | IT & SI / IAM            |
 | Incident réseau (Wifi)           | Problème de connexion Wifi (local, appareil, erreur).                                             | Réseau > Wifi                          | Incident       | Haute        | IT & SI / Réseau         |
 | Demande de logiciel              | Installation d’un logiciel (nom, version, licence).                                               | Logiciels > Installation              | Installation   | Basse        | IT & SI / Support N2     |
 | Problème téléphone IP            | Dysfonctionnement téléphonique (numéro, symptôme).                                               | Téléphonie > Téléphone IP              | Incident       | Moyenne      | IT & SI / Téléphonie     |
 | Demande de salle de réunion      | Réservation de salle (date, équipement nécessaire).                                              | Bâtiment > Salles de réunion           | Service        | Basse        | Bâtiment / Accueil       |
 | Incident climatisation           | Problème de CVC (local, température).                                                             | Bâtiment > CVC                         | Incident       | Urgente      | Bâtiment / Maintenance   |
 | Demande de carte SIM             | Nouvelle carte SIM (forfait, appareil).                                                          | Téléphonie > Carte SIM                | Installation   | Moyenne      | IT & SI / Téléphonie     |
 | Sinistre véhicule                | Déclaration d’accident (véhicule, date, circonstances).                                          | Flotte Auto > Sinistres              | Incident       | Urgente      | Flotte Auto / Assurance  |
 | Demande de formation             | Demande de formation (thème, participants, budget).                                              | RH > Formation                         | Service        | Moyenne      | RH / Formation           |
 | Commande de fournitures          | Commande de matériel (liste, quantité, budget).                                                 | Achats > Sourcing & Commande         | Service        | Basse        | Achats / Logistique      |
 | Incident sécurité (phishing)     | Signalement d’un email suspect (expéditeur, pièce jointe).                                       | Sécurité SI > Phishing                | Incident       | Urgente      | IT & SI / Sécurité       |
 | Demande de badge d’accès          | Nouveau badge (personne, accès nécessaires).                                                    | Sécurité > Contrôle d’Accès           | Installation   | Moyenne      | Sécurité / Accueil       |




📋 2. GABARITS DE CHANGEMENT
markdown
Copier

  **Nom**                          | **Description**                                                                                     | **Type**      | **Impact** | **Urgence** | **Groupe Responsable** |
 |----------------------------------|-----------------------------------------------------------------------------------------------------|---------------|------------|-------------|-------------------------|
 | Mise à jour logicielle           | Déploiement d’une mise à jour (version, scope, rollback).                                         | Standard      | Moyen      | Non          | IT & SI / Applications  |
 | Migration serveur                | Migration d’un serveur (source, cible, fenêtre de maintenance).                                  | Majeur        | Élevé      | Non          | IT & SI / Infrastructure|
 | Changement mot de passe AD       | Mise à jour des politiques de mot de passe.                                                       | Standard      | Faible     | Non          | IT & SI / IAM            |
 | Remplacement matériel            | Remplacement d’un équipement (raison, nouveau modèle).                                           | Standard      | Moyen      | Oui          | IT & SI / Support N2     |
 | Modification droits d’accès      | Ajustement des droits d’un utilisateur (rôle, justificatif).                                     | Standard      | Faible     | Non          | IT & SI / IAM            |
 | Changement de fournisseur        | Changement de prestataire (service concerné, nouveau contrat).                                  | Majeur        | Élevé      | Non          | Achats / Juridique       |




📋 3. GABARITS DE PROBLÈME
markdown
Copier

  **Nom**                          | **Description**                                                                                     | **Catégorie ITIL**               | **Statut par défaut** | **Groupe Responsable** |
 |----------------------------------|-----------------------------------------------------------------------------------------------------|----------------------------------|----------------------|-------------------------|
 | Problème récurrent imprimante    | Analyse des pannes répétées d’une imprimante.                                                     | Impression > Incidents matériel | Nouveau              | IT & SI / Support N2    |
 | Latence réseau                   | Investigation des problèmes de latence (logs, tests).                                              | Réseau & Connectivité           | En cours             | IT & SI / Réseau         |
 | Bug applicatif                  | Documentation d’un bug (étapes, logs, version).                                                    | Logiciels & Applications        | Nouveau              | IT & SI / Développement |
 | Fuite de données                 | Enquête sur une fuite (scope, données concernées).                                                | Sécurité SI > Compte compromis  | Urgent               | IT & SI / Sécurité       |
 | Panne électrique                 | Panne générale (bâtiment, équipement impacté).                                                    | Bâtiment > Électricité           | En cours             | Bâtiment / Maintenance   |




📋 4. CATÉGORIES ITIL
markdown
Copier

  **Niveau 1**       | **Niveau 2**               | **Niveau 3**               | **Description**                                      |
 |--------------------|----------------------------|----------------------------|------------------------------------------------------|
 | **💻 IT & SI**     | 🖥️ Poste de travail        | Ordinateur fixe            | Demandes et incidents liés aux PC fixes.             |
 |                    |                            | Portable                   | Demandes et incidents liés aux laptops.             |
 |                    |                            | Écran & Affichage          | Problèmes d’écran, calibration, remplacement.         |
 |                    | 🖨️ Impression              | Imprimante / Copieur       | Incidents et demandes liés aux imprimantes.          |
 |                    |                            | Scanner                    | Problèmes de numérisation.                            |
 |                    | 📦 Logiciels & Applications | Installation              | Demandes d’installation de logiciels.                |
 |                    |                            | Bug / Dysfonctionnement   | Signalement de bugs applicatifs.                     |
 |                    | 🟧 Microsoft 365            | Messagerie (Outlook)       | Problèmes liés à Outlook.                             |
 |                    |                            | Collaboration (Teams)      | Problèmes liés à Teams/SharePoint.                   |
 |                    | 👤 Comptes & Identités     | Onboarding                 | Création de comptes utilisateurs.                   |
 |                    |                            | Mot de passe              | Réinitialisation ou blocage de mot de passe.         |
 |                    | 🌐 Réseau & Connectivité   | Wifi                       | Problèmes de connexion Wifi.                          |
 |                    |                            | VPN                        | Problèmes d’accès distant.                            |
 | **🏢 Bâtiment**    | 🌡️ CVC                     | Chauffage                  | Demandes d’intervention sur le chauffage.            |
 |                    |                            | Climatisation              | Problèmes de climatisation.                          |
 |                    | ⚡ Électricité              | Éclairage                  | Pannes ou demandes liées à l’éclairage.               |
 | **🚗 Flotte Auto** | 🔧 Entretien & Réparation   | Révision                   | Planification des révisions véhicules.               |
 |                    |                            | Pneus                      | Remplacement ou réparation de pneus.                 |
 | **👤 RH**          | 🚀 Mouvements de personnel  | Arrivée / Onboarding        | Processus d’intégration des nouveaux employés.       |
 |                    |                            | Départ / Offboarding        | Processus de départ.                                  |
 | **🛒 Achats**      | 🛍️ Sourcing & Commande    | Commande de matériel       | Processus d’achat de matériel IT ou bureautique.     |
 | **🔐 Sécurité**    | 🪪 Contrôle d’Accès         | Badges                     | Gestion des badges d’accès physiques.                |




📋 5. CATÉGORIES DE TÂCHES
markdown
Copier

  **Nom**               | **Description**                                                                                     |
 |-----------------------|-----------------------------------------------------------------------------------------------------|
 | Intervention technique | Tâche nécessitant une action technique (réparation, configuration).                              |
 | Validation            | Tâche de validation (test, recette, approbation).                                                |
 | Approbation           | Tâche nécessitant une approbation hiérarchique ou budgétaire.                                    |
 | Suivi                 | Tâche de suivi (relance, vérification).                                                           |
 | Documentation         | Tâche de rédaction ou mise à jour de documentation.                                               |
 | Formation             | Tâche liée à la formation d’un utilisateur ou d’une équipe.                                      |
 | Maintenance préventive | Tâche planifiée pour éviter les incidents.                                                        |
 | Audit                 | Tâche liée à un audit (sécurité, conformité).                                                     |




📋 6. GABARITS DE TÂCHE
markdown
Copier

  **Nom**                          | **Description**                                                                                     | **Catégorie de Tâche** | **Durée Estimée** | **Groupe Responsable** |
 |----------------------------------|-----------------------------------------------------------------------------------------------------|------------------------|-------------------|-------------------------|
 | Installation poste de travail    | Checklist pour l’installation d’un nouveau poste (matériel, logiciels, accès).                     | Intervention technique | 2h                | IT & SI / Support N1    |
 | Réparation imprimante            | Étapes pour diagnostiquer et réparer une imprimante.                                            | Intervention technique | 1h                | IT & SI / Support N2    |
 | Validation mise à jour           | Test et validation d’une mise à jour logicielle.                                                 | Validation             | 30 min            | IT & SI / Applications  |
 | Approbation achat                | Processus d’approbation pour un achat > 1000€.                                                    | Approbation            | 1 jour             | Achats / Direction      |
 | Formation utilisateur            | Plan de formation pour un nouvel outil (support, durée).                                          | Formation              | 1h                | RH / Formation          |




📋 7. TYPES DE SOLUTIONS
markdown
Copier

  **Type**               | **Description**                                                                                     |
 |------------------------|-----------------------------------------------------------------------------------------------------|
 | Correction immédiate   | Solution appliquée directement (redémarrage, réinitialisation).                                  |
 | Remplacement matériel  | Remplacement d’un composant ou équipement défectueux.                                            |
 | Mise à jour            | Application d’un correctif ou d’une mise à jour.                                                 |
 | Configuration          | Modification de paramètres ou de configuration.                                                  |
 | Contournement          | Solution temporaire en attendant une résolution définitive.                                    |
 | Formation              | Solution impliquant une formation utilisateur.                                                  |
 | Documentation          | Solution documentée pour référence future.                                                        |
 | Escalade               | Transmission à un niveau supérieur ou un expert.                                                 |




📋 8. GABARITS DE SOLUTION
markdown
Copier

  **Nom**                          | **Description**                                                                                     | **Type de Solution** | **Catégorie ITIL**          |
 |----------------------------------|-----------------------------------------------------------------------------------------------------|----------------------|-----------------------------|
 | Réinitialisation mot de passe    | Étapes pour réinitialiser un mot de passe via l’AD.                                               | Correction immédiate | Comptes & Identités         |
 | Remplacement écran               | Procédure pour remplacer un écran d’ordinateur portable.                                        | Remplacement matériel | Poste de travail            |
 | Mise à jour Windows              | Gabarit pour documenter une mise à jour système.                                                 | Mise à jour          | Logiciels & Applications    |
 | Configuration VPN                | Étapes pour configurer un accès VPN pour un utilisateur.                                        | Configuration        | Réseau & Connectivité       |




📋 9. GABARITS DE VALIDATION
markdown
Copier

  **Nom**                          | **Description**                                                                                     | **Type de Validation** | **Responsable**          |
 |----------------------------------|-----------------------------------------------------------------------------------------------------|------------------------|--------------------------|
 | Validation mise en production    | Checklist pour valider une mise en production (tests, recette, approbation).                     | Technique              | IT & SI / Développement  |
 | Validation achat                 | Processus de validation d’une commande (budget, conformité).                                    | Budgétaire             | Achats / Direction       |
 | Validation accès                 | Validation des droits d’accès pour un nouvel utilisateur.                                       | Sécurité               | IT & SI / IAM            |
 | Validation changement            | Validation d’un changement planifié (impact, rollback).                                         | Technique              | IT & SI / Infrastructure  |




📋 10. SOURCES DES DEMANDES
markdown
Copier

  **Source**               | **Description**                                                                                     |
 |--------------------------|-----------------------------------------------------------------------------------------------------|
 | Email                    | Demande reçue par email (ex: support@entreprise.com).                                            |
 | Portail utilisateur      | Demande créée via le portail GLPI par un utilisateur.                                            |
 | Téléphone                | Demande reçue par appel au support.                                                                |
 | Chat (Teams/Slack)       | Demande via un canal de chat interne.                                                              |
 | Incident automatique     | Ticket généré automatiquement (ex: alerte de supervision).                                      |
 | Audit                    | Demande issue d’un audit (sécurité, conformité).                                                  |
 | Réunion                  | Demande formalisée lors d’une réunion.                                                            |
 | Fournisseur               | Demande ou incident signalé par un fournisseur/prestataire.                                      |




📋 11. GABARITS DE SUIVIS
markdown
Copier

  **Nom**                          | **Description**                                                                                     | **Fréquence**   | **Responsable**          |
 |----------------------------------|-----------------------------------------------------------------------------------------------------|-----------------|--------------------------|
 | Suivi ticket ouvert > 48h        | Relance automatique pour les tickets non résolus après 48h.                                     | Quotidien       | IT & SI / Support         |
 | Suivi commande fournisseur      | Vérification du statut d’une commande (délai, livraison).                                        | Hebdomadaire    | Achats / Logistique      |
 | Suivi projet infrastructure      | Point d’avancement sur un projet d’infrastructure.                                              | Mensuel         | IT & SI / Infrastructure  |
 | Suivi incident sécurité          | Suivi des incidents de sécurité (résolution, actions correctives).                              | Immédiat        | IT & SI / Sécurité       |




📋 12. STATUTS DE PROJET
markdown
Copier

  **Statut**      | **Description**                                      | **Couleur**   | **Prochain Statut Possible**          |
 |-----------------|------------------------------------------------------|---------------|--------------------------------------|
 | Idée            | Projet à l’état de concept, non validé.               | Gris          | Étude, Annulé                        |
 | Étude           | Analyse de faisabilité et de coût.                  | Bleu clair    | Planification, Annulé                |
 | Planification   | Calendrier, ressources et budget définis.            | Jaune         | En cours, Reporté                    |
 | En cours        | Projet en cours de réalisation.                       | Orange        | Test, Bloqué, Annulé                 |
 | Test            | Phase de recette ou de test.                         | Vert clair    | Validation, Correction               |
 | Validation      | Validation finale avant clôture.                    | Vert          | Clôturé, Correction                  |
 | Clôturé         | Projet terminé et validé.                           | Vert foncé    | -                                    |
 | Reporté         | Projet temporairement suspendu.                     | Rouge clair   | Planification, Annulé                |
 | Bloqué          | Projet bloqué (ressources, budget, dépendance).     | Rouge         | En cours, Annulé                     |
 | Annulé          | Projet abandonné.                                   | Noir          | -                                    |




📋 13. TYPES DE PROJET
markdown
Copier

  **Type**               | **Description**                                                                                     |
 |------------------------|-----------------------------------------------------------------------------------------------------|
 | Infrastructure         | Projet lié à l’infrastructure IT (serveurs, réseau).                                              |
 | Développement          | Projet de développement logiciel ou applicatif.                                                  |
 | Sécurité               | Projet lié à la sécurité (audit, conformité, outils).                                             |
 | Organisationnel        | Projet impactant l’organisation (processus, RH).                                                  |
 | Bâtiment               | Projet lié aux locaux (rénovation, aménagement).                                                  |
 | Flotte Auto            | Projet lié à la gestion de la flotte automobile.                                                  |
 | Achats                 | Projet lié aux achats (négociation, nouveau fournisseur).                                         |




📋 14. TYPES DE TÂCHE DE PROJET
markdown
Copier

  **Type**               | **Description**                                                                                     |
 |------------------------|-----------------------------------------------------------------------------------------------------|
 | Étude                  | Analyse préalable (besoins, faisabilité).                                                         |
 | Conception             | Design technique ou fonctionnel.                                                                  |
 | Développement          | Réalisation technique (code, configuration).                                                      |
 | Test                   | Phase de test (unitaire, intégration, recette).                                                    |
 | Déploiement            | Mise en production.                                                                                 |
 | Formation              | Formation des utilisateurs ou équipes.                                                            |
 | Documentation          | Rédaction de documentation.                                                                       |
 | Suivi                  | Suivi de l’avancement (réunions, rapports).                                                       |




📋 15. GABARITS DE TÂCHES DE PROJET
markdown
Copier

  **Nom**                          | **Description**                                                                                     | **Type de Tâche** | **Durée Estimée** | **Responsable**          |
 |----------------------------------|-----------------------------------------------------------------------------------------------------|-------------------|-------------------|--------------------------|
 | Étude de faisabilité             | Analyse des besoins et contraintes pour un projet.                                               | Étude             | 1 semaine          | Chef de projet           |
 | Rédaction cahier des charges     | Document formalisant les besoins et attentes.                                                    | Conception        | 3 jours            | Chef de projet           |
 | Développement fonctionnalité     | Implémentation d’une nouvelle fonctionnalité.                                                    | Développement    | 2 semaines         | Développeur              |
 | Tests utilisateurs              | Organisation et réalisation des tests avec les utilisateurs finaux.                              | Test              | 5 jours            | QA / Support              |
 | Déploiement en production        | Mise en ligne d’une nouvelle version ou outil.                                                    | Déploiement      | 1 jour             | IT & SI / Infrastructure  |
 | Formation équipe                 | Session de formation pour une nouvelle équipe.                                                   | Formation         | 2h                | RH / Formation            |




📋 16. GABARITS D’ÉVÉNEMENTS EXTERNES
markdown
Copier

  **Nom**                          | **Description**                                                                                     | **Catégorie**          | **Impact** | **Groupe Responsable** |
 |----------------------------------|-----------------------------------------------------------------------------------------------------|------------------------|------------|-------------------------|
 | Panne fournisseur Internet       | Indisponibilité du lien Internet due à un problème chez le fournisseur.                          | Réseau & Connectivité | Élevé      | IT & SI / Réseau         |
 | Maintenance cloud (AWS/Azure)    | Maintenance planifiée par le fournisseur cloud.                                                  | Infrastructure        | Moyen      | IT & SI / Infrastructure |
 | Grève transport                  | Grève impactant les livraisons ou déplacements.                                                  | Logistique            | Faible     | Achats / Logistique      |
 | Audit externe                   | Audit réalisé par un organisme externe (ex: RGPD).                                               | Conformité            | Moyen      | Juridique / QHSE         |
 | Mise à jour logicielle externe   | Mise à jour imposée par un éditeur (ex: patch critique).                                           | Logiciels            | Élevé      | IT & SI / Applications   |




📋 17. CATÉGORIES D’ÉVÉNEMENTS
markdown
Copier

  **Catégorie**               | **Description**                                                                                     |
 |-----------------------------|-----------------------------------------------------------------------------------------------------|
 | Maintenance                 | Événement lié à une maintenance (planifiée ou non).                                               |
 | Incident externe            | Événement causé par un tiers (fournisseur, prestataire).                                           |
 | Audit                       | Événement lié à un audit (interne ou externe).                                                    |
 | Réglementaire               | Événement lié à une obligation légale ou réglementaire.                                          |
 | Budgétaire                  | Événement lié au budget (restrictions, validations).                                              |
 | Environnemental             | Événement lié à l’environnement (ex: intempéries).                                                 |




📋 18. RAISONS D’ATTENTE
markdown
Copier

  **Raison**                          | **Description**                                                                                     | **Exemple**                                  |
 |-------------------------------------|-----------------------------------------------------------------------------------------------------|---------------------------------------------|
 | En attente de l’utilisateur         | Attente d’une information ou d’une action de l’utilisateur.                                       | Attente des identifiants pour créer un compte. |
 | En attente de fournisseur           | Attente d’une intervention ou d’une pièce de la part d’un fournisseur.                            | Attente d’un technicien pour réparer une imprimante. |
 | En attente de livraison            | Attente de la réception d’un matériel ou d’un équipement.                                          | Attente d’un nouveau laptop commandé.        |
 | En attente de validation           | Attente d’une approbation (hiérarchique, budgétaire, technique).                                   | Attente de la validation du DAF pour un achat. |
 | En attente de planification        | Attente d’une fenêtre de maintenance ou d’une disponibilité.                                       | Attente d’une fermeture de site pour une intervention. |
 | En attente d’un événement externe  | Attente d’un audit, d’un contrôle réglementaire ou d’une décision budgétaire.                       | Attente des résultats d’un audit sécurité.   |
 | En attente de dépendance           | Attente de la résolution d’un autre ticket ou projet.                                            | Attente de la fin d’un projet réseau.         |
 | En attente de ressources           | Attente de ressources humaines ou techniques.                                                   | Attente d’un développeur disponible.         |




📋 19. CATÉGORIES DU CATALOGUE DE SERVICES
markdown
Copier

  **Catégorie**               | **Description**                                                                                     |
 |-----------------------------|-----------------------------------------------------------------------------------------------------|
 | Services IT                 | Services liés à l’informatique (support, développement, infrastructure).                          |
 | Services Bâtiment           | Services liés aux locaux (maintenance, nettoyage, aménagement).                                   |
 | Services RH                 | Services liés aux ressources humaines (recrutement, formation, administration).                  |
 | Services Achats             | Services liés aux achats (commandes, fournisseurs, logistique).                                   |
 | Services Sécurité           | Services liés à la sécurité (contrôle d’accès, vidéosurveillance, gestion des incidents).          |
 | Services Flotte Auto        | Services liés à la gestion des véhicules (entretien, sinistres, carburant).                       |
 | Services Généraux           | Services transverses (propreté, restauration, RSE).                                                |




📋 20. ÉTAPES DE VALIDATION
markdown
Copier

  **Étape**               | **Description**                                                                                     | **Responsable**          | **Délai Max** |
 |-------------------------|-----------------------------------------------------------------------------------------------------|--------------------------|---------------|
 | Demande initiale        | Soumission de la demande ou du projet.                                                             | Demandeur               | -             |
 | Analyse technique       | Vérification de la faisabilité technique.                                                         | IT & SI / Expert        | 3 jours        |
 | Analyse budgétaire      | Validation du coût et du budget.                                                                    | Achats / Direction      | 5 jours        |
 | Validation métier       | Approbation par le métier concerné.                                                                | Responsable Métier      | 2 jours        |
 | Validation sécurité      | Vérification des impacts sécurité.                                                                 | IT & SI / Sécurité      | 2 jours        |
 | Validation finale       | Approbation définitive (ex: COMEX).                                                                | Direction               | 1 jour         |
 | Planification            | Définition du calendrier et des ressources.                                                        | Chef de projet          | 2 jours        |
 | Exécution                | Réalisation du changement ou du projet.                                                             | Équipe projet           | Variable      |
 | Recette                  | Tests et validation avant clôture.                                                                 | QA / Utilisateurs       | 3 jours        |
 | Clôture                  | Fermeture administrative et documentation.                                                        | Chef de projet          | 1 jour         |


 ## Fonctionnalité : Assistant intelligent de création des lieux lors de la préconfiguration GLPI

Dans le cadre du plugin de préconfiguration automatique GLPI, ajouter une fonctionnalité permettant de créer les lieux de manière assistée et intelligente avant leur injection dans GLPI.

### Objectif

Lors de la phase de configuration initiale d'une instance GLPI, l'administrateur doit pouvoir renseigner les différents lieux de son organisation (agences, sites, bureaux, datacenters, bâtiments...) avec une expérience moderne similaire aux formulaires d'adresse actuels.

Le plugin doit simplifier la création des lieux et éviter les erreurs de saisie.

### Assistant de création des lieux

Créer un écran dédié dans le wizard de préconfiguration :

```
Préconfiguration GLPI
 ├── Entités
 ├── Utilisateurs
 ├── Catégories
 ├── Modèles
 ├── SLA
 ├── Calendriers
 └── Lieux
```

Dans la partie "Lieux", permettre l'ajout d'un ou plusieurs sites.

### Saisie intelligente d'une adresse

L'administrateur ne doit pas saisir manuellement chaque champ.

Le formulaire doit proposer une recherche d'adresse dynamique avec auto-complétion.

Exemple :

L'utilisateur saisit :

```
Ville : Nantes
Rue : Avenue de
```

Le système propose automatiquement :

```
Avenue de la République, Nantes
Avenue de la Libération, Nantes
Avenue de la Gare, Nantes
```

L'utilisateur sélectionne simplement la bonne adresse.

### Informations récupérées automatiquement

Après sélection d'une adresse, le plugin doit compléter automatiquement :

* Nom du lieu
* Adresse complète
* Numéro de rue
* Rue
* Code postal
* Ville
* Pays
* Latitude
* Longitude
* Altitude (si disponible)

Exemple :

Entrée :

```
12 Avenue de la Libération
44000 Nantes
France
```

Résultat injecté dans GLPI :

```
Nom : Agence Nantes

Adresse :
12 Avenue de la Libération

Code postal :
44000

Ville :
Nantes

Pays :
France

Latitude :
47.218371

Longitude :
-1.553621
```

### Injection dans GLPI

Après validation du wizard :

Le plugin doit automatiquement créer les objets natifs GLPI :

Objet :

```
Location
```

avec les informations récupérées.

Le plugin ne doit pas remplacer la gestion native des lieux GLPI mais uniquement assister leur création initiale.

### Gestion des lieux existants

Ajouter une possibilité :

"Importer / compléter les lieux existants"

Fonctionnement :

* Lire les lieux déjà présents dans GLPI.
* Identifier ceux qui possèdent une adresse incomplète.
* Proposer une recherche automatique des coordonnées.
* Permettre une validation avant modification.

### Fournisseur de géocodage

Prévoir une architecture compatible avec plusieurs fournisseurs.

Priorité :

* OpenStreetMap / Nominatim
* Photon
* OpenRouteService

Prévoir une configuration :

```
Fournisseur géographique :
[x] OpenStreetMap
[ ] Google Maps
[ ] Bing Maps

Clé API :
************
```

### Contraintes techniques

La fonctionnalité doit respecter :

* Architecture plugin GLPI 11+
* Utilisation des classes natives GLPI
* Respect des droits GLPI
* Compatibilité multi-entités
* Support multilingue
* Aucun changement destructif sur le cœur GLPI

Le résultat attendu est un assistant de déploiement GLPI capable de configurer une instance complète avec des lieux propres, normalisés et géolocalisés.




