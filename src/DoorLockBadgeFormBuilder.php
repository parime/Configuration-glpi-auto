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

use Glpi\Asset\AssetDefinition;
use Glpi\Form\Category as FormCategory;
use Glpi\Form\Destination\CommonITILField\AssociatedItemsField;
use Glpi\Form\Destination\CommonITILField\AssociatedItemsFieldConfig;
use Glpi\Form\Destination\CommonITILField\AssociatedItemsFieldStrategy;
use Glpi\Form\Destination\CommonITILField\ContentField;
use Glpi\Form\Destination\CommonITILField\ITILCategoryField;
use Glpi\Form\Destination\CommonITILField\ITILCategoryFieldConfig;
use Glpi\Form\Destination\CommonITILField\ITILCategoryFieldStrategy;
use Glpi\Form\Destination\CommonITILField\RequestTypeField;
use Glpi\Form\Destination\CommonITILField\RequestTypeFieldConfig;
use Glpi\Form\Destination\CommonITILField\RequestTypeFieldStrategy;
use Glpi\Form\Destination\CommonITILField\SimpleValueConfig;
use Glpi\Form\Destination\CommonITILField\TitleField;
use Glpi\Form\Destination\FormDestination;
use Glpi\Form\Destination\FormDestinationTicket;
use Glpi\Form\Form;
use Glpi\Form\Question;
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

/**
 * One of the service-catalog upgrades generalizing issue #207's pattern (pioneered by
 * `AccessBadgeFormBuilder`/`MeetingRoomFormBuilder`/`VpnAccessFormBuilder`) to the rest of the
 * catalog per explicit maintainer request : "Problème de porte, serrure ou badge de local" (Bâtiment
 * & Moyens Généraux / Serrurerie, Portes & Fenêtres), replacing the plain Title+Description entry
 * that used to live in `ServiceCatalogBuilder::SERVICES` (now flagged `'smart' => true` there and
 * skipped by that builder's own loop).
 *
 * Same "where + what" shape as `HeatingClimateFormBuilder`, no conditional branch : the four
 * outcomes (stuck/broken lock, misaligned door, unrecognized badge, other) don't change what else
 * needs asking. Every smart form in this generalization also gets a final free-text "Précisions
 * complémentaires" question as an escape hatch, replacing the generic Description field these forms
 * no longer have.
 *
 * Computed title only references the always-visible `localisation` answer — "<form name> -
 * <localisation>", tag-built via `AnswerTagProvider`/`FormTagProvider`, never hand-written markup.
 *
 * **Second pass (advanced question types)** : same optional `QuestionTypeItem`(`SecuritePhysique`)
 * addition as `VideoSurveillanceFormBuilder` — "Serrure électronique" and "Contrôle d'accès (lecteur
 * de badge)" are two of that asset's own native types, a direct match for this service. Unlike the
 * vehicle/room conversions elsewhere in this pass, this form's own branch (`batiment`) is *not* the
 * same gate `PhysicalSecurityAssetBuilder` requires (`securite` branch + its own opt-in toggle), so
 * the check here verifies both independently rather than assuming — a customer can easily pick
 * "batiment" without "securite". "Localisation précise" is kept exactly as is (not every door/lock
 * problem is on a referenced electronic asset — most physical/mechanical locks never will be), the
 * new question is purely additive and optional. Wired to `AssociatedItemsField`
 * (`LAST_VALID_ANSWER`). `RequestTypeField` pinned to `Ticket::INCIDENT_TYPE` (`SPECIFIC_VALUE`, no
 * question asked) : a lock/door/badge malfunction is unambiguously an incident.
 */
class DoorLockBadgeFormBuilder
{
    private const FORM_NAME = 'Problème de porte, serrure ou badge de local';

    private const BRANCH_KEY = 'batiment';

    private const CATEGORY_PATH = ['Serrurerie, Portes & Fenêtres'];

    // Matches `PhysicalSecurityAssetBuilder::SYSTEM_NAME` — see `VideoSurveillanceFormBuilder`'s
    // identical constant.
    private const SECURITY_ASSET_SYSTEM_NAME = 'SecuritePhysique';

    // Same icon `ServiceCatalogBuilder::BRANCH_ILLUSTRATIONS['batiment']` already gave this
    // branch's other forms.
    private const ILLUSTRATION = 'building';

    private const NATURE_OPTIONS = [
        '1' => 'Serrure bloquée ou cassée',
        '2' => 'Porte qui ferme mal',
        '3' => 'Badge non reconnu',
        '4' => 'Autre',
    ];

    public function build(Config $config): bool
    {
        if (empty($config->fields['service_catalog_enabled'])) {
            return false;
        }

        if (!in_array(self::BRANCH_KEY, $config->getCategoryBranches(), true)) {
            return false;
        }

        $branchLookup = [];
        foreach (CategoryBuilder::getCategoriesPreview() as $branch) {
            $branchLookup[$branch['key']] = $branch;
        }
        $branch = $branchLookup[self::BRANCH_KEY] ?? null;
        if ($branch === null) {
            return false;
        }

        $itilCategoryId = $this->resolveItilCategoryId($branch);
        if ($itilCategoryId === null) {
            return false;
        }

        $formCategoryId = $this->getOrCreateFormCategory($branch);

        return $this->getOrCreateForm($config, $formCategoryId, $itilCategoryId);
    }

    private function resolveItilCategoryId(array $branch): ?int
    {
        $item = new ITILCategory();
        if (!$item->getFromDBByCrit(['name' => $branch['name'], 'itilcategories_id' => 0])) {
            return null;
        }
        $parentId = (int) $item->getID();

        foreach (self::CATEGORY_PATH as $name) {
            if (!$item->getFromDBByCrit(['name' => $name, 'itilcategories_id' => $parentId])) {
                return null;
            }
            $parentId = (int) $item->getID();
        }

        return $parentId;
    }

