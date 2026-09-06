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
use GlpiPlugin\Configurationglpiauto\ProfessionalPhoneFormBuilder;
use Item_Ticket;
use PHPUnit\Framework\TestCase;
use Phone;
use Ticket;

/**
 * Same real-submission proof as `SoftwareBugFormBuilderTest`/`LaptopRequestFormBuilderTest` for the
 * `QuestionTypeUserDevice` + `AssociatedItemsField` pattern — this one against a real `Phone`, not a
 * `Computer`, confirming the "<Itemtype>_<id>" raw-answer format and the association both work for a
 * different native itemtype too.
 */
final class ProfessionalPhoneFormBuilderTest extends TestCase
{
    private const FORM_NAME = 'Demande de téléphone professionnel';

    private const REMPLACEMENT_OPTION_KEY = '2';

    /** @var int[] */
    private array $ticketIdsToDelete = [];

    /** @var int[] */
    private array $phoneIdsToDelete = [];

    protected function tearDown(): void
    {
        foreach ($this->ticketIdsToDelete as $id) {
            (new Ticket())->delete(['id' => $id], true);
        }
        foreach ($this->phoneIdsToDelete as $id) {
            (new Phone())->delete(['id' => $id], true);
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
        (new ProfessionalPhoneFormBuilder())->build($config);

        $form = new Form();
        $this->assertTrue(
            $form->getFromDBByCrit(['name' => self::FORM_NAME]),
            'ProfessionalPhoneFormBuilder should have created the form.'
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

    private function addRealPhoneForUser(string $name, int $usersId): int
    {
        $id = (int) (new Phone())->add([
            'name' => $name,
            'entities_id' => 0,
            'users_id' => $usersId,
        ]);
        $this->phoneIdsToDelete[] = $id;

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

    public function testReplacedPhoneIsAssociatedOnTheTicket(): void
    {
        $form = $this->buildForm();
        $phoneId = $this->addRealPhoneForUser('PHPUnit — Téléphone à remplacer', 2);
        $typeAppId = $this->questionIdByRank($form, 0);
        $motifId = $this->questionIdByRank($form, 1);
        $modeleId = $this->questionIdByRank($form, 2);

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$typeAppId" => ['1'],
            "answers_$motifId" => [self::REMPLACEMENT_OPTION_KEY],
            "answers_$modeleId" => Phone::class . '_' . $phoneId,
        ]);

        $link = new Item_Ticket();
        $this->assertTrue(
            $link->getFromDBByCrit([
                'tickets_id' => $ticket->getID(),
                'itemtype' => Phone::class,
                'items_id' => $phoneId,
            ]),
            'The ticket should be linked to the replaced phone via a real Item_Ticket row.'
        );
        $this->assertSame(Ticket::DEMAND_TYPE, (int) $ticket->fields['type']);
    }

    public function testFirstEquipmentMotifAssociatesNoItem(): void
    {
        $form = $this->buildForm();
        $typeAppId = $this->questionIdByRank($form, 0);
        $motifId = $this->questionIdByRank($form, 1);

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$typeAppId" => ['2'],
            "answers_$motifId" => ['1'],
        ]);

        $link = new Item_Ticket();
        $this->assertFalse(
            $link->getFromDBByCrit([
                'tickets_id' => $ticket->getID(),
                'itemtype' => Phone::class,
            ]),
            'No phone should be associated on the "premier équipement" motif.'
        );
    }
}
