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

use Computer;
use Glpi\Form\AnswersHandler\AnswersHandler;
use Glpi\Form\EndUserInputNameProvider;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\Section;
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\NewScreenFormBuilder;
use Item_Ticket;
use PHPUnit\Framework\TestCase;
use Ticket;

/**
 * Same real-submission proof as `SoftwareBugFormBuilderTest`/`LaptopRequestFormBuilderTest` for the
 * `QuestionTypeUserDevice` + `AssociatedItemsField` pattern — here the device question
 * ("Poste auquel connecter l'écran") is unconditionally optional (no `VISIBLE_IF` gate tied to the
 * motif, unlike Laptop/ProfessionalPhone), so this suite exercises the answered/unanswered cases
 * directly rather than via a specific motif branch.
 */
final class NewScreenFormBuilderTest extends TestCase
{
    private const FORM_NAME = "Demande d'un nouvel écran";

    /** @var int[] */
    private array $ticketIdsToDelete = [];

    /** @var int[] */
    private array $computerIdsToDelete = [];

    protected function tearDown(): void
    {
        foreach ($this->ticketIdsToDelete as $id) {
            (new Ticket())->delete(['id' => $id], true);
        }
        foreach ($this->computerIdsToDelete as $id) {
            (new Computer())->delete(['id' => $id], true);
        }

        $form = new Form();
        if ($form->getFromDBByCrit(['name' => self::FORM_NAME])) {
            $form->delete(['id' => $form->getID()], true);
        }
    }

    private function buildConfig(): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_branches' => json_encode(['it']),
            'service_catalog_enabled' => 1,
        ]);

        return $config;
    }

    private function buildForm(): Form
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);
        (new NewScreenFormBuilder())->build($config);

        $form = new Form();
        $this->assertTrue(
            $form->getFromDBByCrit(['name' => self::FORM_NAME]),
            'NewScreenFormBuilder should have created the form.'
        );

        return $form;
    }

    private function questionIdByRank(Form $form, int $rank): int
    {
        $section = new Section();
        $this->assertTrue(
            $section->getFromDBByCrit(['forms_forms_id' => $form->getID()]),
            'The form should have its default section.'
        );

        $question = new Question();
        $this->assertTrue(
            $question->getFromDBByCrit(['forms_sections_id' => $section->getID(), 'vertical_rank' => $rank]),
            "Question at vertical_rank $rank should exist on the form."
        );

        return (int) $question->getID();
    }

    private function addRealComputerForUser(string $name, int $usersId): int
    {
        $id = (int) (new Computer())->add([
            'name' => $name,
            'entities_id' => 0,
            'users_id' => $usersId,
        ]);
        $this->computerIdsToDelete[] = $id;

        return $id;
    }

    private function submitAndGetTicket(Form $form, array $rawPostAnswers): Ticket
    {
        $provider = new EndUserInputNameProvider();
        $answers = $provider->getAnswers($rawPostAnswers);

        $handler = AnswersHandler::getInstance();
        $validation = $handler->validateAnswers($form, $answers);
        $this->assertTrue(
            $validation->isValid(),
            'Submission should validate: ' . json_encode($validation->getErrors())
        );

        $answersSet = $handler->saveAnswers($form, $answers, users_id: 2);

        $created = array_values(array_filter(
            $answersSet->getCreatedItems(),
            static fn($item) => $item instanceof Ticket
        ));
        $this->assertCount(1, $created, 'Submission should create exactly one Ticket.');

        $ticket = $created[0];
        $this->ticketIdsToDelete[] = (int) $ticket->getID();

        return $ticket;
    }

    public function testReferencedComputerIsAssociatedOnTheTicket(): void
    {
        $form = $this->buildForm();
        $computerId = $this->addRealComputerForUser('PHPUnit — Poste à connecter', 2);
        $nombreId = $this->questionIdByRank($form, 0);
        $motifId = $this->questionIdByRank($form, 2);
        $posteId = $this->questionIdByRank($form, 4);

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$nombreId" => ['1'],
            "answers_$motifId" => ['3'],
            "answers_$posteId" => Computer::class . '_' . $computerId,
        ]);

        $link = new Item_Ticket();
        $this->assertTrue(
            $link->getFromDBByCrit([
                'tickets_id' => $ticket->getID(),
                'itemtype' => Computer::class,
                'items_id' => $computerId,
            ]),
            'The ticket should be linked to the referenced computer via a real Item_Ticket row.'
        );
        $this->assertSame(Ticket::DEMAND_TYPE, (int) $ticket->fields['type']);
    }

    public function testUnansweredDeviceAssociatesNoItem(): void
    {
        $form = $this->buildForm();
        $nombreId = $this->questionIdByRank($form, 0);
        $motifId = $this->questionIdByRank($form, 2);

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$nombreId" => ['2'],
            "answers_$motifId" => ['1'],
        ]);

        $link = new Item_Ticket();
        $this->assertFalse(
            $link->getFromDBByCrit([
                'tickets_id' => $ticket->getID(),
                'itemtype' => Computer::class,
            ]),
            'No computer should be associated when the optional device question was left unanswered.'
        );
    }
}
