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
use Glpi\Form\Destination\CommonITILField\RequestTypeField;
use Glpi\Form\Destination\CommonITILField\RequestTypeFieldConfig;
use Glpi\Form\Destination\CommonITILField\RequestTypeFieldStrategy;
use Glpi\Form\Destination\CommonITILField\SimpleValueConfig;
use Glpi\Form\Destination\CommonITILField\TitleField;
use Glpi\Form\Destination\CommonITILField\UrgencyField;
use Glpi\Form\Destination\CommonITILField\UrgencyFieldConfig;
use Glpi\Form\Destination\CommonITILField\UrgencyFieldStrategy;
use Glpi\Form\Destination\FormDestination;
use Glpi\Form\Destination\FormDestinationTicket;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\QuestionType\QuestionTypeDateTime;
use Glpi\Form\QuestionType\QuestionTypeDateTimeExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeFile;
use Glpi\Form\QuestionType\QuestionTypeItem;
use Glpi\Form\QuestionType\QuestionTypeItemExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeLongText;
use Glpi\Form\QuestionType\QuestionTypeRadio;
use Glpi\Form\QuestionType\QuestionTypeSelectableExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Form\QuestionType\QuestionTypeUrgency;
use Glpi\Form\Section;
use Glpi\Form\Tag\AnswerTagProvider;
use Glpi\Form\Tag\FormTagProvider;
use ITILCategory;
use Ticket;

/**
 * One of the service-catalog upgrades generalizing issue #207's pattern (pioneered by
 * `AccessBadgeFormBuilder`/`MeetingRoomFormBuilder`/`VpnAccessFormBuilder`) to the rest of the
 * catalog per explicit maintainer request : "Déclarer un sinistre ou un dommage véhicule" (Flotte
 * Automobile & Mobilité / Sinistres & Carrosserie), replacing the plain Title+Description entry that
 * used to live in `ServiceCatalogBuilder::SERVICES` (now flagged `'smart' => true` there and skipped
 * by that builder's own loop).
 *
 * Real conditional visibility, same `Question::visibility_strategy = VISIBLE_IF` +
 * `Glpi\Form\Condition\ConditionData` mechanism as `MeetingRoomFormBuilder`/`VpnAccessFormBuilder` :
 * whether a third party is involved (`tiers`) decides whether their contact details are worth asking
 * for at all — a single-vehicle incident has no third party to describe, so `coordTiers` only shows
 * up when `tiers` is answered "Oui" (see those classes' docblocks for the full radio-option-value
 * reasoning). An optional photo upload (`QuestionTypeFile`, not mandatory — some incidents are
 * reported before photos can be taken) is also included, since damage claims are the one case in
 * this generalization where visual evidence materially helps whoever processes the ticket. Every
 * smart form in this generalization also gets a final free-text "Précisions complémentaires"
 * question as an escape hatch, replacing the generic Description field these forms no longer have;
 * here it's placed after the photo upload, both last.
 *
 * Computed title only references the always-answered `vehicule` and `dateSinistre` — never `tiers`,
 * `coordTiers`, or the optional photos — same reasoning as `MeetingRoomFormBuilder` : the conditional
 * field is empty whenever its branch wasn't taken, so folding it into the title would render blank
 * half the time. "<form name> - <vehicule> - <dateSinistre>", tag-built via
 * `AnswerTagProvider`/`FormTagProvider`, never hand-written markup.
 *
 * **Second pass (advanced question types)** : "Véhicule concerné (immatriculation)" used to be free
 * ShortText — this plugin's own `VehicleAssetBuilder` already turns every selected "flotte" branch
 * into a real custom GLPI asset (`AssetDefinition` system_name `Vehicule`, unconditionally, no
 * separate toggle — exactly the same single `flotte`-branch gate this form itself already requires),
 * so the plate number the requester used to type by hand is now a `QuestionTypeItem` pointing at that
 * *real* vehicle record. Wired to `AssociatedItemsField` (`LAST_VALID_ANSWER`) so the incident ticket
 * carries a genuine linked asset, not a string. `resolveVehicleItemtype()` still defensively falls
 * back to the original ShortText if the `Vehicule` definition can't be found (belt-and-braces only —
 * both gates are identical so this should never actually happen once the wizard has run once, same
 * "never assume, always check `getFromDBByCrit()`" discipline as the rest of this codebase).
 *
 * `RequestTypeField` pinned to `Ticket::INCIDENT_TYPE` (`SPECIFIC_VALUE`, no question asked) : an
 * accident/damage declaration is unambiguously an incident.
 *
 * **Third pass (objective urgency, not self-rated)** : `QuestionTypeUrgency` was deliberately
 * excluded catalog-wide during the second pass (see `HelpdeskFormBuilder`'s own docblock : asking a
 * requester to self-rate "how urgent is MY problem" is a documented ITSM anti-pattern, base users
 * consistently over-rate their own issue). But that anti-pattern is specifically about *subjective*
 * self-assessment, not about deriving urgency from an *objective, verifiable fact* the requester is
 * well-placed to state - "is the vehicle currently immobilised/unusable" isn't a feeling, it's
 * something they either can or can't drive right now.
 *
 * First attempt used a plain `QuestionTypeRadio` ("Oui"/"Non") with its option KEYS set to real
 * `CommonITILObject::getUrgencyName()` integers, wired via `UrgencyFieldStrategy::SPECIFIC_ANSWER` -
 * this DOESN'T work, confirmed by actually submitting the form and inspecting the resulting
 * ticket's `urgency` column (fell back to the default, 3, regardless of the radio answer) :
 * `UrgencyFieldStrategy::getUrgencyFromSpecificAnswer()` requires `is_numeric($answer->getRawAnswer())`,
 * but a Selectable-family question type (`QuestionTypeRadio`/`AbstractQuestionTypeSelectable`)
 * stores its raw answer as an ARRAY (`["4"]`, confirmed in `glpi_forms_answerssets.answers`), even
 * for a single-choice radio - `is_numeric(["4"])` is false, so `computeUrgency()` silently returns
 * null and GLPI falls back to its own default. No error anywhere, the ticket is created
 * successfully either way - the only way to catch this was to check the actual persisted urgency
 * value, not just confirm the form saved.
 *
 * `immobilise` is a REAL `QuestionTypeUrgency` question instead, wired the way GLPI actually
 * intends (`UrgencyFieldStrategy::LAST_VALID_ANSWER`, the strategy literally named "answer to last
 * Urgency question" - its own raw answer format is a bare scalar, not array-wrapped, confirmed
 * working end to end this time). To keep the "objective fact, not self-rating" framing despite
 * reusing GLPI's native 5-level urgency dropdown for the answer widget (its option LABELS aren't
 * customisable per-question, only the question's own prompt is), the prompt asks how immobilised
 * the vehicle itself is, never how urgent the requester personally feels about it.
 */
