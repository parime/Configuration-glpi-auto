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
 *
 * `clearDefinitionsCache()` alone is NOT enough, though — it only empties `$this->definitions`;
 * nothing repopulates it afterwards (`getDefinition()` does a plain array lookup, no lazy reload),
 * so leaving it at that permanently blinds every AssetDefinition-backed test that runs later in the
 * same process, including ones this class never touches (e.g. `VehicleIncidentFormBuilderTest`'s own
 * "Vehicule" definition) — confirmed live: dropped when the fix below was momentarily removed to
 * check. `bootDefinitions()` right after is the same call GLPI's own kernel boot listener makes
 * (`CustomObjectsBoot`) — it reloads straight from the DB, so it's cheap and exactly mirrors what a
 * fresh request would see.
 *
 * Deleting the definition alone ALSO leaves a second, nastier trace: `AssetDefinitionManager::
 * bootstrapDefinition()` (run by `bootDefinitions()` for every *currently existing* definition)
 * appends the concrete asset class name to several `$CFG_GLPI` arrays (`ticket_types` among them) the
 * first time it's bootstrapped, but nothing ever pulls it back out when the definition is later
 * deleted — the class itself stays `class_exists() === true` for the rest of the PHP process (PHP
 * cannot un-define a class), so any later code enumerating `$CFG_GLPI['ticket_types']` (e.g.
 * `CommonITILObject::getAllTypesForHelpdesk()`, which a real form submission against an unrelated
 * `QuestionTypeItem` question ends up calling) tries to `new` that now-defined-but-orphaned class and
 * hits the exact same "Asset definition is expected to be defined in concrete class" — confirmed live
 * against `VehicleIncidentFormBuilderTest`, which only started failing, full-suite, once this class's
 * own cleanup ran first. Stripping the class names back out of every key `bootstrapDefinition()` can
 * touch, before deleting the definition, is the only way to leave the process in the same state a
 * fresh request would see.
 */
final class FireSafetyAssetBuilderTest extends TestCase
{
    private const BOOTSTRAPPED_CFG_KEYS = [
        'asset_types', 'assignable_types', 'location_types', 'state_types', 'ticket_types', 'unicity_types',
    ];

    protected function tearDown(): void
    {
        global $CFG_GLPI;

        $definition = new AssetDefinition();
        if ($definition->getFromDBByCrit(['system_name' => 'SecuriteIncendieSecours'])) {
            $staleClasses = [
                $definition->getAssetClassName(),
                $definition->getAssetTypeClassName(),
                $definition->getAssetModelClassName(),
            ];

            // Stripping BEFORE delete() looked right but isn't: AssetDefinition::delete()'s own
            // capacity-cleanup re-triggers bootstrapDefinition() (confirmed live, by printing
            // $CFG_GLPI immediately before/after the delete() call) — presumably because it needs
            // the concrete class name to disable capacities before the row is actually gone, the
            // same way a fresh create does. Stripping must happen AFTER delete() to survive it.
            $definition->delete(['id' => $definition->getID()], true);

            foreach (self::BOOTSTRAPPED_CFG_KEYS as $key) {
                $CFG_GLPI[$key] = array_values(array_diff($CFG_GLPI[$key], $staleClasses));
            }
            $CFG_GLPI['dictionnary_types'] = array_values(array_diff(
                $CFG_GLPI['dictionnary_types'],
                [$staleClasses[1], $staleClasses[2]]
            ));
        }
        AssetDefinitionManager::getInstance()->clearDefinitionsCache();
        AssetDefinitionManager::getInstance()->bootDefinitions();
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
        $builder = new FireSafetyAssetBuilder();
        $config = $this->buildConfig(true, true);
        $config->fields['fire_safety_asset_icons_enabled'] = 1;
        $builder->build($config);

        $definition = new AssetDefinition();
        $definition->getFromDBByCrit(['system_name' => 'SecuriteIncendieSecours']);
        $typeClass = $definition->getAssetTypeClassName();

        $item = new $typeClass();
        $this->assertTrue($item->getFromDBByCrit([
            'name' => '🧯 Extincteur',
            'assets_assetdefinitions_id' => $definition->getID(),
        ]));

        // Toggling the option back off must update the existing row, not leave a stray duplicate.
        $builder->build($this->buildConfig(true, true));
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
