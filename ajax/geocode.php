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

use GlpiPlugin\Configurationglpiauto\Config;

/**
 * Server-side proxy for the wizard's address-autocomplete assistant (step "Lieux") — never called
 * directly by an anonymous visitor, only by the wizard's own JS while an admin is filling in step
 * 15, and never with a client-supplied target host.
 *
 * Deliberately proxied through this plugin's own backend rather than a raw client-side `fetch()`
 * straight to Nominatim, for two real reasons:
 * - A browser cannot set a custom `User-Agent` header (blocked for security by every engine) — the
 *   Nominatim usage policy requires a real identifying one; server-side PHP can set it.
 * - The target endpoint is admin-configurable (`$config->fields['location_geocoding_endpoint']`, self-hosted
 *   Nominatim/Photon/LocationIQ...) — reading it from *this server's own stored config* rather than
 *   from the request means a malicious client can never redirect this proxy to fetch an arbitrary
 *   URL (would otherwise be a textbook SSRF: whoever controls the query params controls what the
 *   GLPI server fetches).
 *
 * `Toolbox::getGuzzleClient()` (GLPI core) rather than raw `file_get_contents`/curl — automatically
 * honours GLPI's own configured outbound proxy (`proxy_name`/`proxy_port`), which a corporate
 * install behind a proxy would otherwise silently fail through.
 */

Session::checkRight(Config::$rightname, READ);

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();

$config = Config::getConfig();

// Deliberately no `location_geocoding_enabled` gate here: the wizard's own JS already never
// calls this endpoint unless the admin has just checked that box in *this* browser session (see
// wizard.html.twig's `locationGeocodingEnabledInput.checked` guard) — that in-the-moment click is
// the real opt-in. Gating on the *persisted* config value instead would reject every call made
// during the very first wizard run, before that toggle has ever been saved (nothing to persist
// until "Terminer" is clicked), while adding no actual protection: this endpoint is already
// restricted to authenticated users holding Config::$rightname READ, the same admins who could
// simply re-check the box and reload to get the same access a moment later.
$endpoint = rtrim((string) ($config->fields['location_geocoding_endpoint'] ?? ''), '/');
if ($endpoint === '' || !preg_match('#^https://#', $endpoint)) {
    // Config::prepareInput() only ever lets a valid https:// URL through at save time — this is
    // a defence-in-depth check, not the primary validation.
    http_response_code(500);
    echo json_encode(['error' => 'misconfigured_endpoint']);
    return;
}

// Free-form query ("12 rue de la Paix, Paris") or a postcode-only lookup (city auto-fill) —
// never both at once, the caller picks one mode.
$query = trim((string) ($_GET['q'] ?? ''));
$postcode = trim((string) ($_GET['postcode'] ?? ''));

if (mb_strlen($query) < 3 && mb_strlen($postcode) < 3) {
    echo json_encode([]);
    return;
}

$params = [
    'format' => 'jsonv2',
    'addressdetails' => 1,
    'limit' => 5,
];
if ($postcode !== '') {
    // A bare postcode is ambiguous worldwide (e.g. "69001" alone matches Lyon, France just as
    // well as a district of Zaporizhzhia, Ukraine — confirmed live against public Nominatim) and
    // this result silently overwrites the town field with no admin review, unlike the free-text
    // suggestions below which the admin always picks from explicitly. Narrow it down with a
    // country: whatever the admin already typed in the "Pays" field, or this plugin's own
    // dominant-audience default (same reasoning as the hardcoded French public holidays already
    // used elsewhere) when that field is still empty.
    $params['postalcode'] = mb_substr($postcode, 0, 16);
    $country = trim((string) ($_GET['country'] ?? ''));
    $params['country'] = $country !== '' ? mb_substr($country, 0, 100) : 'France';
} else {
    $params['q'] = mb_substr($query, 0, 200);
}

try {
    $client = Toolbox::getGuzzleClient();
    $response = $client->request('GET', $endpoint . '/search', [
        'query' => $params,
        'headers' => [
            // Identifies the request per Nominatim's usage policy (a plain browser fetch() can't
            // set this header at all) — real repo URL, not a placeholder, so a legitimate abuse
            // report has somewhere to go.
            'User-Agent' => 'Configuration-glpi-auto-plugin (+https://github.com/parime/Configuration-glpi-auto)',
            'Accept' => 'application/json',
        ],
        'timeout' => 5,
    ]);

    $results = json_decode((string) $response->getBody(), true) ?? [];
} catch (\Throwable $e) {
    \Glpi\Error\ErrorHandler::logCaughtException($e);
    http_response_code(502);
    echo json_encode(['error' => 'geocoding_service_unavailable']);
    return;
}

// Trim to just what the wizard's JS actually needs — never forward the full, sometimes very large
// Nominatim payload (bounding boxes, OSM internal ids...) to the browser.
$suggestions = [];
foreach ($results as $result) {
    $address = $result['address'] ?? [];
    $road = trim(($address['house_number'] ?? '') . ' ' . ($address['road'] ?? ''));
    $suggestions[] = [
        'label' => (string) ($result['display_name'] ?? ''),
        'address' => $road,
        'postcode' => (string) ($address['postcode'] ?? ''),
        'town' => (string) ($address['city'] ?? $address['town'] ?? $address['village'] ?? ''),
        'country' => (string) ($address['country'] ?? ''),
    ];
}

echo json_encode($suggestions);
