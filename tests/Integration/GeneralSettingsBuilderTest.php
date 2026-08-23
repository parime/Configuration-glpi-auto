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
use GlpiPlugin\Configurationglpiauto\GeneralSettingsBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Covers only the `inventory_enabled` group (#147) — the only one targeting the `inventory`
 * config context instead of `core`, and the only one this session added. The other 6 groups
 * (general UI, notifications, financial info, project task states, satisfaction survey,
 * committee validation) predate this test file and are exercised indirectly by the wizard's own
 * end-to-end flow rather than unit-tested here.
 */
final class GeneralSettingsBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        \Config::setConfigurationValues('inventory', ['enabled_inventory' => 0]);
    }

    private function buildConfig(bool $inventoryEnabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'inventory_enabled' => $inventoryEnabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testDisabledByDefaultLeavesNativeInventoryOff(): void
    {
        // Not asserting on apply()'s own return value here: getDefaults() enables several other
        // groups (general UI, notifications...) that legitimately make it return true regardless
        // of inventory_enabled — only the inventory config value itself is this test's concern.
        (new GeneralSettingsBuilder())->apply($this->buildConfig(false));

        $this->assertSame('0', \Config::getConfigurationValue('inventory', 'enabled_inventory'));
    }

    public function testEnabledTurnsOnTheNativeInventoryEndpoint(): void
    {
        $applied = (new GeneralSettingsBuilder())->apply($this->buildConfig(true));

        $this->assertTrue($applied);
        $this->assertSame('1', \Config::getConfigurationValue('inventory', 'enabled_inventory'));
    }
}
