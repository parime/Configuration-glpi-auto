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
use GlpiPlugin\Configurationglpiauto\PasswordResetFormBuilder;
use PHPUnit\Framework\TestCase;
use Ticket;
use User;

/**
 * This form's "Compte utilisateur concerné" is a `QuestionTypeItem` pointing at the native `User`
 * itemtype (not one of this plugin's own custom assets, and not `QuestionTypeUserDevice` either) —
 * unlike its siblings, there's no `AssociatedItemsField` here (see class docblock : locking a
 * requester's *own* ticket to "User X" as an associated item wouldn't mean much), so the meaningful
 * real-submission assertion is that the computed title actually resolves to the target user's real
 * name, and that `RequestTypeField::SPECIFIC_VALUE` (`Ticket::INCIDENT_TYPE`) sticks.
 */
final class PasswordResetFormBuilderTest extends TestCase
{
    private const FORM_NAME = 'Réinitialisation de mot de passe';

    /** @var int[] */
    private array $ticketIdsToDelete = [];

    /** @var int[] */
    private array $userIdsToDelete = [];

    protected function tearDown(): void
    {
        foreach ($this->ticketIdsToDelete as $id) {
            (new Ticket())->delete(['id' => $id], true);
        }
        foreach ($this->userIdsToDelete as $id) {
            (new User())->delete(['id' => $id], true);
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
        (new PasswordResetFormBuilder())->build($config);

        $form = new Form();
        $this->assertTrue(
            $form->getFromDBByCrit(['name' => self::FORM_NAME]),
            'PasswordResetFormBuilder should have created the form.'
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

    private function addUser(string $name): int
    {
        $id = (int) (new User())->add(['name' => $name, 'realname' => $name, '_skip_default_group' => true]);
        $this->userIdsToDelete[] = $id;

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

    public function testTitleIncludesTargetAccountAndTicketIsAnIncident(): void
    {
        $form = $this->buildForm();
        $targetUserId = $this->addUser('phpunit_password_reset_target');
        $identifiantId = $this->questionIdByRank($form, 0);
        $motifId = $this->questionIdByRank($form, 1);

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$identifiantId" => ['itemtype' => User::class, 'items_id' => $targetUserId],
            "answers_$motifId" => ['2'],
        ]);

        $this->assertSame(Ticket::INCIDENT_TYPE, (int) $ticket->fields['type']);
        $this->assertStringContainsString('phpunit_password_reset_target', $ticket->fields['name']);
    }
}
