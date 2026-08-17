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

use Calendar_Holiday;
use Holiday;

/**
 * Turns the countries typed into the wizard's own Location address fields (step "Lieux") — or the
 * default of France when none is typed — into real fixed-date `Holiday` rows (native GLPI,
 * `is_perpetual = 1`), attached to the right calendar automatically. Explicitly scoped to "only
 * for countries where we actually have real data" (the user's own requirement): a country typed
 * in free text that this class doesn't recognize, or that the public holidays API doesn't cover,
 * is silently skipped rather than guessed.
 *
 * France is handled through this exact same mechanism, not a separate hardcoded path — a prior
 * version had a fixed 8-holiday list wired to its own `calendar_holidays_enabled` checkbox,
 * independent of any actual detected country (it would attach *French* holidays to a *German*
 * site's calendar just because the checkbox was on). Replaced on explicit user request ("gérer les
 * fermetures de manière automatique selon le pays") — `front/wizard.php` now defaults every
 * top-level client/site with no country typed (or Locations disabled entirely) to France before
 * calling this class, so the common case (no address ever entered) still gets French holidays out
 * of the box, while a real detected country always wins. Confirmed live that Nager.Date's French
 * fixed-date results are identical to the old hardcoded list (same 8 names/dates), so this is a
 * pure "same output, from live data instead of a hardcoded copy" change, not a behavior regression.
 *
 * Data source: Nager.Date (`https://date.nager.at`), a free public holidays API — confirmed live
 * (real, current French holidays returned) and confirmed to cover ~100 countries
 * (`/api/v3/AvailableCountries`) before committing to this design, not assumed. Called server-side
 * only, at wizard-submission time — same `Toolbox::getGuzzleClient()` pattern already used by
 * `ajax/geocode.php` (honours GLPI's own configured outbound proxy), not a new client-facing
 * endpoint.
 *
 * GLPI's `Holiday.is_perpetual` only repeats a fixed month/day every year — there is no
 * "recompute from Easter" mechanism. Nager.Date returns concrete per-year dates, not a recurrence
 * rule, so this class determines which holidays are actually fixed-date empirically: it fetches
 * two consecutive years and keeps only the holidays whose month/day is identical in both — a
 * movable holiday (Easter Monday, Ascension...) will differ and gets silently dropped, same
 * simplification for every country including France (whose 3 movable holidays are dropped exactly
 * like the old hardcoded list already excluded them).
 *
 * Also filters on Nager.Date's own `global` flag, nationwide-only — confirmed live for Germany
 * that several returned holidays are region/state-specific ("Heilige Drei Könige",
 * "Mariä Himmelfahrt", "Weltkindertag"...), `global: false`. Creating those unqualified would
 * misrepresent a Land-only holiday as if the whole country observed it.
 *
 * Attaches each created/reused holiday to the right calendar(s) via `Calendar_Holiday`. "Right
 * calendar" is resolved by the caller (`front/wizard.php`), not this class: a Location's country
 * is known per wizard-tree *path* (`location_country_<path>`), while a calendar is only ever
 * assigned per *top-level* entity (shared, or one per MSP client) — `wizard.php` already has both
 * pieces (`$calendarMap`, keyed by entity ID) at the point it calls this class, so it resolves each
 * path down to its top-level ancestor's calendar ID and passes `$calendarIdByPath` alongside the
 * country names. A path with no resolvable calendar (calendars disabled entirely) still gets its
 * holidays created, just not linked to anything.
 *
 * The free-text country name typed on a Location has to be mapped to an ISO 3166-1 alpha-2 code
 * for the API — Nager.Date itself covers 204 countries (confirmed live via its own
 * `/api/v3/AvailableCountries`), but `COUNTRY_CODES` only maps the ones this plugin can actually
 * recognize by free-text name: the full European Union plus the UK/Switzerland/Norway/Iceland
 * (confirmed one by one against Nager.Date's list, on explicit user request for full multi-country
 * European coverage) and a handful of non-European countries a French-speaking admin is likely to
 * type (US/Canada/Maghreb). French and English names only, common misspellings/synonyms not
 * included. An unrecognized name is skipped, not guessed.
 */
