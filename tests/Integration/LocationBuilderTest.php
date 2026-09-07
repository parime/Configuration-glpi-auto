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

use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\EntityBuilder;
use GlpiPlugin\Configurationglpiauto\LocationBuilder;
use Location;
use PHPUnit\Framework\TestCase;

/**
 * Like `EntityAddressBuilderTest`, real entities are created up front via `EntityBuilder` — a
 * `Location` is only ever created for a node whose entity actually exists in the DB.
 */
final class LocationBuilderTest extends TestCase
{
    private const TREE = [
        ['name' => 'Client Lieux', 'children' => [
            ['name' => 'Site Nord', 'children' => []],
            ['name' => 'Site Sud', 'children' => [
                ['name' => 'Atelier', 'children' => []],
            ]],
        ]],
    ];

    protected function setUp(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['entity_tree' => json_encode(self::TREE)]);
        (new EntityBuilder())->build($config);
    }

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'locations_enabled' => $enabled ? 1 : 0,
            'entity_tree' => json_encode(self::TREE),
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new LocationBuilder())->build($this->buildConfig(false), ['0-0' => ['address' => '1 rue Test']]);

        $this->assertSame(0, $count);
    }

    public function testReturnsZeroWhenNoDataOrManualChildrenProvided(): void
    {
        $count = (new LocationBuilder())->build($this->buildConfig(true));

        $this->assertSame(0, $count);
    }

    /**
     * "Client Lieux" (path "0") has no data of its own — no Location for it. "Site Nord" (path
     * "0-0") does — gets a Location nested at the root (`locations_id = 0`), since its parent has
     * none. "Site Sud" (path "0-1") has no data either, but its child "Atelier" (path "0-1-0")
     * does — Atelier's Location also attaches directly to the root, skipping the data-less parent.
     */
    public function testCreatesLocationOnlyForNodesWithDataAndSkipsDataLessAncestors(): void
    {
        $dataByPath = [
            '0-0' => ['address' => '1 rue du Nord', 'town' => 'Lille'],
            '0-1-0' => ['address' => '2 rue de l\'Atelier', 'town' => 'Toulouse'],
        ];

        $count = (new LocationBuilder())->build($this->buildConfig(true), $dataByPath);

        $this->assertSame(2, $count);

        $siteNord = new Location();
        $this->assertTrue($siteNord->getFromDBByCrit(['name' => 'Site Nord']));
        $this->assertSame(0, (int) $siteNord->fields['locations_id']);
        $this->assertSame('1 rue du Nord', $siteNord->fields['address']);
        $this->assertSame(1, (int) $siteNord->fields['is_recursive']);

        $atelier = new Location();
        $this->assertTrue($atelier->getFromDBByCrit(['name' => 'Atelier']));
        $this->assertSame(0, (int) $atelier->fields['locations_id'], 'Attaches directly to the root, skipping data-less "Site Sud".');
        $this->assertSame('Toulouse', $atelier->fields['town']);

        $clientLieux = new Location();
        $this->assertFalse($clientLieux->getFromDBByCrit(['name' => 'Client Lieux']), 'No data on this node — no Location created for it.');
    }

    /**
     * "Latest input wins": re-running the wizard after the admin corrects a location's data updates
     * the existing row instead of creating a second one.
     */
    public function testRerunningWithDifferentDataUpdatesTheExistingLocation(): void
    {
        $builder = new LocationBuilder();
        $builder->build($this->buildConfig(true), ['0-0' => ['town' => 'Lille']]);
        $builder->build($this->buildConfig(true), ['0-0' => ['town' => 'Lyon']]);

        global $DB;
        $count = $DB->request(['FROM' => Location::getTable(), 'WHERE' => ['name' => 'Site Nord']])->count();
        $this->assertSame(1, $count, 'No duplicate — the existing row is updated in place.');

        $siteNord = new Location();
        $siteNord->getFromDBByCrit(['name' => 'Site Nord']);
        $this->assertSame('Lyon', $siteNord->fields['town']);
    }

    /**
     * Purely manual sub-locations (`$childrenByPath`) nest under the entity-derived location at
     * that path, regardless of whether that entity itself has any address data — confirmed via a
     * grandchild too, to exercise the recursive nesting.
     */
    public function testBuildManualChildrenCreatesNestedSubLocationsUnderTheEntityDerivedLocation(): void
    {
        $dataByPath = ['0-0' => ['town' => 'Lille']];
        $childrenByPath = [
            '0-0' => [
                [
                    'name' => 'Bâtiment A',
                    'fields' => ['building' => 'A'],
                    'children' => [
                        ['name' => 'Salle 204', 'fields' => ['room' => '204'], 'children' => []],
                    ],
                ],
            ],
        ];

        $count = (new LocationBuilder())->build($this->buildConfig(true), $dataByPath, $childrenByPath);

        // Site Nord (1) + Bâtiment A (1) + Salle 204 (1) = 3.
        $this->assertSame(3, $count);

        $siteNord = new Location();
        $siteNord->getFromDBByCrit(['name' => 'Site Nord']);

        $batiment = new Location();
        $this->assertTrue($batiment->getFromDBByCrit(['name' => 'Bâtiment A']));
        $this->assertSame((int) $siteNord->getID(), (int) $batiment->fields['locations_id']);
        $this->assertSame((int) $siteNord->fields['entities_id'], (int) $batiment->fields['entities_id'], 'A manual sub-location belongs to the same entity as the site it was added under.');

        $salle = new Location();
        $this->assertTrue($salle->getFromDBByCrit(['name' => 'Salle 204']));
        $this->assertSame((int) $batiment->getID(), (int) $salle->fields['locations_id']);
    }

    public function testManualChildWithAnEmptyNameIsSkipped(): void
    {
        $dataByPath = ['0-0' => ['town' => 'Lille']];
        $childrenByPath = ['0-0' => [['name' => '   ', 'fields' => ['building' => 'X'], 'children' => []]]];

        $count = (new LocationBuilder())->build($this->buildConfig(true), $dataByPath, $childrenByPath);

        $this->assertSame(1, $count, 'Only "Site Nord" itself — the unnamed manual child is skipped.');
    }
}