class VehicleIncidentFormBuilder
{
    private const FORM_NAME = 'Déclarer un sinistre ou un dommage véhicule';

    private const BRANCH_KEY = 'flotte';

    private const CATEGORY_PATH = ['Sinistres & Carrosserie'];

    // Matches `VehicleAssetBuilder::SYSTEM_NAME` — resolved by name rather than shared code, same
    // "each builder resolves a sibling's output independently" convention as e.g.
    // `AbroadMissionFormBuilder` resolving `CategoryBuilder`'s own output.
    private const VEHICLE_ASSET_SYSTEM_NAME = 'Vehicule';

    // Same icon `ServiceCatalogBuilder::BRANCH_ILLUSTRATIONS['flotte']` already gave this branch's
    // other forms.
    private const ILLUSTRATION = 'car';

    private const TIERS_OPTIONS = [
        '1' => 'Oui',
        '2' => 'Non',
    ];

    private const TIERS_OUI_OPTION_KEY = '1';

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
     * Resolves `VehicleAssetBuilder`'s own custom asset class by its stable `system_name`. Uses
     * `AssetDefinition::getAssetClassName()` — confirmed by reading GLPI 11 core
     * (`Glpi\Asset\AssetDefinition`) that this, not `getAssetTypeClassName()` (which is the
     * definition's own native "Type" *dropdown* class, `getAssetClassName() . 'Type'` — the one
     * `VehicleAssetBuilder::seedTypes()` itself uses, for a different purpose), is the definition's
     * actual concrete asset item class : `getAssetClassName()`'s own docblock reads "Get the
     * definition's concrete asset class name." Returns null if the definition doesn't exist yet —
     * defensive only, see class docblock.
     */
    private function resolveVehicleItemtype(): ?string
    {
        $definition = new AssetDefinition();
        if (!$definition->getFromDBByCrit(['system_name' => self::VEHICLE_ASSET_SYSTEM_NAME])) {
            return null;
        }

        return $definition->getAssetClassName();
    }

