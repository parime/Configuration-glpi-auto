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
use Glpi\Form\QuestionType\QuestionTypeLongText;
use Glpi\Form\QuestionType\QuestionTypeRadio;
use Glpi\Form\QuestionType\QuestionTypeSelectableExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Form\Section;
use Glpi\Form\Tag\AnswerTagProvider;
use Glpi\Form\Tag\FormTagProvider;
use ITILCategory;

/**
 * One of the service-catalog upgrades generalizing issue #207's pattern (pioneered by
 * `AccessBadgeFormBuilder`/`MeetingRoomFormBuilder`/`VpnAccessFormBuilder`) to the rest of the
 * catalog per explicit maintainer request : "Demande de mobilier ou d'aménagement de poste"
 * (Bâtiment & Moyens Généraux / Mobilier & Aménagement), replacing the plain Title+Description
 * entry that used to live in `ServiceCatalogBuilder::SERVICES` (now flagged `'smart' => true` there
 * and skipped by that builder's own loop).
 *
 * Three questions, all always relevant together, no conditional branch : what furniture, where, and
 * why (first equipment vs. replacing something broken vs. ergonomic fit-out — three outcomes that
 * mean very different things for whoever processes the request, but none of them changes what else
 * needs asking). Every smart form in this generalization also gets a final free-text "Précisions
 * complémentaires" question as an escape hatch, replacing the generic Description field these forms
 * no longer have.
 *
 * Computed title references `typeMobilier` rather than `localisation` on purpose : unlike a facility
 * fault report, what's requested (desk, chair, storage...) is the more useful thing to see at a
 * glance across a list of furniture tickets — "<form name> - <typeMobilier>", tag-built via
 * `AnswerTagProvider`/`FormTagProvider`, never hand-written markup.
 */
class FurnitureRequestFormBuilder
{
    private const FORM_NAME = "Demande de mobilier ou d'aménagement de poste";

    private const BRANCH_KEY = 'batiment';

    private const CATEGORY_PATH = ['Mobilier & Aménagement'];

    // Same icon `ServiceCatalogBuilder::BRANCH_ILLUSTRATIONS['batiment']` already gave this
    // branch's other forms.
    private const ILLUSTRATION = 'building';

    private const TYPE_MOBILIER_OPTIONS = [
        '1' => 'Bureau',
        '2' => 'Siège',
        '3' => 'Rangement (armoire, caisson)',
        '4' => 'Autre',
    ];

    private const MOTIF_OPTIONS = [
        '1' => 'Premier équipement',
        '2' => 'Remplacement (mobilier défectueux)',
        '3' => 'Aménagement ergonomique',
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
     * @return array{typeMobilier: Question, localisation: Question, motif: Question}|null
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

        $typeMobilier = new Question();
        $typeMobilier->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Type de mobilier concerné', 'configurationglpiauto'),
            'type' => QuestionTypeRadio::class,
            'is_mandatory' => 1,
            'vertical_rank' => 0,
            'extra_data' => json_encode((new QuestionTypeSelectableExtraDataConfig(
                options: self::TYPE_MOBILIER_OPTIONS,
            ))->jsonSerialize()),
        ]);

        $localisation = new Question();
        $localisation->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Localisation du poste', 'configurationglpiauto'),
            'type' => QuestionTypeShortText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 1,
        ]);

        $motif = new Question();
        $motif->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Motif', 'configurationglpiauto'),
            'type' => QuestionTypeRadio::class,
            'is_mandatory' => 1,
            'vertical_rank' => 2,
            'extra_data' => json_encode((new QuestionTypeSelectableExtraDataConfig(
                options: self::MOTIF_OPTIONS,
            ))->jsonSerialize()),
        ]);

        $precisions = new Question();
        $precisions->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Précisions complémentaires', 'configurationglpiauto'),
            'type' => QuestionTypeLongText::class,
            'is_mandatory' => 0,
            'vertical_rank' => 3,
        ]);

        if (!$typeMobilier->getID() || !$localisation->getID() || !$motif->getID() || !$precisions->getID()) {
            return null;
        }

        return ['typeMobilier' => $typeMobilier, 'localisation' => $localisation, 'motif' => $motif];
    }

    /**
     * @param array{typeMobilier: Question, localisation: Question, motif: Question} $questions
     */
    private function configureDestination(Form $form, int $itilCategoryId, array $questions): void
    {
        $destination = new FormDestination();
        if (!$destination->getFromDBByCrit(['forms_forms_id' => $form->getID(), 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        $answerProvider = new AnswerTagProvider();
        $formProvider = new FormTagProvider();

        // "<Nom du formulaire> - <typeMobilier>"
        $titleValue = $formProvider->getTagForForm($form)->html
            . ' - ' . $answerProvider->getTagForQuestion($questions['typeMobilier'])->html;

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
