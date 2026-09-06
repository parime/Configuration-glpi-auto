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
use GlpiPlugin\Configurationglpiauto\SocialMediaPostFormBuilder;
use ITILCategory;
use PHPUnit\Framework\TestCase;
use Ticket;

/**
 * "Réseau(x) concerné(s)" is a `QuestionTypeCheckbox` (multi-select) — same real regression to
 * guard as `EventSupportFormBuilderTest` : several networks selected at once must be accepted.
 */
final class SocialMediaPostFormBuilderTest extends TestCase
{
    private const FORM_NAME = 'Demande de publication sur les réseaux sociaux';

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
            'category_branches' => json_encode(['communication']),
            'service_catalog_enabled' => 1,
        ]);

        return $config;
    }

    private function buildForm(): Form
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);
        (new SocialMediaPostFormBuilder())->build($config);

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

    public function testSubmissionWithMultipleNetworksRoutesToSocialMediaCategory(): void
    {
        $form = $this->buildForm();
        $reseauxId = $this->questionIdByRank($form, 0);
        $dateId = $this->questionIdByRank($form, 1);

        $provider = new EndUserInputNameProvider();
        $answers = $provider->getAnswers([
            "answers_$reseauxId" => ['1', '3'],
            "answers_$dateId" => '2026-11-20',
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
        $this->assertTrue($branch->getFromDBByCrit(['name' => 'Communication & Marketing', 'itilcategories_id' => 0]));
        $expectedCategory = new ITILCategory();
        $this->assertTrue($expectedCategory->getFromDBByCrit(['name' => 'Réseaux Sociaux & Marketing', 'itilcategories_id' => $branch->getID()]));

        $this->assertSame((int) $expectedCategory->getID(), (int) $ticket->fields['itilcategories_id']);
        $this->assertStringContainsString('LinkedIn', $ticket->fields['name']);
        $this->assertStringContainsString('Instagram', $ticket->fields['name']);
    }
}
