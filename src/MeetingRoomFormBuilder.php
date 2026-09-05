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
use Glpi\Form\Condition\ConditionData;
use Glpi\Form\Condition\Type as ConditionType;
use Glpi\Form\Condition\ValueOperator;
use Glpi\Form\Condition\VisibilityStrategy;
use Glpi\Form\Destination\CommonITILField\AssociatedItemsField;
use Glpi\Form\Destination\CommonITILField\AssociatedItemsFieldConfig;
use Glpi\Form\Destination\CommonITILField\AssociatedItemsFieldStrategy;
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

/**
 * One of the ~6 service-catalog upgrades for issue #207 : "Réservation ou problème d'équipement de
 * salle de réunion" (Bâtiment & Moyens Généraux / Salles de réunion & Équipements), replacing the
 * plain Title+Description entry that used to live in `ServiceCatalogBuilder::SERVICES` (now flagged
 * `'smart' => true` there and skipped by that builder's own loop).
 *
 * The clearest conditional-question candidate of the six, and the closest match to the issue's own
 * example ("type de demande -> champs spécifiques") : this service's name already admits it covers
 * two unrelated needs (booking a room ahead of time vs. reporting broken equipment right now), each
 * needing completely different follow-up fields (room + date/time vs. equipment + problem
 * description). One `Question::visibility_strategy = VISIBLE_IF` branch per choice, same
 * `Glpi\Form\Condition\ConditionData` mechanism as `LeaveRequestFormBuilder`/`VpnAccessFormBuilder`
 * (see the former's docblock for the full radio-option-value reasoning).
 *
 * Computed title deliberately only references the always-visible "type" answer, not the
 * conditional room/equipment fields : whichever branch wasn't chosen leaves its own fields
 * unanswered, so folding either into the title would render an empty tag half the time. "<form
 * name> - <type>" is still strictly more informative than the plain form name repeated on every
 * ticket, without that risk.
 *
 * **Second pass (advanced question types)** : "Salle souhaitée" used to be free ShortText — this
 * plugin's own `BuildingAssetBuilder` already turns every selected "batiment" branch into a real
 * custom GLPI asset (`AssetDefinition` system_name `Local`, unconditionally, same single gate this
 * form itself requires) that's explicitly `IsReservableCapacity`-enabled and whose native type
 * dropdown includes "Salle de réunion" — exactly the reservable-resource concept this branch of the
 * question is about. Replaced with `QuestionTypeItem` pointing at that asset class (kept under the
 * exact same "Réservation de salle" `VISIBLE_IF` condition as the ShortText it replaces). It isn't
 * filtered down to only the "Salle de réunion" sub-type — `QuestionTypeItem`'s own tree/root
 * restriction only applies to `CommonTreeDropdown` itemtypes, and "Local" is a flat custom asset list
 * — so the picker shows every "Local" (bureaux, entrepôts... included), a real trade-off worth being
 * explicit about, but still a genuine linked GLPI record instead of an unstructured room name.
 * Wired to `AssociatedItemsField` (`LAST_VALID_ANSWER`) : a no-op on the "Problème d'équipement"
 * branch (question never answered), a real linked room asset on the "Réservation" branch. No
 * `RequestTypeField` change : this form's own nature is genuinely mixed (booking ahead vs. reporting
 * a fault right now), so pinning one fixed type would misclassify whichever branch wasn't taken —
 * same reasoning the class docblock above already gives for keeping the title untouched by either
 * conditional branch.
 */
class MeetingRoomFormBuilder
{
    private const FORM_NAME = "Réservation ou problème d'équipement de salle de réunion";

    private const BRANCH_KEY = 'batiment';

    private const CATEGORY_PATH = ['Salles de réunion & Équipements'];

    // Matches `BuildingAssetBuilder::SYSTEM_NAME` — resolved by name rather than shared code, same
    // convention as `VehicleIncidentFormBuilder`'s identical-shaped constant for `Vehicule`.
    private const ROOM_ASSET_SYSTEM_NAME = 'Local';

    // Same icon `ServiceCatalogBuilder::BRANCH_ILLUSTRATIONS['batiment']` already gave this
    // branch's other forms.
    private const ILLUSTRATION = 'building';

    private const TYPE_OPTIONS = [
        '1' => 'Réservation de salle',
        '2' => "Problème d'équipement",
    ];

    private const RESERVATION_OPTION_KEY = '1';

    private const PROBLEM_OPTION_KEY = '2';

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
     * Same lookup pattern as `VehicleIncidentFormBuilder::resolveVehicleItemtype()` — see that
     * class's docblock.
     */
    private function resolveRoomItemtype(): ?string
    {
        $definition = new AssetDefinition();
        if (!$definition->getFromDBByCrit(['system_name' => self::ROOM_ASSET_SYSTEM_NAME])) {
            return null;
        }

        return $definition->getAssetClassName();
    }

