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
use Glpi\Form\Destination\CommonITILField\SimpleValueConfig;
use Glpi\Form\Destination\CommonITILField\TitleField;
use Glpi\Form\Destination\FormDestination;
use Glpi\Form\Destination\FormDestinationTicket;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\QuestionType\QuestionTypeDateTime;
use Glpi\Form\QuestionType\QuestionTypeDateTimeExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeRadio;
use Glpi\Form\QuestionType\QuestionTypeSelectableExtraDataConfig;
use Glpi\Form\Section;
use Glpi\Form\Tag\AnswerTagProvider;
use Glpi\Form\Tag\FormTagProvider;
use ITILCategory;

/**
 * One of the ~6 service-catalog upgrades for issue #207 : "Demande de congé ou absence" (RH /
 * Absences & Congés), replacing the plain Title+Description entry `ServiceCatalogBuilder::SERVICES`
 * used to seed for it (that entry is now flagged `'smart' => true` there and skipped by its own
 * build loop so this class is the sole owner of the form).
 *
 * Picked for both mechanisms the issue asks about, not one in isolation:
 * - **Computed title**: a leave request's dates are exactly the "at a glance in a ticket list" value
 *   the issue describes, same reasoning as the already-merged pilot (`AbroadMissionFormBuilder`,
 *   issue #208) which this class copies the tag-provider approach from verbatim
 *   (`AnswerTagProvider::getTagForQuestion()` / `FormTagProvider::getTagForForm()`, never hand-written
 *   `<span>` markup).
 * - **Conditional question**: "Absence maladie" is the one leave type HR actually needs an extra
 *   proof-of-illness date for, the other three (Congés payés / RTT / Sans solde) don't. A real
 *   `Question::visibility_strategy = VISIBLE_IF` + `conditions` targeting the type question's
 *   answer, same mechanism `HelpdeskFormBuilder` already uses in this plugin for the opposite
 *   (permanently-hidden) case, `Glpi\Form\Condition\ConditionData` used to build the JSON rather
 *   than hand-writing it, for the exact same "never drift from core's own shape" reason the pilot
 *   gives for tags.
 *
 * **Radio option values, confirmed by reading GLPI 11 core directly**: `QuestionTypeRadio` stores
 * its choices as a `QuestionTypeSelectableExtraDataConfig` (`{"options": {"<key>": "<label>", ...}}`)
 * and a submitted answer is the *option key*, not its label
 * (`AbstractQuestionTypeSelectable::renderEndUserTemplate()` posts `value.uuid`) : integer-like
 * string keys ("1", "2"...) are exactly what core's own `convertExtraData()` produces when it
 * migrates legacy Formcreator questions, so that's what's used here too rather than inventing real
 * UUIDs. `SingleChoiceFromValuesConditionHandler::applyValueOperator()` (the handler
 * `QuestionTypeRadio::getConditionHandlers()` registers) compares the raw submitted key against the
 * condition's stored `value` as plain strings, so the condition's `value` must be that same key, not
 * the label, confirmed in `Glpi\Form\Condition\Engine::computeCondition()` which passes
 * `$condition->getValue()` straight through for the `equals` operator without any label lookup.
 * `AnswerTagProvider`'s tag still resolves to the human label in the final title, since
 * `Answer::getFormattedAnswer()` runs through `AbstractQuestionTypeSelectable::formatRawAnswer()`
 * first (replaces the key by its option label before it ever reaches the tag system).
 */
class LeaveRequestFormBuilder
{
    private const FORM_NAME = 'Demande de congé ou absence';

    private const BRANCH_KEY = 'rh';

    private const CATEGORY_PATH = ['Absences & Congés'];

    // Same icon `ServiceCatalogBuilder::BRANCH_ILLUSTRATIONS['rh']` already gave this branch's
    // other forms, so this upgraded form keeps the exact tile look it already had.
    private const ILLUSTRATION = 'group';

    private const TYPE_OPTIONS = [
        '1' => 'Congés payés',
        '2' => 'RTT',
        '3' => 'Absence maladie',
        '4' => 'Sans solde',
    ];

    private const SICK_LEAVE_OPTION_KEY = '3';

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
     * @return array{type: Question, debut: Question, fin: Question, justificatif: Question}|null
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

        $type = new Question();
        $type->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Type de congé', 'configurationglpiauto'),
            'type' => QuestionTypeRadio::class,
            'is_mandatory' => 1,
            'vertical_rank' => 0,
            'extra_data' => json_encode((new QuestionTypeSelectableExtraDataConfig(
                options: self::TYPE_OPTIONS,
            ))->jsonSerialize()),
        ]);
        if (!$type->getID()) {
            return null;
        }
        $type->getFromDB($type->getID());
        $typeUuid = $type->fields['uuid'];

        $debut = new Question();
        $debut->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Date de début', 'configurationglpiauto'),
            'type' => QuestionTypeDateTime::class,
            'is_mandatory' => 1,
            'vertical_rank' => 1,
            'extra_data' => $dateExtraData,
        ]);

        $fin = new Question();
        $fin->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Date de fin', 'configurationglpiauto'),
            'type' => QuestionTypeDateTime::class,
            'is_mandatory' => 1,
            'vertical_rank' => 2,
            'extra_data' => $dateExtraData,
        ]);

        // Visible only when "Absence maladie" is selected : empty `conditions` + VISIBLE_IF would be
        // permanently hidden (see `HelpdeskFormBuilder`'s docblock), a real condition targeting the
        // type question's own uuid is what makes it actually conditional instead.
        $sickLeaveCondition = new ConditionData(
            item_uuid: $typeUuid,
            item_type: ConditionType::QUESTION->value,
            value_operator: ValueOperator::EQUALS->value,
            value: self::SICK_LEAVE_OPTION_KEY,
        );

        $justificatif = new Question();
        $justificatif->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Date de réception du justificatif médical', 'configurationglpiauto'),
            'type' => QuestionTypeDateTime::class,
            'is_mandatory' => 1,
            'vertical_rank' => 3,
            'extra_data' => $dateExtraData,
            'visibility_strategy' => VisibilityStrategy::VISIBLE_IF->value,
            'conditions' => json_encode([$sickLeaveCondition->jsonSerialize()]),
        ]);

        if (!$debut->getID() || !$fin->getID() || !$justificatif->getID()) {
            return null;
        }

        return ['type' => $type, 'debut' => $debut, 'fin' => $fin, 'justificatif' => $justificatif];
    }

    /**
     * @param array{type: Question, debut: Question, fin: Question, justificatif: Question} $questions
     */
    private function configureDestination(Form $form, int $itilCategoryId, array $questions): void
    {
        $destination = new FormDestination();
        if (!$destination->getFromDBByCrit(['forms_forms_id' => $form->getID(), 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        $answerProvider = new AnswerTagProvider();
        $formProvider = new FormTagProvider();

        // "<Nom du formulaire> (<type>) du <début> au <fin>" : the justificatif date is
        // deliberately left out of the title, it's only ever answered on one of the four leave
        // types so it would render as an empty tag most of the time.
        $titleValue = $formProvider->getTagForForm($form)->html
            . ' (' . $answerProvider->getTagForQuestion($questions['type'])->html . ')'
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
