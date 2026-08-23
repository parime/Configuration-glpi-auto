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

namespace GlpiPlugin\Configurationglpiauto;

use Entity_RSSFeed;
use RSSFeed;

/**
 * Turns on `rss_feeds_enabled` into real `RSSFeed` rows (Outils > Flux RSS), native GLPI, empty by
 * default on a fresh install.
 *
 * CERT-FR (Centre gouvernemental de veille, d'alerte et de réponse aux attaques informatiques, run
 * by ANSSI) — requested directly by the user ("notamment le CERT-FR pour les français"), matching
 * the plugin's existing France-first defaults elsewhere (public holidays). Confirmed real and live
 * by fetching https://www.cert.ssi.gouv.fr/feed/ directly (real, current `<item>` entries), not a
 * guessed URL.
 *
 * GLPI's own release notes (#148: "évaluer l'intérêt" of a GLPI changelog feed) — GitHub's native
 * per-repo Atom feed (`https://github.com/glpi-project/glpi/releases.atom`), confirmed real and
 * live the same way (fetched directly: real `<entry>` per GLPI release, including security-relevant
 * releases like 11.0.8's CVE fixes) rather than inventing a URL. Not France-specific, but genuinely
 * useful for every admin running this plugin — knowing when a new GLPI version ships (security
 * fixes especially) matters regardless of country. `SimplePie` (confirmed in GLPI core's
 * `RSSFeed::getSimplePie()`) parses Atom feeds natively, same as RSS — no format-specific handling
 * needed here.
 *
 * `RSSFeed::prepareInputForAdd()` fetches each feed live at add-time to auto-populate
 * `name`/`comment` from the feed's own title/description — no need to hardcode those here, and it
 * degrades gracefully (`have_error=1`, native GLPI cron retries later) if the fetch fails at
 * install time, so a transient network issue during setup doesn't need special handling.
 *
 * `Entity_RSSFeed` (entities_id=0, is_recursive=1) grants visibility instance-wide — without it,
 * `RSSFeed` is only visible to its creator (`Session::getLoginUserID()`, set automatically by
 * `prepareInputForAdd()`), same ownership-vs-visibility split as `glpi_locations`/`glpi_entities`
 * elsewhere in GLPI.
 */
class RSSFeedBuilder
{
    /**
     * @var array<int, array{name: string, url: string}>
     */
    private const FEEDS = [
        ['name' => 'CERT-FR — Avis et alertes de sécurité (ANSSI)', 'url' => 'https://www.cert.ssi.gouv.fr/feed/'],
        ['name' => 'GLPI — Notes de version (GitHub)', 'url' => 'https://github.com/glpi-project/glpi/releases.atom'],
    ];

    /**
     * @return int Number of feeds created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['rss_feeds_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::FEEDS as $feedData) {
            $feed = new RSSFeed();
            if ($feed->getFromDBByCrit(['url' => $feedData['url']])) {
                $count++;
                continue;
            }

            $feedId = $feed->add(['url' => $feedData['url'], 'is_active' => 1]);
            if (!$feedId) {
                continue;
            }

            (new Entity_RSSFeed())->add([
                'rssfeeds_id' => $feedId,
                'entities_id' => 0,
                'is_recursive' => 1,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    public static function getFeedsPreview(): array
    {
        return self::FEEDS;
    }
}