    /**
     * @return array{type: Question}|null
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

        $type = new Question();
        $type->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Type de demande', 'configurationglpiauto'),
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

        $reservationCondition = new ConditionData(
            item_uuid: $typeUuid,
            item_type: ConditionType::QUESTION->value,
            value_operator: ValueOperator::EQUALS->value,
            value: self::RESERVATION_OPTION_KEY,
        );
        $problemCondition = new ConditionData(
            item_uuid: $typeUuid,
            item_type: ConditionType::QUESTION->value,
            value_operator: ValueOperator::EQUALS->value,
            value: self::PROBLEM_OPTION_KEY,
        );

        $roomItemtype = $this->resolveRoomItemtype();

        // Was a free-text ShortText — replaced with QuestionTypeItem(Local), see class docblock.
        $salle = new Question();
        $salle->add($roomItemtype !== null ? [
            'forms_sections_id' => $sectionId,
            'name' => __('Salle souhaitée', 'configurationglpiauto'),
            'type' => QuestionTypeItem::class,
            'is_mandatory' => 1,
            'vertical_rank' => 1,
            'extra_data' => json_encode((new QuestionTypeItemExtraDataConfig(
                itemtype: $roomItemtype,
                root_items_id: 0,
                subtree_depth: 0,
                selectable_tree_root: false,
            ))->jsonSerialize()),
            'visibility_strategy' => VisibilityStrategy::VISIBLE_IF->value,
            'conditions' => json_encode([$reservationCondition->jsonSerialize()]),
        ] : [
            // Fallback if BuildingAssetBuilder's definition can't be found — see class docblock.
            'forms_sections_id' => $sectionId,
            'name' => __('Salle souhaitée', 'configurationglpiauto'),
            'type' => QuestionTypeShortText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 1,
            'visibility_strategy' => VisibilityStrategy::VISIBLE_IF->value,
            'conditions' => json_encode([$reservationCondition->jsonSerialize()]),
        ]);

        $dateSouhaitee = new Question();
        $dateSouhaitee->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Date souhaitée', 'configurationglpiauto'),
            'type' => QuestionTypeDateTime::class,
            'is_mandatory' => 1,
            'vertical_rank' => 2,
            'extra_data' => json_encode((new QuestionTypeDateTimeExtraDataConfig(
                is_default_value_current_time: false,
                is_date_enabled: true,
                is_time_enabled: false,
            ))->jsonSerialize()),
            'visibility_strategy' => VisibilityStrategy::VISIBLE_IF->value,
            'conditions' => json_encode([$reservationCondition->jsonSerialize()]),
        ]);

        $equipement = new Question();
        $equipement->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Équipement concerné', 'configurationglpiauto'),
            'type' => QuestionTypeShortText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 3,
            'visibility_strategy' => VisibilityStrategy::VISIBLE_IF->value,
            'conditions' => json_encode([$problemCondition->jsonSerialize()]),
        ]);

        $descriptionProbleme = new Question();
        $descriptionProbleme->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Description du problème', 'configurationglpiauto'),
            'type' => QuestionTypeLongText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 4,
            'visibility_strategy' => VisibilityStrategy::VISIBLE_IF->value,
            'conditions' => json_encode([$problemCondition->jsonSerialize()]),
        ]);

        if (
            !$salle->getID() || !$dateSouhaitee->getID()
            || !$equipement->getID() || !$descriptionProbleme->getID()
        ) {
            return null;
        }

        return ['type' => $type];
    }

    /**
     * @param array{type: Question} $questions
     */
    private function configureDestination(Form $form, int $itilCategoryId, array $questions): void
    {
        $destination = new FormDestination();
        if (!$destination->getFromDBByCrit(['forms_forms_id' => $form->getID(), 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        $answerProvider = new AnswerTagProvider();
        $formProvider = new FormTagProvider();

        // "<Nom du formulaire> - <type>"
        $titleValue = $formProvider->getTagForForm($form)->html
            . ' - ' . $answerProvider->getTagForQuestion($questions['type'])->html;

        $config = [
            ITILCategoryField::getKey() => (new ITILCategoryFieldConfig(
                strategy: ITILCategoryFieldStrategy::SPECIFIC_VALUE,
                specific_itilcategory_id: $itilCategoryId,
            ))->jsonSerialize(),
            TitleField::getKey() => (new SimpleValueConfig($titleValue))->jsonSerialize(),
            ContentField::getAutoConfigKey() => 1,
            // "salle" is the only QuestionTypeItem/QuestionTypeUserDevice question on this form —
            // a no-op on the "Problème d'équipement" branch (never answered), a real linked room
            // asset on the "Réservation" branch.
            AssociatedItemsField::getKey() => (new AssociatedItemsFieldConfig(
                strategies: [AssociatedItemsFieldStrategy::LAST_VALID_ANSWER],
            ))->jsonSerialize(),
        ];

        $destination->update([
            'id' => $destination->getID(),
            'config' => json_encode($config),
        ]);
    }
}
