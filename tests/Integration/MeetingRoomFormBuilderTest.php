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

use Glpi\Asset\AssetDefinition;
use Glpi\Form\AnswersHandler\AnswersHandler;
use Glpi\Form\EndUserInputNameProvider;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\Section;
use GlpiPlugin\Configurationglpiauto\BuildingAssetBuilder;
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\MeetingRoomFormBuilder;
use Item_Ticket;
use PHPUnit\Framework\TestCase;
use Ticket;

/**
 * Regression guard for a real bug from the same PR that introduced this builder's advanced question
 * types (#224) : the "Salle souhaitée" `QuestionTypeItem` was first wired with
 * `AssetDefinition::getAssetTypeClassName()` instead of `getAssetClassName()` — the former is the
 * definition's native "Type" *dropdown* class (`getAssetClassName() . 'Type'`), not the asset itself.
 * GLPI rejected the resulting `QuestionTypeItemExtraDataConfig` with an `InvalidArgumentException`
 * ("Invalid extra data for question") the moment the wizard tried to build this specific form,
 * interrupting the whole assistant mid-run for every builder queued after it — found only by actually
 * running the wizard against the live instance, not by reading the code (both class-name getters
 * return a syntactically valid `class-string`, so nothing short of instantiating the config catches
 * the mix-up).
 *
 * This suite goes one step further than "does it throw" : it submits the form exactly like a real
 * browser would (`EndUserInputNameProvider` → `AnswersHandler::saveAnswers()`, the same pipeline
 * `SubmitAnswerController` uses) and asserts the REAL resulting `Item_Ticket` link — the only way to
 * confirm `AssociatedItemsField` actually attaches the chosen room, not just that the form saved
 * without error.
 */
final class MeetingRoomFormBuilderTest extends TestCase
{
    private const FORM_NAME = "Réservation ou problème d'équipement de salle de réunion";

    /** @var int[] */
    private array $ticketIdsToDelete = [];

    /** @var int[] */
    private array $roomIdsToDelete = [];

    protected function tearDown(): void
    {
        foreach ($this->ticketIdsToDelete as $id) {
            (new Ticket())->delete(['id' => $id], true);
        }

        if (!empty($this->roomIdsToDelete)) {
            $roomClass = $this->roomClassName();
            foreach ($this->roomIdsToDelete as $id) {
                (new $roomClass())->delete(['id' => $id], true);
            }
        }

        $form = new Form();
        if ($form->getFromDBByCrit(['name' => self::FORM_NAME])) {
            $form->delete(['id' => $form->getID()], true);
        }

        // See VehicleIncidentFormBuilderTest's tearDown docblock: the "Local" AssetDefinition is a
        // real, shared, pre-existing asset type — never delete it, never clearDefinitionsCache().
    }

    private function buildConfig(): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_branches' => json_encode(['batiment']),
            'service_catalog_enabled' => 1,
        ]);

        return $config;
    }

    private function buildForm(): Form
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);
        (new BuildingAssetBuilder())->build($config);
        (new MeetingRoomFormBuilder())->build($config);

        $form = new Form();
        $this->assertTrue(
            $form->getFromDBByCrit(['name' => self::FORM_NAME]),
            'MeetingRoomFormBuilder should have created the form.'
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

    private function roomClassName(): string
    {
        $definition = new AssetDefinition();
        $definition->getFromDBByCrit(['system_name' => 'Local']);

        return $definition->getAssetClassName();
    }

    private function addRealRoom(string $name): int
    {
        $roomClass = $this->roomClassName();

        $id = (int) (new $roomClass())->add([
            'name' => $name,
            'entities_id' => 0,
        ]);
        $this->roomIdsToDelete[] = $id;

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

    /**
     * The actual regression : the "Réservation de salle" branch must produce a ticket with a REAL
     * `Item_Ticket` link to the chosen room asset — not just a form that saves without throwing.
     */
    public function testReservationBranchAssociatesRoomOnTicket(): void
    {
        $form = $this->buildForm();
        $roomId = $this->addRealRoom('PHPUnit — Salle Ariane');
        $typeId = $this->questionIdByRank($form, 0);
        $salleId = $this->questionIdByRank($form, 1);
        $dateId = $this->questionIdByRank($form, 2);

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$typeId" => ['1'],
            "answers_$salleId" => ['itemtype' => $this->roomClassName(), 'items_id' => $roomId],
            "answers_$dateId" => '2026-09-10',
        ]);

        $link = new Item_Ticket();
        $this->assertTrue(
            $link->getFromDBByCrit([
                'tickets_id' => $ticket->getID(),
                'itemtype' => $this->roomClassName(),
                'items_id' => $roomId,
            ]),
            'The ticket should be linked to the chosen room via a real Item_Ticket row.'
        );
    }

    /**
     * The "Problème d'équipement" branch never shows/answers the room question — this must NOT
     * silently associate a stale or wrong item; `AssociatedItemsFieldStrategy::LAST_VALID_ANSWER`
     * must genuinely return null (no item), not just happen to not error.
     */
    public function testProblemBranchAssociatesNoItem(): void
    {
        $form = $this->buildForm();
        $typeId = $this->questionIdByRank($form, 0);
        $equipementId = $this->questionIdByRank($form, 3);
        $descriptionId = $this->questionIdByRank($form, 4);

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$typeId" => ['2'],
            "answers_$equipementId" => 'Vidéoprojecteur HS',
            "answers_$descriptionId" => "L'image reste noire même après changement de câble HDMI.",
        ]);

        // Every ticket created from a Form gets one automatic, unrelated `Item_Ticket` row linking
        // it back to the `Glpi\Form\Form` itself (a native GLPI traceability link, confirmed live —
        // nothing to do with `AssociatedItemsField`) — so the real assertion is "no *room* was
        // associated", not "no Item_Ticket row exists at all".
        $link = new Item_Ticket();
        $this->assertFalse(
            $link->getFromDBByCrit([
                'tickets_id' => $ticket->getID(),
                'itemtype' => $this->roomClassName(),
            ]),
            'No room should be associated on the equipment-problem branch.'
        );
    }
}
