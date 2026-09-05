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
use Glpi\Form\Destination\CommonITILField\ITILActorFieldStrategy;
use Glpi\Form\Destination\CommonITILField\ITILCategoryField;
use Glpi\Form\Destination\CommonITILField\ITILCategoryFieldConfig;
use Glpi\Form\Destination\CommonITILField\ITILCategoryFieldStrategy;
use Glpi\Form\Destination\CommonITILField\ObserverField;
use Glpi\Form\Destination\CommonITILField\ObserverFieldConfig;
use Glpi\Form\Destination\CommonITILField\SimpleValueConfig;
use Glpi\Form\Destination\CommonITILField\TitleField;
use Glpi\Form\Destination\FormDestination;
use Glpi\Form\Destination\FormDestinationTicket;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\QuestionType\QuestionTypeDateTime;
use Glpi\Form\QuestionType\QuestionTypeDateTimeExtraDataConfig;
use Glpi\Form\QuestionType\QuestionTypeLongText;
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Form\Section;
use Glpi\Form\Tag\AnswerTagProvider;
use Glpi\Form\Tag\FormTagProvider;
use ITILCategory;

/**
 * Pilot for issue #207 (service-catalog forms with computed titles) : a single concrete service,
 * "Demande de droit d'accès / mission à l'étranger" (issue #208), built the same way
 * `ServiceCatalogBuilder` builds its own minimal services but going one step further — real typed
 * questions (dates, country) instead of just Title/Description, and a *computed* Ticket title
 * instead of a plain fixed one.
 *
 * Deliberately its own builder rather than an extra `ServiceCatalogBuilder::SERVICES` row: that
 * class's whole `addQuestions()`/`configureDestinationCategory()` pair is hardcoded to the
 * "Title + Description only, fixed category" shape shared by all ~50 other services — this one
 * needs a different question set and a `TitleField` config the others don't touch at all. Kept
 * inside the Service Catalog step of the wizard and gated on `service_catalog_enabled` +
 * the 'rh' category branch, since that's conceptually exactly what it is: one more entry in the
 * catalog, just a smarter one.
 *
 * **Computed title, confirmed by reading GLPI 11 core directly (not guessed):**
 * `Glpi\Form\Destination\CommonITILField\TitleField` stores its config as a `SimpleValueConfig`
 * (`{"value": "<html>"}`) mixing literal text with `<span data-form-tag="true" ...>` tags that
 * `Glpi\Form\Tag\FormTagsManager::insertTagsContent()` resolves against the real submitted answers
 * at ticket-creation time, then `strip_tags()` + `html_entity_decode()`'s the result into the final
 * plain-text Ticket title. Built here via `Glpi\Form\Tag\Tag`'s own provider classes
 * (`AnswerTagProvider::getTagForQuestion()`, `FormTagProvider::getTagForForm()`) instead of
 * hand-writing the `<span>` markup — the exact same object GLPI's own admin UI would produce,
 * so the byte-for-byte tag format (attribute names/order, provider FQCN, color) can never drift
 * from core.
 *
 * No requester name in the title (unlike the "- [Prénom] [Nom]" sketched in #208): confirmed by
 * reading every class in `src/Glpi/Form/Tag/` that there is no native tag provider exposing the
 * requester's identity to a destination field's computed value — only Answer/Question/Section/Form.
 * The Ticket itself still records the real requester as its `_users_id_requester` the normal way;
 * only the *title* can't reference their name via this tag system. Matches the one real hand-built
 * example the plugin owner actually verified working on a separate instance (a "Demande de
 * télétravail depuis l'étranger" form whose title is `#Nom du formulaire: ... du #Réponse: ... au
 * #Réponse: ...`, no requester tag either) rather than the aspirational text in the issue.
 *
 * **No conditional questions**: this pilot's 3 requester fields (country, start date, end date) are
 * always relevant together, same as the plugin owner's own verified example — nothing here needs
 * `Question::visibility_strategy`/`conditions` (see `HelpdeskFormBuilder` for that mechanism's
 * established usage in this codebase). Left for whichever future #207 service actually needs it.
 *
 * **No native country dropdown**: confirmed by grepping GLPI 11 core — "country" only ever appears
 * as a free-text field (`Location`, `Entity`, `User`, `Supplier`...), there is no dedicated
 * `Country` itemtype/dropdown to point a `QuestionTypeItemDropdown` at. A plain short text question
 * is the honest choice here, not a missing feature.
 *
 * **Content = "Configuration automatique"**: confirmed by reading
 * `Glpi\Form\Destination\AbstractConfigField`/`ContentField` — a destination field opts into
 * auto-generation (a live Q&A recap grouped by section, computed fresh per ticket by
 * `ContentField::getAutoGeneratedConfig()`) via a `"<field key>_auto"` boolean in `config`, which
 * already defaults to `true` when the key is simply absent (`isAutoConfigurated()`). Set explicitly
 * here rather than relying on that silent default, so this stays correct even if that default ever
 * changes in a future GLPI release.
 *
 * **Second pass (advanced question types, per explicit maintainer request that the first pass's
 * basic-types-only treatment was insufficient)** : no new question here — instead, wires
 * `ObserverField` to `ITILActorFieldStrategy::FORM_FILLER_SUPERVISOR`, a destination-level mechanism
 * that reads the requester's `users_id_supervisor` and adds them as an Observer automatically, no
 * question needed at all. Bridges with the exact same native column `ValidationRoutingBuilder`
 * already uses for its own instance-wide approval rule (see that class's docblock) — a mission abroad
 * is a textbook case for the requester's manager to be looped in automatically. Deliberately an
 * Observer, not an approval step : `ValidationField` (the mechanism that would add a real blocking
 * approval) has no equivalent "supervisor of requester" strategy of its own — its `SPECIFIC_ANSWER`/
 * `SPECIFIC_ACTORS` strategies need either a real actor-picking question (friction this plugin's own
 * "automatic" mandate explicitly wants to avoid) or a fixed actor set at admin time (wrong per
 * requester). An org that also wants a hard approval gate already has `ValidationRoutingBuilder`'s own
 * opt-in instance-wide rule for exactly that, orthogonal to and compatible with this per-form CC.
 */
