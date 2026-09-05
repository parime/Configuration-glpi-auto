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
use Glpi\Form\QuestionType\QuestionTypeFile;
use Glpi\Form\QuestionType\QuestionTypeLongText;
use Glpi\Form\QuestionType\QuestionTypeRadio;
use Glpi\Form\QuestionType\QuestionTypeSelectableExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Form\QuestionType\QuestionTypeUserDevice;
use Glpi\Form\QuestionType\QuestionTypeUserDevicesConfig;
use Glpi\Form\Section;
use Glpi\Form\Tag\AnswerTagProvider;
use Glpi\Form\Tag\FormTagProvider;
use ITILCategory;
use Ticket;

/**
 * Part of the generalization of issue #207's smart-form pattern to the full catalog, per explicit
 * maintainer request : "Signaler un bug ou dysfonctionnement logiciel" (IT / Logiciels &
 * Applications / Bug / Dysfonctionnement), replacing the plain Title+Description entry that used to
 * live in `ServiceCatalogBuilder::SERVICES`.
 *
 * No conditional question : the affected software and whether the bug is fully blocking are both
 * always relevant, and the blocking/non-blocking answer is exactly the kind of signal a support team
 * wants visible at a glance without opening the ticket, so it's asked directly rather than gated
 * behind anything. A final free-text "Précisions complémentaires" field is added on every class in
 * this generalization pass, replacing the generic Description field these smart forms no longer
 * have.
 *
 * **Second pass (deep-dive into GLPI 11's advanced question types, per explicit maintainer request
 * that the first pass's basic-types-only treatment was insufficient)** : adds `QuestionTypeUserDevice`
 * ("Poste ou appareil concerné", optional) so the reporter can point at the *real* Computer/Monitor/
 * Phone/... GLPI already has affected to them (`CommonItilObject_Item::getMyDevices()`), instead of
 * re-describing it in free text inside "Précisions" — wired to the ticket as a genuine associated item
 * via `AssociatedItemsField` (`LAST_VALID_ANSWER`, the only `QuestionTypeUserDevice`/`QuestionTypeItem`
 * question on this form so no ambiguity). Optional, not mandatory : a software bug can also affect a
 * shared/non-personal machine, or the reporter may simply not know which asset record it's filed
 * under — free text in "Précisions" remains the fallback either way. Also adds `QuestionTypeFile`
 * ("Capture d'écran de l'erreur", optional) — GLPI attaches any answered file straight to the ticket's
 * content automatically (confirmed via `AbstractCommonITILFormDestination::setFilesInput()`, no extra
 * destination config needed for that part).
 *
 * `RequestTypeField` pinned to `Ticket::INCIDENT_TYPE` (`SPECIFIC_VALUE`, no question asked) : a bug
 * report is unambiguously an incident, not a request, and this plugin doesn't otherwise set a ticket
 * type at all for its smart forms — worth doing explicitly rather than relying on whatever GLPI/the
 * ticket template happens to default to.
 *
 * Deliberately did NOT add a `QuestionTypeUrgency` question here (or anywhere in this second pass) —
 * see `HelpdeskFormBuilder`'s own docblock: asking requesters to self-rate urgency is a well-documented
 * ITSM anti-pattern this plugin already goes out of its way to avoid (it actively hides GLPI's native
 * "Urgency" question on the default self-service forms for exactly that reason). The existing
 * "bloquant" radio below already gives a support team the signal that matters without asking anyone to
 * self-rate anything, so it's kept exactly as is.
 */
class SoftwareBugFormBuilder
{
    private const FORM_NAME = 'Signaler un bug ou dysfonctionnement logiciel';

    private const BRANCH_KEY = 'it';

    private const CATEGORY_PATH = ['Logiciels & Applications', 'Bug / Dysfonctionnement'];

    // Same icon `ServiceCatalogBuilder::BRANCH_ILLUSTRATIONS['it']` already gave this branch's
    // other forms.
    private const ILLUSTRATION = 'asset-desktop-1';

