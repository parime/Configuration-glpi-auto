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
use Glpi\Asset\AssetDefinitionManager;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\FireSafetyAssetBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Runs against a real GLPI instance — exercises the actual `AssetDefinition`/`CustomFieldDefinition`
 * API and the generated "type" dropdown class, not just the builder's own return value. Same overall
 * pattern as `AssetTypeBuilderTest`, but also covers the two-condition trigger (category branch AND
 * dedicated toggle) unique to this builder and `PhysicalSecurityAssetBuilder` — see
 * `FireSafetyAssetBuilder`'s docblock for why it differs from `VehicleAssetBuilder`/
 * `ServerAssetBuilder`/`BuildingAssetBuilder`'s single branch-only trigger.
 *
 * `AssetDefinitionManager::clearDefinitionsCache()` in tearDown() is required, not cosmetic: that
 * manager caches every known `AssetDefinition` for the lifetime of the PHP process to back its own
 * dynamic class autoloader (`Glpi\CustomAsset\<SystemName>Asset[Type]`, confirmed by reading
 * `AbstractDefinitionManager`/`AssetDefinitionManager` core source) — never an issue for the real
 * wizard (one HTTP request = one fresh process), but PHPUnit reuses a single process across every
 * test in the suite, so a definition deleted by one test's tearDown() left the next test's freshly
 * re-created definition invisible to the still-cached manager, surfacing as a spurious "Class
 * Glpi\CustomAsset\AssetType not found" only when the full suite ran together (never reproduced
 * running this test class in isolation) — root-caused live rather than worked around blindly.
 */
final class FireSafetyAssetBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        $definition = new AssetDefinition();
        if ($definition->getFromDBByCrit(['system_name' => 'SecuriteIncendieSecours'])) {
            $definition->delete(['id' => $definition->getID()], true);
        }
        AssetDefinitionManager::getInstance()->clearDefinitionsCache();
    }

    private function buildConfig(bool $branchSelected, bool $toggleEnabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_branches' => json_encode($branchSelected ? ['batiment'] : []),
            'fire_safety_assets_enabled' => $toggleEnabled ? 1 : 0,
            'fire_safety_asset_icons_enabled' => 0,
        ]);

        return $config;
    }

    public function testBranchSelectedButToggleOffReturnsZeroAndCreatesNothing(): void
    {
        $count = (new FireSafetyAssetBuilder())->build($this->buildConfig(true, false));

        $this->assertSame(0, $count);
        $this->assertFalse((new AssetDefinition())->getFromDBByCrit(['system_name' => 'SecuriteIncendieSecours']));
    }

    public function testToggleOnButBranchNotSelectedReturnsZeroAndCreatesNothing(): void
    {
        $count = (new FireSafetyAssetBuilder())->build($this->buildConfig(false, true));

        $this->assertSame(0, $count);
        $this->assertFalse((new AssetDefinition())->getFromDBByCrit(['system_name' => 'SecuriteIncendieSecours']));
    }

    public function testBranchAndToggleBothOnCreatesDefinitionWithExpectedShape(): void
    {
        $config = $this->buildConfig(true, true);
        $count = (new FireSafetyAssetBuilder())->build($config);

        $this->assertSame(1, $count);

        $definition = new AssetDefinition();
        $this->assertTrue($definition->getFromDBByCrit(['system_name' => 'SecuriteIncendieSecours']));

        $capacities = json_decode((string) $definition->fields['capacities'], true);
        $capacityNames = array_column($capacities, 'name');
        $this->assertContains(\Glpi\Asset\Capacity\HasInfocomCapacity::class, $capacityNames);
        $this->assertNotContains(
            \Glpi\Asset\Capacity\IsReservableCapacity::class,
            $capacityNames,
            'Fire safety equipment is never booked out — reservability should not be enabled.'
        );

        // Custom field (periodic verification date) actually landed.
        global $DB;
        $fieldExists = $DB->request([
            'FROM' => 'glpi_assets_customfielddefinitions',
            'WHERE' => [
                'assets_assetdefinitions_id' => $definition->getID(),
                'system_name' => 'date_verification_periodique',
            ],
        ])->count() === 1;
        $this->assertTrue($fieldExists);

        // Native "type" dropdown seeded with every configured sub-kind, including the folded-in AED.
        $typeClass = $definition->getAssetTypeClassName();
        $item = new $typeClass();
        $this->assertTrue($item->getFromDBByCrit(['name' => 'Extincteur', 'assets_assetdefinitions_id' => $definition->getID()]));
        $this->assertTrue($item->getFromDBByCrit(['name' => 'Défibrillateur automatisé externe (DAE)', 'assets_assetdefinitions_id' => $definition->getID()]));
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateTheDefinition(): void
    {
        $builder = new FireSafetyAssetBuilder();
        $config = $this->buildConfig(true, true);

        $first = $builder->build($config);
        $second = $builder->build($config);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);

        global $DB;
        $matches = $DB->request(['FROM' => 'glpi_assets_assetdefinitions', 'WHERE' => ['system_name' => 'SecuriteIncendieSecours']])->count();
        $this->assertSame(1, $matches);
    }
}
