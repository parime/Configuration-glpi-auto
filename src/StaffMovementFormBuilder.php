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
use Glpi\Form\Condition\LogicOperator;
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
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Form\Section;
use Glpi\Form\Tag\AnswerTagProvider;
use Glpi\Form\Tag\FormTagProvider;
use ITILCategory;

/**
 * One of the ~6 service-catalog upgrades for issue #207 : "Déclarer une arrivée, un départ ou une
 * mutation" (Ressources Humaines / Mouvements de personnel), replacing the plain Title+Description
 * entry that used to live in `ServiceCatalogBuilder::SERVICES` (now flagged `'smart' => true` there
 * and skipped by that builder's own loop).
 *
 * Both mechanisms, and the only one of the six that needs `LogicOperator::OR` : "Poste concerné" is
 * only relevant for an arrival (their new role) or a transfer (their new role), never for a
 * departure, so it's shown when the type answer is *either* value rather than just one. Confirmed
 * in `Glpi\Form\Condition\Engine::computeConditions()` (read directly, not guessed) : each
 * condition's own `logic_operator` connects it to the *previous* condition in the array (the first
 * condition's operator is always ignored), so two `Glpi\Form\Condition\ConditionData` rows on the
 * same target question with `LogicOperator::OR` between them is the correct way to express "value A
 * or value B" (there's no dedicated "IN" operator). See `LeaveRequestFormBuilder`'s docblock for the
 * full reasoning behind using `QuestionTypeRadio` option keys (not labels) as condition values.
 *
 * "Date d'effet" and "Nom du salarié concerné" are deliberately *not* conditional : every one of the
 * three movement types has an effective date and concerns a named employee, so asking them
 * unconditionally (rather than three near-duplicate "date d'arrivée"/"date de départ"/"date de
 * mutation" questions, one per branch) keeps the form's conditional logic focused on the one field
 * that actually differs by type. Computed title built the same tag-provider way as the pilot
 * (`AbroadMissionFormBuilder`).
 */
class StaffMovementFormBuilder
{
    private const FORM_NAME = 'Déclarer une arrivée, un départ ou une mutation';

    private const BRANCH_KEY = 'rh';

    private const CATEGORY_PATH = ['Mouvements de personnel'];

    // Same icon `ServiceCatalogBuilder::BRANCH_ILLUSTRATIONS['rh']` already gave this branch's
    // other forms.
    private const ILLUSTRATION = 'group';

    private const TYPE_OPTIONS = [
        '1' => 'Arrivée',
        '2' => 'Départ',
        '3' => 'Mutation',
    ];

    private const ARRIVAL_OPTION_KEY = '1';

    private const TRANSFER_OPTION_KEY = '3';

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
     * @return array{nom: Question, type: Question, effet: Question}|null
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

        $nom = new Question();
        $nom->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Nom du salarié concerné', 'configurationglpiauto'),
            'type' => QuestionTypeShortText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 0,
        ]);

        $type = new Question();
        $type->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Type de mouvement', 'configurationglpiauto'),
            'type' => QuestionTypeRadio::class,
            'is_mandatory' => 1,
            'vertical_rank' => 1,
            'extra_data' => json_encode((new QuestionTypeSelectableExtraDataConfig(
                options: self::TYPE_OPTIONS,
            ))->jsonSerialize()),
        ]);
        if (!$type->getID()) {
            return null;
        }
        $type->getFromDB($type->getID());
        $typeUuid = $type->fields['uuid'];

        // "Arrivée" OR "Mutation" : operators[0] is always ignored by the engine, operators[1]
        // ('or') is what connects the two rows together, see class docblock.
        $arrivalOrTransfer = [
            (new ConditionData(
                item_uuid: $typeUuid,
                item_type: ConditionType::QUESTION->value,
                value_operator: ValueOperator::EQUALS->value,
                value: self::ARRIVAL_OPTION_KEY,
                logic_operator: LogicOperator::AND->value,
            ))->jsonSerialize(),
            (new ConditionData(
                item_uuid: $typeUuid,
                item_type: ConditionType::QUESTION->value,
                value_operator: ValueOperator::EQUALS->value,
                value: self::TRANSFER_OPTION_KEY,
                logic_operator: LogicOperator::OR->value,
            ))->jsonSerialize(),
        ];

        $poste = new Question();
        $poste->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Poste concerné', 'configurationglpiauto'),
            'type' => QuestionTypeShortText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 2,
            'visibility_strategy' => VisibilityStrategy::VISIBLE_IF->value,
            'conditions' => json_encode($arrivalOrTransfer),
        ]);

        $effet = new Question();
        $effet->add([
            'forms_sections_id' => $sectionId,
            'name' => __("Date d'effet", 'configurationglpiauto'),
            'type' => QuestionTypeDateTime::class,
            'is_mandatory' => 1,
            'vertical_rank' => 3,
            'extra_data' => json_encode((new QuestionTypeDateTimeExtraDataConfig(
                is_default_value_current_time: false,
                is_date_enabled: true,
                is_time_enabled: false,
            ))->jsonSerialize()),
        ]);

        if (!$nom->getID() || !$poste->getID() || !$effet->getID()) {
            return null;
        }

        return ['nom' => $nom, 'type' => $type, 'effet' => $effet];
    }

    /**
     * @param array{nom: Question, type: Question, effet: Question} $questions
     */
    private function configureDestination(Form $form, int $itilCategoryId, array $questions): void
    {
        $destination = new FormDestination();
        if (!$destination->getFromDBByCrit(['forms_forms_id' => $form->getID(), 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        $answerProvider = new AnswerTagProvider();
        $formProvider = new FormTagProvider();

        // "<Nom du formulaire> : <type> - <nom> - <date d'effet>"
        $titleValue = $formProvider->getTagForForm($form)->html
            . ' : ' . $answerProvider->getTagForQuestion($questions['type'])->html
            . ' - ' . $answerProvider->getTagForQuestion($questions['nom'])->html
            . ' - ' . __('le', 'configurationglpiauto') . ' ' . $answerProvider->getTagForQuestion($questions['effet'])->html;

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