    private const BLOQUANT_OPTIONS = [
        '1' => 'Oui, totalement bloquant',
        '2' => 'Non, je peux contourner le problème',
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
     * @return array{logiciel: Question, bloquant: Question, poste: Question}|null
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

        $logiciel = new Question();
        $logiciel->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Logiciel concerné', 'configurationglpiauto'),
            'type' => QuestionTypeShortText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 0,
        ]);

        $bloquant = new Question();
        $bloquant->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Le problème vous empêche-t-il de travailler ?', 'configurationglpiauto'),
            'type' => QuestionTypeRadio::class,
            'is_mandatory' => 1,
            'vertical_rank' => 1,
            'extra_data' => json_encode((new QuestionTypeSelectableExtraDataConfig(
                options: self::BLOQUANT_OPTIONS,
            ))->jsonSerialize()),
        ]);

        // Optional: lets the reporter point at their own real GLPI-tracked device instead of
        // re-describing it in free text — see class docblock. `getMyDevices()` already scopes
        // this to devices affected to the current end user, so no itemtype restriction to set.
        $poste = new Question();
        $poste->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Poste ou appareil concerné (si le bug est lié à un poste précis)', 'configurationglpiauto'),
            'type' => QuestionTypeUserDevice::class,
            'is_mandatory' => 0,
            'vertical_rank' => 2,
            'extra_data' => json_encode((new QuestionTypeUserDevicesConfig(
                is_multiple_devices: false,
            ))->jsonSerialize()),
        ]);

        $capture = new Question();
        $capture->add([
            'forms_sections_id' => $sectionId,
            'name' => __("Capture d'écran de l'erreur", 'configurationglpiauto'),
            'type' => QuestionTypeFile::class,
            'is_mandatory' => 0,
            'vertical_rank' => 3,
        ]);

        $precisions = new Question();
        $precisions->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Précisions complémentaires', 'configurationglpiauto'),
            'type' => QuestionTypeLongText::class,
            'is_mandatory' => 0,
            'vertical_rank' => 4,
        ]);

        if (
            !$logiciel->getID() || !$bloquant->getID() || !$poste->getID()
            || !$capture->getID() || !$precisions->getID()
        ) {
            return null;
        }

        return ['logiciel' => $logiciel, 'bloquant' => $bloquant, 'poste' => $poste];
    }

    /**
     * @param array{logiciel: Question, bloquant: Question, poste: Question} $questions
     */
    private function configureDestination(Form $form, int $itilCategoryId, array $questions): void
    {
        $destination = new FormDestination();
        if (!$destination->getFromDBByCrit(['forms_forms_id' => $form->getID(), 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        $answerProvider = new AnswerTagProvider();
        $formProvider = new FormTagProvider();

        // "<Nom du formulaire> - <logiciel>"
        $titleValue = $formProvider->getTagForForm($form)->html
            . ' - ' . $answerProvider->getTagForQuestion($questions['logiciel'])->html;

        $config = [
            ITILCategoryField::getKey() => (new ITILCategoryFieldConfig(
                strategy: ITILCategoryFieldStrategy::SPECIFIC_VALUE,
                specific_itilcategory_id: $itilCategoryId,
            ))->jsonSerialize(),
            TitleField::getKey() => (new SimpleValueConfig($titleValue))->jsonSerialize(),
            ContentField::getAutoConfigKey() => 1,
            // The "poste" question is the only QuestionTypeUserDevice/QuestionTypeItem question on
            // this form, so LAST_VALID_ANSWER unambiguously means "whatever device was answered
            // there" — turns a free-text device description into a real linked Ticket item.
            AssociatedItemsField::getKey() => (new AssociatedItemsFieldConfig(
                strategies: [AssociatedItemsFieldStrategy::LAST_VALID_ANSWER],
            ))->jsonSerialize(),
            // A bug/dysfunction report is unambiguously an incident, not a request — see class
            // docblock.
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