    /**
     * @return array{vehicule: Question, dateSinistre: Question, immobilise: Question}|null
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

        $vehicleItemtype = $this->resolveVehicleItemtype();

        $vehicule = new Question();
        $vehicule->add($vehicleItemtype !== null ? [
            'forms_sections_id' => $sectionId,
            'name' => __('Véhicule concerné', 'configurationglpiauto'),
            'type' => QuestionTypeItem::class,
            'is_mandatory' => 1,
            'vertical_rank' => 0,
            'extra_data' => json_encode((new QuestionTypeItemExtraDataConfig(
                itemtype: $vehicleItemtype,
                root_items_id: 0,
                subtree_depth: 0,
                selectable_tree_root: false,
            ))->jsonSerialize()),
        ] : [
            // Fallback if VehicleAssetBuilder's definition can't be found — see class docblock.
            'forms_sections_id' => $sectionId,
            'name' => __('Véhicule concerné (immatriculation)', 'configurationglpiauto'),
            'type' => QuestionTypeShortText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 0,
        ]);

        $dateSinistre = new Question();
        $dateSinistre->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Date du sinistre', 'configurationglpiauto'),
            'type' => QuestionTypeDateTime::class,
            'is_mandatory' => 1,
            'vertical_rank' => 1,
            'extra_data' => json_encode((new QuestionTypeDateTimeExtraDataConfig(
                is_default_value_current_time: false,
                is_date_enabled: true,
                is_time_enabled: false,
            ))->jsonSerialize()),
        ]);

        $immobilise = new Question();
        $immobilise->add([
            'forms_sections_id' => $sectionId,
            'name' => __("Dans quelle mesure le véhicule est-il immobilisé ou inutilisable ?", 'configurationglpiauto'),
            'type' => QuestionTypeUrgency::class,
            'is_mandatory' => 1,
            'vertical_rank' => 2,
        ]);

        $tiers = new Question();
        $tiers->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Un tiers est-il impliqué ?', 'configurationglpiauto'),
            'type' => QuestionTypeRadio::class,
            'is_mandatory' => 1,
            'vertical_rank' => 3,
            'extra_data' => json_encode((new QuestionTypeSelectableExtraDataConfig(
                options: self::TIERS_OPTIONS,
            ))->jsonSerialize()),
        ]);
        if (!$tiers->getID()) {
            return null;
        }
        $tiers->getFromDB($tiers->getID());
        $tiersUuid = $tiers->fields['uuid'];

        $tiersOuiCondition = new ConditionData(
            item_uuid: $tiersUuid,
            item_type: ConditionType::QUESTION->value,
            value_operator: ValueOperator::EQUALS->value,
            value: self::TIERS_OUI_OPTION_KEY,
        );

        $coordTiers = new Question();
        $coordTiers->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Coordonnées et informations du tiers', 'configurationglpiauto'),
            'type' => QuestionTypeLongText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 4,
            'visibility_strategy' => VisibilityStrategy::VISIBLE_IF->value,
            'conditions' => json_encode([$tiersOuiCondition->jsonSerialize()]),
        ]);

        $photos = new Question();
        $photos->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Photos des dommages', 'configurationglpiauto'),
            'type' => QuestionTypeFile::class,
            'is_mandatory' => 0,
            'vertical_rank' => 5,
        ]);

        $precisions = new Question();
        $precisions->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Précisions complémentaires', 'configurationglpiauto'),
            'type' => QuestionTypeLongText::class,
            'is_mandatory' => 0,
            'vertical_rank' => 6,
        ]);

        if (
            !$vehicule->getID() || !$dateSinistre->getID() || !$immobilise->getID()
            || !$tiers->getID() || !$coordTiers->getID() || !$photos->getID() || !$precisions->getID()
        ) {
            return null;
        }

        return ['vehicule' => $vehicule, 'dateSinistre' => $dateSinistre, 'immobilise' => $immobilise];
    }

    /**
     * @param array{vehicule: Question, dateSinistre: Question, immobilise: Question} $questions
     */
    private function configureDestination(Form $form, int $itilCategoryId, array $questions): void
    {
        $destination = new FormDestination();
        if (!$destination->getFromDBByCrit(['forms_forms_id' => $form->getID(), 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        $answerProvider = new AnswerTagProvider();
        $formProvider = new FormTagProvider();

        // "<Nom du formulaire> - <vehicule> - <dateSinistre>"
        $titleValue = $formProvider->getTagForForm($form)->html
            . ' - ' . $answerProvider->getTagForQuestion($questions['vehicule'])->html
            . ' - ' . $answerProvider->getTagForQuestion($questions['dateSinistre'])->html;

        $config = [
            ITILCategoryField::getKey() => (new ITILCategoryFieldConfig(
                strategy: ITILCategoryFieldStrategy::SPECIFIC_VALUE,
                specific_itilcategory_id: $itilCategoryId,
            ))->jsonSerialize(),
            TitleField::getKey() => (new SimpleValueConfig($titleValue))->jsonSerialize(),
            ContentField::getAutoConfigKey() => 1,
            // "vehicule" is the only QuestionTypeItem/QuestionTypeUserDevice question on this form
            // (when the AssetDefinition lookup succeeded) — LAST_VALID_ANSWER unambiguously means
            // that answer; a no-op (no associated item set) on the ShortText fallback path.
            AssociatedItemsField::getKey() => (new AssociatedItemsFieldConfig(
                strategies: [AssociatedItemsFieldStrategy::LAST_VALID_ANSWER],
            ))->jsonSerialize(),
            RequestTypeField::getKey() => (new RequestTypeFieldConfig(
                strategy: RequestTypeFieldStrategy::SPECIFIC_VALUE,
                specific_request_type: Ticket::INCIDENT_TYPE,
            ))->jsonSerialize(),
            // Objective fact framing on the prompt, not self-rated urgency - see class docblock,
            // third pass. LAST_VALID_ANSWER (not SPECIFIC_ANSWER) : the only strategy compatible
            // with QuestionTypeUrgency's own raw answer format, confirmed by testing.
            UrgencyField::getKey() => (new UrgencyFieldConfig(
                strategy: UrgencyFieldStrategy::LAST_VALID_ANSWER,
            ))->jsonSerialize(),
        ];

        $destination->update([
            'id' => $destination->getID(),
            'config' => json_encode($config),
        ]);
    }
}
