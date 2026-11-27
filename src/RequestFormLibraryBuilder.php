<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Vincent GUILLOTTE
 * https://github.com/parime/Configuration-glpi-auto
 * -------------------------------------------------------------------------
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See LICENSE for the full text.
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Configurationglpiauto;

use Glpi\Form\Category as FormCategory;
use Glpi\Form\Condition\ConditionData;
use Glpi\Form\Condition\CreationStrategy;
use Glpi\Form\Condition\LogicOperator;
use Glpi\Form\Condition\Type as ConditionType;
use Glpi\Form\Condition\ValidationStrategy;
use Glpi\Form\Condition\ValueOperator;
use Glpi\Form\Condition\VisibilityStrategy;
use Glpi\Form\Destination\CommonITILField\AssociatedItemsField;
use Glpi\Form\Destination\CommonITILField\AssociatedItemsFieldConfig;
use Glpi\Form\Destination\CommonITILField\AssociatedItemsFieldStrategy;
use Glpi\Form\Destination\CommonITILField\ContentField;
use Glpi\Form\Destination\CommonITILField\ITILActorFieldStrategy;
use Glpi\Form\Destination\CommonITILField\ITILCategoryField;
use Glpi\Form\Destination\CommonITILField\ITILCategoryFieldConfig;
use Glpi\Form\Destination\CommonITILField\ITILCategoryFieldStrategy;
use Glpi\Form\Destination\CommonITILField\LocationField;
use Glpi\Form\Destination\CommonITILField\LocationFieldConfig;
use Glpi\Form\Destination\CommonITILField\LocationFieldStrategy;
use Glpi\Form\Destination\CommonITILField\ObserverField;
use Glpi\Form\Destination\CommonITILField\ObserverFieldConfig;
use Glpi\Form\Destination\CommonITILField\RequestTypeField;
use Glpi\Form\Destination\CommonITILField\RequestTypeFieldConfig;
use Glpi\Form\Destination\CommonITILField\RequestTypeFieldStrategy;
use Glpi\Form\Destination\CommonITILField\SimpleValueConfig;
use Glpi\Form\Destination\CommonITILField\TitleField;
use Glpi\Form\Destination\CommonITILField\UrgencyField;
use Glpi\Form\Destination\CommonITILField\UrgencyFieldConfig;
use Glpi\Form\Destination\CommonITILField\UrgencyFieldStrategy;
use Glpi\Form\Destination\CommonITILField\ValidationField;
use Glpi\Form\Destination\CommonITILField\ValidationFieldConfig;
use Glpi\Form\Destination\CommonITILField\ValidationFieldStrategy;
use Glpi\Form\Destination\CommonITILField\ValidationFieldStrategyConfig;
use Glpi\Form\Destination\FormDestination;
use Glpi\Form\Destination\FormDestinationTicket;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\QuestionType\QuestionTypeCheckbox;
use Glpi\Form\QuestionType\QuestionTypeDateTime;
use Glpi\Form\QuestionType\QuestionTypeDateTimeExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeDropdown;
use Glpi\Form\QuestionType\QuestionTypeDropdownExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeEmail;
use Glpi\Form\QuestionType\QuestionTypeFile;
use Glpi\Form\QuestionType\QuestionTypeItem;
use Glpi\Form\QuestionType\QuestionTypeItemDropdown;
use Glpi\Form\QuestionType\QuestionTypeItemDropdownExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeItemExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeLongText;
use Glpi\Form\QuestionType\QuestionTypeRadio;
use Glpi\Form\QuestionType\QuestionTypeSelectableExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Form\QuestionType\QuestionTypeUrgency;
use Glpi\Form\QuestionType\QuestionTypeUserDevice;
use Glpi\Form\QuestionType\QuestionTypeUserDevicesConfig;
use Glpi\Form\Section;
use Glpi\Form\Tag\AnswerTagProvider;
use Glpi\Form\Tag\FormTagProvider;
use ITILCategory;
use Location;
use Ramsey\Uuid\Uuid;
use Ticket;
use User;

/**
 * Issue #346 : formulaires du catalogue de services tirés de catégories de demandes récurrentes
 * (support infrastructure et application métier), décrits par des données plutôt que par autant de
 * builders copiés de `LaptopRequestFormBuilder`. Questions typées, questions conditionnelles
 * (`VISIBLE_IF` sur la clé d'une option), titre de ticket calculé (`FormTagProvider` +
 * `AnswerTagProvider`), catégorie ITIL, type de demande.
 *
 * Certains formulaires demandent une **approbation** avant exécution : le demandeur désigne la
 * personne qui valide (question « Utilisateur »), et le ticket naît avec une approbation à son nom
 * (`ValidationField`, stratégie `SPECIFIC_ANSWERS`). Rien n'est exécuté sans elle : c'est la trace
 * attendue en gestion des changements (A.8.32).
 *
 * Issue #320 (formulaires plus complets et plus conditionnels), mêmes mécanismes natifs de GLPI 11 et
 * 12, sans couche de compatibilité (API identique dans les deux versions) :
 * - plusieurs **sections**, elles-mêmes conditionnelles (`Section` porte les mêmes conditions de
 *   visibilité que les questions) ;
 * - conditions « vaut l'une de ces options » (plusieurs `ConditionData` reliées par `OR`), « la case
 *   est cochée » (`CONTAINS` sur une question à cases), « urgence au moins haute »
 *   (`GREATER_THAN_OR_EQUALS` sur une question Urgence) ;
 * - questions Urgence, Lieu (liste des lieux), Appareil de l'utilisateur, Adresse e-mail ;
 * - destination calculée depuis les réponses : urgence, lieu, éléments associés (l'appareil choisi),
 *   observateurs (le responsable désigné), et **catégorie selon la réponse** (`routes` : une
 *   destination par option, créée seulement si l'option est choisie, `CreationStrategy::CREATED_IF`) ;
 * - texte de présentation de chaque formulaire dans le catalogue (`description`).
 *
 * Mêmes conditions que les autres formulaires du catalogue : `service_catalog_enabled` et la
 * branche cochée ; une catégorie introuvable (branche décochée, catégorie renommée) saute le
 * formulaire au lieu de le créer sans catégorie. Relancer l'assistant met à jour un formulaire dont
 * la définition a changé, sauf s'il a été retouché dans GLPI (voir `ManagedForm`).
 */
class RequestFormLibraryBuilder
{
    private const BRANCH_KEY = 'it';

    private const ILLUSTRATION = 'asset-desktop-1';

    /** Catégorie ITIL (sous IT & SI) et sous-rubrique du catalogue des demandes d'application métier. */
    private const BUSINESS_APPLICATION = 'Application métier';

    private const BUSINESS_ILLUSTRATION = 'factory';

    /** Préfixe des clés de `ManagedForm` propres à ce builder. */
    private const MANAGED_PREFIX = 'library:';

    /** Urgence proposée par défaut (moyenne, comme un ticket saisi à la main). */
    private const DEFAULT_URGENCY = 3;

    /**
     * Validation des justifications : assez de texte pour que le support ou l'approbateur puisse
     * décider sans recontacter le demandeur (message d'erreur natif de GLPI).
     */
    private const JUSTIFICATION_RULE = ['min_length', 15];

    /** Prénom et nom : au moins deux mots. */
    private const FIRST_AND_LAST_NAME = '/^\S+(\s+\S+)+$/u';

    /** Numéro ou référence : au moins un chiffre. */
    private const HAS_DIGIT = '/\d/u';

    /** @var array<string, string> clé de définition => statut `ManagedForm::*` du dernier build() */
    private array $statuses = [];

