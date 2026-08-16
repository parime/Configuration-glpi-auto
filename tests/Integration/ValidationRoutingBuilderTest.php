<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Parime
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

use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ValidationRoutingBuilder;
use PHPUnit\Framework\TestCase;
use Ticket;
use User;

/**
 * Runs against a real GLPI instance — exercises the actual RuleTicket engine end to end (not just
 * the rule's own rows), since that's the only way to confirm `responsible_id_validate` really
 * routes to the requester's `users_id_supervisor`.
 *
 * Confirmed live (docker-compose.test.yml) before writing this test that GLPI 11 stores the
 * resolved validation target on `glpi_ticketvalidations.itemtype_target`/`items_id_target`
 * ("User"/<supervisor id>) — the older `users_id_validate` column is left at 0, not populated by
 * this action. Discovered by inspecting the real row rather than assumed from an older GLPI
 * version's behavior.
 */
final class ValidationRoutingBuilderTest extends TestCase
{
    private const RULE_NAME = 'Validation automatique — supérieur hiérarchique du demandeur';

    /** @var int[] */
    private array $userIdsToDelete = [];

    /** @var int[] */
    private array $ticketIdsToDelete = [];

    protected function tearDown(): void
    {
        foreach ($this->ticketIdsToDelete as $id) {
            (new Ticket())->delete(['id' => $id], true);
        }
        foreach ($this->userIdsToDelete as $id) {
            (new User())->delete(['id' => $id], true);
        }

        $rule = new \RuleTicket();
        if ($rule->getFromDBByCrit(['name' => self::RULE_NAME])) {
            $rule->delete(['id' => $rule->getID()], true);
        }
    }

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['validation_supervisor_routing_enabled' => $enabled ? 1 : 0]);

        return $config;
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

    private function addDemandTicket(int $requesterId, string $name): int
    {
        $id = (int) (new Ticket())->add([
            'name' => $name,
            'content' => 'phpunit',
            'type' => Ticket::DEMAND_TYPE,
            '_users_id_requester' => $requesterId,
            'entities_id' => 0,
        ]);
        $this->ticketIdsToDelete[] = $id;

        return $id;
    }

    private function validationTargets(int $ticketId): array
    {
        global $DB;

        $rows = $DB->request(['FROM' => 'glpi_ticketvalidations', 'WHERE' => ['tickets_id' => $ticketId]]);

        return iterator_to_array($rows);
    }

    public function testDisabledCreatesNoRuleAndReturnsZero(): void
    {
        $result = (new ValidationRoutingBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $result);
        $this->assertFalse((new \RuleTicket())->getFromDBByCrit(['name' => self::RULE_NAME]));
    }

    public function testEnabledIsIdempotentOnSecondBuild(): void
    {
        $builder = new ValidationRoutingBuilder();
        $config = $this->buildConfig(true);

        $first = $builder->build($config);
        $second = $builder->build($config);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
    }

    public function testRequesterWithSupervisorGetsValidationRoutedToThatSupervisor(): void
    {
        (new ValidationRoutingBuilder())->build($this->buildConfig(true));

        $supervisorId = $this->addUser('phpunit_supervisor');
        $requesterId = $this->addUser('phpunit_requester', $supervisorId);
        $ticketId = $this->addDemandTicket($requesterId, 'PHPUnit — demande avec superviseur');

        $rows = $this->validationTargets($ticketId);

        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame('User', $row['itemtype_target']);
        $this->assertSame($supervisorId, (int) $row['items_id_target']);
    }

    public function testRequesterWithoutSupervisorGetsNoValidation(): void
    {
        (new ValidationRoutingBuilder())->build($this->buildConfig(true));

        $requesterId = $this->addUser('phpunit_requester_no_supervisor');
        $ticketId = $this->addDemandTicket($requesterId, 'PHPUnit — demande sans superviseur');

        $this->assertCount(0, $this->validationTargets($ticketId));
    }
}
