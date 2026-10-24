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
use Glpi\Form\Condition\Type as ConditionType;
use Glpi\Form\Condition\ValueOperator;
use Glpi\Form\Condition\VisibilityStrategy;
use Glpi\Form\Destination\CommonITILField\ContentField;
use Glpi\Form\Destination\CommonITILField\ITILCategoryField;
use Glpi\Form\Destination\CommonITILField\ITILCategoryFieldConfig;
use Glpi\Form\Destination\CommonITILField\ITILCategoryFieldStrategy;
use Glpi\Form\Destination\CommonITILField\RequestTypeField;
use Glpi\Form\Destination\CommonITILField\RequestTypeFieldConfig;
use Glpi\Form\Destination\CommonITILField\RequestTypeFieldStrategy;
use Glpi\Form\Destination\CommonITILField\SimpleValueConfig;
use Glpi\Form\Destination\CommonITILField\TitleField;
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
use Glpi\Form\QuestionType\QuestionTypeFile;
use Glpi\Form\QuestionType\QuestionTypeItem;
use Glpi\Form\QuestionType\QuestionTypeItemExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeLongText;
use Glpi\Form\QuestionType\QuestionTypeRadio;
use Glpi\Form\QuestionType\QuestionTypeSelectableExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Form\Section;
use Glpi\Form\Tag\AnswerTagProvider;
use Glpi\Form\Tag\FormTagProvider;
use ITILCategory;
use Ticket;
use User;

/**
 * Issue #346 : 13 formulaires du catalogue de services tirés de catégories de demandes récurrentes
 * (support infrastructure et application métier), décrits par des données plutôt que par 13
 * builders copiés de `LaptopRequestFormBuilder`. Même forme que les formulaires « intelligents »
 * existants : questions typées, questions conditionnelles (`VISIBLE_IF` sur la clé d'une option),
 * titre de ticket calculé (`FormTagProvider` + `AnswerTagProvider`), catégorie ITIL fixe, type de
 * demande fixe.
 *
 * Deux formulaires demandent une **approbation** avant exécution (accès à un dossier partagé,
 * correction de données en base, mouvement de stock en masse) : le demandeur désigne la personne
 * qui valide (question « Utilisateur »), et le ticket naît avec une approbation à son nom
 * (`ValidationField`, stratégie `SPECIFIC_ANSWERS`). Rien n'est exécuté sans elle : c'est la trace
 * attendue en gestion des changements (A.8.32).
 *
 * Mêmes conditions que les autres formulaires du catalogue : `service_catalog_enabled` et la
 * branche cochée ; une catégorie introuvable (branche décochée, catégorie renommée) saute le
 * formulaire au lieu de le créer sans catégorie. Un formulaire déjà présent (même nom, même
 * rubrique) est laissé tel quel : l'administrateur a pu l'adapter.
 */
class RequestFormLibraryBuilder
{
    private const BRANCH_KEY = 'it';

    private const ILLUSTRATION = 'asset-desktop-1';

    /** Catégorie ITIL (sous IT & SI) et sous-rubrique du catalogue des demandes d'application métier. */
    private const BUSINESS_APPLICATION = 'Application métier';

    private const BUSINESS_ILLUSTRATION = 'factory';

