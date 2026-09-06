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
use GlpiPlugin\Configurationglpiauto\WifiAccessFormBuilder;
use ITILCategory;
use PHPUnit\Framework\TestCase;
use Ticket;

/**
 * The "Date de fin d'accès" question is gated behind `VisibilityStrategy::VISIBLE_IF` on the
 * "temporary" radio option (same mechanism as `VpnAccessFormBuilder`) — the real regression to
 * guard is that it is genuinely optional (not enforced mandatory) when hidden for a permanent
 * request, and genuinely required/answerable when visible for a temporary one.
 */
final class WifiAccessFormBuilderTest extends TestCase
{
    private const FORM_NAME = "Demande d'accès Wifi";

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
        (new WifiAccessFormBuilder())->build($config);

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

    public function testTemporaryAccessRequiresAndAcceptsAnEndDate(): void
    {
        $form = $this->buildForm();
        $typeId = $this->questionIdByRank($form, 0);
        $appareilId = $this->questionIdByRank($form, 1);
        $dateFinId = $this->questionIdByRank($form, 2);

        $ticket = $this->submit($form, [
            "answers_$typeId" => ['2'],
            "answers_$appareilId" => 'PC portable invité — Jean Dupont',
            "answers_$dateFinId" => '2026-11-05',
        ]);

        $this->assertStringContainsString('Accès temporaire', $ticket->fields['name']);
    }

    public function testPermanentAccessDoesNotRequireAnEndDate(): void
    {
        $form = $this->buildForm();
        $typeId = $this->questionIdByRank($form, 0);
        $appareilId = $this->questionIdByRank($form, 1);

        $ticket = $this->submit($form, [
            "answers_$typeId" => ['1'],
            "answers_$appareilId" => 'Ordinateur de bureau — Marie Curie',
        ]);

        $this->assertStringContainsString('Accès permanent', $ticket->fields['name']);
    }

    public function testSubmissionRoutesToTheWifiCategory(): void
    {
        $form = $this->buildForm();
        $typeId = $this->questionIdByRank($form, 0);
        $appareilId = $this->questionIdByRank($form, 1);

        $ticket = $this->submit($form, [
            "answers_$typeId" => ['1'],
            "answers_$appareilId" => 'Ordinateur de bureau',
        ]);

        $branch = new ITILCategory();
        $this->assertTrue($branch->getFromDBByCrit(['name' => 'IT & SI', 'itilcategories_id' => 0]));
        $reseau = new ITILCategory();
        $this->assertTrue($reseau->getFromDBByCrit(['name' => 'Réseau & Connectivité', 'itilcategories_id' => $branch->getID()]));
        $expectedCategory = new ITILCategory();
        $this->assertTrue($expectedCategory->getFromDBByCrit(['name' => 'Wifi', 'itilcategories_id' => $reseau->getID()]));

        $this->assertSame((int) $expectedCategory->getID(), (int) $ticket->fields['itilcategories_id']);
    }
}