class CountryHolidayBuilder
{
    /**
     * French/English country name (lowercased for matching) => ISO 3166-1 alpha-2 code.
     *
     * @var array<string, string>
     */
    private const COUNTRY_CODES = [
        'france' => 'FR',
        'allemagne' => 'DE', 'germany' => 'DE',
        'belgique' => 'BE', 'belgium' => 'BE',
        'suisse' => 'CH', 'switzerland' => 'CH',
        'luxembourg' => 'LU',
        'espagne' => 'ES', 'spain' => 'ES',
        'italie' => 'IT', 'italy' => 'IT',
        'portugal' => 'PT',
        'royaume-uni' => 'GB', 'united kingdom' => 'GB', 'uk' => 'GB', 'angleterre' => 'GB',
        'pays-bas' => 'NL', 'netherlands' => 'NL', 'hollande' => 'NL',
        'autriche' => 'AT', 'austria' => 'AT',
        'irlande' => 'IE', 'ireland' => 'IE',
        'danemark' => 'DK', 'denmark' => 'DK',
        'suede' => 'SE', 'suède' => 'SE', 'sweden' => 'SE',
        'norvege' => 'NO', 'norvège' => 'NO', 'norway' => 'NO',
        'finlande' => 'FI', 'finland' => 'FI',
        'pologne' => 'PL', 'poland' => 'PL',
        'republique tcheque' => 'CZ', 'république tchèque' => 'CZ', 'czechia' => 'CZ',
        'grece' => 'GR', 'grèce' => 'GR', 'greece' => 'GR',
        'roumanie' => 'RO', 'romania' => 'RO',
        'hongrie' => 'HU', 'hungary' => 'HU',
        'bulgarie' => 'BG', 'bulgaria' => 'BG',
        'croatie' => 'HR', 'croatia' => 'HR',
        'chypre' => 'CY', 'cyprus' => 'CY',
        'estonie' => 'EE', 'estonia' => 'EE',
        'lettonie' => 'LV', 'latvia' => 'LV',
        'lituanie' => 'LT', 'lithuania' => 'LT',
        'malte' => 'MT', 'malta' => 'MT',
        'slovaquie' => 'SK', 'slovakia' => 'SK',
        'slovenie' => 'SI', 'slovénie' => 'SI', 'slovenia' => 'SI',
        'islande' => 'IS', 'iceland' => 'IS',
        'etats-unis' => 'US', 'états-unis' => 'US', 'usa' => 'US', 'united states' => 'US',
        'canada' => 'CA',
        'maroc' => 'MA', 'morocco' => 'MA',
        'tunisie' => 'TN', 'tunisia' => 'TN',
        'algerie' => 'DZ', 'algérie' => 'DZ', 'algeria' => 'DZ',
    ];

    private const HOLIDAY_REFERENCE_YEAR = 2026;

    /**
     * Canonical French display name per ISO code, for the "(Pays)" suffix on a created holiday's
     * name — kept separate from `COUNTRY_CODES` (which maps every recognized synonym to a code)
     * so the label is always the one proper spelling regardless of which synonym the admin typed.
     *
     * @var array<string, string>
     */
    private const COUNTRY_LABELS = [
        'FR' => 'France',
        'DE' => 'Allemagne', 'BE' => 'Belgique', 'CH' => 'Suisse', 'LU' => 'Luxembourg',
        'ES' => 'Espagne', 'IT' => 'Italie', 'PT' => 'Portugal', 'GB' => 'Royaume-Uni',
        'NL' => 'Pays-Bas', 'AT' => 'Autriche', 'IE' => 'Irlande', 'DK' => 'Danemark',
        'SE' => 'Suède', 'NO' => 'Norvège', 'FI' => 'Finlande', 'PL' => 'Pologne',
        'CZ' => 'République tchèque', 'GR' => 'Grèce', 'RO' => 'Roumanie', 'HU' => 'Hongrie',
        'BG' => 'Bulgarie', 'HR' => 'Croatie', 'CY' => 'Chypre', 'EE' => 'Estonie',
        'LV' => 'Lettonie', 'LT' => 'Lituanie', 'MT' => 'Malte', 'SK' => 'Slovaquie',
        'SI' => 'Slovénie', 'IS' => 'Islande',
        'US' => 'États-Unis', 'CA' => 'Canada', 'MA' => 'Maroc', 'TN' => 'Tunisie', 'DZ' => 'Algérie',
    ];

    /**
     * @param array<string, string> $countryByPath Raw free-text country values typed on Location
     *        address fields, keyed by the same wizard-tree path as `location_country_<path>`.
     * @param array<string, int> $calendarIdByPath Calendar to attach each path's country holidays
     *        to, keyed by the same paths — resolved by the caller from `$calendarMap` (see class
     *        docblock). A path absent here (or whose country isn't recognized) still creates the
     *        holidays, just doesn't attach them to anything.
     *
     * @return int Number of foreign public holidays created/reused.
     */
    public function build(Config $config, array $countryByPath, array $calendarIdByPath = []): int
    {
        if (empty($config->fields['country_holidays_enabled'])) {
            return 0;
        }

        $calendarIdsByCode = [];
        foreach ($countryByPath as $path => $name) {
            $key = mb_strtolower(trim($name));
            if (!isset(self::COUNTRY_CODES[$key])) {
                continue;
            }
            $code = self::COUNTRY_CODES[$key];
            $calendarIdsByCode[$code] ??= [];
            if (isset($calendarIdByPath[$path])) {
                $calendarIdsByCode[$code][$calendarIdByPath[$path]] = true;
            }
        }

        $count = 0;
        foreach ($calendarIdsByCode as $code => $calendarIds) {
            $count += $this->buildForCountry($code, array_keys($calendarIds));
        }

        return $count;
    }

