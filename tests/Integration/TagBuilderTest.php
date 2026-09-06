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
use GlpiPlugin\Configurationglpiauto\TagBuilder;
use PHPUnit\Framework\TestCase;

/**
 * `TagBuilder` only ever does real work when the third-party "Tag" plugin (pluginsGLPI/tag) is
 * active — never installed on this test instance, so the "plugin active AND enabled" creation path
 * is out of this suite's reach (installing an unrelated third-party plugin just to test one guard
 * clause is out of scope, same reasoning already applied to SLA cron behaviour elsewhere in this
 * plugin). What IS fully verifiable here, and had zero coverage before this test, is both of this
 * class's own gates : `tag_library_enabled` and the third-party plugin check.
 */
final class TagBuilderTest extends TestCase
{
    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'tag_library_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new TagBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testReturnsZeroWhenEnabledButThirdPartyTagPluginIsNotActive(): void
    {
        $this->assertFalse(TagBuilder::isThirdPartyPluginActive(), 'This test instance has no "tag" plugin installed.');

        $count = (new TagBuilder())->build($this->buildConfig(true));

        $this->assertSame(0, $count, 'Must not touch glpi_plugin_tag_tags when the owning plugin is not active.');
    }
}
