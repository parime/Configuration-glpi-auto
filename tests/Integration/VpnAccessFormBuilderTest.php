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
use GlpiPlugin\Configurationglpiauto\VpnAccessFormBuilder;
use ITILCategory;
use PHPUnit\Framework\TestCase;
use Ticket;

/**
 * Same conditional-visibility mechanism as `WifiAccessFormBuilderTest` — the "Date de fin d'accès"
 * question must be genuinely optional for a permanent request and genuinely taken into account for
 * a temporary one.
 */
final class VpnAccessFormBuilderTest extends TestCase
{
    private const FORM_NAME = "Demande d'accès VPN";

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
        (new VpnAccessFormBuilder())->build($config);

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

    public function testTemporaryAccessAcceptsAnEndDate(): void
    {
        $form = $this->buildForm();
        $reseauId = $this->questionIdByRank($form, 0);
        $dureeId = $this->questionIdByRank($form, 1);
        $finId = $this->questionIdByRank($form, 2);

        $ticket = $this->submit($form, [
            "answers_$reseauId" => 'Serveur de fichiers RH',
            "answers_$dureeId" => ['1'],
            "answers_$finId" => '2026-12-01',
        ]);

        $this->assertStringContainsString('Serveur de fichiers RH', $ticket->fields['name']);
        $this->assertStringContainsString('Temporaire', $ticket->fields['name']);
    }

    public function testPermanentAccessDoesNotRequireAnEndDate(): void
    {
        $form = $this->buildForm();
        $reseauId = $this->questionIdByRank($form, 0);
        $dureeId = $this->questionIdByRank($form, 1);

        $ticket = $this->submit($form, [
            "answers_$reseauId" => 'Réseau interne complet',
            "answers_$dureeId" => ['2'],
        ]);

        $this->assertStringContainsString('Permanent', $ticket->fields['name']);
    }

    public function testSubmissionRoutesToTheRemoteAccessCategory(): void
    {
        $form = $this->buildForm();
        $reseauId = $this->questionIdByRank($form, 0);
        $dureeId = $this->questionIdByRank($form, 1);

        $ticket = $this->submit($form, [
            "answers_$reseauId" => 'Réseau interne',
            "answers_$dureeId" => ['2'],
        ]);

        $branch = new ITILCategory();
        $this->assertTrue($branch->getFromDBByCrit(['name' => 'IT & SI', 'itilcategories_id' => 0]));
        $reseau = new ITILCategory();
        $this->assertTrue($reseau->getFromDBByCrit(['name' => 'Réseau & Connectivité', 'itilcategories_id' => $branch->getID()]));
        $expectedCategory = new ITILCategory();
        $this->assertTrue($expectedCategory->getFromDBByCrit(['name' => 'Accès Distant', 'itilcategories_id' => $reseau->getID()]));

        $this->assertSame((int) $expectedCategory->getID(), (int) $ticket->fields['itilcategories_id']);
    }
}
