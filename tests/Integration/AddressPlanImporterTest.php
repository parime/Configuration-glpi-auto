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

use GlpiPlugin\Configurationglpiauto\AddressPlan\AddressPlanImporter;
use GlpiPlugin\Configurationglpiauto\AddressPlan\AddressPlanParser;
use IPNetwork;
use IPNetwork_Vlan;
use Location;
use PHPUnit\Framework\TestCase;
use Vlan;

/**
 * Issue #343 : création du plan d'adressage avec les objets natifs (VLAN, réseau IP, lien), sans
 * doublon au réimport. Adresses de documentation uniquement (RFC 5737).
 */
final class AddressPlanImporterTest extends TestCase
{
    private string $site;

    private int $locationId;

    protected function setUp(): void
    {
        $this->site = 'PHPUNIT Site ' . random_int(1000, 9999);
        $this->locationId = (int) (new Location())->add(['name' => $this->site, 'entities_id' => 0]);
    }

    protected function tearDown(): void
    {
        global $DB;

        foreach ($DB->request(['FROM' => IPNetwork::getTable(), 'WHERE' => ['comment' => ['LIKE', '%' . $this->site . '%']]]) as $row) {
            (new IPNetwork())->delete(['id' => $row['id']], true);
        }
        foreach ($DB->request(['FROM' => Vlan::getTable(), 'WHERE' => ['comment' => ['LIKE', '%' . $this->site . '%']]]) as $row) {
            (new Vlan())->delete(['id' => $row['id']], true);
        }
        (new Location())->delete(['id' => $this->locationId], true);
    }

    private function plan(string $vlanName = 'Postes'): array
    {
        $text = "site\tvlan\tnom\treseau\tpasserelle\trole\n"
            . "{$this->site}\t3810\t{$vlanName}\t198.51.100.0/26\t198.51.100.1\tworkstations\n"
            . "{$this->site}\t3820\tServeurs\t198.51.100.64/26\t\tservers\n"
            . "Site inconnu PHPUNIT\t3830\tVoix\t198.51.100.128/26\t\tvoice\n";

        return AddressPlanImporter::preview(AddressPlanParser::parse($text)['rows']);
    }

    public function testImportCreatesNativeObjectsOnce(): void
    {
        $rows = $this->plan();
        $this->assertSame(['create', 'create', null], array_column($rows, 'vlan_action'));
        $this->assertSame(['site_unknown'], $rows[2]['errors']);
        $this->assertSame(0, $rows[0]['entities_id']);

        $result = AddressPlanImporter::import($rows);
        $this->assertSame([2, 0, 2, 0, []], [
            $result['vlans_created'], $result['vlans_updated'],
            $result['networks_created'], $result['networks_updated'], $result['errors'],
        ]);

        $vlan = new Vlan();
        $this->assertTrue($vlan->getFromDBByCrit(['tag' => 3810, 'entities_id' => 0, 'name' => 'Postes']));
        $network = new IPNetwork();
        $this->assertTrue($network->getFromDBByCrit(['name' => 'Postes', 'comment' => ['LIKE', '%' . $this->site . '%']]));
        $this->assertSame('198.51.100.0', $network->fields['address']);
        $this->assertSame('198.51.100.1', $network->fields['gateway']);
        $this->assertTrue((new IPNetwork_Vlan())->getFromDBByCrit([
            'ipnetworks_id' => $network->getID(),
            'vlans_id'      => $vlan->getID(),
        ]));

        // Réimport : rien de nouveau ; un nom changé met à jour le VLAN et le réseau.
        $this->assertSame(['same', 'same', null], array_column($this->plan(), 'vlan_action'));
        $renamed = $this->plan('Postes bureautique');
        $this->assertSame('update', $renamed[0]['network_action']);
        $result = AddressPlanImporter::import($renamed);
        $this->assertSame([0, 1, 0, 1], [
            $result['vlans_created'], $result['vlans_updated'],
            $result['networks_created'], $result['networks_updated'],
        ]);
        $this->assertSame(1, countElementsInTable(Vlan::getTable(), ['tag' => 3810, 'entities_id' => 0, 'comment' => ['LIKE', '%' . $this->site . '%']]));
    }

    public function testSiteResolution(): void
    {
        $this->assertSame($this->locationId, AddressPlanImporter::resolveSite($this->site)['locations_id'] ?? null);
        $this->assertNull(AddressPlanImporter::resolveSite('Site inconnu PHPUNIT'));
        $this->assertContains($this->site, AddressPlanImporter::siteNames());
    }
}
