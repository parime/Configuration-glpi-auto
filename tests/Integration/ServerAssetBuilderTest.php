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
use Glpi\Asset\Capacity\HasCertificatesCapacity;
use Glpi\Asset\Capacity\HasDatabaseInstanceCapacity;
use Glpi\Asset\Capacity\HasNetworkPortCapacity;
use Glpi\Asset\Capacity\HasRemoteManagementCapacity;
use Glpi\Asset\Capacity\HasVirtualMachineCapacity;
use Glpi\Asset\Capacity\IsRackableCapacity;
use Glpi\Asset\Capacity\IsReservableCapacity;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ServerAssetBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Same "real, shared, permanent fixture" reasoning as `VehicleAssetBuilderTest`/
 * `BuildingAssetBuilderTest` — a "Serveur" definition already exists on this instance (id 50).
 */
final class ServerAssetBuilderTest extends TestCase
{
    private const SYSTEM_NAME = 'Serveur';

    private const EXPECTED_FIELDS = ['position_baie', 'configuration_raid', 'hyperviseur'];

    private const EXPECTED_TYPES = ['Serveur rack', 'Serveur tour', 'Lame (blade)', 'Serveur virtuel', 'NAS', 'SAN'];

    private function buildConfig(array $branches): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_branches' => json_encode($branches),
        ]);

        return $config;
    }

    public function testReturnsZeroWhenItBranchIsNotSelected(): void
    {
        $count = (new ServerAssetBuilder())->build($this->buildConfig(['rh']));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresTheServeurDefinitionExistsDistinctFromComputer(): void
    {
        (new ServerAssetBuilder())->build($this->buildConfig(['it']));

        $definition = new AssetDefinition();
        $this->assertTrue($definition->getFromDBByCrit(['system_name' => self::SYSTEM_NAME]));
        $this->assertSame(1, (int) $definition->fields['is_active']);

        $capacities = array_column(json_decode($definition->fields['capacities'], true), 'name');
        foreach ([
            HasNetworkPortCapacity::class, HasVirtualMachineCapacity::class, IsRackableCapacity::class,
            HasRemoteManagementCapacity::class, HasCertificatesCapacity::class, HasDatabaseInstanceCapacity::class,
        ] as $capacity) {
            $this->assertContains($capacity, $capacities, "$capacity must be present — server-relevant, unlike Vehicule's own capacity set.");
        }
        $this->assertNotContains(IsReservableCapacity::class, $capacities, 'A server is not booked out like a pool car or meeting room.');
    }

    public function testBuildSeedsExactlyTheExpectedCustomFields(): void
    {
        (new ServerAssetBuilder())->build($this->buildConfig(['it']));

        $definition = new AssetDefinition();
        $definition->getFromDBByCrit(['system_name' => self::SYSTEM_NAME]);

        global $DB;
        $names = [];
        foreach ($DB->request([
            'FROM' => 'glpi_assets_customfielddefinitions',
            'WHERE' => ['assets_assetdefinitions_id' => $definition->getID()],
        ]) as $row) {
            $names[] = $row['system_name'];
        }
        sort($names);
        $expected = self::EXPECTED_FIELDS;
        sort($expected);
        $this->assertSame($expected, $names);
    }

    public function testBuildSeedsTheNativeTypeDropdownWithoutDuplicating(): void
    {
        $builder = new ServerAssetBuilder();
        $builder->build($this->buildConfig(['it']));
        $builder->build($this->buildConfig(['it']));

        $definition = new AssetDefinition();
        $definition->getFromDBByCrit(['system_name' => self::SYSTEM_NAME]);
        $itemtype = $definition->getAssetTypeClassName();

        global $DB;
        foreach (self::EXPECTED_TYPES as $name) {
            $count = $DB->request([
                'FROM' => (new $itemtype())->getTable(),
                'WHERE' => ['name' => $name, 'assets_assetdefinitions_id' => $definition->getID()],
            ])->count();
            $this->assertSame(1, $count, "Native asset type '$name' must exist exactly once.");
        }
    }

    public function testEveryExistingProfileCanSeeServeurInTheHelpdeskItemTypeList(): void
    {
        (new ServerAssetBuilder())->build($this->buildConfig(['it']));

        $definition = new AssetDefinition();
        $definition->getFromDBByCrit(['system_name' => self::SYSTEM_NAME]);
        $customObjectClass = $definition->getCustomObjectClassName();

        global $DB;
        foreach ($DB->request(['FROM' => 'glpi_profiles', 'FIELDS' => ['id', 'name', 'helpdesk_item_type']]) as $row) {
            $allowed = importArrayFromDB($row['helpdesk_item_type']);
            $this->assertContains($customObjectClass, $allowed, "Profile '{$row['name']}' must be able to associate a Serveur with a ticket.");
        }
    }
}
