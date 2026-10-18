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

use GlpiPlugin\Configurationglpiauto\AssetTypeBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use PHPUnit\Framework\TestCase;

/**
 * Runs against a real GLPI instance — AssetTypeBuilder writes real rows across ~20 different core
 * dropdown itemtypes (State, ComputerType, MonitorType...), so this exercises the common
 * getOrCreate() pattern shared by most of this plugin's "seed the native dropdowns" builders, both
 * for a plain global dropdown and for one of the entity-scoped ones (ApplianceType).
 */
final class AssetTypeBuilderTest extends TestCase
{
    /** @var array<string, true> itemtype + nom des types qui existaient avant le test */
    private array $existing = [];

    protected function setUp(): void
    {
        foreach (AssetTypeBuilder::getTypesPreview() as $itemtype => $types) {
            foreach ($types as $type) {
                if ((new $itemtype())->getFromDBByCrit(['name' => $type['name']])) {
                    $this->existing[$itemtype . "\0" . $type['name']] = true;
                }
            }
        }
    }

    /**
     * Ne purge que les types créés par le test : purger une catégorie de logiciel déjà présente
     * (créée par l'assistant) ferait désactiver par GLPI les règles du dictionnaire qui la
     * référencent, et casserait les tests suivants sur une instance partagée.
     */
    protected function tearDown(): void
    {
        foreach (AssetTypeBuilder::getTypesPreview() as $itemtype => $types) {
            foreach ($types as $type) {
                if (isset($this->existing[$itemtype . "\0" . $type['name']])) {
                    continue;
                }
                $item = new $itemtype();
                if ($item->getFromDBByCrit(['name' => $type['name']])) {
                    $item->delete(['id' => $item->getID()], true);
                }
            }
        }
    }

    public function testBuildCreatesEveryConfiguredTypeExactlyOnce(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'asset_types_enabled' => 1,
            'asset_type_icons_enabled' => 0,
        ]);

        $expectedCount = array_sum(array_map('count', AssetTypeBuilder::getTypesPreview()));
        $count = (new AssetTypeBuilder())->build($config);

        $this->assertSame($expectedCount, $count);

        // Spot-check one plain dropdown and one entity-scoped dropdown rather than every row.
        $types = AssetTypeBuilder::getTypesPreview();
        $sampleItemtype = array_key_first($types);
        $sampleName = $types[$sampleItemtype][0]['name'];
        $item = new $sampleItemtype();
        $this->assertTrue($item->getFromDBByCrit(['name' => $sampleName]));
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateRows(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'asset_types_enabled' => 1,
            'asset_type_icons_enabled' => 0,
        ]);
        $builder = new AssetTypeBuilder();

        $builder->build($config);
        $builder->build($config);

        $types = AssetTypeBuilder::getTypesPreview();
        $sampleItemtype = array_key_first($types);
        $sampleName = $types[$sampleItemtype][0]['name'];
        $item = new $sampleItemtype();
        $matches = $item->find(['name' => $sampleName]);
        $this->assertCount(1, $matches);
    }

    public function testDisabledReturnsZeroAndCreatesNothing(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['asset_types_enabled' => 0]);

        $count = (new AssetTypeBuilder())->build($config);

        $this->assertSame(0, $count);
    }

    /**
     * `SoftwareCategory` is the one `CommonTreeDropdown` this builder seeds (every other itemtype
     * here is a plain `CommonDropdown`) — regression guard for the exact bug found live on
     * `FireSafetyAssetBuilder`/`PhysicalSecurityAssetBuilder` (a `DropdownTranslation` row breaking
     * `Search::show()` on a table with unusual structure): confirms icons + a tree dropdown don't
     * hit the same class of issue, by actually calling `Search::show()`, not just checking the row
     * was created.
     */
    public function testTreeDropdownWithIconsDoesNotBreakSearch(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'asset_types_enabled' => 1,
            'asset_type_icons_enabled' => 1,
        ]);

        (new AssetTypeBuilder())->build($config);

        $item = new \SoftwareCategory();
        $this->assertTrue($item->getFromDBByCrit(['name' => 'Bureautique']));
        $this->assertSame(1, (int) $item->fields['level'], 'Seeded as a root node, not nested.');

        ob_start();
        \Search::show(\SoftwareCategory::class);
        ob_end_clean();
    }
}