class AbroadMissionFormBuilder
{
    private const FORM_NAME = "Demande de droit d'accès / mission à l'étranger";

    // Reuses the exact same ITILCategory path ServiceCatalogBuilder's own "Demande de télétravail
    // ou d'aménagement du temps de travail" service already routes to — same real-world team
    // (Organisation du travail), no new category invented just for this pilot.
    private const BRANCH_KEY = 'rh';

    private const CATEGORY_PATH = ['Organisation du travail'];

    // GLPI's own bundled illustration catalog (see ServiceCatalogBuilder's BRANCH_ILLUSTRATIONS
    // docblock) — "world" (globe/planet) reads immediately as "abroad" in the self-service tile grid,
    // more specific than the RH branch's generic "group" icon.
    private const ILLUSTRATION = 'world';

    /**
     * @return bool True if the form was created (or already existed).
     */
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

    /**
     * Walks CATEGORY_PATH starting from the branch's own root `ITILCategory` — same tree
     * `CategoryBuilder` built and `ServiceCatalogBuilder` already resolves the identical way.
     */
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

    /**
     * Same Forms\Category ("<branche icon> <branche name>") `ServiceCatalogBuilder` creates —
     * looked up/created by name here too rather than shared code, matching this codebase's
     * established "resolve a sibling builder's output by name" convention (see e.g.
     * `TaskTemplateBuilder` resolving `TaskCategoryBuilder`'s categories). Idempotent either way:
     * whichever builder runs first creates it, the other just reuses it.
     */
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
     * @return array{pays: Question, debut: Question, fin: Question, motif: Question}|null
     */
    private function addQuestions(Form $form): ?array
    {
        // Form::add() already created a first Section via its own post_addItem() hook (same as
        // ServiceCatalogBuilder relies on) — reused and renamed here rather than creating a second
        // one, since every field in this pilot is requester-facing (no separate support-only
        // section, unlike the plugin owner's own hand-built example — see class docblock).
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

        $pays = new Question();
        $pays->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Pays de destination', 'configurationglpiauto'),
            'type' => QuestionTypeShortText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 0,
        ]);

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

        $motif = new Question();
        $motif->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Motif / précisions', 'configurationglpiauto'),
            'type' => QuestionTypeLongText::class,
            'is_mandatory' => 0,
            'vertical_rank' => 3,
        ]);

        if (!$pays->getID() || !$debut->getID() || !$fin->getID() || !$motif->getID()) {
            return null;
        }

        return ['pays' => $pays, 'debut' => $debut, 'fin' => $fin, 'motif' => $motif];
    }

    /**
     * @param array{pays: Question, debut: Question, fin: Question, motif: Question} $questions
     */
    private function configureDestination(Form $form, int $itilCategoryId, array $questions): void
    {
        $destination = new FormDestination();
        if (!$destination->getFromDBByCrit(['forms_forms_id' => $form->getID(), 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        $answerProvider = new AnswerTagProvider();
        $formProvider = new FormTagProvider();

        // "<Nom du formulaire> du <date de début> au <date de fin> - <pays>" — same pattern
        // (literal text + tag spans) as the plugin owner's own verified real-world example, see
        // class docblock for why it has no requester-name tag.
        $titleValue = $formProvider->getTagForForm($form)->html
            . ' ' . __('du', 'configurationglpiauto') . ' ' . $answerProvider->getTagForQuestion($questions['debut'])->html
            . ' ' . __('au', 'configurationglpiauto') . ' ' . $answerProvider->getTagForQuestion($questions['fin'])->html
            . ' - ' . $answerProvider->getTagForQuestion($questions['pays'])->html;

        $config = [
            ITILCategoryField::getKey() => (new ITILCategoryFieldConfig(
                strategy: ITILCategoryFieldStrategy::SPECIFIC_VALUE,
                specific_itilcategory_id: $itilCategoryId,
            ))->jsonSerialize(),
            TitleField::getKey() => (new SimpleValueConfig($titleValue))->jsonSerialize(),
            ContentField::getAutoConfigKey() => 1,
            // No question asked : ITILActorFieldStrategy::FORM_FILLER_SUPERVISOR reads the
            // requester's own `users_id_supervisor` directly (confirmed real, same native GLPI
            // column `ValidationRoutingBuilder` already relies on for its own instance-wide
            // approval rule — see that class's docblock) and CCs them as an Observer on the
            // resulting ticket automatically, with nothing for the requester to fill in or forget.
            // A mission abroad is exactly the kind of request a manager should see land, without
            // this plugin having to ask "who is your manager?" (a question the requester might not
            // even answer correctly, and one this mechanism makes entirely unnecessary).
            ObserverField::getKey() => (new ObserverFieldConfig(
                strategies: [ITILActorFieldStrategy::FORM_FILLER_SUPERVISOR],
            ))->jsonSerialize(),
        ];

        $destination->update([
            'id' => $destination->getID(),
            'config' => json_encode($config),
        ]);
    }
}