    /**
     * Clé => définition (libellés traduits ; noms de formulaires et options en français, comme le
     * reste du catalogue). `title` : clés des questions reprises dans le titre du ticket.
     * `approval` : clé de la question « Utilisateur » qui désigne l'approbateur. `urgency`,
     * `location` : question dont la réponse devient l'urgence, le lieu du ticket. `items` : questions
     * « appareil » dont la réponse devient un élément associé. `observers` : questions « Utilisateur »
     * dont la réponse devient observateur. `routes` : catégorie selon l'option choisie.
     * `questions` (une section « Votre demande ») ou `sections` (nom, aide, condition, questions).
     * Types de question : short, long, radio, checkbox, dropdown, date, file, user, urgency,
     * location, device, email. Conditions : `visible_if` [clé, option], `visible_if_not`,
     * `visible_if_any` [clé, [options]], `visible_if_contains` (case cochée),
     * `visible_if_at_least` [clé, valeur] (urgence).
     */
    private static function definitions(): array
    {
        return [
            'shared_folder' => [
                'name' => 'Accès à un dossier partagé',
                'description' => __("Ouvrir, modifier ou retirer l'accès à un dossier du réseau. Le responsable du dossier approuve la demande.", 'configurationglpiauto'),
                'path' => ['Comptes & Identités', 'Droits, Rôles & Groupes AD'],
                'request_type' => 'demand',
                'title' => ['path', 'access'],
                'approval' => 'owner',
                'questions' => [
                    ['key' => 'path', 'type' => 'short', 'mandatory' => true, 'label' => __('Dossier ou partage concerné', 'configurationglpiauto'),
                        'help' => __("Chemin complet tel qu'affiché dans l'explorateur, ou nom du dossier.", 'configurationglpiauto')],
                    ['key' => 'access', 'type' => 'radio', 'mandatory' => true, 'label' => __("Type d'accès", 'configurationglpiauto'),
                        'options' => ['1' => 'Lecture seule', '2' => 'Lecture et écriture', '3' => "Retrait d'accès"]],
                    ['key' => 'owner', 'type' => 'user', 'mandatory' => true, 'label' => __('Responsable du dossier (approbateur)', 'configurationglpiauto')],
                    ['key' => 'end', 'type' => 'date', 'mandatory' => false, 'label' => __("Date de fin d'accès (si temporaire)", 'configurationglpiauto')],
                    ['key' => 'reason', 'type' => 'long', 'mandatory' => true, 'label' => __('Justification', 'configurationglpiauto'),
                        'validate' => self::JUSTIFICATION_RULE],
                ],
            ],
            'locked_account' => [
                'name' => 'Compte bloqué ou session verrouillée',
                'description' => __('Impossible de vous connecter à votre session, à la messagerie ou à une application.', 'configurationglpiauto'),
                'path' => ['Comptes & Identités', 'Mot de passe & Réinitialisation'],
                'request_type' => 'incident',
                'title' => ['account'],
                'urgency' => 'urgency',
                'questions' => [
                    ['key' => 'account', 'type' => 'radio', 'mandatory' => true, 'label' => __('Compte concerné', 'configurationglpiauto'),
                        'options' => ['1' => 'Session Windows', '2' => 'Messagerie', '3' => 'Application métier', '4' => 'Autre']],
                    ['key' => 'other', 'type' => 'short', 'mandatory' => true, 'label' => __('Quelle application ?', 'configurationglpiauto'),
                        'visible_if' => ['account', '4']],
                    ['key' => 'message', 'type' => 'long', 'mandatory' => false, 'label' => __("Message d'erreur affiché", 'configurationglpiauto')],
                    ...self::urgencyQuestions(),
                ],
            ],
            'data_restore' => [
                'name' => 'Restauration de données',
                'description' => __("Récupérer un fichier, un dossier, une boîte mail ou une base supprimés ou abîmés, à partir d'une sauvegarde.", 'configurationglpiauto'),
                'path' => ['Sauvegardes & Restauration'],
                'request_type' => 'demand',
                'title' => ['what'],
                'urgency' => 'urgency',
                'questions' => [
                    ['key' => 'what', 'type' => 'radio', 'mandatory' => true, 'label' => __('Que faut-il restaurer ?', 'configurationglpiauto'),
                        'options' => ['1' => 'Un ou plusieurs fichiers', '2' => 'Un dossier complet', '3' => 'Une boîte mail',
                            '4' => 'Une base de données', '5' => 'Une machine virtuelle']],
                    ['key' => 'location', 'type' => 'short', 'mandatory' => true, 'label' => __('Emplacement (chemin, serveur, application)', 'configurationglpiauto')],
                    ['key' => 'version', 'type' => 'date', 'mandatory' => true, 'label' => __('Date de la version souhaitée', 'configurationglpiauto')],
                    ['key' => 'urgency', 'type' => 'urgency', 'mandatory' => true, 'label' => __('Urgence', 'configurationglpiauto'),
                        'default' => self::DEFAULT_URGENCY],
                    ['key' => 'blocking', 'type' => 'long', 'mandatory' => true, 'validate' => self::JUSTIFICATION_RULE, 'label' => __('Quel travail est bloqué en attendant ?', 'configurationglpiauto'),
                        'help' => __('Aide le support à traiter les demandes urgentes en premier.', 'configurationglpiauto'),
                        'visible_if_at_least' => ['urgency', 4]],
                    ['key' => 'details', 'type' => 'long', 'mandatory' => false, 'label' => __('Précisions (noms de fichiers, cause de la perte)', 'configurationglpiauto')],
                ],
            ],
            'removable_device' => [
                'name' => 'Périphérique USB ou support amovible',
                'description' => __('Autoriser ou bloquer une clé USB, un disque externe ou un autre support amovible.', 'configurationglpiauto'),
                'path' => ['Sécurité SI', 'Périphériques & Supports amovibles'],
                'request_type' => 'demand',
                'title' => ['action'],
                'questions' => [
                    ['key' => 'action', 'type' => 'radio', 'mandatory' => true, 'label' => __('Demande', 'configurationglpiauto'),
                        'options' => ['1' => 'Autoriser un périphérique', '2' => 'Bloquer un périphérique', '3' => 'Renommer ou identifier un périphérique']],
                    ['key' => 'device', 'type' => 'short', 'mandatory' => true, 'label' => __('Périphérique (type, marque, numéro de série)', 'configurationglpiauto'),
                        'validate' => ['min_length', 5]],
                    ['key' => 'duration', 'type' => 'radio', 'mandatory' => true, 'label' => __('Durée', 'configurationglpiauto'),
                        'options' => ['1' => 'Ponctuelle (moins d\'une semaine)', '2' => 'Temporaire (date de fin)', '3' => 'Permanente'],
                        'visible_if' => ['action', '1']],
                    ['key' => 'end', 'type' => 'date', 'mandatory' => true, 'label' => __('Date de fin', 'configurationglpiauto'), 'visible_if' => ['duration', '2']],
                    ['key' => 'reason', 'type' => 'long', 'mandatory' => true, 'label' => __('Justification', 'configurationglpiauto'),
                        'validate' => self::JUSTIFICATION_RULE],
                ],
            ],
            'label_printing' => [
                'name' => "Problème d'impression d'étiquettes ou de codes-barres",
                'description' => __("Une étiquette ou un code-barres ne s'imprime pas, ou mal.", 'configurationglpiauto'),
                'path' => ['Impression', 'Étiquettes & Codes-barres'],
                'request_type' => 'incident',
                'title' => ['printer'],
                'urgency' => 'urgency',
                'questions' => [
                    ['key' => 'printer', 'type' => 'short', 'mandatory' => true, 'label' => __('Imprimante concernée (nom ou emplacement)', 'configurationglpiauto')],
                    ['key' => 'application', 'type' => 'short', 'mandatory' => true, 'label' => __("Application qui lance l'impression", 'configurationglpiauto')],
                    ['key' => 'label', 'type' => 'short', 'mandatory' => false, 'label' => __("Modèle d'étiquette", 'configurationglpiauto')],
                    ['key' => 'symptom', 'type' => 'radio', 'mandatory' => true, 'label' => __('Symptôme', 'configurationglpiauto'),
                        'options' => ['1' => "Rien ne s'imprime", '2' => 'Impression décalée ou tronquée', '3' => 'Code-barres illisible',
                            '4' => 'Mauvaises données imprimées', '5' => 'Autre']],
                    ['key' => 'sample', 'type' => 'file', 'mandatory' => false, 'label' => __("Photo ou exemple de l'étiquette", 'configurationglpiauto')],
                    ...self::urgencyQuestions(),
                ],
            ],
            'partner_request' => [
                'name' => "Demande d'un partenaire externe",
                'description' => __("Transmettre une demande technique d'un client, d'un fournisseur ou d'un prestataire.", 'configurationglpiauto'),
                'path' => ['Infrastructure & Serveurs'],
                'request_type' => 'demand',
                'title' => ['partner', 'subject'],
                'questions' => [
                    ['key' => 'partner', 'type' => 'short', 'mandatory' => true, 'label' => __('Partenaire', 'configurationglpiauto')],
                    ['key' => 'subject', 'type' => 'radio', 'mandatory' => true, 'label' => __('Objet', 'configurationglpiauto'),
                        'options' => ['1' => 'Adresses IP autorisées', '2' => 'Contacts techniques', '3' => 'Certificat ou clé',
                            '4' => 'Questionnaire de sécurité', '5' => 'Autre']],
                    ['key' => 'due', 'type' => 'date', 'mandatory' => false, 'label' => __('Échéance demandée par le partenaire', 'configurationglpiauto')],
                    ['key' => 'yearly', 'type' => 'radio', 'mandatory' => true, 'label' => __('Demande qui revient chaque année ?', 'configurationglpiauto'),
                        'options' => ['1' => 'Oui', '2' => 'Non']],
                    ['key' => 'attachment', 'type' => 'file', 'mandatory' => false, 'label' => __('Demande du partenaire (pièce jointe)', 'configurationglpiauto')],
                    ['key' => 'details', 'type' => 'long', 'mandatory' => false, 'label' => __('Précisions', 'configurationglpiauto')],
                ],
            ],
            'data_correction' => [
                'name' => 'Correction de données dans une application',
                'description' => __("Corriger une valeur erronée dans une application, après approbation d'un responsable.", 'configurationglpiauto'),
                'path' => ['Application métier', 'Données & Corrections'],
                'request_type' => 'demand',
                'title' => ['record'],
                'approval' => 'approver',
                'questions' => [
                    ['key' => 'record', 'type' => 'short', 'mandatory' => true, 'label' => __('Enregistrement concerné (numéro, référence)', 'configurationglpiauto'),
                        'validate' => ['regex', self::HAS_DIGIT],
                        'help' => __('Le numéro ou la référence tels qu'ils apparaissent dans l'application (au moins un chiffre).', 'configurationglpiauto')],
                    ['key' => 'current', 'type' => 'long', 'mandatory' => true, 'label' => __('Valeur actuelle', 'configurationglpiauto')],
                    ['key' => 'expected', 'type' => 'long', 'mandatory' => true, 'label' => __('Valeur attendue', 'configurationglpiauto')],
                    ['key' => 'reason', 'type' => 'long', 'mandatory' => true, 'label' => __('Justification', 'configurationglpiauto'),
                        'validate' => self::JUSTIFICATION_RULE],
                    ['key' => 'approver', 'type' => 'user', 'mandatory' => true, 'label' => __('Responsable qui valide la correction', 'configurationglpiauto'),
                        'help' => __("La correction ne sera faite qu'après son approbation dans le ticket.", 'configurationglpiauto')],
                    ['key' => 'attachment', 'type' => 'file', 'mandatory' => false, 'label' => __("Capture d'écran ou fichier", 'configurationglpiauto')],
                ],
            ],
            'report_extract' => [
                'name' => "Extraction de données ou rapport",
                'description' => __('Obtenir une extraction ponctuelle ou un rapport envoyé régulièrement.', 'configurationglpiauto'),
                'path' => ['Application métier', 'Extractions & Rapports'],
                'request_type' => 'demand',
                'title' => ['frequency'],
                'questions' => [
                    ['key' => 'data', 'type' => 'long', 'mandatory' => true, 'label' => __('Données souhaitées', 'configurationglpiauto')],
                    ['key' => 'filters', 'type' => 'long', 'mandatory' => false, 'label' => __('Filtres (période, site, client, statut…)', 'configurationglpiauto')],
                    ['key' => 'frequency', 'type' => 'radio', 'mandatory' => true, 'label' => __('Fréquence', 'configurationglpiauto'),
                        'options' => ['1' => 'Ponctuelle', '2' => 'Chaque jour', '3' => 'Chaque semaine', '4' => 'Chaque mois']],
                    ['key' => 'format', 'type' => 'radio', 'mandatory' => true, 'label' => __('Format', 'configurationglpiauto'),
                        'options' => ['1' => 'Tableur (Excel, CSV)', '2' => 'PDF', '3' => 'Tableau de bord']],
                    ['key' => 'recipients', 'type' => 'short', 'mandatory' => false, 'label' => __('Destinataires', 'configurationglpiauto'),
                        'visible_if_not' => ['frequency', '1']],
                ],
            ],
            'feature_request' => [
                'name' => "Demande d'évolution d'une application",
                'description' => __('Proposer une amélioration ou une nouvelle fonction dans une application.', 'configurationglpiauto'),
                'path' => ['Application métier', 'Évolutions'],
                'request_type' => 'demand',
                'title' => ['application'],
                'questions' => [
                    ['key' => 'application', 'type' => 'short', 'mandatory' => true, 'label' => __('Application concernée', 'configurationglpiauto')],
                    ['key' => 'need', 'type' => 'long', 'mandatory' => true, 'validate' => self::JUSTIFICATION_RULE, 'label' => __('Besoin métier', 'configurationglpiauto')],
                    ['key' => 'gain', 'type' => 'long', 'mandatory' => true, 'validate' => self::JUSTIFICATION_RULE, 'label' => __('Gain attendu (temps, erreurs évitées, chiffre)', 'configurationglpiauto')],
                    ['key' => 'users', 'type' => 'radio', 'mandatory' => true, 'label' => __('Utilisateurs concernés', 'configurationglpiauto'),
                        'options' => ['1' => 'Moi seulement', '2' => 'Mon équipe', '3' => 'Plusieurs services', '4' => 'Toute l\'entreprise']],
                    ['key' => 'deadline', 'type' => 'date', 'mandatory' => false, 'label' => __('Date souhaitée', 'configurationglpiauto')],
                ],
            ],
            'interface_error' => [
                'name' => "Erreur d'interface ou de traitement automatique",
                'description' => __("Un import, un échange avec un partenaire ou un traitement de nuit est en erreur.", 'configurationglpiauto'),
                'path' => ['Application métier', 'Interfaces & Traitements'],
                'request_type' => 'incident',
                'title' => ['interface'],
                'urgency' => 'urgency',
                'questions' => [
                    ['key' => 'interface', 'type' => 'short', 'mandatory' => true, 'label' => __('Interface ou traitement (import, échange partenaire, lot de nuit…)', 'configurationglpiauto')],
                    ['key' => 'when', 'type' => 'date', 'mandatory' => true, 'label' => __("Date de l'erreur", 'configurationglpiauto')],
                    ['key' => 'message', 'type' => 'long', 'mandatory' => true, 'label' => __("Message d'erreur", 'configurationglpiauto')],
                    ['key' => 'volume', 'type' => 'short', 'mandatory' => false, 'label' => __('Volume touché (commandes, lignes, fichiers)', 'configurationglpiauto')],
                    ['key' => 'impact', 'type' => 'radio', 'mandatory' => true, 'label' => __('Impact', 'configurationglpiauto'),
                        'options' => ['1' => 'Activité bloquée', '2' => 'Activité ralentie', '3' => 'Pas d\'impact immédiat']],
                    ['key' => 'attachment', 'type' => 'file', 'mandatory' => false, 'label' => __('Fichier ou journal', 'configurationglpiauto')],
                    ...self::urgencyQuestions(),
                ],
            ],
            'new_site' => [
                'name' => "Nouveau site ou entrepôt dans une application",
                'description' => __("Préparer l'ouverture d'un site ou d'un entrepôt dans les applications de l'entreprise.", 'configurationglpiauto'),
                'path' => ['Application métier', 'Paramétrage'],
                'request_type' => 'demand',
                'title' => ['site'],
                'questions' => [
                    ['key' => 'site', 'type' => 'short', 'mandatory' => true, 'label' => __('Nom du site ou de l\'entrepôt', 'configurationglpiauto')],
                    ['key' => 'start', 'type' => 'date', 'mandatory' => true, 'label' => __('Date de démarrage', 'configurationglpiauto')],
                    ['key' => 'setup', 'type' => 'checkbox', 'mandatory' => true, 'label' => __('Paramétrage à prévoir', 'configurationglpiauto'),
                        'options' => ['1' => 'Adresses et emplacements', '2' => 'Utilisateurs et droits', '3' => 'Imprimantes et étiquettes',
                            '4' => 'Stocks initiaux', '5' => 'Interfaces avec les partenaires']],
                    ['key' => 'manager', 'type' => 'user', 'mandatory' => false, 'label' => __('Responsable du site', 'configurationglpiauto')],
                    ['key' => 'details', 'type' => 'long', 'mandatory' => false, 'label' => __('Précisions', 'configurationglpiauto')],
                ],
            ],
            'stock_movement' => [
                'name' => 'Mouvement de stock en masse',
                'description' => __("Déstocker, transférer ou ajuster un grand nombre d'articles à partir d'un fichier, après approbation.", 'configurationglpiauto'),
                'path' => ['Application métier', 'Données & Corrections'],
                'request_type' => 'demand',
                'title' => ['movement'],
                'approval' => 'approver',
                'questions' => [
                    ['key' => 'movement', 'type' => 'radio', 'mandatory' => true, 'label' => __('Type de mouvement', 'configurationglpiauto'),
                        'options' => ['1' => 'Déstockage', '2' => 'Transfert entre sites', '3' => 'Ajustement d\'inventaire']],
                    ['key' => 'file', 'type' => 'file', 'mandatory' => true, 'label' => __('Fichier des articles et quantités', 'configurationglpiauto')],
                    ['key' => 'urgency', 'type' => 'radio', 'mandatory' => true, 'label' => __('Urgence', 'configurationglpiauto'),
                        'options' => ['1' => 'Dans la journée', '2' => 'Cette semaine', '3' => 'Sans urgence']],
                    ['key' => 'approver', 'type' => 'user', 'mandatory' => true, 'label' => __('Responsable qui valide le mouvement', 'configurationglpiauto')],
                    ['key' => 'reason', 'type' => 'long', 'mandatory' => true, 'label' => __('Justification', 'configurationglpiauto'),
                        'validate' => self::JUSTIFICATION_RULE],
                ],
            ],
            'label_template' => [
                'name' => "Nouveau modèle d'étiquette ou modification",
                'description' => __("Créer un modèle d'étiquette ou modifier un modèle existant.", 'configurationglpiauto'),
                'path' => ['Impression', 'Étiquettes & Codes-barres'],
                'request_type' => 'demand',
                'title' => ['kind'],
                'questions' => [
                    ['key' => 'kind', 'type' => 'radio', 'mandatory' => true, 'label' => __('Demande', 'configurationglpiauto'),
                        'options' => ['1' => 'Nouveau modèle', '2' => "Modification d'un modèle existant"]],
                    ['key' => 'existing', 'type' => 'short', 'mandatory' => true, 'label' => __('Modèle à modifier', 'configurationglpiauto'),
                        'visible_if' => ['kind', '2']],
                    ['key' => 'content', 'type' => 'long', 'mandatory' => true, 'label' => __('Informations à faire figurer', 'configurationglpiauto')],
                    ['key' => 'size', 'type' => 'short', 'mandatory' => false, 'label' => __('Format (dimensions, imprimante)', 'configurationglpiauto')],
                    ['key' => 'sample', 'type' => 'file', 'mandatory' => false, 'label' => __('Exemple ou maquette', 'configurationglpiauto')],
                ],
            ],
            // Issue #320 : arrivée complète (matériel, accès, date, responsable), questions
            // affichées selon le matériel coché.
            'arrival' => [
                'name' => "Arrivée d'un nouveau collaborateur",
                'description' => __("Préparer le poste, le matériel et les accès d'une personne qui arrive.", 'configurationglpiauto'),
                'path' => ['Comptes & Identités', 'Onboarding / Création de compte'],
                'request_type' => 'demand',
                'title' => ['person', 'start'],
                'observers' => ['manager'],
                'location' => 'site',
                'sections' => [
                    [
                        'name' => __('Le nouveau collaborateur', 'configurationglpiauto'),
                        'questions' => [
                            ['key' => 'person', 'type' => 'short', 'mandatory' => true, 'label' => __('Prénom et nom', 'configurationglpiauto'),
                                'validate' => ['regex', self::FIRST_AND_LAST_NAME]],
                            ['key' => 'job', 'type' => 'short', 'mandatory' => true, 'label' => __('Poste occupé', 'configurationglpiauto')],
                            ['key' => 'start', 'type' => 'date', 'mandatory' => true, 'label' => __("Date d'arrivée", 'configurationglpiauto'),
                                'help' => __('Prévenez au moins une semaine avant : le matériel doit être commandé et préparé.', 'configurationglpiauto')],
                            ['key' => 'manager', 'type' => 'user', 'mandatory' => true, 'label' => __('Responsable hiérarchique', 'configurationglpiauto'),
                                'help' => __('Il suit le ticket et reçoit les informations de connexion.', 'configurationglpiauto')],
                            ['key' => 'contract', 'type' => 'radio', 'mandatory' => true, 'label' => __('Type de contrat', 'configurationglpiauto'),
                                'options' => ['1' => 'CDI', '2' => 'CDD', '3' => 'Intérim ou prestataire', '4' => 'Stage ou alternance'], 'default' => '1'],
                            ['key' => 'end', 'type' => 'date', 'mandatory' => true, 'label' => __('Date de fin de contrat', 'configurationglpiauto'),
                                'help' => __('Les comptes seront fermés automatiquement à cette date.', 'configurationglpiauto'),
                                'visible_if_any' => ['contract', ['2', '3', '4']]],
                            ['key' => 'site', 'type' => 'location', 'mandatory' => false, 'label' => __('Site de travail', 'configurationglpiauto')],
                        ],
                    ],
                    [
                        'name' => __('Matériel', 'configurationglpiauto'),
                        'questions' => [
                            ['key' => 'equipment', 'type' => 'checkbox', 'mandatory' => true, 'label' => __('Matériel nécessaire', 'configurationglpiauto'),
                                'options' => ['1' => 'Ordinateur portable', '2' => 'Ordinateur fixe', '3' => 'Écran supplémentaire',
                                    '4' => 'Téléphone professionnel', '5' => 'Casque', '6' => "Badge d'accès", '7' => 'Aucun matériel']],
                            ['key' => 'laptop', 'type' => 'radio', 'mandatory' => true, 'label' => __('Modèle de portable', 'configurationglpiauto'),
                                'options' => ['1' => 'Standard (bureautique)', '2' => 'Performance (développement, graphisme)', '3' => 'Léger (déplacements fréquents)'],
                                'default' => '1', 'visible_if_contains' => ['equipment', '1']],
                            ['key' => 'phone', 'type' => 'radio', 'mandatory' => true, 'label' => __('Type de téléphone', 'configurationglpiauto'),
                                'options' => ['1' => 'Smartphone', '2' => 'Téléphone fixe ou logiciel de téléphonie'],
                                'visible_if_contains' => ['equipment', '4']],
                        ],
                    ],
                    [
                        'name' => __('Accès', 'configurationglpiauto'),
                        'questions' => [
                            ['key' => 'mail', 'type' => 'radio', 'mandatory' => true, 'label' => __('Adresse e-mail', 'configurationglpiauto'),
                                'options' => ['1' => 'Adresse nominative', '2' => "Pas d'adresse e-mail"], 'default' => '1'],
                            ['key' => 'same_as', 'type' => 'user', 'mandatory' => false, 'label' => __("Mêmes accès qu'un collègue (facultatif)", 'configurationglpiauto'),
                                'help' => __('Le support reprend ses groupes, dossiers partagés et applications.', 'configurationglpiauto')],
                            ['key' => 'applications', 'type' => 'long', 'mandatory' => false, 'label' => __('Applications et dossiers partagés nécessaires', 'configurationglpiauto')],
                            ['key' => 'remote', 'type' => 'radio', 'mandatory' => true, 'label' => __('Accès à distance (VPN)', 'configurationglpiauto'),
                                'options' => ['1' => 'Non', '2' => 'Oui'], 'default' => '1'],
                        ],
                    ],
                ],
            ],
            // Issue #320 : départ complet (matériel à restituer, messagerie, fichiers).
            'departure' => [
                'name' => "Départ d'un collaborateur",
                'description' => __('Prévenir le service informatique d\'un départ : comptes à fermer, matériel à récupérer, données à conserver.', 'configurationglpiauto'),
                'path' => ['Comptes & Identités', 'Offboarding / Suppression'],
                'request_type' => 'demand',
                'title' => ['person', 'date'],
                'observers' => ['manager'],
                'sections' => [
                    [
                        'name' => __('Le collaborateur', 'configurationglpiauto'),
                        'questions' => [
                            ['key' => 'person', 'type' => 'user', 'mandatory' => true, 'label' => __('Collaborateur qui part', 'configurationglpiauto')],
                            ['key' => 'date', 'type' => 'date', 'mandatory' => true, 'label' => __('Dernier jour de présence', 'configurationglpiauto'),
                                'help' => __('Les accès sont fermés le soir de ce jour.', 'configurationglpiauto')],
                            ['key' => 'manager', 'type' => 'user', 'mandatory' => true, 'label' => __('Responsable hiérarchique', 'configurationglpiauto')],
                            ['key' => 'reason', 'type' => 'radio', 'mandatory' => true, 'label' => __('Motif', 'configurationglpiauto'),
                                'options' => ['1' => 'Fin de contrat ou de mission', '2' => 'Démission ou rupture', '3' => 'Départ en retraite',
                                    '4' => 'Mutation vers un autre site ou une autre entité']],
                            ['key' => 'destination', 'type' => 'short', 'mandatory' => true, 'label' => __('Site ou entité de destination', 'configurationglpiauto'),
                                'help' => __('Ses comptes sont alors déplacés, pas fermés.', 'configurationglpiauto'),
                                'visible_if' => ['reason', '4']],
                        ],
                    ],
                    [
                        'name' => __('Matériel à restituer', 'configurationglpiauto'),
                        'visible_if_not' => ['reason', '4'],
                        'questions' => [
                            ['key' => 'equipment', 'type' => 'checkbox', 'mandatory' => true, 'label' => __('Matériel à récupérer', 'configurationglpiauto'),
                                'options' => ['1' => 'Ordinateur portable', '2' => 'Ordinateur fixe', '3' => 'Téléphone professionnel',
                                    '4' => "Badge d'accès", '5' => 'Clés', '6' => 'Aucun matériel', '7' => 'Autre']],
                            ['key' => 'other_equipment', 'type' => 'short', 'mandatory' => true, 'label' => __('Autre matériel', 'configurationglpiauto'),
                                'visible_if_contains' => ['equipment', '7']],
                            ['key' => 'return', 'type' => 'radio', 'mandatory' => true, 'label' => __('Restitution', 'configurationglpiauto'),
                                'options' => ['1' => 'Remis en main propre au service informatique', '2' => 'Laissé au poste de travail', '3' => 'Envoyé par transporteur'],
                                'visible_if_not_contains' => ['equipment', '6']],
                        ],
                    ],
                    [
                        'name' => __('Messagerie et fichiers', 'configurationglpiauto'),
                        'questions' => [
                            ['key' => 'mailbox', 'type' => 'radio', 'mandatory' => true, 'label' => __('Boîte mail', 'configurationglpiauto'),
                                'options' => ['1' => 'Fermer le jour du départ', '2' => 'Rediriger vers un collègue', '3' => 'Conserver en lecture pendant trois mois']],
                            ['key' => 'mail_delegate', 'type' => 'user', 'mandatory' => true, 'label' => __('Collègue qui reprend la messagerie', 'configurationglpiauto'),
                                'visible_if_any' => ['mailbox', ['2', '3']]],
                            ['key' => 'files', 'type' => 'radio', 'mandatory' => true, 'label' => __('Fichiers personnels', 'configurationglpiauto'),
                                'options' => ['1' => 'Supprimer', '2' => 'Transférer à un collègue', '3' => 'Archiver']],
                            ['key' => 'files_delegate', 'type' => 'user', 'mandatory' => true, 'label' => __('Collègue qui reçoit les fichiers', 'configurationglpiauto'),
                                'visible_if' => ['files', '2']],
                            ['key' => 'accesses', 'type' => 'long', 'mandatory' => false, 'label' => __('Accès particuliers à retirer (applications, comptes partagés)', 'configurationglpiauto')],
                        ],
                    ],
                ],
            ],
            // Issue #320 : accès à une application, validé par le responsable.
            'application_access' => [
                'name' => "Demande d'accès à une application",
                'description' => __("Obtenir un accès ou des droits supplémentaires dans une application. Votre responsable approuve la demande.", 'configurationglpiauto'),
                'path' => ['Comptes & Identités', 'Droits, Rôles & Groupes AD'],
                'request_type' => 'demand',
                'title' => ['application', 'profile'],
                'approval' => 'manager',
                'observers' => ['colleague'],
                'questions' => [
                    ['key' => 'beneficiary', 'type' => 'radio', 'mandatory' => true, 'label' => __('Pour qui ?', 'configurationglpiauto'),
                        'options' => ['1' => 'Pour moi', '2' => 'Pour un collègue'], 'default' => '1'],
                    ['key' => 'colleague', 'type' => 'user', 'mandatory' => true, 'label' => __('Collègue concerné', 'configurationglpiauto'),
                        'visible_if' => ['beneficiary', '2']],
                    ['key' => 'application', 'type' => 'short', 'mandatory' => true, 'label' => __('Application', 'configurationglpiauto'),
                        'help' => __("Nom de l'application tel qu'il apparaît à l'écran.", 'configurationglpiauto')],
                    ['key' => 'profile', 'type' => 'radio', 'mandatory' => true, 'label' => __("Niveau d'accès", 'configurationglpiauto'),
                        'options' => ['1' => 'Consultation', '2' => 'Saisie', '3' => 'Validation', '4' => "Administration de l'application"]],
                    ['key' => 'admin_reason', 'type' => 'long', 'mandatory' => true, 'validate' => self::JUSTIFICATION_RULE, 'label' => __("Pourquoi un accès d'administration est-il nécessaire ?", 'configurationglpiauto'),
                        'visible_if' => ['profile', '4']],
                    ['key' => 'same_as', 'type' => 'user', 'mandatory' => false, 'label' => __("Mêmes droits qu'un collègue (facultatif)", 'configurationglpiauto')],
                    ['key' => 'duration', 'type' => 'radio', 'mandatory' => true, 'label' => __('Durée', 'configurationglpiauto'),
                        'options' => ['1' => 'Permanente', '2' => 'Temporaire'], 'default' => '1'],
                    ['key' => 'end', 'type' => 'date', 'mandatory' => true, 'label' => __("Date de fin d'accès", 'configurationglpiauto'),
                        'visible_if' => ['duration', '2']],
                    ['key' => 'manager', 'type' => 'user', 'mandatory' => true, 'label' => __("Responsable qui valide l'accès", 'configurationglpiauto'),
                        'help' => __("L'accès n'est ouvert qu'après son approbation dans le ticket.", 'configurationglpiauto')],
                    ['key' => 'reason', 'type' => 'long', 'mandatory' => true, 'label' => __('Justification', 'configurationglpiauto'),
                        'validate' => self::JUSTIFICATION_RULE],
                ],
            ],
            // Issue #320 : signalement guidé ; la catégorie dépend de ce qui est signalé.
            'security_incident' => [
                'name' => 'Signaler un e-mail suspect ou un incident de sécurité',
                'description' => __('E-mail suspect, virus, compte piraté, appareil perdu ou données envoyées par erreur : signalez-le tout de suite, même en cas de doute.', 'configurationglpiauto'),
                'path' => ['Sécurité SI', 'Phishing & Mails suspects'],
                'request_type' => 'incident',
                'title' => ['kind'],
                'urgency' => 'urgency',
                'items' => ['device'],
                'routes' => ['question' => 'kind', 'paths' => [
                    '1' => ['Sécurité SI', 'Phishing & Mails suspects'],
                    '2' => ['Sécurité SI', 'Malware / Ransomware'],
                    '3' => ['Sécurité SI', 'Compte compromis / Accès non autorisé'],
                    '4' => ['Sécurité SI', 'Fuite de données'],
                    // Un appareil perdu ou volé expose d'abord les données qu'il contient.
                    '5' => ['Sécurité SI', 'Fuite de données'],
                    '6' => ['Sécurité SI'],
                ]],
                'sections' => [
                    [
                        'name' => __('Que se passe-t-il ?', 'configurationglpiauto'),
                        'help' => __("Ne supprimez rien et n'éteignez pas votre poste : l'équipe sécurité peut en avoir besoin.", 'configurationglpiauto'),
                        'questions' => [
                            ['key' => 'kind', 'type' => 'radio', 'mandatory' => true, 'label' => __('Ce que vous signalez', 'configurationglpiauto'),
                                'options' => ['1' => 'Un e-mail suspect (hameçonnage)', '2' => 'Un virus ou un comportement anormal du poste',
                                    '3' => "Un compte utilisé par quelqu'un d'autre", '4' => 'Des données envoyées ou publiées par erreur',
                                    '5' => 'Un appareil perdu ou volé', '6' => 'Autre']],
                            ['key' => 'description', 'type' => 'long', 'mandatory' => true, 'label' => __('Ce que vous avez constaté', 'configurationglpiauto')],
                        ],
                    ],
                    [
                        'name' => __("L'e-mail suspect", 'configurationglpiauto'),
                        'visible_if' => ['kind', '1'],
                        'questions' => [
                            ['key' => 'sender', 'type' => 'email', 'mandatory' => false, 'label' => __("Adresse de l'expéditeur", 'configurationglpiauto')],
                            ['key' => 'clicked', 'type' => 'radio', 'mandatory' => true, 'label' => __('Avez-vous cliqué sur un lien ou ouvert une pièce jointe ?', 'configurationglpiauto'),
                                'options' => ['1' => 'Non', '2' => "Oui, j'ai cliqué sur un lien", '3' => "Oui, j'ai ouvert une pièce jointe"]],
                            ['key' => 'password', 'type' => 'radio', 'mandatory' => true, 'label' => __('Avez-vous saisi un mot de passe ou un code ?', 'configurationglpiauto'),
                                'help' => __('Si oui, changez tout de suite ce mot de passe, sur tous les sites où vous l\'utilisez.', 'configurationglpiauto'),
                                'options' => ['1' => 'Non', '2' => 'Oui'],
                                'visible_if_any' => ['clicked', ['2', '3']]],
                            ['key' => 'message', 'type' => 'file', 'mandatory' => false, 'label' => __("L'e-mail suspect", 'configurationglpiauto'),
                                'help' => __("Enregistrez l'e-mail (fichier .eml ou .msg) ou faites une capture d'écran, puis joignez-le ici. Ne le transférez pas à vos collègues.", 'configurationglpiauto')],
                        ],
                    ],
                    [
                        'name' => __("L'appareil", 'configurationglpiauto'),
                        'visible_if_any' => ['kind', ['2', '5']],
                        'questions' => [
                            ['key' => 'device', 'type' => 'device', 'mandatory' => false, 'label' => __('Appareil concerné', 'configurationglpiauto'),
                                'help' => __("Choisissez-le parmi vos équipements ; s'il n'y figure pas, décrivez-le ci-dessous.", 'configurationglpiauto')],
                            ['key' => 'device_text', 'type' => 'short', 'mandatory' => false, 'label' => __('Appareil (s\'il ne figure pas dans la liste)', 'configurationglpiauto')],
                            ['key' => 'lost', 'type' => 'short', 'mandatory' => true, 'label' => __("Où et quand l'appareil a-t-il été perdu ou volé ?", 'configurationglpiauto'),
                                'visible_if' => ['kind', '5']],
                        ],
                    ],
                    [
                        'name' => __('Gravité', 'configurationglpiauto'),
                        'questions' => [
                            ['key' => 'urgency', 'type' => 'urgency', 'mandatory' => true, 'label' => __('Urgence', 'configurationglpiauto'),
                                'default' => self::DEFAULT_URGENCY],
                            ['key' => 'impact', 'type' => 'radio', 'mandatory' => true, 'label' => __('Qui est touché ?', 'configurationglpiauto'),
                                'options' => ['1' => 'Moi seulement', '2' => 'Mon équipe', '3' => "Plusieurs services ou toute l'entreprise"],
                                'visible_if_at_least' => ['urgency', 4]],
                            ['key' => 'personal_data', 'type' => 'radio', 'mandatory' => true, 'label' => __('Des données personnelles (clients, salariés) sont-elles concernées ?', 'configurationglpiauto'),
                                'help' => __("Si oui, l'entreprise peut devoir prévenir l'autorité de protection des données dans les 72 heures.", 'configurationglpiauto'),
                                'options' => ['1' => 'Non', '2' => 'Oui', '3' => 'Je ne sais pas'],
                                'visible_if_any' => ['kind', ['3', '4', '5']]],
                        ],
                    ],
                ],
            ],
            // Issue #320 : droits administrateur justifiés, limités dans le temps, approuvés.
            'admin_rights' => [
                'name' => 'Demande de droits administrateur',
                'description' => __("Obtenir des droits d'administration sur un poste, un serveur ou une application, pour une durée limitée et après approbation.", 'configurationglpiauto'),
                'path' => ['Comptes & Identités', 'Droits, Rôles & Groupes AD'],
                'request_type' => 'demand',
                'title' => ['scope', 'duration'],
                'approval' => 'manager',
                'items' => ['device'],
                'questions' => [
                    ['key' => 'scope', 'type' => 'radio', 'mandatory' => true, 'label' => __('Sur quoi ?', 'configurationglpiauto'),
                        'options' => ['1' => 'Mon poste de travail', '2' => 'Un serveur', '3' => 'Une application'], 'default' => '1'],
                    ['key' => 'device', 'type' => 'device', 'mandatory' => false, 'label' => __('Poste concerné', 'configurationglpiauto'),
                        'visible_if' => ['scope', '1']],
                    ['key' => 'target', 'type' => 'short', 'mandatory' => true, 'label' => __('Nom du serveur ou de l\'application', 'configurationglpiauto'),
                        'visible_if_not' => ['scope', '1']],
                    ['key' => 'need', 'type' => 'radio', 'mandatory' => true, 'label' => __('Pour quoi faire ?', 'configurationglpiauto'),
                        'options' => ['1' => 'Installer un logiciel', '2' => 'Modifier un paramètre du système', '3' => 'Développement ou tests', '4' => 'Autre']],
                    ['key' => 'duration', 'type' => 'radio', 'mandatory' => true, 'label' => __('Durée', 'configurationglpiauto'),
                        'options' => ['1' => 'Une intervention, avec un technicien', '2' => 'Temporaire', '3' => 'Permanente'], 'default' => '1'],
                    ['key' => 'end', 'type' => 'date', 'mandatory' => true, 'label' => __('Fin des droits', 'configurationglpiauto'),
                        'visible_if' => ['duration', '2']],
                    ['key' => 'reason', 'type' => 'long', 'mandatory' => true, 'label' => __('Justification', 'configurationglpiauto'),
                        'validate' => self::JUSTIFICATION_RULE,
                        'help' => __("Expliquez pourquoi l'intervention d'un technicien ne suffit pas : les droits d'administration sont accordés au plus juste et pour la durée la plus courte.", 'configurationglpiauto')],
                    ['key' => 'manager', 'type' => 'user', 'mandatory' => true, 'label' => __('Responsable qui valide la demande', 'configurationglpiauto')],
                ],
            ],
        ];
    }

