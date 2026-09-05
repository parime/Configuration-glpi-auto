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
use Glpi\Form\QuestionType\QuestionTypeCheckbox;
use Glpi\Form\QuestionType\QuestionTypeDateTime;
use Glpi\Form\QuestionType\QuestionTypeDateTimeExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeLongText;
use Glpi\Form\QuestionType\QuestionTypeSelectableExtraDataConfig;
use Glpi\Form\Section;
use Glpi\Form\Tag\AnswerTagProvider;
use Glpi\Form\Tag\FormTagProvider;
use ITILCategory;

/**
 * One of the service-catalog upgrades generalizing issue #207's pattern to the full catalog (per
 * the plugin owner's explicit request to extend the six-service pilot everywhere) : "Demande de
 * publication sur les réseaux sociaux" (Communication & Marketing / Réseaux Sociaux & Marketing),
 * replacing the plain Title+Description entry that used to live in `ServiceCatalogBuilder::SERVICES`
 * (now flagged `'smart' => true` there and skipped by that builder's own loop).
 *
 * A post can genuinely target several networks at once, so "réseaux" is a `QuestionTypeCheckbox`
 * (same `QuestionTypeSelectableExtraDataConfig` shape as any radio in this catalog, just multi-select)
 * rather than a radio — and the desired publication date is always relevant, no branch needed. Same
 * "all fields answered every time" shape as the pilot (`AbroadMissionFormBuilder`, issue #208),
 * computed title built the same way (`AnswerTagProvider::getTagForQuestion()` /
 * `FormTagProvider::getTagForForm()`, never hand-written `<span>` markup). Also adds the "Précisions
 * complémentaires" free-text field every class in this generalization wave adds, replacing the
 * generic Description these smart forms no longer have.
 */
class SocialMediaPostFormBuilder
{
    private const FORM_NAME = 'Demande de publication sur les réseaux sociaux';

    private const BRANCH_KEY = 'communication';

    private const CATEGORY_PATH = ['Réseaux Sociaux & Marketing'];

    // Same icon `ServiceCatalogBuilder::BRANCH_ILLUSTRATIONS['communication']` already gave this
    // branch's other forms.
    private const ILLUSTRATION = 'presentation';

    private const RESEAUX_OPTIONS = [
        '1' => 'LinkedIn',
        '2' => 'Facebook',
        '3' => 'Instagram',
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
     * @return array{reseaux: Question, datePublication: Question}|null
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

        $reseaux = new Question();
        $reseaux->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Réseau(x) concerné(s)', 'configurationglpiauto'),
            'type' => QuestionTypeCheckbox::class,
            'is_mandatory' => 1,
            'vertical_rank' => 0,
            'extra_data' => json_encode((new QuestionTypeSelectableExtraDataConfig(
                options: self::RESEAUX_OPTIONS,
            ))->jsonSerialize()),
        ]);

        $datePublication = new Question();
        $datePublication->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Date de publication souhaitée', 'configurationglpiauto'),
            'type' => QuestionTypeDateTime::class,
            'is_mandatory' => 1,
            'vertical_rank' => 1,
            'extra_data' => json_encode((new QuestionTypeDateTimeExtraDataConfig(
                is_default_value_current_time: false,
                is_date_enabled: true,
                is_time_enabled: false,
            ))->jsonSerialize()),
        ]);

        $precisions = new Question();
        $precisions->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Précisions complémentaires', 'configurationglpiauto'),
            'type' => QuestionTypeLongText::class,
            'is_mandatory' => 0,
            'vertical_rank' => 2,
        ]);

        if (!$reseaux->getID() || !$datePublication->getID() || !$precisions->getID()) {
            return null;
        }

        return ['reseaux' => $reseaux, 'datePublication' => $datePublication];
    }

    /**
     * @param array{reseaux: Question, datePublication: Question} $questions
     */
    private function configureDestination(Form $form, int $itilCategoryId, array $questions): void
    {
        $destination = new FormDestination();
        if (!$destination->getFromDBByCrit(['forms_forms_id' => $form->getID(), 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        $answerProvider = new AnswerTagProvider();
        $formProvider = new FormTagProvider();

        // "<Nom du formulaire> - <réseaux>"
        $titleValue = $formProvider->getTagForForm($form)->html
            . ' - ' . $answerProvider->getTagForQuestion($questions['reseaux'])->html;

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
