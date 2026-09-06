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
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\VehicleAssetBuilder;
use GlpiPlugin\Configurationglpiauto\VehicleMaintenanceFormBuilder;
use Item_Ticket;
use PHPUnit\Framework\TestCase;
use Ticket;

/**
 * Same "real submission, real assertion" pattern as `VehicleIncidentFormBuilderTest`/
 * `FuelCardFormBuilderTest` : the chosen vehicle must show up as a genuine `Item_Ticket` link on the
 * resulting ticket, and the ticket must genuinely be a Request (`Ticket::DEMAND_TYPE`).
 */
final class VehicleMaintenanceFormBuilderTest extends TestCase
{
    private const FORM_NAME = "Demande d'entretien véhicule";

    /** @var int[] */
    private array $ticketIdsToDelete = [];

    /** @var int[] */
    private array $vehicleIdsToDelete = [];

    protected function tearDown(): void
    {
        foreach ($this->ticketIdsToDelete as $id) {
            (new Ticket())->delete(['id' => $id], true);
        }

        if (!empty($this->vehicleIdsToDelete)) {
            $vehicleClass = $this->vehicleClassName();
            foreach ($this->vehicleIdsToDelete as $id) {
                (new $vehicleClass())->delete(['id' => $id], true);
            }
        }

        $form = new Form();
        if ($form->getFromDBByCrit(['name' => self::FORM_NAME])) {
            $form->delete(['id' => $form->getID()], true);
        }

        // See VehicleIncidentFormBuilderTest's tearDown docblock: "Vehicule" is a real, shared,
        // pre-existing asset type — never delete it, never clearDefinitionsCache().
    }

    private function buildConfig(): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_branches' => json_encode(['flotte']),
            'service_catalog_enabled' => 1,
        ]);

        return $config;
    }

    private function buildForm(): Form
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);
        (new VehicleAssetBuilder())->build($config);
        (new VehicleMaintenanceFormBuilder())->build($config);

        $form = new Form();
        $this->assertTrue(
            $form->getFromDBByCrit(['name' => self::FORM_NAME]),
            'VehicleMaintenanceFormBuilder should have created the form.'
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

    private function vehicleClassName(): string
    {
        $definition = new AssetDefinition();
        $definition->getFromDBByCrit(['system_name' => 'Vehicule']);

        return $definition->getAssetClassName();
    }

    private function addRealVehicle(string $name): int
    {
        $vehicleClass = $this->vehicleClassName();

        $id = (int) (new $vehicleClass())->add([
            'name' => $name,
            'entities_id' => 0,
        ]);
        $this->vehicleIdsToDelete[] = $id;

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

    public function testVehicleIsAssociatedAndTicketIsADemand(): void
    {
        $form = $this->buildForm();
        $vehicleId = $this->addRealVehicle('PHPUnit — Citroën Jumpy');
        $vehiculeId = $this->questionIdByRank($form, 0);
        $typeId = $this->questionIdByRank($form, 1);
        $vehicleClass = $this->vehicleClassName();

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$vehiculeId" => ['itemtype' => $vehicleClass, 'items_id' => $vehicleId],
            "answers_$typeId" => ['1'],
        ]);

        $link = new Item_Ticket();
        $this->assertTrue(
            $link->getFromDBByCrit([
                'tickets_id' => $ticket->getID(),
                'itemtype' => $vehicleClass,
                'items_id' => $vehicleId,
            ]),
            'The ticket should be linked to the chosen vehicle via a real Item_Ticket row.'
        );
        $this->assertSame(Ticket::DEMAND_TYPE, (int) $ticket->fields['type']);
    }

    public function testTicketTitleIncludesVehicleName(): void
    {
        $form = $this->buildForm();
        $vehicleId = $this->addRealVehicle('PHPUnit — Citroën Jumpy 2');
        $vehiculeId = $this->questionIdByRank($form, 0);
        $typeId = $this->questionIdByRank($form, 1);
        $vehicleClass = $this->vehicleClassName();

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$vehiculeId" => ['itemtype' => $vehicleClass, 'items_id' => $vehicleId],
            "answers_$typeId" => ['2'],
        ]);

        $this->assertStringContainsString('PHPUnit — Citroën Jumpy 2', $ticket->fields['name']);
    }
}
