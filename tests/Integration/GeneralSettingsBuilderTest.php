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
 * config context instead of `core`, and the only one this session added. The other groups
 * (general UI, notifications, financial info, project task states, satisfaction survey,
 * committee validation, audit watch) predate this test file and are exercised indirectly by the
 * wizard's own end-to-end flow rather than unit-tested here.
 *
 * `buildConfig()` deliberately does NOT start from `Config::getDefaults()`: every other group
 * defaults to enabled there, and each one writes to *real*, shared GLPI core state (native
 * `Config` values, `Notification`/`CronTask` rows, even a new `ValidationStep`) — a real leak
 * caught the hard way running this suite alongside a sibling plugin's own test suite on the same
 * shared instance: `financial_info_enabled` (on by default in `getDefaults()`) silently flipped
 * `auto_create_infocoms` to 1, which then made an unrelated plugin's own Infocom-related tests
 * fail with duplicate-key errors, because nothing in this file ever reset it back. Every group
 * this test doesn't care about is explicitly forced to 0 so `apply()` only ever touches the one
 * flag actually under test, and the single `tearDown()` reset stays sufficient.
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
        $config->fields = [
            'general_ui_enabled'           => 0,
            'notifications_enabled'        => 0,
            'financial_info_enabled'       => 0,
            'project_task_states_enabled'  => 0,
            'satisfaction_survey_enabled'  => 0,
            'committee_validation_enabled' => 0,
            'audit_watch_enabled'          => 0,
            'inventory_enabled'            => $inventoryEnabled ? 1 : 0,
        ];

        return $config;
    }

    public function testDisabledByDefaultLeavesNativeInventoryOff(): void
    {
        $applied = (new GeneralSettingsBuilder())->apply($this->buildConfig(false));

        $this->assertFalse($applied);
        $this->assertSame('0', \Config::getConfigurationValue('inventory', 'enabled_inventory'));
    }

    public function testEnabledTurnsOnTheNativeInventoryEndpoint(): void
    {
        $applied = (new GeneralSettingsBuilder())->apply($this->buildConfig(true));

        $this->assertTrue($applied);
        $this->assertSame('1', \Config::getConfigurationValue('inventory', 'enabled_inventory'));
    }
}
