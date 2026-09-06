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

use Entity_RSSFeed;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\RSSFeedBuilder;
use PHPUnit\Framework\TestCase;
use RSSFeed;

/**
 * `RSSFeed::prepareInputForAdd()` only fetches the feed live over the network the *first* time a
 * given URL is added (`getFromDBByCrit()` short-circuits before any fetch on every later run) — both
 * feeds already exist for real on this instance from an earlier run, so this suite never needs to
 * reach the real network itself, and deliberately doesn't force-delete them to trigger a live re-fetch
 * on every future CI run (unlike a DB row, an external site's uptime is outside this plugin's
 * control — same reasoning already applied to leaving `CountryHolidayBuilder`'s own live Nager.Date
 * call out of this suite's automated scope). What's fully verifiable without any network dependency,
 * and had zero coverage before this test, is the visibility wiring this class is actually responsible
 * for : without a matching `Entity_RSSFeed` row, a feed stays visible only to whichever user id
 * happened to create it, invisible instance-wide.
 */
final class RSSFeedBuilderTest extends TestCase
{
    private const URLS = [
        'https://www.cert.ssi.gouv.fr/feed/',
        'https://github.com/glpi-project/glpi/releases.atom',
    ];

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'rss_feeds_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new RSSFeedBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresBothFeedsExistActiveAndVisibleInstanceWide(): void
    {
        $count = (new RSSFeedBuilder())->build($this->buildConfig(true));

        $this->assertSame(2, $count);

        foreach (self::URLS as $url) {
            $feed = new RSSFeed();
            $this->assertTrue($feed->getFromDBByCrit(['url' => $url]), "Feed '$url' must exist.");
            $this->assertSame(1, (int) $feed->fields['is_active']);

            $link = new Entity_RSSFeed();
            $this->assertTrue(
                $link->getFromDBByCrit(['rssfeeds_id' => $feed->getID(), 'entities_id' => 0]),
                "Feed '$url' must be linked to the root entity, or it stays visible only to its creator."
            );
            $this->assertSame(1, (int) $link->fields['is_recursive']);
        }
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateFeedsOrLinks(): void
    {
        $builder = new RSSFeedBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(2, $second);

        global $DB;
        foreach (self::URLS as $url) {
            $feedCount = $DB->request(['FROM' => RSSFeed::getTable(), 'WHERE' => ['url' => $url]])->count();
            $this->assertSame(1, $feedCount, "Exactly one row must exist for '$url' — no duplicate.");
        }
    }
}
