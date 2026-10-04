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

use GlpiPlugin\Configurationglpiauto\AssetsignTriggerBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use PHPUnit\Framework\TestCase;

/**
 * Same "sibling plugin not installed on this instance" scope limit as SatisfactionSurveyBuilderTest:
 * the actual write path is covered by the pure plan() tests (tests/Unit) and checked live on an
 * instance with assetsign installed.
 */
final class AssetsignTriggerBuilderTest extends TestCase
{
    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'assetsign_triggers_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $this->assertSame(0, (new AssetsignTriggerBuilder())->build($this->buildConfig(false)));
    }

    public function testReturnsZeroAndNoPreviewWhenAssetsignIsNotActive(): void
    {
        $this->assertFalse(AssetsignTriggerBuilder::isThirdPartyPluginActive(), 'This test instance has no assetsign plugin.');

        $this->assertSame(0, (new AssetsignTriggerBuilder())->build($this->buildConfig(true)));
        $this->assertSame([], AssetsignTriggerBuilder::preview());
    }

    public function testDefaultsSelectEveryTrigger(): void
    {
        $this->assertSame(array_keys(AssetsignTriggerBuilder::TRIGGERS), $this->buildConfig(true)->getAssetsignTriggerFields());
    }
}
