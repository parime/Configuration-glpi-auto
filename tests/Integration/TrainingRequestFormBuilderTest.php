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

use CommonITILActor;
use Glpi\Form\AnswersHandler\AnswersHandler;
use Glpi\Form\EndUserInputNameProvider;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\Section;
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\TrainingRequestFormBuilder;
use PHPUnit\Framework\TestCase;
use Ticket;
use Ticket_User;
use User;

/**
 * Same real-submission proof as `LeaveRequestFormBuilderTest` for the identical
 * `ObserverField`(`FORM_FILLER_SUPERVISOR`) wiring — see that class's docblock for the full
 * reasoning.
 */
final class TrainingRequestFormBuilderTest extends TestCase
{
    private const FORM_NAME = 'Demande de formation';

    /** @var int[] */
    private array $ticketIdsToDelete = [];

    /** @var int[] */
    private array $userIdsToDelete = [];

    private int|false $originalSessionUserId = false;

    protected function tearDown(): void
    {
        if ($this->originalSessionUserId !== false) {
            $_SESSION['glpiID'] = $this->originalSessionUserId;
        }

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
            'category_branches' => json_encode(['rh']),
            'service_catalog_enabled' => 1,
        ]);

        return $config;
    }

    private function buildForm(): Form
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);
        (new TrainingRequestFormBuilder())->build($config);

        $form = new Form();
        $this->assertTrue(
            $form->getFromDBByCrit(['name' => self::FORM_NAME]),
            'TrainingRequestFormBuilder should have created the form.'
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

    private function addUser(string $name, ?int $supervisorId = null): int
    {
        $input = ['name' => $name, 'realname' => $name, '_skip_default_group' => true];
        if ($supervisorId !== null) {
            $input['users_id_supervisor'] = $supervisorId;
        }
        $id = (int) (new User())->add($input);
        $this->userIdsToDelete[] = $id;

        return $id;
    }

    private function submitAsUser(int $usersId, Form $form, array $rawPostAnswers): Ticket
    {
        if ($this->originalSessionUserId === false) {
            $this->originalSessionUserId = $_SESSION['glpiID'];
        }
        $_SESSION['glpiID'] = $usersId;

        $provider = new EndUserInputNameProvider();
        $answers = $provider->getAnswers($rawPostAnswers);

        $handler = AnswersHandler::getInstance();
        $validation = $handler->validateAnswers($form, $answers);
        $this->assertTrue(
            $validation->isValid(),
            'Submission should validate: ' . json_encode($validation->getErrors())
        );

        $answersSet = $handler->saveAnswers($form, $answers, users_id: $usersId);

        $_SESSION['glpiID'] = $this->originalSessionUserId;

        $created = array_values(array_filter(
            $answersSet->getCreatedItems(),
            static fn($item) => $item instanceof Ticket
        ));
        $this->assertCount(1, $created, 'Submission should create exactly one Ticket.');

        $ticket = $created[0];
        $this->ticketIdsToDelete[] = (int) $ticket->getID();

        return $ticket;
    }

    public function testRequesterWithSupervisorGetsSupervisorAddedAsObserver(): void
    {
        $form = $this->buildForm();
        $supervisorId = $this->addUser('phpunit_training_supervisor');
        $requesterId = $this->addUser('phpunit_training_requester', $supervisorId);

        $intituleId = $this->questionIdByRank($form, 0);
        $periodeId = $this->questionIdByRank($form, 1);

        $ticket = $this->submitAsUser($requesterId, $form, [
            "answers_$intituleId" => 'Certification ITIL 4 Foundation',
            "answers_$periodeId" => '2026-11-03',
        ]);

        $link = new Ticket_User();
        $this->assertTrue(
            $link->getFromDBByCrit([
                'tickets_id' => $ticket->getID(),
                'users_id' => $supervisorId,
                'type' => CommonITILActor::OBSERVER,
            ]),
            'The supervisor should be added as a real Observer on the ticket.'
        );
    }
}
