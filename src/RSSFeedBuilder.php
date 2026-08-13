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

namespace GlpiPlugin\Configurationglpiauto;

use Entity_RSSFeed;
use RSSFeed;

/**
 * Turns on `rss_feeds_enabled` into a real `RSSFeed` row (Outils > Flux RSS), native GLPI, empty
 * by default on a fresh install. Scoped to a single, France-specific but near-universally relevant
 * feed rather than a generic "add any feed" mechanism — requested directly by the user
 * ("notamment le CERT-FR pour les français"), matching the plugin's existing France-first defaults
 * elsewhere (public holidays).
 *
 * CERT-FR (Centre gouvernemental de veille, d'alerte et de réponse aux attaques informatiques, run
 * by ANSSI) publishes security advisories/alerts as a real, live RSS feed — confirmed by fetching
 * https://www.cert.ssi.gouv.fr/feed/ directly (returns real, current `<item>` entries), not a
 * guessed URL. `RSSFeed::prepareInputForAdd()` fetches the feed live at add-time to auto-populate
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
    private const FEED_URL = 'https://www.cert.ssi.gouv.fr/feed/';

    /**
     * @return int Number of feeds created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['rss_feeds_enabled'])) {
            return 0;
        }

        $feed = new RSSFeed();
        if ($feed->getFromDBByCrit(['url' => self::FEED_URL])) {
            return 1;
        }

        $feedId = $feed->add(['url' => self::FEED_URL, 'is_active' => 1]);
        if (!$feedId) {
            return 0;
        }

        (new Entity_RSSFeed())->add([
            'rssfeeds_id' => $feedId,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);

        return 1;
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    public static function getFeedsPreview(): array
    {
        return [
            ['name' => 'CERT-FR — Avis et alertes de sécurité (ANSSI)', 'url' => self::FEED_URL],
        ];
    }
}
