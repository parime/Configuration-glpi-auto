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
use GlpiPlugin\Configurationglpiauto\LaptopRequestFormBuilder;
use Item_Ticket;
use PHPUnit\Framework\TestCase;
use Ticket;

/**
 * Same real-submission proof as `SoftwareBugFormBuilderTest` for the `QuestionTypeUserDevice` +
 * `AssociatedItemsField` pattern — see that class's docblock for the raw-answer-format gotcha
 * ("<Itemtype>_<id>" string, not the `{itemtype, items_id}` array `QuestionTypeItem` expects). Also
 * confirms the "poste actuel" question's own conditional visibility (only shown/mandatory on the
 * "Remplacement" motif) doesn't break the association wiring.
 */
final class LaptopRequestFormBuilderTest extends TestCase
{
    private const FORM_NAME = "Demande d'un ordinateur portable";

    private const REMPLACEMENT_OPTION_KEY = '2';

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
        (new LaptopRequestFormBuilder())->build($config);

        $form = new Form();
        $this->assertTrue(
            $form->getFromDBByCrit(['name' => self::FORM_NAME]),
            'LaptopRequestFormBuilder should have created the form.'
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

    public function testReplacedComputerIsAssociatedOnTheTicket(): void
    {
        $form = $this->buildForm();
        $computerId = $this->addRealComputerForUser('PHPUnit — Poste à remplacer', 2);
        $motifId = $this->questionIdByRank($form, 0);
        $posteId = $this->questionIdByRank($form, 1);

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$motifId" => [self::REMPLACEMENT_OPTION_KEY],
            "answers_$posteId" => Computer::class . '_' . $computerId,
        ]);

        $link = new Item_Ticket();
        $this->assertTrue(
            $link->getFromDBByCrit([
                'tickets_id' => $ticket->getID(),
                'itemtype' => Computer::class,
                'items_id' => $computerId,
            ]),
            'The ticket should be linked to the replaced computer via a real Item_Ticket row.'
        );
        $this->assertSame(Ticket::DEMAND_TYPE, (int) $ticket->fields['type']);
    }

    /**
     * "Premier équipement" (no existing machine to replace) never shows the device question at
     * all — this must not silently associate a stale or wrong item.
     */
    public function testFirstEquipmentMotifAssociatesNoItem(): void
    {
        $form = $this->buildForm();
        $motifId = $this->questionIdByRank($form, 0);

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$motifId" => ['1'],
        ]);

        $link = new Item_Ticket();
        $this->assertFalse(
            $link->getFromDBByCrit([
                'tickets_id' => $ticket->getID(),
                'itemtype' => Computer::class,
            ]),
            'No computer should be associated on the "premier équipement" motif.'
        );
    }
}
