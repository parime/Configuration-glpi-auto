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
use GlpiPlugin\Configurationglpiauto\TeamCollaborationSpaceFormBuilder;
use ITILCategory;
use PHPUnit\Framework\TestCase;
use Ticket;

final class TeamCollaborationSpaceFormBuilderTest extends TestCase
{
    private const FORM_NAME = "Demande d'accès à un espace collaboratif d'équipe";

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
            'category_branches' => json_encode(['it']),
            'service_catalog_enabled' => 1,
        ]);

        return $config;
    }

    private function buildForm(): Form
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);
        (new TeamCollaborationSpaceFormBuilder())->build($config);

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

    public function testSubmissionRoutesToCollaborationCategoryAndTitleIncludesTeamName(): void
    {
        $form = $this->buildForm();
        $nomEquipeId = $this->questionIdByRank($form, 0);
        $typeAccesId = $this->questionIdByRank($form, 1);

        $provider = new EndUserInputNameProvider();
        $answers = $provider->getAnswers([
            "answers_$nomEquipeId" => 'Projet Phoenix',
            "answers_$typeAccesId" => ['2'],
        ]);

        $handler = AnswersHandler::getInstance();
        $validation = $handler->validateAnswers($form, $answers);
        $this->assertTrue($validation->isValid(), 'Submission should validate: ' . json_encode($validation->getErrors()));

        $answersSet = $handler->saveAnswers($form, $answers, users_id: 2);
        $created = array_values(array_filter($answersSet->getCreatedItems(), static fn ($item) => $item instanceof Ticket));
        $this->assertCount(1, $created);
        $ticket = $created[0];
        $this->ticketIdsToDelete[] = (int) $ticket->getID();

        $branch = new ITILCategory();
        $this->assertTrue($branch->getFromDBByCrit(['name' => 'IT & SI', 'itilcategories_id' => 0]));
        $messagerie = new ITILCategory();
        $this->assertTrue($messagerie->getFromDBByCrit(['name' => 'Messagerie & Collaboration', 'itilcategories_id' => $branch->getID()]));
        $expectedCategory = new ITILCategory();
        $this->assertTrue($expectedCategory->getFromDBByCrit(['name' => 'Collaboration', 'itilcategories_id' => $messagerie->getID()]));

        $this->assertSame((int) $expectedCategory->getID(), (int) $ticket->fields['itilcategories_id']);
        $this->assertStringContainsString('Projet Phoenix', $ticket->fields['name']);
    }
}
