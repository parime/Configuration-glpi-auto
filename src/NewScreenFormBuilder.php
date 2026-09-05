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
 * Part of the generalization of issue #207's smart-form pattern to the full catalog, per explicit
 * maintainer request : "Demande d'un nouvel écran" (IT / Poste de travail / Écran & Affichage),
 * replacing the plain Title+Description entry that used to live in
 * `ServiceCatalogBuilder::SERVICES`.
 *
 * No conditional question : the number of screens, the desired size, and the reason are independent
 * facts that don't gate one another, so all three are asked directly. The size field is the one
 * genuinely optional question in this class (not every requester has a preference), same pattern as
 * `is_mandatory => 0` used elsewhere for non-essential fields. A final free-text "Précisions
 * complémentaires" field is added on every class in this generalization pass, replacing the generic
 * Description field these smart forms no longer have.
 */
class NewScreenFormBuilder
{
    private const FORM_NAME = "Demande d'un nouvel écran";

    private const BRANCH_KEY = 'it';

    private const CATEGORY_PATH = ['Poste de travail', 'Écran & Affichage'];

    // Same icon `ServiceCatalogBuilder::BRANCH_ILLUSTRATIONS['it']` already gave this branch's
    // other forms.
    private const ILLUSTRATION = 'asset-desktop-1';

    private const NOMBRE_OPTIONS = [
        '1' => '1 écran',
        '2' => '2 écrans',
    ];

    private const MOTIF_OPTIONS = [
        '1' => 'Premier équipement',
        '2' => "Remplacement d'un écran défectueux",
        '3' => 'Écran supplémentaire',
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
     * @return array{nombre: Question, taille: Question, motif: Question}|null
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

        $nombre = new Question();
        $nombre->add([
            'forms_sections_id' => $sectionId,
            'name' => __("Nombre d'écrans souhaités", 'configurationglpiauto'),
            'type' => QuestionTypeRadio::class,
            'is_mandatory' => 1,
            'vertical_rank' => 0,
            'extra_data' => json_encode((new QuestionTypeSelectableExtraDataConfig(
                options: self::NOMBRE_OPTIONS,
            ))->jsonSerialize()),
        ]);

        $taille = new Question();
        $taille->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Taille souhaitée (ex : 24 pouces)', 'configurationglpiauto'),
            'type' => QuestionTypeShortText::class,
            'is_mandatory' => 0,
            'vertical_rank' => 1,
        ]);

        $motif = new Question();
        $motif->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Motif de la demande', 'configurationglpiauto'),
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

        if (!$nombre->getID() || !$taille->getID() || !$motif->getID() || !$precisions->getID()) {
            return null;
        }

        return ['nombre' => $nombre, 'taille' => $taille, 'motif' => $motif];
    }

    /**
     * @param array{nombre: Question, taille: Question, motif: Question} $questions
     */
    private function configureDestination(Form $form, int $itilCategoryId, array $questions): void
    {
        $destination = new FormDestination();
        if (!$destination->getFromDBByCrit(['forms_forms_id' => $form->getID(), 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        $answerProvider = new AnswerTagProvider();
        $formProvider = new FormTagProvider();

        // "<Nom du formulaire> - <nombre>"
        $titleValue = $formProvider->getTagForForm($form)->html
            . ' - ' . $answerProvider->getTagForQuestion($questions['nombre'])->html;

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