    /**
     * Clé => définition (libellés traduits ; noms de formulaires et options en français, comme le
     * reste du catalogue). `title` : clés des questions reprises dans le titre du ticket.
     * `approval` : clé de la question « Utilisateur » qui désigne l'approbateur.
     * Types de question : short, long, radio, checkbox, dropdown, date, file, user.
     */
    private static function definitions(): array
    {
        return [
            'shared_folder' => [
                'name' => 'Accès à un dossier partagé',
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
                    ['key' => 'reason', 'type' => 'long', 'mandatory' => true, 'label' => __('Justification', 'configurationglpiauto')],
                ],
            ],
            'locked_account' => [
                'name' => 'Compte bloqué ou session verrouillée',
                'path' => ['Comptes & Identités', 'Mot de passe & Réinitialisation'],
                'request_type' => 'incident',
                'title' => ['account'],
                'questions' => [
                    ['key' => 'account', 'type' => 'radio', 'mandatory' => true, 'label' => __('Compte concerné', 'configurationglpiauto'),
                        'options' => ['1' => 'Session Windows', '2' => 'Messagerie', '3' => 'Application métier', '4' => 'Autre']],
                    ['key' => 'other', 'type' => 'short', 'mandatory' => true, 'label' => __('Quelle application ?', 'configurationglpiauto'),
                        'visible_if' => ['account', '4']],
                    ['key' => 'message', 'type' => 'long', 'mandatory' => false, 'label' => __("Message d'erreur affiché", 'configurationglpiauto')],
                ],
            ],
            'data_restore' => [
                'name' => 'Restauration de données',
                'path' => ['Sauvegardes & Restauration'],
                'request_type' => 'demand',
                'title' => ['what', 'urgency'],
                'questions' => [
                    ['key' => 'what', 'type' => 'radio', 'mandatory' => true, 'label' => __('Que faut-il restaurer ?', 'configurationglpiauto'),
                        'options' => ['1' => 'Un ou plusieurs fichiers', '2' => 'Un dossier complet', '3' => 'Une boîte mail',
                            '4' => 'Une base de données', '5' => 'Une machine virtuelle']],
                    ['key' => 'location', 'type' => 'short', 'mandatory' => true, 'label' => __('Emplacement (chemin, serveur, application)', 'configurationglpiauto')],
                    ['key' => 'version', 'type' => 'date', 'mandatory' => true, 'label' => __('Date de la version souhaitée', 'configurationglpiauto')],
                    ['key' => 'urgency', 'type' => 'radio', 'mandatory' => true, 'label' => __('Urgence', 'configurationglpiauto'),
                        'options' => ['1' => 'Bloquant', '2' => 'Dans la journée', '3' => 'Sans urgence']],
                    ['key' => 'details', 'type' => 'long', 'mandatory' => false, 'label' => __('Précisions (noms de fichiers, cause de la perte)', 'configurationglpiauto')],
                ],
            ],
            'removable_device' => [
                'name' => 'Périphérique USB ou support amovible',
                'path' => ['Sécurité SI', 'Périphériques & Supports amovibles'],
                'request_type' => 'demand',
                'title' => ['action'],
                'questions' => [
                    ['key' => 'action', 'type' => 'radio', 'mandatory' => true, 'label' => __('Demande', 'configurationglpiauto'),
                        'options' => ['1' => 'Autoriser un périphérique', '2' => 'Bloquer un périphérique', '3' => 'Renommer ou identifier un périphérique']],
                    ['key' => 'device', 'type' => 'short', 'mandatory' => true, 'label' => __('Périphérique (type, marque, numéro de série)', 'configurationglpiauto')],
                    ['key' => 'duration', 'type' => 'radio', 'mandatory' => true, 'label' => __('Durée', 'configurationglpiauto'),
                        'options' => ['1' => 'Ponctuelle (moins d\'une semaine)', '2' => 'Temporaire (date de fin)', '3' => 'Permanente'],
                        'visible_if' => ['action', '1']],
                    ['key' => 'end', 'type' => 'date', 'mandatory' => true, 'label' => __('Date de fin', 'configurationglpiauto'), 'visible_if' => ['duration', '2']],
                    ['key' => 'reason', 'type' => 'long', 'mandatory' => true, 'label' => __('Justification', 'configurationglpiauto')],
                ],
            ],
            'label_printing' => [
                'name' => "Problème d'impression d'étiquettes ou de codes-barres",
                'path' => ['Impression', 'Étiquettes & Codes-barres'],
                'request_type' => 'incident',
                'title' => ['printer'],
                'questions' => [
                    ['key' => 'printer', 'type' => 'short', 'mandatory' => true, 'label' => __('Imprimante concernée (nom ou emplacement)', 'configurationglpiauto')],
                    ['key' => 'application', 'type' => 'short', 'mandatory' => true, 'label' => __("Application qui lance l'impression", 'configurationglpiauto')],
                    ['key' => 'label', 'type' => 'short', 'mandatory' => false, 'label' => __("Modèle d'étiquette", 'configurationglpiauto')],
                    ['key' => 'symptom', 'type' => 'radio', 'mandatory' => true, 'label' => __('Symptôme', 'configurationglpiauto'),
                        'options' => ['1' => "Rien ne s'imprime", '2' => 'Impression décalée ou tronquée', '3' => 'Code-barres illisible',
                            '4' => 'Mauvaises données imprimées', '5' => 'Autre']],
                    ['key' => 'sample', 'type' => 'file', 'mandatory' => false, 'label' => __("Photo ou exemple de l'étiquette", 'configurationglpiauto')],
                ],
            ],
            'partner_request' => [
                'name' => "Demande d'un partenaire externe",
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
                'path' => ['Application métier', 'Données & Corrections'],
                'request_type' => 'demand',
                'title' => ['record'],
                'approval' => 'approver',
                'questions' => [
                    ['key' => 'record', 'type' => 'short', 'mandatory' => true, 'label' => __('Enregistrement concerné (numéro, référence)', 'configurationglpiauto')],
                    ['key' => 'current', 'type' => 'long', 'mandatory' => true, 'label' => __('Valeur actuelle', 'configurationglpiauto')],
                    ['key' => 'expected', 'type' => 'long', 'mandatory' => true, 'label' => __('Valeur attendue', 'configurationglpiauto')],
                    ['key' => 'reason', 'type' => 'long', 'mandatory' => true, 'label' => __('Justification', 'configurationglpiauto')],
                    ['key' => 'approver', 'type' => 'user', 'mandatory' => true, 'label' => __('Responsable qui valide la correction', 'configurationglpiauto'),
                        'help' => __("La correction ne sera faite qu'après son approbation dans le ticket.", 'configurationglpiauto')],
                    ['key' => 'attachment', 'type' => 'file', 'mandatory' => false, 'label' => __("Capture d'écran ou fichier", 'configurationglpiauto')],
                ],
            ],
            'report_extract' => [
                'name' => "Extraction de données ou rapport",
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
                'path' => ['Application métier', 'Évolutions'],
                'request_type' => 'demand',
                'title' => ['application'],
                'questions' => [
                    ['key' => 'application', 'type' => 'short', 'mandatory' => true, 'label' => __('Application concernée', 'configurationglpiauto')],
                    ['key' => 'need', 'type' => 'long', 'mandatory' => true, 'label' => __('Besoin métier', 'configurationglpiauto')],
                    ['key' => 'gain', 'type' => 'long', 'mandatory' => true, 'label' => __('Gain attendu (temps, erreurs évitées, chiffre)', 'configurationglpiauto')],
                    ['key' => 'users', 'type' => 'radio', 'mandatory' => true, 'label' => __('Utilisateurs concernés', 'configurationglpiauto'),
                        'options' => ['1' => 'Moi seulement', '2' => 'Mon équipe', '3' => 'Plusieurs services', '4' => 'Toute l\'entreprise']],
                    ['key' => 'deadline', 'type' => 'date', 'mandatory' => false, 'label' => __('Date souhaitée', 'configurationglpiauto')],
                ],
            ],
            'interface_error' => [
                'name' => "Erreur d'interface ou de traitement automatique",
                'path' => ['Application métier', 'Interfaces & Traitements'],
                'request_type' => 'incident',
                'title' => ['interface'],
                'questions' => [
                    ['key' => 'interface', 'type' => 'short', 'mandatory' => true, 'label' => __('Interface ou traitement (import, échange partenaire, lot de nuit…)', 'configurationglpiauto')],
                    ['key' => 'when', 'type' => 'date', 'mandatory' => true, 'label' => __("Date de l'erreur", 'configurationglpiauto')],
                    ['key' => 'message', 'type' => 'long', 'mandatory' => true, 'label' => __("Message d'erreur", 'configurationglpiauto')],
                    ['key' => 'volume', 'type' => 'short', 'mandatory' => false, 'label' => __('Volume touché (commandes, lignes, fichiers)', 'configurationglpiauto')],
                    ['key' => 'impact', 'type' => 'radio', 'mandatory' => true, 'label' => __('Impact', 'configurationglpiauto'),
                        'options' => ['1' => 'Activité bloquée', '2' => 'Activité ralentie', '3' => 'Pas d\'impact immédiat']],
                    ['key' => 'attachment', 'type' => 'file', 'mandatory' => false, 'label' => __('Fichier ou journal', 'configurationglpiauto')],
                ],
            ],
            'new_site' => [
                'name' => "Nouveau site ou entrepôt dans une application",
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
                    ['key' => 'reason', 'type' => 'long', 'mandatory' => true, 'label' => __('Justification', 'configurationglpiauto')],
                ],
            ],
            'label_template' => [
                'name' => "Nouveau modèle d'étiquette ou modification",
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
        ];
    }

    /**
     * @return int Nombre de formulaires créés ou déjà présents.
     */
    public function build(Config $config): int
    {
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
        foreach (self::definitions() as $definition) {
            $itilCategoryId = $this->resolveItilCategoryId($branch, $definition['path']);
            $rubricId = $definition['path'][0] === self::BUSINESS_APPLICATION ? $businessRubricId : $formCategoryId;
            if ($itilCategoryId !== null && $this->getOrCreateForm($definition, $rubricId, $itilCategoryId)) {
                $count++;
            }
        }

        return $count;
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

    private function getOrCreateForm(array $definition, int $formCategoryId, int $itilCategoryId): bool
    {
        $form = new Form();
        if (Lookup::loadFirst($form, ['name' => $definition['name'], 'forms_categories_id' => $formCategoryId])) {
            return true;
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
            return false;
        }
        $form->getFromDB($formId);

        $questions = $this->addQuestions($form, $definition['questions']);
        if ($questions === null) {
            return false;
        }
        $this->configureDestination($form, $definition, $itilCategoryId, $questions);

        return true;
    }

    /**
     * @param list<array<string, mixed>> $definitions
     * @return array<string, Question>|null
     */
    private function addQuestions(Form $form, array $definitions): ?array
    {
        $section = new Section();
        if (!Lookup::loadFirst($section, ['forms_forms_id' => $form->getID()])) {
            return null;
        }
        $section->update(['id' => $section->getID(), 'name' => __('Votre demande', 'configurationglpiauto')]);

        $questions = [];
        foreach ($definitions as $rank => $definition) {
            $input = [
                'forms_sections_id' => (int) $section->getID(),
                'name' => $definition['label'],
                'type' => self::questionType($definition['type']),
                'is_mandatory' => !empty($definition['mandatory']) ? 1 : 0,
                'vertical_rank' => $rank,
            ];
            if (isset($definition['help'])) {
                $input['description'] = $definition['help'];
            }
            $extraData = self::extraData($definition);
            if ($extraData !== null) {
                $input['extra_data'] = json_encode($extraData);
            }
            $condition = $definition['visible_if'] ?? $definition['visible_if_not'] ?? null;
            if ($condition !== null) {
                $parent = $questions[$condition[0]] ?? null;
                if ($parent === null) {
                    return null;
                }
                $input['visibility_strategy'] = VisibilityStrategy::VISIBLE_IF->value;
                $input['conditions'] = json_encode([(new ConditionData(
                    item_uuid: $parent->fields['uuid'],
                    item_type: ConditionType::QUESTION->value,
                    value_operator: isset($definition['visible_if'])
                        ? ValueOperator::EQUALS->value
                        : ValueOperator::NOT_EQUALS->value,
                    value: $condition[1],
                ))->jsonSerialize()]);
            }

            $question = new Question();
            if (!$question->add($input)) {
                return null;
            }
            $question->getFromDB($question->getID());
            $questions[$definition['key']] = $question;
        }

        return $questions;
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
            default => null,
        };
    }

    /**
     * @param array<string, Question> $questions
     */
    private function configureDestination(Form $form, array $definition, int $itilCategoryId, array $questions): void
    {
        $destination = new FormDestination();
        if (!Lookup::loadFirst($destination, ['forms_forms_id' => $form->getID(), 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        // « <Nom du formulaire> - <réponse> - <réponse> »
        $answerProvider = new AnswerTagProvider();
        $title = (new FormTagProvider())->getTagForForm($form)->html;
        foreach ($definition['title'] as $key) {
            $title .= ' - ' . $answerProvider->getTagForQuestion($questions[$key])->html;
        }

        $config = [
            ITILCategoryField::getKey() => (new ITILCategoryFieldConfig(
                strategy: ITILCategoryFieldStrategy::SPECIFIC_VALUE,
                specific_itilcategory_id: $itilCategoryId,
            ))->jsonSerialize(),
            TitleField::getKey() => (new SimpleValueConfig($title))->jsonSerialize(),
            ContentField::getAutoConfigKey() => 1,
            RequestTypeField::getKey() => (new RequestTypeFieldConfig(
                strategy: RequestTypeFieldStrategy::SPECIFIC_VALUE,
                specific_request_type: $definition['request_type'] === 'incident'
                    ? Ticket::INCIDENT_TYPE
                    : Ticket::DEMAND_TYPE,
            ))->jsonSerialize(),
        ];
        if (isset($definition['approval'])) {
            $config[ValidationField::getKey()] = (new ValidationFieldConfig([
                new ValidationFieldStrategyConfig(
                    strategy: ValidationFieldStrategy::SPECIFIC_ANSWERS,
                    specific_question_ids: [(int) $questions[$definition['approval']]->getID()],
                ),
            ]))->jsonSerialize();
        }

        $destination->update(['id' => $destination->getID(), 'config' => json_encode($config)]);
    }
}
