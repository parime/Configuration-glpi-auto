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
use GlpiPlugin\Configurationglpiauto\CountryHolidayBuilder;
use PHPUnit\Framework\TestCase;

/**
 * `build()` only ever reaches the live Nager.Date network call (`buildForCountry()`) for a
 * recognized country name — same reasoning already applied in `RSSFeedBuilderTest`'s own docblock
 * for leaving this exact class's live call out of the automated suite's scope: an external site's
 * uptime is outside this plugin's control, so CI shouldn't depend on it. What's fully verifiable
 * without ever reaching the network is `COUNTRY_CODES` resolution itself — every path here is
 * exercised without a single unrecognized/disabled/empty case ever calling `buildForCountry()`.
 */
final class CountryHolidayBuilderTest extends TestCase
{
    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'country_holidays_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new CountryHolidayBuilder())->build($this->buildConfig(false), ['0' => 'France']);

        $this->assertSame(0, $count);
    }

    public function testReturnsZeroWhenCountryByPathIsEmpty(): void
    {
        $count = (new CountryHolidayBuilder())->build($this->buildConfig(true), []);

        $this->assertSame(0, $count);
    }

    /**
     * A free-text country name this class doesn't recognize is silently skipped — never guessed,
     * never reaches the network — per the class's own documented "only for countries where we
     * actually have real data" rule.
     */
    public function testReturnsZeroAndNeverReachesTheNetworkForAnUnrecognizedCountryName(): void
    {
        $count = (new CountryHolidayBuilder())->build($this->buildConfig(true), ['0' => 'Narnia', '1' => '   ']);

        $this->assertSame(0, $count);
    }
}
