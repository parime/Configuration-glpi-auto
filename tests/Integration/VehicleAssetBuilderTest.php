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
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\FuelType;
use GlpiPlugin\Configurationglpiauto\VehicleAssetBuilder;
use PHPUnit\Framework\TestCase;

/**
 * `VehicleAssetBuilder` is the one class in this plugin that creates a real GLPI 11 native custom
 * asset type (`Glpi\Asset\AssetDefinition`), not a form/category/dropdown — it had no test of its
 * own, only incidental coverage from `VehicleIncidentFormBuilderTest` relying on its side effects.
 *
 * Confirmed on the shared instance (a real "Vehicule" definition already exists from earlier runs,
 * id 49) that this test suite must NOT assume a fresh "isNew" creation path — same "real, shared,
 * permanent fixture" reasoning as `CategoryBuilderTest`/`ServiceCatalogBuilderTest`'s own
 * idempotency tests. Every assertion here checks the definition's real, current shape rather than
 * "how many rows got created just now."
 */
final class VehicleAssetBuilderTest extends TestCase
{
    private const SYSTEM_NAME = 'Vehicule';

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

    private const EXPECTED_FIELDS = [
        'immatriculation',
        'type_carburant',
        'date_mise_circulation',
        'date_controle_technique',
        'date_expiration_assurance',
    ];

    private const EXPECTED_FUEL_TYPES = [
        'Essence',
        'Diesel',
        'Électrique',
        'Hybride rechargeable',
        'Hybride',
        'GPL',
        'Hydrogène',
    ];

    private const EXPECTED_TYPES = [
        'Voiture',
        'Utilitaire léger',
        'Poids lourd',
        'Moto / Scooter',
        'Vélo / Vélo électrique',
        'Engin de chantier',
    ];

    private function buildConfig(array $branches): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_branches' => json_encode($branches),
        ]);

        return $config;
    }

    public function testReturnsZeroWhenFlotteBranchIsNotSelected(): void
    {
        $count = (new VehicleAssetBuilder())->build($this->buildConfig(['rh']));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresTheVehiculeDefinitionExistsWithExpectedCapacitiesAndProfiles(): void
    {
        (new VehicleAssetBuilder())->build($this->buildConfig(['flotte']));

        $definition = new AssetDefinition();
        $this->assertTrue($definition->getFromDBByCrit(['system_name' => self::SYSTEM_NAME]));
        $this->assertSame(1, (int) $definition->fields['is_active']);

        $capacities = array_column(json_decode($definition->fields['capacities'], true), 'name');
        sort($capacities);
        $expected = self::EXPECTED_CAPACITIES;
        sort($expected);
        $this->assertSame($expected, $capacities, 'A vehicle has none of the hardware-inventory capacities other assets get.');

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
        (new VehicleAssetBuilder())->build($this->buildConfig(['flotte']));

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
        $this->assertSame($expected, $names, 'Exactly the 5 fields this class declares — no more, no fewer, no duplicates.');
    }

    public function testBuildSeedsFuelTypesAndNativeTypeDropdownWithoutDuplicating(): void
    {
        $builder = new VehicleAssetBuilder();
        $builder->build($this->buildConfig(['flotte']));
        // Called twice deliberately: the real regression is that a second run must not duplicate
        // either list (both are seeded unconditionally, independent of the definition's own
        // isNew state — see the class's own build() comments).
        $builder->build($this->buildConfig(['flotte']));

        $fuelNames = [];
        foreach ((new FuelType())->find() as $row) {
            $fuelNames[] = $row['name'];
        }
        $missing = array_diff(self::EXPECTED_FUEL_TYPES, $fuelNames);
        $this->assertSame([], $missing, 'Every declared fuel type must be seeded.');

        global $DB;
        foreach (self::EXPECTED_FUEL_TYPES as $name) {
            $count = $DB->request(['FROM' => FuelType::getTable(), 'WHERE' => ['name' => $name]])->count();
            $this->assertSame(1, $count, "Fuel type '$name' must exist exactly once, not be duplicated by the second run.");
        }

        $definition = new AssetDefinition();
        $definition->getFromDBByCrit(['system_name' => self::SYSTEM_NAME]);
        $itemtype = $definition->getAssetTypeClassName();

        foreach (self::EXPECTED_TYPES as $name) {
            $count = $DB->request([
                'FROM' => (new $itemtype())->getTable(),
                'WHERE' => ['name' => $name, 'assets_assetdefinitions_id' => $definition->getID()],
            ])->count();
            $this->assertSame(1, $count, "Native asset type '$name' must exist exactly once, not be duplicated by the second run.");
        }
    }

    /**
     * Real regression guard for the bug documented directly in the class's own docblock (found by
     * testing `VehicleIncidentFormBuilder`'s real submission path) : without syncing
     * `helpdesk_item_type` on every profile, a `QuestionTypeItem` question pointing at a Vehicule
     * silently links nothing to the resulting ticket, no error anywhere.
     */
    public function testEveryExistingProfileCanSeeVehiculeInTheHelpdeskItemTypeList(): void
    {
        (new VehicleAssetBuilder())->build($this->buildConfig(['flotte']));

        $definition = new AssetDefinition();
        $definition->getFromDBByCrit(['system_name' => self::SYSTEM_NAME]);
        $customObjectClass = $definition->getCustomObjectClassName();

        global $DB;
        foreach ($DB->request(['FROM' => 'glpi_profiles', 'FIELDS' => ['id', 'name', 'helpdesk_item_type']]) as $row) {
            $allowed = importArrayFromDB($row['helpdesk_item_type']);
            $this->assertContains(
                $customObjectClass,
                $allowed,
                "Profile '{$row['name']}' must be able to associate a Vehicule with a ticket."
            );
        }
    }

    /**
     * Real regression found while auditing this session's own test DB (2026-09-06) : calling
     * `AssetDefinition::update()` unconditionally on every `build()` run — done here via
     * `syncHelpdeskItemTypeProfiles()`, on the reasoning that the write itself is additive-only and
     * idempotent — turned out to have an expensive side effect: GLPI core resyncs
     * `DropdownVisibility` for the custom asset type against every existing dropdown (`State` among
     * others) on every single `update()` call, with no check for already-existing rows. Confirmed
     * live: 6552 duplicate rows out of 6678 total on this project's own dev instance, all traceable
     * to this exact pattern across the 5 custom-asset builders that share it
     * (Vehicle/Building/Server/PhysicalSecurity/FireSafety). Fixed by skipping the `update()` call
     * entirely once every profile is already synced — this test proves a second `build()` call no
     * longer grows the `DropdownVisibility` table at all for this asset type.
     */
    public function testSecondBuildDoesNotGrowDropdownVisibilityRows(): void
    {
        $builder = new VehicleAssetBuilder();
        $builder->build($this->buildConfig(['flotte']));

        $definition = new AssetDefinition();
        $definition->getFromDBByCrit(['system_name' => self::SYSTEM_NAME]);
        $customObjectClass = $definition->getCustomObjectClassName();

        global $DB;
        $before = $DB->request([
            'FROM' => 'glpi_dropdownvisibilities',
            'WHERE' => ['itemtype' => 'State', 'visible_itemtype' => $customObjectClass],
        ])->count();

        $builder->build($this->buildConfig(['flotte']));

        $after = $DB->request([
            'FROM' => 'glpi_dropdownvisibilities',
            'WHERE' => ['itemtype' => 'State', 'visible_itemtype' => $customObjectClass],
        ])->count();

        $this->assertSame($before, $after, 'A second build() call must not add any new DropdownVisibility rows.');
    }
}
