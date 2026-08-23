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
use GlpiPlugin\Configurationglpiauto\PhysicalSecurityAssetBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Runs against a real GLPI instance — same overall pattern as `FireSafetyAssetBuilderTest`. Also
 * confirms the trigger is the "securite" branch (Sécurité & Protection des Personnes), not
 * "batiment" — the whole point of `PhysicalSecurityAssetBuilder`'s docblock reasoning for picking a
 * different branch than its sibling `FireSafetyAssetBuilder`.
 *
 * See `FireSafetyAssetBuilderTest`'s docblock for why `clearDefinitionsCache()` is required in
 * tearDown() (PHPUnit process-lifetime cache staleness across tests, not a production concern).
 */
final class PhysicalSecurityAssetBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        $definition = new AssetDefinition();
        if ($definition->getFromDBByCrit(['system_name' => 'SecuritePhysique'])) {
            $definition->delete(['id' => $definition->getID()], true);
        }
        AssetDefinitionManager::getInstance()->clearDefinitionsCache();
    }

    private function buildConfig(array $branches, bool $toggleEnabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_branches' => json_encode($branches),
            'physical_security_assets_enabled' => $toggleEnabled ? 1 : 0,
            'physical_security_asset_icons_enabled' => 0,
        ]);

        return $config;
    }

    public function testBatimentBranchAloneDoesNotTriggerThisBuilder(): void
    {
        // Regression guard: this builder must NOT fire off the "Bâtiment" branch, unlike
        // FireSafetyAssetBuilder/BuildingAssetBuilder — it needs "securite" specifically.
        $count = (new PhysicalSecurityAssetBuilder())->build($this->buildConfig(['batiment'], true));

        $this->assertSame(0, $count);
        $this->assertFalse((new AssetDefinition())->getFromDBByCrit(['system_name' => 'SecuritePhysique']));
    }

    public function testSecuriteBranchButToggleOffReturnsZero(): void
    {
        $count = (new PhysicalSecurityAssetBuilder())->build($this->buildConfig(['securite'], false));

        $this->assertSame(0, $count);
    }

    public function testSecuriteBranchAndToggleBothOnCreatesDefinitionWithExpectedShape(): void
    {
        $config = $this->buildConfig(['securite'], true);
        $count = (new PhysicalSecurityAssetBuilder())->build($config);

        $this->assertSame(1, $count);

        $definition = new AssetDefinition();
        $this->assertTrue($definition->getFromDBByCrit(['system_name' => 'SecuritePhysique']));

        $capacities = json_decode((string) $definition->fields['capacities'], true);
        $capacityNames = array_column($capacities, 'name');
        $this->assertNotContains(
            \Glpi\Asset\Capacity\IsReservableCapacity::class,
            $capacityNames
        );
        $this->assertNotContains(
            \Glpi\Asset\Capacity\HasNetworkPortCapacity::class,
            $capacityNames,
            'Tracks physical-security compliance, not the equipment as a managed network device.'
        );

        global $DB;
        foreach (['zone_couverte', 'date_derniere_maintenance'] as $systemName) {
            $exists = $DB->request([
                'FROM' => 'glpi_assets_customfielddefinitions',
                'WHERE' => ['assets_assetdefinitions_id' => $definition->getID(), 'system_name' => $systemName],
            ])->count() === 1;
            $this->assertTrue($exists, "Custom field $systemName should exist.");
        }

        $typeClass = $definition->getAssetTypeClassName();
        $item = new $typeClass();
        $this->assertTrue($item->getFromDBByCrit(['name' => "Contrôle d'accès (lecteur de badge)", 'assets_assetdefinitions_id' => $definition->getID()]));
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateTheDefinition(): void
    {
        $builder = new PhysicalSecurityAssetBuilder();
        $config = $this->buildConfig(['securite'], true);

        $first = $builder->build($config);
        $second = $builder->build($config);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);

        global $DB;
        $matches = $DB->request(['FROM' => 'glpi_assets_assetdefinitions', 'WHERE' => ['system_name' => 'SecuritePhysique']])->count();
        $this->assertSame(1, $matches);
    }

    /**
     * Regression guard for a real bug (found manually against a live instance, not by this suite,
     * which is exactly why it's added here): a `DropdownTranslation` row on the generated "type"
     * dropdown class made GLPI's own `Search`/`SQLProvider` emit a `..._trans_name` column with no
     * matching JOIN — `glpi_assets_assettypes` is one table shared by every `AssetDefinition`, not a
     * dedicated one, unlike the ~20 other builders this plugin applies `Translations::applyIcon()`
     * to. Fixed by baking the icon into `name` directly instead. This test both confirms that (the
     * icon prefix, no duplicate row when the option is later toggled off) and that `Search::show()`
     * on the resulting asset class no longer throws — the actual observable symptom.
     */
    public function testIconsEnabledPrefixesNameAndSearchStillWorks(): void
    {
        $builder = new PhysicalSecurityAssetBuilder();
        $config = $this->buildConfig(['securite'], true);
        $config->fields['physical_security_asset_icons_enabled'] = 1;
        $builder->build($config);

        $definition = new AssetDefinition();
        $definition->getFromDBByCrit(['system_name' => 'SecuritePhysique']);
        $typeClass = $definition->getAssetTypeClassName();

        $item = new $typeClass();
        $this->assertTrue($item->getFromDBByCrit([
            'name' => "🪪 Contrôle d'accès (lecteur de badge)",
            'assets_assetdefinitions_id' => $definition->getID(),
        ]));

        // Toggling the option back off must update the existing row, not leave a stray duplicate.
        $configNoIcons = $this->buildConfig(['securite'], true);
        $builder->build($configNoIcons);
        global $DB;
        $count = $DB->request([
            'COUNT' => 'c',
            'FROM' => 'glpi_assets_assettypes',
            'WHERE' => ['assets_assetdefinitions_id' => $definition->getID()],
        ])->current()['c'];
        $this->assertSame(6, $count, 'Toggling icons off must update existing rows, not duplicate them.');

        // The actual observable symptom: Search::show() threw a RuntimeException ("Unknown column
        // ..._trans_name") on the broken code, it doesn't just embed an error string in its output —
        // letting it throw here is the regression guard, no assertion needed beyond that.
        $assetClass = $definition->getAssetClassName();
        ob_start();
        \Search::show($assetClass);
        ob_end_clean();
    }
}
