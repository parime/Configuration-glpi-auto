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
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\NetworkConsistencyCheck;
use PHPUnit\Framework\TestCase;
use Vlan;

/**
 * Issue #344 : constats réseau sur des données fictives. Les adresses sont posées dans
 * glpi_ipaddresses comme les écrirait l'inventaire (élément porteur renseigné).
 */
final class NetworkConsistencyCheckTest extends TestCase
{
    /** @var list<int> */
    private array $computers = [];

    private int $vlanId = 0;

    private string $tag;

    protected function setUp(): void
    {
        $this->tag = 'PHPUNIT-NET-' . random_int(1000, 9999);
    }

    protected function tearDown(): void
    {
        global $DB;

        $DB->delete('glpi_ipaddresses', ['mainitemtype' => Computer::class, 'mainitems_id' => $this->computers ?: [0]]);
        foreach ($this->computers as $id) {
            (new Computer())->delete(['id' => $id], true);
        }
        if ($this->vlanId > 0) {
            (new Vlan())->delete(['id' => $this->vlanId], true);
        }
    }

    private function computerWithAddress(string $name, string $address): void
    {
        global $DB;

        $id = (int) (new Computer())->add(['name' => $this->tag . ' ' . $name, 'entities_id' => 0]);
        $this->computers[] = $id;
        $DB->insert('glpi_ipaddresses', [
            'entities_id'  => 0,
            'items_id'     => 0,
            'itemtype'     => 'NetworkName',
            'version'      => 4,
            'name'         => $address,
            'is_deleted'   => 0,
            'is_dynamic'   => 1,
            'mainitems_id' => $id,
            'mainitemtype' => Computer::class,
        ]);
    }

    public function testDuplicateAddressAndOrphanVlan(): void
    {
        $this->computerWithAddress('A', '192.0.2.231');
        $this->computerWithAddress('B', '192.0.2.231');
        $this->vlanId = (int) (new Vlan())->add(['name' => $this->tag, 'tag' => 3999, 'entities_id' => 0]);

        $findings = (new NetworkConsistencyCheck())->analyze();
        $duplicates = array_values(array_filter(
            $findings,
            static fn (AuditFinding $f): bool => $f->severity === AuditFinding::SEVERITY_WARNING
                && str_contains($f->description, '192.0.2.231 :')
        ));
        $this->assertCount(1, $duplicates);
        $this->assertSame(AuditFinding::SEVERITY_WARNING, $duplicates[0]->severity);
        $this->assertStringContainsString($this->tag . ' A', $duplicates[0]->description);
        $this->assertStringContainsString($this->tag . ' B', $duplicates[0]->description);
        $this->assertFalse($duplicates[0]->fixable, 'Rien n\'est corrigé automatiquement.');
        $this->assertNotEmpty($duplicates[0]->links);

        $label = $this->tag . ' (VLAN 3999)';
        $orphans = array_values(array_filter(
            $findings,
            static fn (AuditFinding $f): bool => str_contains($f->description, $label)
        ));
        $this->assertCount(1, $orphans);
        $this->assertSame(AuditFinding::SEVERITY_INFO, $orphans[0]->severity);
    }
}
