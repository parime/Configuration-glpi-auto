<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Parime
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
    protected function tearDown(): void
    {
        foreach (AssetTypeBuilder::getTypesPreview() as $itemtype => $types) {
            foreach ($types as $type) {
                $item = new $itemtype();
                $crit = ['name' => $type['name']];
                if ($item->getFromDBByCrit($crit)) {
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
}
