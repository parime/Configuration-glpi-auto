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
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\VehicleAssetBuilder;
use GlpiPlugin\Configurationglpiauto\VehicleIncidentFormBuilder;
use PHPUnit\Framework\TestCase;
use Ticket;

/**
 * Regression guard for a real bug found manually against a live instance (not by an earlier
 * version of this suite, since none existed for any `*FormBuilder` class before this test) : a
 * `QuestionTypeRadio` question wired to `UrgencyFieldStrategy::SPECIFIC_ANSWER` looked correct at
 * every level short of actually submitting the form — `php -l`, PHPStan, the destination `config`
 * JSON inspected by hand all looked right, GLPI raised no error, the ticket was created
 * successfully. Only the created ticket's own `urgency` column revealed the truth : always the
 * default (3), never the submitted answer, because `QuestionTypeRadio`'s raw answer is an ARRAY
 * (`["4"]`) even for a single choice, and `is_numeric(["4"])` is false.
 *
 * This is exactly the class of bug a "does it save without error" test cannot catch — the whole
 * point of this suite is to go through the SAME pipeline a real end user's browser does
 * (`Glpi\Form\Controller\Form\SubmitAnswerController`, confirmed by reading it directly) rather
 * than a shortcut that only proves the builder wires *a* config, not that GLPI actually *acts* on
 * it the way the config claims : `EndUserInputNameProvider::getAnswers()` on a real `answers_<id>`
 * shaped input array (exactly what a browser POSTs), then `AnswersHandler::saveAnswers()` (the same
 * call the real controller makes), then assert the real, persisted `Ticket::urgency` column — never
 * assert against the destination's own `config` JSON, which is precisely what looked fine while the
 * feature was actually broken.
 */
final class VehicleIncidentFormBuilderTest extends TestCase
{
    private const FORM_NAME = 'Déclarer un sinistre ou un dommage véhicule';

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

        // Unlike the sibling *AssetBuilderTest classes, this suite never deletes the "Vehicule"
        // AssetDefinition itself (it's a real, shared, pre-existing asset type other builders and
        // the live instance rely on) — so, unlike those tests, it must NOT call
        // AssetDefinitionManager::clearDefinitionsCache() here: that cache is only ever populated
        // once at kernel boot (AbstractDefinitionManager::bootDefinitions(), never re-run on
        // demand), so clearing it after the first test method would leave every subsequent test in
        // this same PHPUnit process unable to resolve `Vehicule` at all — confirmed live: the next
        // test's `new $vehicleClass()` failed with "Asset definition is expected to be defined in
        // concrete class" (Glpi\Asset\Asset::getDefinition()) until this line was removed.
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
        // The vehicle picker question only becomes a real QuestionTypeItem if this definition
        // already exists — same "flotte" gate as VehicleIncidentFormBuilder itself, see its own
        // resolveVehicleItemtype() docblock.
        (new VehicleAssetBuilder())->build($config);
        (new VehicleIncidentFormBuilder())->build($config);

        $form = new Form();
        $this->assertTrue(
            $form->getFromDBByCrit(['name' => self::FORM_NAME]),
            'VehicleIncidentFormBuilder should have created the form.'
        );

        return $form;
    }

    private function questionIdByName(Form $form, string $name): int
    {
        $section = new Section();
        $section->getFromDBByCrit(['forms_forms_id' => $form->getID()]);

        $question = new Question();
        $this->assertTrue(
            $question->getFromDBByCrit(['forms_sections_id' => $section->getID(), 'name' => $name]),
            "Question \"$name\" should exist on the form."
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

    /**
     * Submits the form exactly like a real browser would (`answers_<id>` shaped POST fields,
     * converted the same way `SubmitAnswerController` converts them), not a shortcut that hands
     * `AnswersHandler` a pre-shaped array a real submission would never actually produce.
     */
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
     * The actual regression : submitting "Très haute" (5) must produce a ticket with
     * `urgency = 5`, not the default (3) — this is the exact scenario that silently failed before
     * the fix (`UrgencyFieldStrategy::LAST_VALID_ANSWER` on a real `QuestionTypeUrgency` question,
     * not `SPECIFIC_ANSWER` on a `QuestionTypeRadio`).
     */
    public function testHighestUrgencyAnswerProducesHighestTicketUrgency(): void
    {
        $form = $this->buildForm();
        $vehicleId = $this->addRealVehicle('PHPUnit — Renault Kangoo');
        $immobiliseId = $this->questionIdByName($form, 'Dans quelle mesure le véhicule est-il immobilisé ou inutilisable ?');
        $vehiculeId = $this->questionIdByName($form, 'Véhicule concerné');
        $dateId = $this->questionIdByName($form, 'Date du sinistre');
        $tiersId = $this->questionIdByName($form, 'Un tiers est-il impliqué ?');

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$vehiculeId" => ['itemtype' => $this->vehicleClassName(), 'items_id' => $vehicleId],
            "answers_$dateId" => '2026-09-05',
            "answers_$immobiliseId" => 5,
            "answers_$tiersId" => ['2'],
        ]);

        $this->assertSame(5, (int) $ticket->fields['urgency']);
    }

    /**
     * Same submission, lowest urgency this time — proves the mapping actually tracks the answer
     * (a builder that always fell back to some other fixed value, just not 3, would pass the test
     * above alone but fail this one).
     */
    public function testLowestUrgencyAnswerProducesLowestTicketUrgency(): void
    {
        $form = $this->buildForm();
        $vehicleId = $this->addRealVehicle('PHPUnit — Renault Kangoo 2');
        $immobiliseId = $this->questionIdByName($form, 'Dans quelle mesure le véhicule est-il immobilisé ou inutilisable ?');
        $vehiculeId = $this->questionIdByName($form, 'Véhicule concerné');
        $dateId = $this->questionIdByName($form, 'Date du sinistre');
        $tiersId = $this->questionIdByName($form, 'Un tiers est-il impliqué ?');
        $vehicleClass = $this->vehicleClassName();

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$vehiculeId" => ['itemtype' => $vehicleClass, 'items_id' => $vehicleId],
            "answers_$dateId" => '2026-09-05',
            "answers_$immobiliseId" => 1,
            "answers_$tiersId" => ['2'],
        ]);

        $this->assertSame(1, (int) $ticket->fields['urgency']);
    }

    /**
     * The computed title only ever references `vehicule`/`dateSinistre` (see class docblock) — a
     * real submission is the only way to confirm the tag-built title actually resolves to the
     * chosen vehicle's name, not the tag markup itself or an empty string.
     */
    public function testTicketTitleIncludesVehicleName(): void
    {
        $form = $this->buildForm();
        $vehicleId = $this->addRealVehicle('PHPUnit — Renault Kangoo 3');
        $immobiliseId = $this->questionIdByName($form, 'Dans quelle mesure le véhicule est-il immobilisé ou inutilisable ?');
        $vehiculeId = $this->questionIdByName($form, 'Véhicule concerné');
        $dateId = $this->questionIdByName($form, 'Date du sinistre');
        $tiersId = $this->questionIdByName($form, 'Un tiers est-il impliqué ?');
        $vehicleClass = $this->vehicleClassName();

        $ticket = $this->submitAndGetTicket($form, [
            "answers_$vehiculeId" => ['itemtype' => $vehicleClass, 'items_id' => $vehicleId],
            "answers_$dateId" => '2026-09-05',
            "answers_$immobiliseId" => 3,
            "answers_$tiersId" => ['2'],
        ]);

        $this->assertStringContainsString('PHPUnit — Renault Kangoo 3', $ticket->fields['name']);
    }
}
