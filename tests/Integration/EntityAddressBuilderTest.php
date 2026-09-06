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

use Entity;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\EntityAddressBuilder;
use GlpiPlugin\Configurationglpiauto\EntityBuilder;
use PHPUnit\Framework\TestCase;

/**
 * `EntityAddressBuilder` resolves each entity independently by walking `Config::getEntityTree()`
 * itself (same path encoding as `LocationBuilder::buildNode()`) — it never relies on
 * `EntityBuilder` having run first, but the entities still need to actually exist in the DB for
 * anything to be found, so this suite creates them via `EntityBuilder` up front, exactly like an
 * admin would have via the wizard's earlier "Entités" step.
 */
final class EntityAddressBuilderTest extends TestCase
{
    private const TREE = [
        ['name' => 'Client Adresse', 'children' => [
            ['name' => 'Site Nord', 'children' => []],
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
            'entity_native_address_enabled' => $enabled ? 1 : 0,
            'entity_tree' => json_encode(self::TREE),
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new EntityAddressBuilder())->build($this->buildConfig(false), ['0' => ['address' => '1 rue Test']]);

        $this->assertSame(0, $count);
    }

    public function testReturnsZeroWhenNoDataProvidedForAnyPath(): void
    {
        $count = (new EntityAddressBuilder())->build($this->buildConfig(true));

        $this->assertSame(0, $count);
    }

    public function testAppliesOnlyLocationFieldsFromDataByPath(): void
    {
        $dataByPath = [
            '0' => [
                'address' => '1 rue de la Mairie',
                'postcode' => '75000',
                'town' => 'Paris',
                'country' => 'France',
                // Location-only fields (no Entity equivalent) must be ignored, not error out.
                'building' => 'Bâtiment A',
                'room' => '101',
            ],
        ];

        $count = (new EntityAddressBuilder())->build($this->buildConfig(true), $dataByPath);

        $this->assertSame(1, $count);

        $entity = new Entity();
        $this->assertTrue($entity->getFromDBByCrit(['name' => 'Client Adresse', 'entities_id' => 0]));
        $this->assertSame('1 rue de la Mairie', $entity->fields['address']);
        $this->assertSame('75000', $entity->fields['postcode']);
        $this->assertSame('Paris', $entity->fields['town']);
        $this->assertSame('France', $entity->fields['country']);
    }

    public function testAppliesCommsByPathFieldsAndCountsEachUpdatedEntityOnce(): void
    {
        $dataByPath = ['0' => ['address' => '1 rue de la Mairie']];
        $commsByPath = ['0' => ['phonenumber' => '0100000000', 'website' => 'https://example.test']];

        $count = (new EntityAddressBuilder())->build($this->buildConfig(true), $dataByPath, $commsByPath);

        $this->assertSame(1, $count);

        $entity = new Entity();
        $entity->getFromDBByCrit(['name' => 'Client Adresse', 'entities_id' => 0]);
        $this->assertSame('0100000000', $entity->fields['phonenumber']);
        $this->assertSame('https://example.test', $entity->fields['website']);
    }

    public function testAppliesDataToNestedChildEntitiesByPath(): void
    {
        $dataByPath = ['0-0' => ['town' => 'Lille']];

        $count = (new EntityAddressBuilder())->build($this->buildConfig(true), $dataByPath);

        $this->assertSame(1, $count);

        $parent = new Entity();
        $parent->getFromDBByCrit(['name' => 'Client Adresse', 'entities_id' => 0]);

        $child = new Entity();
        $this->assertTrue($child->getFromDBByCrit(['name' => 'Site Nord', 'entities_id' => $parent->getID()]));
        $this->assertSame('Lille', $child->fields['town']);
    }
}
