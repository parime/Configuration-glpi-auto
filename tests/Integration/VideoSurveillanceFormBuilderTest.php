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
use Glpi\Asset\AssetDefinitionManager;
use Glpi\Form\AnswersHandler\AnswersHandler;
use Glpi\Form\EndUserInputNameProvider;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\Section;
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\PhysicalSecurityAssetBuilder;
use GlpiPlugin\Configurationglpiauto\VideoSurveillanceFormBuilder;
use Item_Ticket;
use PHPUnit\Framework\TestCase;
use Ticket;

/**
 * Same "real submission, real assertion" pattern as `MeetingRoomFormBuilderTest` — the optional
 * "Équipement concerné" `QuestionTypeItem` question only exists when BOTH the `securite` branch and
 * `physical_security_assets_enabled` are on (a two-gate condition this class's own docblock is
 * explicit about, unlike the single-gate vehicle/room conversions) : this suite exercises that gated
 * question for real, submitting an actual `SecuritePhysique` asset and checking the resulting
 * `Item_Ticket` link — not just that the destination `config` mentions `AssociatedItemsField`.
 */
final class VideoSurveillanceFormBuilderTest extends TestCase
{
    private const FORM_NAME = 'Signaler un dysfonctionnement vidéosurveillance ou alarme';

    private const BOOTSTRAPPED_CFG_KEYS = [
        'asset_types', 'assignable_types', 'location_types', 'state_types', 'ticket_types', 'unicity_types',
    ];

    /** @var int[] */
    private array $ticketIdsToDelete = [];

    /** @var int[] */
    private array $equipmentIdsToDelete = [];

    protected function tearDown(): void
    {
        global $CFG_GLPI;

        foreach ($this->ticketIdsToDelete as $id) {
            (new Ticket())->delete(['id' => $id], true);
        }

        // Unlike VehicleIncidentFormBuilderTest/MeetingRoomFormBuilderTest's "Vehicule"/"Local"
        // (real, permanent, shared fixtures nothing else deletes), "SecuritePhysique" has its OWN
        // dedicated create/delete-cycle test file (`PhysicalSecurityAssetBuilderTest`) that assumes
        // a clean slate at the start of its own negative-branch tests. Leaving this definition behind
        // permanently — like Vehicule/Local — broke that other test's own assumption the moment both
        // ran in the same PHPUnit process (confirmed live). So, uniquely for this asset, this suite
        // must delete it every time, mirroring FireSafetyAssetBuilderTest's own tearDown exactly
        // (including the $CFG_GLPI strip-after-delete + reboot — see that class's docblock for why).
        if (!empty($this->equipmentIdsToDelete)) {
            $equipmentClass = $this->equipmentClassName();
            foreach ($this->equipmentIdsToDelete as $id) {
                (new $equipmentClass())->delete(['id' => $id], true);
            }
        }

        $form = new Form();
        if ($form->getFromDBByCrit(['name' => self::FORM_NAME])) {
            $form->delete(['id' => $form->getID()], true);
        }

        $definition = new AssetDefinition();
        if ($definition->getFromDBByCrit(['system_name' => 'SecuritePhysique'])) {
            $staleClasses = [
                $definition->getAssetClassName(),
                $definition->getAssetTypeClassName(),
                $definition->getAssetModelClassName(),
            ];

            $definition->delete(['id' => $definition->getID()], true);

            foreach (self::BOOTSTRAPPED_CFG_KEYS as $key) {
                $CFG_GLPI[$key] = array_values(array_diff($CFG_GLPI[$key], $staleClasses));
            }
            $CFG_GLPI['dictionnary_types'] = array_values(array_diff(
                $CFG_GLPI['dictionnary_types'],
                [$staleClasses[1], $staleClasses[2]]
            ));
        }
        AssetDefinitionManager::getInstance()->clearDefinitionsCache();
        AssetDefinitionManager::getInstance()->bootDefinitions();
    }

    private function buildConfig(): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_branches' => json_encode(['securite']),
            'service_catalog_enabled' => 1,
            'physical_security_assets_enabled' => 1,
            'physical_security_asset_icons_enabled' => 0,
        ]);

        return $config;
    }

    private function buildForm(): Form
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);
        (new PhysicalSecurityAssetBuilder())->build($config);
        (new VideoSurveillanceFormBuilder())->build($config);

        $form = new Form();
        $this->assertTrue(
            $form->getFromDBByCrit(['name' => self::FORM_NAME]),
            'VideoSurveillanceFormBuilder should have created the form.'
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

    private function equipmentClassName(): string
    {
        $definition = new AssetDefinition();
        $definition->getFromDBByCrit(['system_name' => 'SecuritePhysique']);

        return $definition->getAssetClassName();
    }

    private function addRealEquipment(string $name): int
    {
        $equipmentClass = $this->equipmentClassName();

        $id = (int) (new $equipmentClass())->add([
            'name' => $name,
            'entities_id' => 0,
        ]);
        $this->equipmentIdsToDelete[] = $id;

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

    public function testReferencedEquipmentIsAssociatedOnTheTicket(): void
    {
        $form = $this->buildForm();
        $equipmentId = $this->addRealEquipment('PHPUnit — Caméra hall entrée');
        $localisationId = $this->questionIdByRank($form, 0);
        $typeId = $this->questionIdByRank($form, 1);
        $equipementQId = $this->questionIdByRank($form, 2);
        $equipmentClass = $this->equipmentClassName();

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$localisationId" => 'Hall entrée bâtiment A',
            "answers_$typeId" => ['1'],
            "answers_$equipementQId" => ['itemtype' => $equipmentClass, 'items_id' => $equipmentId],
        ]);

        $link = new Item_Ticket();
        $this->assertTrue(
            $link->getFromDBByCrit([
                'tickets_id' => $ticket->getID(),
                'itemtype' => $equipmentClass,
                'items_id' => $equipmentId,
            ]),
            'The ticket should be linked to the chosen equipment via a real Item_Ticket row.'
        );
        $this->assertSame(Ticket::INCIDENT_TYPE, (int) $ticket->fields['type']);
    }

    /**
     * Leaving the optional equipment question unanswered (a reporter who doesn't know the exact
     * referenced asset) must not silently associate a stale or wrong item.
     */
    public function testUnreferencedEquipmentAssociatesNoItem(): void
    {
        $form = $this->buildForm();
        $localisationId = $this->questionIdByRank($form, 0);
        $typeId = $this->questionIdByRank($form, 1);

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$localisationId" => 'Parking sous-sol',
            "answers_$typeId" => ['2'],
        ]);

        $link = new Item_Ticket();
        $this->assertFalse(
            $link->getFromDBByCrit([
                'tickets_id' => $ticket->getID(),
                'itemtype' => $this->equipmentClassName(),
            ]),
            'No equipment should be associated when the optional question was left unanswered.'
        );
    }
}
