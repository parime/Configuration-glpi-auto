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
use Glpi\Form\Question;
use Glpi\Form\Section;
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\StaffMovementFormBuilder;
use ITILCategory;
use PHPUnit\Framework\TestCase;
use Ticket;

/**
 * The only one of the catalog's smart forms using `LogicOperator::OR` (see class docblock) :
 * "Poste concerné" must be visible/answerable for *either* "Arrivée" or "Mutation", but not for
 * "Départ" — the real regression to guard is that the OR condition genuinely covers both values,
 * not just the first one a naive AND-based implementation would silently limit it to.
 */
final class StaffMovementFormBuilderTest extends TestCase
{
    private const FORM_NAME = 'Déclarer une arrivée, un départ ou une mutation';

    /** @var int[] */
    private array $ticketIdsToDelete = [];

    protected function tearDown(): void
    {
        foreach ($this->ticketIdsToDelete as $id) {
            (new Ticket())->delete(['id' => $id], true);
        }
    }

    private function buildConfig(): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_branches' => json_encode(['rh']),
            'service_catalog_enabled' => 1,
        ]);

        return $config;
    }

    private function buildForm(): Form
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);
        (new StaffMovementFormBuilder())->build($config);

        $form = new Form();
        $this->assertTrue($form->getFromDBByCrit(['name' => self::FORM_NAME]));

        return $form;
    }

    private function questionIdByRank(Form $form, int $rank): int
    {
        $section = new Section();
        $this->assertTrue($section->getFromDBByCrit(['forms_forms_id' => $form->getID()]));

        $question = new Question();
        $this->assertTrue($question->getFromDBByCrit(['forms_sections_id' => $section->getID(), 'vertical_rank' => $rank]));

        return (int) $question->getID();
    }

    private function submit(Form $form, array $rawPostAnswers): Ticket
    {
        $provider = new EndUserInputNameProvider();
        $answers = $provider->getAnswers($rawPostAnswers);

        $handler = AnswersHandler::getInstance();
        $validation = $handler->validateAnswers($form, $answers);
        $this->assertTrue($validation->isValid(), 'Submission should validate: ' . json_encode($validation->getErrors()));

        $answersSet = $handler->saveAnswers($form, $answers, users_id: 2);
        $created = array_values(array_filter($answersSet->getCreatedItems(), static fn ($item) => $item instanceof Ticket));
        $this->assertCount(1, $created);
        $ticket = $created[0];
        $this->ticketIdsToDelete[] = (int) $ticket->getID();

        return $ticket;
    }

    public function testArrivalRequiresAndAcceptsThePosition(): void
    {
        $form = $this->buildForm();
        $nomId = $this->questionIdByRank($form, 0);
        $typeId = $this->questionIdByRank($form, 1);
        $posteId = $this->questionIdByRank($form, 2);
        $effetId = $this->questionIdByRank($form, 3);

        $ticket = $this->submit($form, [
            "answers_$nomId" => 'Alice Martin',
            "answers_$typeId" => ['1'],
            "answers_$posteId" => 'Chef de projet',
            "answers_$effetId" => '2026-11-01',
        ]);

        $this->assertStringContainsString('Arrivée', $ticket->fields['name']);
        $this->assertStringContainsString('Alice Martin', $ticket->fields['name']);
    }

    public function testTransferAlsoRequiresAndAcceptsThePosition(): void
    {
        $form = $this->buildForm();
        $nomId = $this->questionIdByRank($form, 0);
        $typeId = $this->questionIdByRank($form, 1);
        $posteId = $this->questionIdByRank($form, 2);
        $effetId = $this->questionIdByRank($form, 3);

        $ticket = $this->submit($form, [
            "answers_$nomId" => 'Bruno Petit',
            "answers_$typeId" => ['3'],
            "answers_$posteId" => 'Responsable régional',
            "answers_$effetId" => '2026-12-01',
        ]);

        $this->assertStringContainsString('Mutation', $ticket->fields['name']);
    }

    public function testDepartureDoesNotRequireThePosition(): void
    {
        $form = $this->buildForm();
        $nomId = $this->questionIdByRank($form, 0);
        $typeId = $this->questionIdByRank($form, 1);
        $effetId = $this->questionIdByRank($form, 3);

        $ticket = $this->submit($form, [
            "answers_$nomId" => 'Chloé Dubois',
            "answers_$typeId" => ['2'],
            "answers_$effetId" => '2026-12-15',
        ]);

        $this->assertStringContainsString('Départ', $ticket->fields['name']);
    }

    public function testSubmissionRoutesToStaffMovementsCategory(): void
    {
        $form = $this->buildForm();
        $nomId = $this->questionIdByRank($form, 0);
        $typeId = $this->questionIdByRank($form, 1);
        $effetId = $this->questionIdByRank($form, 3);

        $ticket = $this->submit($form, [
            "answers_$nomId" => 'Test',
            "answers_$typeId" => ['2'],
            "answers_$effetId" => '2026-12-15',
        ]);

        $branch = new ITILCategory();
        $this->assertTrue($branch->getFromDBByCrit(['name' => 'Ressources Humaines', 'itilcategories_id' => 0]));
        $expectedCategory = new ITILCategory();
        $this->assertTrue($expectedCategory->getFromDBByCrit(['name' => 'Mouvements de personnel', 'itilcategories_id' => $branch->getID()]));

        $this->assertSame((int) $expectedCategory->getID(), (int) $ticket->fields['itilcategories_id']);
    }
}
