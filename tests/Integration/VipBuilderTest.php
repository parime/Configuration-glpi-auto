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
use GlpiPlugin\Configurationglpiauto\VipBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Same "third-party plugin not installed on this instance" scope limit as `TagBuilderTest` — see
 * that test's docblock. Both of this class's own gates (`vip_group_enabled` and the third-party
 * plugin check) had zero coverage before this test.
 */
final class VipBuilderTest extends TestCase
{
    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'vip_group_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new VipBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testReturnsZeroWhenEnabledButThirdPartyVipPluginIsNotActive(): void
    {
        $this->assertFalse(VipBuilder::isThirdPartyPluginActive(), 'This test instance has no "vip" plugin installed.');

        $count = (new VipBuilder())->build($this->buildConfig(true));

        $this->assertSame(0, $count, 'Must not touch glpi_plugin_vip_groups when the owning plugin is not active.');
    }
}