    /**
     * Questions communes aux formulaires d'incident : l'urgence (reprise sur le ticket) et, quand
     * elle est au moins haute, qui est touché, pour que le support traite d'abord ce qui bloque le
     * plus de monde.
     *
     * @return list<array<string, mixed>>
     */
    private static function urgencyQuestions(): array
    {
        return [
            ['key' => 'urgency', 'type' => 'urgency', 'mandatory' => true, 'label' => __('Urgence', 'configurationglpiauto'),
                'default' => self::DEFAULT_URGENCY],
            ['key' => 'reach', 'type' => 'radio', 'mandatory' => true, 'label' => __('Qui est touché ?', 'configurationglpiauto'),
                'help' => __('Aide le support à traiter les demandes urgentes en premier.', 'configurationglpiauto'),
                'options' => ['1' => 'Moi seulement', '2' => 'Mon équipe', '3' => "Plusieurs services ou toute l'entreprise"],
                'visible_if_at_least' => ['urgency', 4]],
        ];
    }

    /**
     * @return int Nombre de formulaires créés, mis à jour ou déjà présents.
     */
    public function build(Config $config): int
    {
        $this->statuses = [];
        if (empty($config->fields['service_catalog_enabled'])
            || !in_array(self::BRANCH_KEY, $config->getCategoryBranches(), true)
        ) {
            return 0;
        }
        $branch = null;
        foreach (CategoryBuilder::getCategoriesPreview() as $candidate) {
            if ($candidate['key'] === self::BRANCH_KEY) {
                $branch = $candidate;
            }
        }
        if ($branch === null) {
            return 0;
        }
        $formCategoryId = $this->getOrCreateFormCategory($branch['icon'] . ' ' . $branch['name'], 0, '');
        // Les demandes propres aux applications métier ont leur sous-rubrique dans le catalogue :
        // sans elle, la rubrique IT & SI passait à près de 30 formulaires sur plusieurs pages.
        $businessRubricId = $this->getOrCreateFormCategory(
            '🏭 ' . self::BUSINESS_APPLICATION,
            $formCategoryId,
            self::BUSINESS_ILLUSTRATION
        );

        $count = 0;
        foreach (self::definitions() as $key => $definition) {
            $itilCategoryId = $this->resolveItilCategoryId($branch, $definition['path']);
            if ($itilCategoryId === null) {
                continue;
            }
            $routes = [];
            foreach ($definition['routes']['paths'] ?? [] as $option => $path) {
                $routes[(string) $option] = $this->resolveItilCategoryId($branch, $path) ?? $itilCategoryId;
            }
            $rubricId = $definition['path'][0] === self::BUSINESS_APPLICATION ? $businessRubricId : $formCategoryId;
            $status = $this->syncForm($key, $definition, $rubricId, $itilCategoryId, $routes);
            if ($status !== null) {
                $this->statuses[$key] = $status;
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, string> clé de définition => `ManagedForm::CREATED`, `UPDATED`,
     *         `UNCHANGED` ou `KEPT`, pour le dernier appel à build().
     */
    public function getStatuses(): array
    {
        return $this->statuses;
    }

    /**
     * @return list<string> Noms des formulaires, pour l'aperçu de l'assistant et les tests.
     */
    public static function getFormNames(): array
    {
        return array_values(array_map(static fn (array $form): string => $form['name'], self::definitions()));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getDefinitions(): array
    {
        return self::definitions();
    }

    /**
     * Sections d'une définition, sous une seule forme : un formulaire décrit par `questions` a une
     * seule section « Votre demande ».
     *
     * @return list<array<string, mixed>>
     */
    public static function getSections(array $definition): array
    {
        return $definition['sections'] ?? [[
            'name' => __('Votre demande', 'configurationglpiauto'),
            'questions' => $definition['questions'],
        ]];
    }

    public static function managedKey(string $key): string
    {
        return self::MANAGED_PREFIX . $key;
    }

    private function resolveItilCategoryId(array $branch, array $path): ?int
    {
        $item = new ITILCategory();
        if (!Lookup::loadFirst($item, ['name' => $branch['name'], 'itilcategories_id' => 0])) {
            return null;
        }
        $parentId = (int) $item->getID();
        foreach ($path as $name) {
            if (!Lookup::loadFirst($item, ['name' => $name, 'itilcategories_id' => $parentId])) {
                return null;
            }
            $parentId = (int) $item->getID();
        }

        return $parentId;
    }

    private function getOrCreateFormCategory(string $name, int $parentId, string $illustration): int
    {
        $item = new FormCategory();
        if (!Lookup::loadFirst($item, ['name' => $name, 'forms_categories_id' => $parentId])) {
            $id = $item->add(['name' => $name, 'forms_categories_id' => $parentId, 'illustration' => $illustration]);
            $item->getFromDB($id);
        }

        return (int) $item->getID();
    }

    /**
     * Crée le formulaire, le met à jour si sa définition a changé et que personne ne l'a retouché,
     * ou le laisse tel quel.
     *
     * @param array<string, int> $routes option => catégorie ITIL
     * @return string|null statut `ManagedForm::*`, null en cas d'échec
     */
    private function syncForm(string $key, array $definition, int $formCategoryId, int $itilCategoryId, array $routes): ?string
    {
        $managedKey = self::managedKey($key);
        $definitionHash = ManagedForm::definitionHash($definition + ['_category' => $itilCategoryId, '_routes' => $routes]);
        $managed = ManagedForm::find($managedKey);

        $form = new Form();
        if ($managed !== null && $form->getFromDB($managed['forms_forms_id'])) {
            if (!empty($form->fields['is_deleted'])) {
                // Mis à la corbeille par un administrateur : ni recréé, ni modifié.
                return ManagedForm::KEPT;
            }
            if ($managed['definition_hash'] === $definitionHash) {
                return ManagedForm::UNCHANGED;
            }
            if (ManagedForm::structureHash((int) $form->getID()) !== $managed['structure_hash']) {
                return ManagedForm::KEPT;
            }
            if (!$this->fillForm($form, $definition, $itilCategoryId, $routes)) {
                return null;
            }
            ManagedForm::record($managedKey, (int) $form->getID(), $definitionHash);

            return ManagedForm::UPDATED;
        }

        if (Lookup::loadFirst($form, ['name' => $definition['name'], 'forms_categories_id' => $formCategoryId])) {
            // Créé par une version précédente (ou à la main) : rien ne dit qu'il n'a pas été adapté.
            return ManagedForm::KEPT;
        }

        $formId = $form->add([
            'name' => $definition['name'],
            'forms_categories_id' => $formCategoryId,
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_active' => 1,
            'illustration' => self::ILLUSTRATION,
        ]);
        if (!$formId) {
            return null;
        }
        $form->getFromDB($formId);
        if (!$this->fillForm($form, $definition, $itilCategoryId, $routes)) {
            return null;
        }
        ManagedForm::record($managedKey, (int) $formId, $definitionHash);

        return ManagedForm::CREATED;
    }

    /**
     * (Re)construit le contenu du formulaire : texte de présentation, sections, questions et
     * destinations. Les sections et destinations existantes (celles que `Form::add()` crée d'office,
     * ou celles d'une version précédente de la définition) sont supprimées d'abord ; la rubrique, les
     * droits d'accès et l'état actif du formulaire sont conservés.
     *
     * @param array<string, int> $routes
     */
    private function fillForm(Form $form, array $definition, int $itilCategoryId, array $routes): bool
    {
        $formId = (int) $form->getID();
        $form->update([
            'id' => $formId,
            'name' => $definition['name'],
            'description' => $definition['description'] ?? '',
        ]);
        foreach ((new Section())->find(['forms_forms_id' => $formId]) as $row) {
            (new Section())->delete(['id' => $row['id']], true);
        }
        foreach ((new FormDestination())->find(['forms_forms_id' => $formId]) as $row) {
            (new FormDestination())->delete(['id' => $row['id']], true);
        }

        $questions = $this->addSections($formId, self::getSections($definition));
        if ($questions === null) {
            return false;
        }

        return $this->addDestinations($formId, $form, $definition, $itilCategoryId, $routes, $questions);
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @return array<string, Question>|null
     */
    private function addSections(int $formId, array $sections): ?array
    {
        $questions = [];
        foreach ($sections as $rank => $sectionDefinition) {
            $input = [
                'forms_forms_id' => $formId,
                'name' => $sectionDefinition['name'],
                'description' => $sectionDefinition['help'] ?? '',
                'rank' => $rank,
            ];
            $conditions = self::conditions($sectionDefinition, $questions);
            if ($conditions === null) {
                return null;
            }
            if ($conditions !== []) {
                $input['visibility_strategy'] = VisibilityStrategy::VISIBLE_IF->value;
                $input['conditions'] = json_encode($conditions);
            }
            $section = new Section();
            if (!$section->add($input)) {
                return null;
            }

            foreach ($sectionDefinition['questions'] as $verticalRank => $definition) {
                $question = $this->addQuestion((int) $section->getID(), $verticalRank, $definition, $questions);
                if ($question === null) {
                    return null;
                }
                $questions[$definition['key']] = $question;
            }
        }

        return $questions;
    }

    /**
     * @param array<string, Question> $previous questions déjà créées (cibles des conditions)
     */
    private function addQuestion(int $sectionId, int $rank, array $definition, array $previous): ?Question
    {
        $input = [
            'forms_sections_id' => $sectionId,
            'name' => $definition['label'],
            'type' => self::questionType($definition['type']),
            'is_mandatory' => !empty($definition['mandatory']) ? 1 : 0,
            'vertical_rank' => $rank,
        ];
        if (isset($definition['help'])) {
            $input['description'] = $definition['help'];
        }
        if (isset($definition['default'])) {
            $input['default_value'] = (string) $definition['default'];
        }
        $extraData = self::extraData($definition);
        if ($extraData !== null) {
            $input['extra_data'] = json_encode($extraData);
        }
        $conditions = self::conditions($definition, $previous);
        if ($conditions === null) {
            return null;
        }
        if ($conditions !== []) {
            $input['visibility_strategy'] = VisibilityStrategy::VISIBLE_IF->value;
            $input['conditions'] = json_encode($conditions);
        }
        if (isset($definition['validate'])) {
            // La condition de validation vise la question elle-même : son identifiant est fixé ici.
            $input['uuid'] = Uuid::uuid4()->toString();
            $input['validation_strategy'] = ValidationStrategy::VALID_IF->value;
            $input['validation_conditions'] = json_encode([self::validationCondition($input['uuid'], $definition['validate'])]);
        }

        $question = new Question();
        if (!$question->add($input)) {
            return null;
        }
        $question->getFromDB($question->getID());

        return $question;
    }

    /**
     * Règle de validation d'une réponse (mécanisme natif « Valide si... » de GLPI, message d'erreur
     * natif) : `['min_length', n]` ou `['regex', motif]`. GLPI ne contrôle pas une réponse vide
     * d'une question facultative.
     *
     * @param array{0: string, 1: int|string} $rule
     * @return array<string, mixed>
     */
    public static function validationCondition(string $questionUuid, array $rule): array
    {
        [$kind, $value] = $rule;

        return (new ConditionData(
            item_uuid: $questionUuid,
            item_type: ConditionType::QUESTION->value,
            value_operator: match ($kind) {
                'min_length' => ValueOperator::LENGTH_GREATER_THAN_OR_EQUALS->value,
                'regex' => ValueOperator::MATCH_REGEX->value,
                default => throw new \LogicException("Règle de validation inconnue : $kind"),
            },
            value: (string) $value,
            logic_operator: null,
        ))->jsonSerialize();
    }

    /**
     * Conditions de visibilité d'une question ou d'une section, au format enregistré par GLPI.
     * Plusieurs options possibles : une condition par option, reliées par OU (seul l'opérateur de la
     * deuxième condition et des suivantes compte, voir `Glpi\Form\Condition\Engine`).
     *
     * @param array<string, Question> $questions
     * @return list<array<string, mixed>>|null liste vide sans condition, null si la question visée
     *         n'existe pas (définition incohérente)
     */
    private static function conditions(array $definition, array $questions): ?array
    {
        $rules = [];
        foreach (self::conditionRules($definition) as [$target, $operator, $value]) {
            $parent = $questions[$target] ?? null;
            if ($parent === null) {
                return null;
            }
            $rules[] = (new ConditionData(
                item_uuid: $parent->fields['uuid'],
                item_type: ConditionType::QUESTION->value,
                value_operator: $operator->value,
                value: $value,
                logic_operator: $rules === [] ? null : LogicOperator::OR->value,
            ))->jsonSerialize();
        }

        return $rules;
    }

    /**
     * Règles de visibilité d'une définition : [clé visée, opérateur, valeur], reliées par OU.
     *
     * @return list<array{0: string, 1: ValueOperator, 2: mixed}>
     */
    public static function conditionRules(array $definition): array
    {
        if (isset($definition['visible_if'])) {
            return [[$definition['visible_if'][0], ValueOperator::EQUALS, $definition['visible_if'][1]]];
        }
        if (isset($definition['visible_if_not'])) {
            return [[$definition['visible_if_not'][0], ValueOperator::NOT_EQUALS, $definition['visible_if_not'][1]]];
        }
        if (isset($definition['visible_if_any'])) {
            [$target, $values] = $definition['visible_if_any'];

            return array_map(static fn (string $value): array => [$target, ValueOperator::EQUALS, $value], $values);
        }
        if (isset($definition['visible_if_contains'])) {
            return [[$definition['visible_if_contains'][0], ValueOperator::CONTAINS, [$definition['visible_if_contains'][1]]]];
        }
        if (isset($definition['visible_if_not_contains'])) {
            return [[$definition['visible_if_not_contains'][0], ValueOperator::NOT_CONTAINS, [$definition['visible_if_not_contains'][1]]]];
        }
        if (isset($definition['visible_if_at_least'])) {
            return [[$definition['visible_if_at_least'][0], ValueOperator::GREATER_THAN_OR_EQUALS, (string) $definition['visible_if_at_least'][1]]];
        }

        return [];
    }

    private static function questionType(string $type): string
    {
        return match ($type) {
            'short' => QuestionTypeShortText::class,
            'long' => QuestionTypeLongText::class,
            'radio' => QuestionTypeRadio::class,
            'checkbox' => QuestionTypeCheckbox::class,
            'dropdown' => QuestionTypeDropdown::class,
            'date' => QuestionTypeDateTime::class,
            'file' => QuestionTypeFile::class,
            'user' => QuestionTypeItem::class,
            'urgency' => QuestionTypeUrgency::class,
            'location' => QuestionTypeItemDropdown::class,
            'device' => QuestionTypeUserDevice::class,
            'email' => QuestionTypeEmail::class,
            default => throw new \LogicException("Type de question inconnu : $type"),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function extraData(array $definition): ?array
    {
        return match ($definition['type']) {
            'radio', 'checkbox' => (new QuestionTypeSelectableExtraDataConfig(
                options: $definition['options'],
            ))->jsonSerialize(),
            'dropdown' => (new QuestionTypeDropdownExtraDataConfig(
                options: $definition['options'],
            ))->jsonSerialize(),
            'date' => (new QuestionTypeDateTimeExtraDataConfig(
                is_date_enabled: true,
                is_time_enabled: false,
            ))->jsonSerialize(),
            'user' => (new QuestionTypeItemExtraDataConfig(itemtype: User::class))->jsonSerialize(),
            'location' => (new QuestionTypeItemDropdownExtraDataConfig(itemtype: Location::class))->jsonSerialize(),
            'device' => (new QuestionTypeUserDevicesConfig(is_multiple_devices: false))->jsonSerialize(),
            default => null,
        };
    }

    /**
     * Une destination « Ticket », ou une par option quand la catégorie dépend de la réponse
     * (`routes`) : chacune n'est créée que si son option est choisie, la question étant obligatoire
     * et à choix unique, une soumission crée exactement un ticket.
     *
     * @param array<string, int> $routes
     * @param array<string, Question> $questions
     */
    private function addDestinations(int $formId, Form $form, array $definition, int $itilCategoryId, array $routes, array $questions): bool
    {
        $config = $this->destinationConfig($form, $definition, $questions);
        if ($routes === []) {
            return (bool) (new FormDestination())->add([
                'forms_forms_id' => $formId,
                'itemtype' => FormDestinationTicket::class,
                'name' => Ticket::getTypeName(1),
                'config' => json_encode(self::withCategory($config, $itilCategoryId)),
            ]);
        }

        $routeQuestion = $questions[$definition['routes']['question']] ?? null;
        if ($routeQuestion === null) {
            return false;
        }
        $options = [];
        foreach (self::getSections($definition) as $section) {
            foreach ($section['questions'] as $question) {
                if ($question['key'] === $definition['routes']['question']) {
                    $options = $question['options'];
                }
            }
        }
        foreach ($routes as $option => $categoryId) {
            $condition = (new ConditionData(
                item_uuid: $routeQuestion->fields['uuid'],
                item_type: ConditionType::QUESTION->value,
                value_operator: ValueOperator::EQUALS->value,
                value: (string) $option,
            ))->jsonSerialize();
            $added = (new FormDestination())->add([
                'forms_forms_id' => $formId,
                'itemtype' => FormDestinationTicket::class,
                'name' => Ticket::getTypeName(1) . ' - ' . ($options[$option] ?? $option),
                'config' => json_encode(self::withCategory($config, $categoryId)),
                'creation_strategy' => CreationStrategy::CREATED_IF->value,
                'conditions' => json_encode([$condition]),
            ]);
            if (!$added) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function withCategory(array $config, int $itilCategoryId): array
    {
        $config[ITILCategoryField::getKey()] = (new ITILCategoryFieldConfig(
            strategy: ITILCategoryFieldStrategy::SPECIFIC_VALUE,
            specific_itilcategory_id: $itilCategoryId,
        ))->jsonSerialize();

        return $config;
    }

    /**
     * Configuration commune des destinations, hors catégorie.
     *
     * @param array<string, Question> $questions
     * @return array<string, mixed>
     */
    private function destinationConfig(Form $form, array $definition, array $questions): array
    {
        // « <Nom du formulaire> - <réponse> - <réponse> »
        $answerProvider = new AnswerTagProvider();
        $title = (new FormTagProvider())->getTagForForm($form)->html;
        foreach ($definition['title'] as $key) {
            $title .= ' - ' . $answerProvider->getTagForQuestion($questions[$key])->html;
        }

        $config = [
            TitleField::getKey() => (new SimpleValueConfig($title))->jsonSerialize(),
            ContentField::getAutoConfigKey() => 1,
            RequestTypeField::getKey() => (new RequestTypeFieldConfig(
                strategy: RequestTypeFieldStrategy::SPECIFIC_VALUE,
                specific_request_type: $definition['request_type'] === 'incident'
                    ? Ticket::INCIDENT_TYPE
                    : Ticket::DEMAND_TYPE,
            ))->jsonSerialize(),
        ];
        $questionId = static fn (string $key): int => (int) $questions[$key]->getID();
        if (isset($definition['approval'])) {
            $config[ValidationField::getKey()] = (new ValidationFieldConfig([
                new ValidationFieldStrategyConfig(
                    strategy: ValidationFieldStrategy::SPECIFIC_ANSWERS,
                    specific_question_ids: [$questionId($definition['approval'])],
                ),
            ]))->jsonSerialize();
        }
        if (isset($definition['urgency'])) {
            $config[UrgencyField::getKey()] = (new UrgencyFieldConfig(
                strategy: UrgencyFieldStrategy::SPECIFIC_ANSWER,
                specific_question_id: $questionId($definition['urgency']),
            ))->jsonSerialize();
        }
        if (isset($definition['location'])) {
            $config[LocationField::getKey()] = (new LocationFieldConfig(
                strategy: LocationFieldStrategy::SPECIFIC_ANSWER,
                specific_question_id: $questionId($definition['location']),
            ))->jsonSerialize();
        }
        if (isset($definition['items'])) {
            $config[AssociatedItemsField::getKey()] = (new AssociatedItemsFieldConfig(
                strategies: [AssociatedItemsFieldStrategy::SPECIFIC_ANSWERS],
                specific_question_ids: array_map($questionId, $definition['items']),
            ))->jsonSerialize();
        }
        if (isset($definition['observers'])) {
            $config[ObserverField::getKey()] = (new ObserverFieldConfig(
                strategies: [ITILActorFieldStrategy::SPECIFIC_ANSWERS],
                specific_question_ids: array_map($questionId, $definition['observers']),
            ))->jsonSerialize();
        }

        return $config;
    }
}
