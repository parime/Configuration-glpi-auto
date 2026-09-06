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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration;

use Glpi\Form\AnswersHandler\AnswersHandler;
use Glpi\Form\EndUserInputNameProvider;
use Glpi\Form\Form;
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ServiceCatalogBuilder;
use ITILCategory;
use PHPUnit\Framework\TestCase;
use Ticket;

/**
 * `ServiceCatalogBuilder` is the piece tying the whole ~50-entry catalog together : it builds the
 * form-category tree AND is still the sole owner of the 2 entries deliberately left plain (see its
 * own class docblock — every other entry is `'smart' => true` and built by its own dedicated
 * `*FormBuilder` instead). Despite that, it had no test of its own before this one.
 *
 * Same "real submission, real assertion" pattern as the rest of this suite : the actual point of
 * `configureDestinationCategory()` is that a submitted ticket lands in the *specific*
 * `ITILCategory` the service maps to (`ITILCategoryFieldStrategy::SPECIFIC_VALUE`) — a config that
 * "looks right" in its own JSON is not proof GLPI actually routes the ticket there.
 */
final class ServiceCatalogBuilderTest extends TestCase
{
    private const RH_FORM_NAME = 'Demande administrative RH';

    private const SECURITE_FORM_NAME = 'Signaler un incident ou une urgence sécurité';

    /** @var int[] */
    private array $ticketIdsToDelete = [];

    protected function tearDown(): void
    {
        foreach ($this->ticketIdsToDelete as $id) {
            (new Ticket())->delete(['id' => $id], true);
        }

        foreach ([self::RH_FORM_NAME, self::SECURITE_FORM_NAME] as $formName) {
            $form = new Form();
            if ($form->getFromDBByCrit(['name' => $formName])) {
                $form->delete(['id' => $form->getID()], true);
            }
        }
    }

    private function buildConfig(bool $enabled = true): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_branches' => json_encode(['rh', 'securite']),
            'service_catalog_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testDisabledReturnsZeroAndCreatesNothing(): void
    {
        $config = $this->buildConfig(false);
        (new CategoryBuilder())->build($config);

        $count = (new ServiceCatalogBuilder())->build($config);

        $this->assertSame(0, $count);
        $this->assertFalse((new Form())->getFromDBByCrit(['name' => self::RH_FORM_NAME]));
    }

    public function testBuildCreatesBothPlainServiceFormsAndSkipsSmartOnes(): void
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);

        $count = (new ServiceCatalogBuilder())->build($config);

        // Exactly 2, not e.g. 44+2 — this is the real proof the loop's own 'smart' skip works:
        // the shared instance may legitimately already have some smart-flagged forms for real
        // (built by their own dedicated *FormBuilder from earlier wizard runs), so asserting a
        // specific one's absence here would be unsafe — the return count is the robust check.
        $this->assertSame(2, $count, 'Only the 2 deliberately-plain entries are this builder\'s own responsibility.');
        $this->assertTrue((new Form())->getFromDBByCrit(['name' => self::RH_FORM_NAME]));
        $this->assertTrue((new Form())->getFromDBByCrit(['name' => self::SECURITE_FORM_NAME]));
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateForms(): void
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);
        $builder = new ServiceCatalogBuilder();

        $first = $builder->build($config);

        $form = new Form();
        $this->assertTrue($form->getFromDBByCrit(['name' => self::RH_FORM_NAME]));
        $firstRunFormId = (int) $form->getID();

        $second = $builder->build($config);

        // Unlike most other *Builder classes in this plugin (e.g. PhysicalSecurityAssetBuilder,
        // whose own return value means "how many were newly created this call" — 0 on a second
        // run), this method's own docblock documents a different, still-correct contract: "Number
        // of service forms created *or reused*" — every call that matches the same enabled
        // branches counts the same 2 plain services, whether they already existed or not. The
        // real idempotency guarantee to verify is that the underlying form row is the exact same
        // one (same id), not recreated — not that the return value drops to 0.
        $this->assertSame(2, $first);
        $this->assertSame(2, $second);

        $form = new Form();
        $this->assertTrue($form->getFromDBByCrit(['name' => self::RH_FORM_NAME]));
        $this->assertSame(
            $firstRunFormId,
            (int) $form->getID(),
            'The second run must reuse the exact same form row, not create a duplicate.'
        );

        global $DB;
        $matches = $DB->request(['FROM' => Form::getTable(), 'WHERE' => ['name' => self::RH_FORM_NAME]])->count();
        $this->assertSame(1, $matches, 'Exactly one row must exist for this form name — no duplicate.');
    }

    /**
     * The actual regression this class of bug belongs to (see VehicleIncidentFormBuilderTest's own
     * docblock for the precedent) : a submitted ticket must really land in the specific
     * `ITILCategory` this service maps to, not just have a destination `config` that mentions it.
     */
    public function testSubmittedTicketIsRoutedToTheMappedItilCategory(): void
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);
        (new ServiceCatalogBuilder())->build($config);

        $form = new Form();
        $this->assertTrue($form->getFromDBByCrit(['name' => self::SECURITE_FORM_NAME]));

        $expectedCategory = new ITILCategory();
        $this->assertTrue($expectedCategory->getFromDBByCrit([
            'name' => 'Gestion des Incidents & Urgences',
        ]));

        $provider = new EndUserInputNameProvider();
        // Only "Description" is mandatory on these minimal forms (see addQuestions()) — Title is
        // optional, matching the "enter the least possible" philosophy documented on the class.
        $descriptionId = $this->questionIdByRank($form, 1);
        $answers = $provider->getAnswers([
            "answers_$descriptionId" => "Odeur suspecte dans le local technique, PHPUnit.",
        ]);

        $handler = AnswersHandler::getInstance();
        $validation = $handler->validateAnswers($form, $answers);
        $this->assertTrue($validation->isValid(), 'Submission should validate: ' . json_encode($validation->getErrors()));

        $answersSet = $handler->saveAnswers($form, $answers, users_id: 2);
        $created = array_values(array_filter(
            $answersSet->getCreatedItems(),
            static fn ($item) => $item instanceof Ticket
        ));
        $this->assertCount(1, $created);
        $ticket = $created[0];
        $this->ticketIdsToDelete[] = (int) $ticket->getID();

        $this->assertSame(
            (int) $expectedCategory->getID(),
            (int) $ticket->fields['itilcategories_id'],
            'The ticket must be routed to the real ITILCategory this service maps to.'
        );
    }

    private function questionIdByRank(Form $form, int $rank): int
    {
        $section = new \Glpi\Form\Section();
        $this->assertTrue($section->getFromDBByCrit(['forms_forms_id' => $form->getID()]));

        $question = new \Glpi\Form\Question();
        $this->assertTrue($question->getFromDBByCrit(['forms_sections_id' => $section->getID(), 'vertical_rank' => $rank]));

        return (int) $question->getID();
    }
}
