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
use Glpi\Asset\Capacity\AllowedInGlobalSearchCapacity;
use Glpi\Asset\Capacity\HasContractsCapacity;
use Glpi\Asset\Capacity\HasDocumentsCapacity;
use Glpi\Asset\Capacity\HasHistoryCapacity;
use Glpi\Asset\Capacity\HasInfocomCapacity;
use Glpi\Asset\Capacity\HasLinksCapacity;
use Glpi\Asset\Capacity\HasNotepadCapacity;
use Glpi\Asset\Capacity\IsReservableCapacity;
use GlpiPlugin\Configurationglpiauto\BuildingAssetBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use PHPUnit\Framework\TestCase;

/**
 * `BuildingAssetBuilder` creates GLPI 11's third custom asset type this plugin manages ("Local") —
 * see `VehicleAssetBuilderTest`'s own docblock for why the shared instance means every assertion
 * here checks the definition's real, current shape rather than "how many rows got created just
 * now" (a "Local" definition already exists on this instance, id 51, from earlier runs).
 */
final class BuildingAssetBuilderTest extends TestCase
{
    private const SYSTEM_NAME = 'Local';

    private const EXPECTED_CAPACITIES = [
        HasInfocomCapacity::class,
        HasContractsCapacity::class,
        HasDocumentsCapacity::class,
        HasHistoryCapacity::class,
        HasNotepadCapacity::class,
        HasLinksCapacity::class,
        AllowedInGlobalSearchCapacity::class,
        IsReservableCapacity::class,
    ];

    private const EXPECTED_FIELDS = ['surface_m2', 'capacite_personnes', 'type_local'];

    private const EXPECTED_TYPES = [
        'Bureau',
        'Salle de réunion',
        'Entrepôt',
        'Atelier',
        'Salle serveur / Datacenter',
        'Site industriel',
        'Boutique / Point de vente',
    ];

    private function buildConfig(array $branches): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_branches' => json_encode($branches),
        ]);

        return $config;
    }

    public function testReturnsZeroWhenBatimentBranchIsNotSelected(): void
    {
        $count = (new BuildingAssetBuilder())->build($this->buildConfig(['rh']));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresTheLocalDefinitionExistsWithExpectedCapacitiesAndProfiles(): void
    {
        (new BuildingAssetBuilder())->build($this->buildConfig(['batiment']));

        $definition = new AssetDefinition();
        $this->assertTrue($definition->getFromDBByCrit(['system_name' => self::SYSTEM_NAME]));
        $this->assertSame(1, (int) $definition->fields['is_active']);

        $capacities = array_column(json_decode($definition->fields['capacities'], true), 'name');
        sort($capacities);
        $expected = self::EXPECTED_CAPACITIES;
        sort($expected);
        $this->assertSame($expected, $capacities, 'A room has no hardware-inventory capacities (no network ports, OS, devices).');

        $profiles = json_decode($definition->fields['profiles'], true);
        global $DB;
        $adminIds = [];
        foreach ($DB->request(['FROM' => 'glpi_profiles', 'WHERE' => ['name' => ['Super-Admin', 'Admin']]]) as $row) {
            $adminIds[] = (int) $row['id'];
        }
        sort($adminIds);
        $profileIds = array_map('intval', array_keys($profiles));
        sort($profileIds);
        $this->assertSame($adminIds, $profileIds, 'Only Super-Admin/Admin get CRUD rights on the asset registry by default.');
        foreach ($profiles as $right) {
            $this->assertSame(ALLSTANDARDRIGHT, (int) $right);
        }
    }

    public function testBuildSeedsExactlyTheExpectedCustomFields(): void
    {
        (new BuildingAssetBuilder())->build($this->buildConfig(['batiment']));

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
        $this->assertSame($expected, $names, 'Exactly the 3 fields this class declares — no more, no fewer, no duplicates.');
    }

    public function testBuildSeedsTheNativeTypeDropdownWithoutDuplicating(): void
    {
        $builder = new BuildingAssetBuilder();
        $builder->build($this->buildConfig(['batiment']));
        // Called twice deliberately — the real regression is that a second run must not duplicate
        // the native type dropdown (seeded unconditionally, independent of the definition's own
        // isNew state — see the class's own build() comments).
        $builder->build($this->buildConfig(['batiment']));

        $definition = new AssetDefinition();
        $definition->getFromDBByCrit(['system_name' => self::SYSTEM_NAME]);
        $itemtype = $definition->getAssetTypeClassName();

        global $DB;
        foreach (self::EXPECTED_TYPES as $name) {
            $count = $DB->request([
                'FROM' => (new $itemtype())->getTable(),
                'WHERE' => ['name' => $name, 'assets_assetdefinitions_id' => $definition->getID()],
            ])->count();
            $this->assertSame(1, $count, "Native asset type '$name' must exist exactly once, not be duplicated by the second run.");
        }
    }

    /**
     * Same regression class as `VehicleAssetBuilderTest`'s own equivalent test — see that test's
     * docblock for the full root cause (`MeetingRoomFormBuilder`'s real submission path is what
     * first surfaced it for this second custom asset type).
     */
    public function testEveryExistingProfileCanSeeLocalInTheHelpdeskItemTypeList(): void
    {
        (new BuildingAssetBuilder())->build($this->buildConfig(['batiment']));

        $definition = new AssetDefinition();
        $definition->getFromDBByCrit(['system_name' => self::SYSTEM_NAME]);
        $customObjectClass = $definition->getCustomObjectClassName();

        global $DB;
        foreach ($DB->request(['FROM' => 'glpi_profiles', 'FIELDS' => ['id', 'name', 'helpdesk_item_type']]) as $row) {
            $allowed = importArrayFromDB($row['helpdesk_item_type']);
            $this->assertContains(
                $customObjectClass,
                $allowed,
                "Profile '{$row['name']}' must be able to associate a Local with a ticket."
            );
        }
    }
}
