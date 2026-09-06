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
use GlpiPlugin\Configurationglpiauto\LeaveRequestFormBuilder;
use PHPUnit\Framework\TestCase;
use Ticket;
use Ticket_User;
use User;

/**
 * Real-submission regression guard for `ObserverField`(`ITILActorFieldStrategy::
 * FORM_FILLER_SUPERVISOR`) — a destination mechanism that reads the *actual submitting session's*
 * `Session::getLoginUserID()`, looks up that user's own `users_id_supervisor`, and CCs them as an
 * Observer, confirmed by reading `ITILActorFieldStrategy::getActorsFromSupervisorOfCurrentUser()`
 * directly. This plugin's own integration bootstrap always authenticates as the "glpi" superadmin
 * (id 2, no supervisor), so a test that only submits as that account could never actually exercise
 * this path — this suite temporarily points `$_SESSION['glpiID']` at a real, freshly created user
 * with a real supervisor (restored in `tearDown()`), the same session key `Session::getLoginUserID()`
 * reads, to submit "as" that user for real without a second HTTP login round-trip. Same class of
 * proof as the other builders' tests in this suite : the destination `config` JSON has always looked
 * correct for this wiring (`ObserverField::getKey() => ObserverFieldConfig([FORM_FILLER_SUPERVISOR])`)
 * — only a real submission followed by a real `Ticket_User` row proves GLPI actually acts on it.
 */
final class LeaveRequestFormBuilderTest extends TestCase
{
    private const FORM_NAME = 'Demande de congé ou absence';

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
        (new LeaveRequestFormBuilder())->build($config);

        $form = new Form();
        $this->assertTrue(
            $form->getFromDBByCrit(['name' => self::FORM_NAME]),
            'LeaveRequestFormBuilder should have created the form.'
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

    /**
     * Points the real GLPI session at `$usersId` for the duration of the submission — the exact
     * session key `ITILActorFieldStrategy::getActorsFromSupervisorOfCurrentUser()` reads via
     * `Session::getLoginUserID()`. Captured once so `tearDown()` can restore the superadmin session
     * every other test in this process expects.
     */
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
        $supervisorId = $this->addUser('phpunit_leave_supervisor');
        $requesterId = $this->addUser('phpunit_leave_requester', $supervisorId);

        $typeId = $this->questionIdByRank($form, 0);
        $debutId = $this->questionIdByRank($form, 1);
        $finId = $this->questionIdByRank($form, 2);

        $ticket = $this->submitAsUser($requesterId, $form, [
            "answers_$typeId" => ['1'],
            "answers_$debutId" => '2026-09-14',
            "answers_$finId" => '2026-09-18',
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

    public function testRequesterWithoutSupervisorGetsNoObserverAdded(): void
    {
        $form = $this->buildForm();
        $requesterId = $this->addUser('phpunit_leave_requester_no_supervisor');

        $typeId = $this->questionIdByRank($form, 0);
        $debutId = $this->questionIdByRank($form, 1);
        $finId = $this->questionIdByRank($form, 2);

        $ticket = $this->submitAsUser($requesterId, $form, [
            "answers_$typeId" => ['2'],
            "answers_$debutId" => '2026-09-21',
            "answers_$finId" => '2026-09-22',
        ]);

        global $DB;
        $count = $DB->request([
            'COUNT' => 'c',
            'FROM' => 'glpi_tickets_users',
            'WHERE' => ['tickets_id' => $ticket->getID(), 'type' => CommonITILActor::OBSERVER],
        ])->current()['c'];
        $this->assertSame(0, $count, 'No observer should be added when the requester has no supervisor.');
    }
}
