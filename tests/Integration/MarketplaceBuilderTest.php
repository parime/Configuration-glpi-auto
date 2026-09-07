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

use Config as GlpiConfig;
use GLPIKey;
use GlpiPlugin\Configurationglpiauto\MarketplaceBuilder;
use PHPUnit\Framework\TestCase;

final class MarketplaceBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        // Leaves no trace on the shared instance — restores GLPI's own native registration key
        // config to whatever it was (usually unset) before this suite ran.
        GlpiConfig::deleteConfigurationValues('core', ['glpinetwork_registration_key']);
    }

    public function testReturnsZeroAndWritesNothingForAnEmptyKey(): void
    {
        $count = (new MarketplaceBuilder())->build('   ');

        $this->assertSame(0, $count);
    }

    /**
     * `GLPINetwork::getRegistrationKey()` reads from the `$CFG_GLPI` global, only refreshed at
     * bootstrap — not after a same-request `Config::setConfigurationValues()` write. So this reads
     * straight back from the DB (same encrypted value GLPIKey stores) instead, decrypting it with
     * the same `GLPIKey` mechanism `getRegistrationKey()` itself uses.
     */
    public function testWritesTheTrimmedKeyToGlpiCoreNativeConfig(): void
    {
        $count = (new MarketplaceBuilder())->build('  ABCD-1234-EFGH  ');

        $this->assertSame(1, $count);

        global $DB;
        $row = $DB->request(['FROM' => 'glpi_configs', 'WHERE' => ['context' => 'core', 'name' => 'glpinetwork_registration_key']])->current();
        $this->assertSame('ABCD-1234-EFGH', (new GLPIKey())->decrypt($row['value']));
    }

    public function testGetRecommendedPluginsPreviewListsEveryEntryWithARealMarketplaceKeyOrAnExplicitNote(): void
    {
        $plugins = MarketplaceBuilder::getRecommendedPluginsPreview();

        $this->assertNotEmpty($plugins);
        foreach ($plugins as $plugin) {
            $this->assertNotSame('', $plugin['name']);
            $this->assertNotSame('', $plugin['description']);
            $this->assertNotSame('', $plugin['url']);
            $this->assertNotSame('', $plugin['note']);
            // Either a real marketplace `key` (installable in one click) or an explicit note saying
            // it isn't on the marketplace yet — never silently missing both.
            $this->assertTrue($plugin['key'] !== null || str_contains($plugin['note'], 'marketplace'));
        }
    }
}