    /**
     * @param int[] $calendarIds Every calendar this country's holidays should be linked to (can be
     *        empty — holidays are still created, just left unattached).
     */
    private function buildForCountry(string $isoCode, array $calendarIds): int
    {
        $yearA = $this->fetchHolidays($isoCode, (int) date('Y'));
        $yearB = $this->fetchHolidays($isoCode, (int) date('Y') + 1);
        if ($yearA === null || $yearB === null) {
            return 0;
        }

        $yearBMonthDays = [];
        foreach ($yearB as $holiday) {
            $yearBMonthDays[substr($holiday['date'], 5, 5)] = true; // "MM-DD"
        }

        $count = 0;
        foreach ($yearA as $holiday) {
            if (($holiday['global'] ?? false) !== true) {
                // Regional/state-specific holiday (Nager.Date's own `global` flag) — confirmed live
                // for Germany: "Heilige Drei Könige"/"Mariä Himmelfahrt"/"Weltkindertag" etc. are
                // only observed in specific Länder, not nationwide. Creating them unqualified as if
                // they applied to the whole country would misrepresent them, the same class of
                // mistake this project has explicitly avoided elsewhere (e.g. not inventing a
                // manufacturer-dictionary variant with no real source).
                continue;
            }

            $monthDay = substr($holiday['date'], 5, 5);
            if (!isset($yearBMonthDays[$monthDay])) {
                continue; // Movable date (Easter-linked...) — same simplification as for France.
            }

            $holidayId = $this->getOrCreateHoliday($holiday['localName'], $isoCode, (int) substr($monthDay, 0, 2), (int) substr($monthDay, 3, 2));
            if ($holidayId > 0) {
                foreach ($calendarIds as $calendarId) {
                    $this->attachToCalendar($holidayId, $calendarId);
                }
            }
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{date: string, localName: string, global: bool}>|null Null on any fetch/parse failure.
     */
    private function fetchHolidays(string $isoCode, int $year): ?array
    {
        try {
            $client = \Toolbox::getGuzzleClient();
            $response = $client->request('GET', "https://date.nager.at/api/v3/PublicHolidays/{$year}/{$isoCode}", [
                'headers' => [
                    'User-Agent' => 'Configuration-glpi-auto-plugin (+https://github.com/parime/Configuration-glpi-auto)',
                    'Accept' => 'application/json',
                ],
                'timeout' => 5,
            ]);

            $data = json_decode((string) $response->getBody(), true);

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            \Glpi\Error\ErrorHandler::logCaughtException($e);

            return null;
        }
    }

    private function getOrCreateHoliday(string $localName, string $isoCode, int $month, int $day): int
    {
        // No "(France)" suffix, unlike every other country: this plugin's own default/home
        // market, and matching the exact plain names the older, now-removed
        // `CalendarBuilder::attachFrenchHolidays()` already used ("Jour de l'An", not
        // "Jour de l'an (France)") — an admin's own country doesn't need the tag a foreign one
        // benefits from for clarity.
        $name = $isoCode === 'FR' ? $localName : sprintf('%s (%s)', $localName, self::COUNTRY_LABELS[$isoCode] ?? $isoCode);
        $date = sprintf('%04d-%02d-%02d', self::HOLIDAY_REFERENCE_YEAR, $month, $day);

        $item = new Holiday();
        if ($item->getFromDBByCrit(['name' => $name, 'entities_id' => 0])) {
            return (int) $item->getID();
        }

        $id = $item->add([
            'name' => $name,
            'entities_id' => 0,
            'is_recursive' => 1,
            'begin_date' => $date,
            'end_date' => $date,
            'is_perpetual' => 1,
        ]);

        return (int) $id;
    }

    /**
     * Idempotent: skips a `Calendar_Holiday` link that's already there.
     */
    private function attachToCalendar(int $holidayId, int $calendarId): void
    {
        $link = new Calendar_Holiday();
        if (!$link->getFromDBByCrit(['calendars_id' => $calendarId, 'holidays_id' => $holidayId])) {
            $link->add(['calendars_id' => $calendarId, 'holidays_id' => $holidayId]);
        }
    }
}
