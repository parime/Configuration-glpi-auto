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
use Glpi\Form\Destination\CommonITILField\ContentField;
use Glpi\Form\Destination\CommonITILField\ITILCategoryField;
use Glpi\Form\Destination\CommonITILField\ITILCategoryFieldConfig;
use Glpi\Form\Destination\CommonITILField\ITILCategoryFieldStrategy;
use Glpi\Form\Destination\CommonITILField\SimpleValueConfig;
use Glpi\Form\Destination\CommonITILField\TitleField;
use Glpi\Form\Destination\FormDestination;
use Glpi\Form\Destination\FormDestinationTicket;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\QuestionType\QuestionTypeDateTime;
use Glpi\Form\QuestionType\QuestionTypeDateTimeExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Form\Section;
use Glpi\Form\Tag\AnswerTagProvider;
use Glpi\Form\Tag\FormTagProvider;
use ITILCategory;

/**
 * One of the ~6 service-catalog upgrades for issue #207 : "Demande de badge d'accès" (Sécurité &
 * Protection des Personnes / Contrôle d'Accès & Badges), replacing the plain Title+Description
 * entry that used to live in `ServiceCatalogBuilder::SERVICES` (now flagged `'smart' => true` there
 * and skipped by that builder's own loop).
 *
 * Computed title only, no conditional question : a badge request's zone and validity window are
 * always relevant together, same "all fields answered every time" shape as the pilot
 * (`AbroadMissionFormBuilder`, issue #208) which this class copies verbatim (both dates mandatory,
 * `AnswerTagProvider::getTagForQuestion()` / `FormTagProvider::getTagForForm()` used to build the
 * title, never hand-written `<span>` markup). Deliberately doesn't try to shoehorn a "temporary vs
 * permanent" branch like `VpnAccessFormBuilder` does : a badge is a physical object with a real
 * expiry printed/encoded on it, security teams manage that as a defined end date either way (even a
 * long one), unlike a VPN grant where "permanent" genuinely has no end date to ask for.
 */
class AccessBadgeFormBuilder
{
    private const FORM_NAME = "Demande de badge d'accès";

    private const BRANCH_KEY = 'securite';

    private const CATEGORY_PATH = ["Contrôle d'Accès & Badges"];

    // Same icon `ServiceCatalogBuilder::BRANCH_ILLUSTRATIONS['securite']` already gave this
    // branch's other forms.
    private const ILLUSTRATION = 'security';

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

        return $this->getOrCreateForm($formCategoryId, $itilCategoryId);
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

    private function getOrCreateForm(int $formCategoryId, int $itilCategoryId): bool
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

        $questions = $this->addQuestions($form);
        if ($questions === null) {
            return false;
        }

        $this->configureDestination($form, $itilCategoryId, $questions);

        return true;
    }

    /**
     * @return array{zone: Question, debut: Question, fin: Question}|null
     */
    private function addQuestions(Form $form): ?array
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

        $dateExtraData = json_encode((new QuestionTypeDateTimeExtraDataConfig(
            is_default_value_current_time: false,
            is_date_enabled: true,
            is_time_enabled: false,
        ))->jsonSerialize());

        $zone = new Question();
        $zone->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Zone ou local concerné', 'configurationglpiauto'),
            'type' => QuestionTypeShortText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 0,
        ]);

        $debut = new Question();
        $debut->add([
            'forms_sections_id' => $sectionId,
            'name' => __("Date de début d'accès", 'configurationglpiauto'),
            'type' => QuestionTypeDateTime::class,
            'is_mandatory' => 1,
            'vertical_rank' => 1,
            'extra_data' => $dateExtraData,
        ]);

        $fin = new Question();
        $fin->add([
            'forms_sections_id' => $sectionId,
            'name' => __("Date de fin d'accès", 'configurationglpiauto'),
            'type' => QuestionTypeDateTime::class,
            'is_mandatory' => 1,
            'vertical_rank' => 2,
            'extra_data' => $dateExtraData,
        ]);

        if (!$zone->getID() || !$debut->getID() || !$fin->getID()) {
            return null;
        }

        return ['zone' => $zone, 'debut' => $debut, 'fin' => $fin];
    }

    /**
     * @param array{zone: Question, debut: Question, fin: Question} $questions
     */
    private function configureDestination(Form $form, int $itilCategoryId, array $questions): void
    {
        $destination = new FormDestination();
        if (!$destination->getFromDBByCrit(['forms_forms_id' => $form->getID(), 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        $answerProvider = new AnswerTagProvider();
        $formProvider = new FormTagProvider();

        // "<Nom du formulaire> - <zone> du <début> au <fin>"
        $titleValue = $formProvider->getTagForForm($form)->html
            . ' - ' . $answerProvider->getTagForQuestion($questions['zone'])->html
            . ' ' . __('du', 'configurationglpiauto') . ' ' . $answerProvider->getTagForQuestion($questions['debut'])->html
            . ' ' . __('au', 'configurationglpiauto') . ' ' . $answerProvider->getTagForQuestion($questions['fin'])->html;

        $config = [
            ITILCategoryField::getKey() => (new ITILCategoryFieldConfig(
                strategy: ITILCategoryFieldStrategy::SPECIFIC_VALUE,
                specific_itilcategory_id: $itilCategoryId,
            ))->jsonSerialize(),
            TitleField::getKey() => (new SimpleValueConfig($titleValue))->jsonSerialize(),
            ContentField::getAutoConfigKey() => 1,
        ];

        $destination->update([
            'id' => $destination->getID(),
            'config' => json_encode($config),
        ]);
    }
}
