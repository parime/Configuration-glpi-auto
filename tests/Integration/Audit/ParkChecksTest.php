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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration\Audit;

use Computer;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\GhostAssetsCheck;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\IncompleteAssetsCheck;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\WarrantyCheck;
use GlpiPlugin\Configurationglpiauto\Audit\ParkAudit;
use GlpiPlugin\Configurationglpiauto\Config;
use Infocom;
use Item_Ticket;
use Location;
use PHPUnit\Framework\TestCase;
use State;
use Ticket;

/**
 * Issue #306 : contrôles du parc du mode Audit. La correction ouvre des tickets liés au matériel,
 * sans jamais modifier le matériel, et ne crée pas de doublon.
 */
final class ParkChecksTest extends TestCase
{
    private string $suffix;

    protected function setUp(): void
    {
        $this->suffix = (string) mt_rand(100000, 999999);
        $config = Config::getConfig();
        $config->update(['id' => $config->getID(), 'audit_ghost_days' => 30, 'audit_warranty_days' => 30]);
    }

    private function state(string $name): int
    {
        $state = new State();

        return $state->getFromDBByCrit(['name' => $name]) ? (int) $state->getID() : (int) $state->add(['name' => $name, 'entities_id' => 0, 'is_recursive' => 1]);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function computer(string $name, array $fields = []): int
    {
        global $DB;
        $location = (int) (new Location())->add(['name' => 'PHPUnit lieu ' . $this->suffix . mt_rand(), 'entities_id' => 0]);
        $id = (int) (new Computer())->add(['name' => $name . '-' . $this->suffix, 'entities_id' => 0]);
        $DB->update(Computer::getTable(), $fields + ['states_id' => $this->state('En service'), 'locations_id' => $location], ['id' => $id]);

        return $id;
    }

    /**
     * @param list<array{itemtype: string, row: array<string, mixed>}> $found
     * @return list<int>
     */
    private static function ids(array $found): array
    {
        return array_map(static fn (array $f): int => (int) $f['row']['id'], array_filter($found, static fn (array $f): bool => $f['itemtype'] === Computer::class));
    }

    private static function linkedTickets(int $computerId, string $prefix): int
    {
        global $DB;

        return (int) $DB->request([
            'COUNT'      => 'c',
            'FROM'       => Ticket::getTable(),
            'INNER JOIN' => [Item_Ticket::getTable() => ['FKEY' => [Item_Ticket::getTable() => 'tickets_id', Ticket::getTable() => 'id']]],
            'WHERE'      => [
                Item_Ticket::getTable() . '.itemtype' => Computer::class,
                Item_Ticket::getTable() . '.items_id' => $computerId,
                Ticket::getTable() . '.name'          => ['LIKE', $prefix . '%'],
            ],
        ])->current()['c'];
    }

    public function testAMachineThatNoLongerReportsGetsOneTicketAndIsNotModified(): void
    {
        $ghost = $this->computer('PHPUNIT-FANTOME', ['is_dynamic' => 1, 'last_inventory_update' => date('Y-m-d H:i:s', strtotime('-60 days'))]);
        $recent = $this->computer('PHPUNIT-RECENT', ['is_dynamic' => 1, 'last_inventory_update' => date('Y-m-d H:i:s', strtotime('-2 days'))]);
        $inStock = $this->computer('PHPUNIT-STOCK', ['is_dynamic' => 1, 'last_inventory_update' => date('Y-m-d H:i:s', strtotime('-90 days')), 'states_id' => $this->state('En stock')]);

        $found = self::ids(GhostAssetsCheck::findGhosts());
        $this->assertContains($ghost, $found);
        $this->assertNotContains($recent, $found);
        $this->assertNotContains($inStock, $found, 'un matériel en stock n\'a pas à remonter');
        $this->assertNotNull((new GhostAssetsCheck())->analyze()[0]->fixLabel, 'bouton « Créer les tickets », pas « Corriger »');

        $before = new Computer();
        $before->getFromDB($ghost);
        (new GhostAssetsCheck())->fix();
        (new GhostAssetsCheck())->fix();

        $this->assertSame(1, self::linkedTickets($ghost, GhostAssetsCheck::TICKET_PREFIX), 'un seul ticket même après deux corrections');
        $after = new Computer();
        $after->getFromDB($ghost);
        foreach (['states_id', 'users_id', 'locations_id', 'is_deleted'] as $field) {
            $this->assertSame($before->fields[$field], $after->fields[$field], "$field inchangé");
        }
    }

    public function testTheGhostCheckCanBeDisabled(): void
    {
        $config = Config::getConfig();
        $config->update(['id' => $config->getID(), 'audit_ghost_days' => 0]);
        $this->computer('PHPUNIT-FANTOME-OFF', ['is_dynamic' => 1, 'last_inventory_update' => date('Y-m-d H:i:s', strtotime('-60 days'))]);

        $this->assertSame([], GhostAssetsCheck::findGhosts());
    }

    public function testWarrantiesEndingSoonOrExpiredAreGroupedInOneTicket(): void
    {
        $soon = $this->computer('PHPUNIT-GARANTIE-PROCHE');
        $expired = $this->computer('PHPUNIT-GARANTIE-FINIE');
        $lifetime = $this->computer('PHPUNIT-GARANTIE-A-VIE');
        $far = $this->computer('PHPUNIT-GARANTIE-LOIN');
        $infocoms = [
            $soon => [date('Y-m-d', strtotime('-12 months +10 days')), 12],
            $expired => [date('Y-m-d', strtotime('-3 years')), 12],
            $lifetime => [date('Y-m-d', strtotime('-5 years')), -1],
            $far => [date('Y-m-d'), 36],
        ];
        global $DB;
        foreach ($infocoms as $id => [$date, $duration]) {
            // GLPI peut créer d'office une fiche Gestion vide avec le matériel (auto_create_infocoms).
            $values = ['warranty_date' => $date, 'warranty_duration' => $duration];
            $where = ['itemtype' => Computer::class, 'items_id' => $id];
            if (countElementsInTable(Infocom::getTable(), $where) > 0) {
                $DB->update(Infocom::getTable(), $values, $where);
            } else {
                $DB->insert(Infocom::getTable(), $values + $where + ['entities_id' => 0]);
            }
        }

        $found = self::ids(WarrantyCheck::findItems());
        $this->assertContains($soon, $found);
        $this->assertContains($expired, $found);
        $this->assertNotContains($lifetime, $found, 'garantie à vie');
        $this->assertNotContains($far, $found);

        (new WarrantyCheck())->fix();
        (new WarrantyCheck())->fix();

        $this->assertSame(1, self::linkedTickets($soon, WarrantyCheck::TICKET_PREFIX));
        $this->assertSame(1, self::linkedTickets($expired, WarrantyCheck::TICKET_PREFIX));
    }

    public function testIncompleteAssetsAreListedWithWhatIsMissing(): void
    {
        $noLocation = $this->computer('PHPUNIT-SANS-LIEU', ['locations_id' => 0]);
        $assignedNobody = $this->computer('PHPUNIT-ATTRIBUE-PERSONNE', ['states_id' => $this->state('Attribué'), 'users_id' => 0]);
        $complete = $this->computer('PHPUNIT-COMPLET');

        $found = [];
        foreach (IncompleteAssetsCheck::findItems() as $item) {
            if ($item['itemtype'] === Computer::class) {
                $found[(int) $item['row']['id']] = $item['missing'];
            }
        }

        $this->assertArrayHasKey($noLocation, $found);
        $this->assertArrayHasKey($assignedNobody, $found);
        $this->assertArrayNotHasKey($complete, $found);
        $this->assertCount(1, $found[$assignedNobody]);

        (new IncompleteAssetsCheck())->fix();
        $this->assertSame(1, self::linkedTickets($noLocation, IncompleteAssetsCheck::TICKET_PREFIX));
    }

    public function testOutOfServiceStatesAreTheWizardOnes(): void
    {
        $stock = $this->state('En stock');

        $this->assertContains($stock, ParkAudit::outOfServiceStateIds());
    }
}
