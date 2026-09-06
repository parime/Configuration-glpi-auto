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
use GlpiPlugin\Configurationglpiauto\ItemReturnFormBuilder;
use PHPUnit\Framework\TestCase;
use Ticket;

/**
 * Unlike its siblings elsewhere in this suite, this form has no `QuestionTypeItem`/
 * `QuestionTypeUserDevice` question — no real GLPI itemtype in the "achats/logistique" branch for one
 * to point at (see class docblock). Its only destination-level behavior worth a real assertion is
 * `RequestTypeField::SPECIFIC_VALUE` and the computed title — still real submission, real assertion,
 * not just "the form saves without error".
 */
final class ItemReturnFormBuilderTest extends TestCase
{
    private const FORM_NAME = "Retour ou SAV d'un article";

    /** @var int[] */
    private array $ticketIdsToDelete = [];

    protected function tearDown(): void
    {
        foreach ($this->ticketIdsToDelete as $id) {
            (new Ticket())->delete(['id' => $id], true);
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
            'category_branches' => json_encode(['achats']),
            'service_catalog_enabled' => 1,
        ]);

        return $config;
    }

    private function buildForm(): Form
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);
        (new ItemReturnFormBuilder())->build($config);

        $form = new Form();
        $this->assertTrue(
            $form->getFromDBByCrit(['name' => self::FORM_NAME]),
            'ItemReturnFormBuilder should have created the form.'
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

    public function testSubmissionCreatesADemandWithTitleFromArticle(): void
    {
        $form = $this->buildForm();
        $articleId = $this->questionIdByRank($form, 0);
        $typeId = $this->questionIdByRank($form, 1);

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$articleId" => 'Casque audio PHPUnit-500',
            "answers_$typeId" => ['2'],
        ]);

        $this->assertSame(Ticket::DEMAND_TYPE, (int) $ticket->fields['type']);
        $this->assertStringContainsString('Casque audio PHPUnit-500', $ticket->fields['name']);
    }
}