    private function getOrCreateFormCategory(array $branch): int
    {
        $item = new FormCategory();
        $name = $branch['icon'] . ' ' . $branch['name'];
        if (!$item->getFromDBByCrit(['name' => $name, 'forms_categories_id' => 0])) {
            $id = $item->add([
                'name' => $name,
                'forms_categories_id' => 0,
                'illustration' => '',
            ]);
            $item->getFromDB($id);
        }

        return (int) $item->getID();
    }

    private function getOrCreateForm(Config $config, int $formCategoryId, int $itilCategoryId): bool
    {
        $form = new Form();
        if ($form->getFromDBByCrit(['name' => self::FORM_NAME, 'forms_categories_id' => $formCategoryId])) {
            return true;
        }

        $formId = $form->add([
            'name' => self::FORM_NAME,
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

        $questions = $this->addQuestions($form, $config);
        if ($questions === null) {
            return false;
        }

        $this->configureDestination($form, $itilCategoryId, $questions);

        return true;
    }

    /**
     * Same lookup pattern as `VideoSurveillanceFormBuilder::resolveSecurityAssetItemtype()` — see
     * that class's docblock. Also checks the `securite` branch explicitly (unlike
     * `VideoSurveillanceFormBuilder`, this form's own `batiment` gate doesn't imply it).
     */
    private function resolveSecurityAssetItemtype(Config $config): ?string
    {
        if (
            !in_array('securite', $config->getCategoryBranches(), true)
            || empty($config->fields['physical_security_assets_enabled'])
        ) {
            return null;
        }

        $definition = new AssetDefinition();
        if (!$definition->getFromDBByCrit(['system_name' => self::SECURITY_ASSET_SYSTEM_NAME])) {
            return null;
        }

        return $definition->getAssetClassName();
    }

    /**
     * @return array{localisation: Question, nature: Question}|null
     */
    private function addQuestions(Form $form, Config $config): ?array
    {
        $section = new Section();
        if (!$section->getFromDBByCrit(['forms_forms_id' => $form->getID()])) {
            return null;
        }
        $section->update([
            'id' => $section->getID(),
            'name' => __('Votre demande', 'configurationglpiauto'),
        ]);
        $sectionId = (int) $section->getID();

        $localisation = new Question();
        $localisation->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Localisation précise', 'configurationglpiauto'),
            'type' => QuestionTypeShortText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 0,
        ]);

        $nature = new Question();
        $nature->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Nature du problème', 'configurationglpiauto'),
            'type' => QuestionTypeRadio::class,
            'is_mandatory' => 1,
            'vertical_rank' => 1,
            'extra_data' => json_encode((new QuestionTypeSelectableExtraDataConfig(
                options: self::NATURE_OPTIONS,
            ))->jsonSerialize()),
        ]);

        // Optional: only added when this plugin's own physical-security asset catalog actually
        // exists — see class docblock.
        $securityAssetItemtype = $this->resolveSecurityAssetItemtype($config);
        $equipement = null;
        if ($securityAssetItemtype !== null) {
            $equipement = new Question();
            $equipement->add([
                'forms_sections_id' => $sectionId,
                'name' => __('Équipement concerné (si déjà référencé)', 'configurationglpiauto'),
                'type' => QuestionTypeItem::class,
                'is_mandatory' => 0,
                'vertical_rank' => 2,
                'extra_data' => json_encode((new QuestionTypeItemExtraDataConfig(
                    itemtype: $securityAssetItemtype,
                    root_items_id: 0,
                    subtree_depth: 0,
                    selectable_tree_root: false,
                ))->jsonSerialize()),
            ]);
            if (!$equipement->getID()) {
                return null;
            }
        }

        $precisions = new Question();
        $precisions->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Précisions complémentaires', 'configurationglpiauto'),
            'type' => QuestionTypeLongText::class,
            'is_mandatory' => 0,
            'vertical_rank' => 3,
        ]);

        if (!$localisation->getID() || !$nature->getID() || !$precisions->getID()) {
            return null;
        }

        return ['localisation' => $localisation, 'nature' => $nature];
    }

    /**
     * @param array{localisation: Question, nature: Question} $questions
     */
    private function configureDestination(Form $form, int $itilCategoryId, array $questions): void
    {
        $destination = new FormDestination();
        if (!$destination->getFromDBByCrit(['forms_forms_id' => $form->getID(), 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        $answerProvider = new AnswerTagProvider();
        $formProvider = new FormTagProvider();

        // "<Nom du formulaire> - <localisation>"
        $titleValue = $formProvider->getTagForForm($form)->html
            . ' - ' . $answerProvider->getTagForQuestion($questions['localisation'])->html;

        $config = [
            ITILCategoryField::getKey() => (new ITILCategoryFieldConfig(
                strategy: ITILCategoryFieldStrategy::SPECIFIC_VALUE,
                specific_itilcategory_id: $itilCategoryId,
            ))->jsonSerialize(),
            TitleField::getKey() => (new SimpleValueConfig($titleValue))->jsonSerialize(),
            ContentField::getAutoConfigKey() => 1,
            AssociatedItemsField::getKey() => (new AssociatedItemsFieldConfig(
                strategies: [AssociatedItemsFieldStrategy::LAST_VALID_ANSWER],
            ))->jsonSerialize(),
            RequestTypeField::getKey() => (new RequestTypeFieldConfig(
                strategy: RequestTypeFieldStrategy::SPECIFIC_VALUE,
                specific_request_type: Ticket::INCIDENT_TYPE,
            ))->jsonSerialize(),
        ];

        $destination->update([
            'id' => $destination->getID(),
            'config' => json_encode($config),
        ]);
    }
}
