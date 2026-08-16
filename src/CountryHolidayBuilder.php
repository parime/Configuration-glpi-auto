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

use Holiday;

/**
 * Turns the countries typed into the wizard's own Location address fields (step "Lieux") into
 * real fixed-date `Holiday` rows — native GLPI, `is_perpetual = 1`, same mechanism
 * `CalendarBuilder::attachFrenchHolidays()` already uses for France. Explicitly scoped to "only
 * for countries where we actually have real data" (the user's own requirement): a country typed
 * in free text that this class doesn't recognize, or that the public holidays API doesn't cover,
 * is silently skipped rather than guessed.
 *
 * Data source: Nager.Date (`https://date.nager.at`), a free public holidays API — confirmed live
 * (real, current French holidays returned) and confirmed to cover ~100 countries
 * (`/api/v3/AvailableCountries`) before committing to this design, not assumed. Called server-side
 * only, at wizard-submission time — same `Toolbox::getGuzzleClient()` pattern already used by
 * `ajax/geocode.php` (honours GLPI's own configured outbound proxy), not a new client-facing
 * endpoint.
 *
 * GLPI's `Holiday.is_perpetual` only repeats a fixed month/day every year — there is no
 * "recompute from Easter" mechanism (the same limitation `CalendarBuilder` already documents for
 * why French Easter-linked holidays are excluded). Nager.Date returns concrete per-year dates, not
 * a recurrence rule, so this class determines which holidays are actually fixed-date empirically:
 * it fetches two consecutive years and keeps only the holidays whose month/day is identical in
 * both — a movable holiday (Easter Monday, Ascension...) will differ and gets silently dropped,
 * exactly the same "fixed dates only" simplification already applied to France, just derived from
 * real data instead of manual knowledge for each country.
 *
 * Also filters on Nager.Date's own `global` flag, nationwide-only — confirmed live for Germany
 * that several returned holidays are region/state-specific ("Heilige Drei Könige",
 * "Mariä Himmelfahrt", "Weltkindertag"...), `global: false`. Creating those unqualified would
 * misrepresent a Land-only holiday as if the whole country observed it.
 *
 * Deliberately does NOT attach the created holidays to any `Calendar` (unlike France's, which are
 * attached to the calendar the wizard itself just built) — there's no reliable way from this
 * plugin's data model to know *which* calendar (shared, or a specific MSP client's) a given
 * Location's country should apply to, since a calendar is built per client/site while a country is
 * per address. The holidays are created as real, immediately reusable native GLPI `Holiday`
 * entries (Configuration > Calendriers > Jours fériés) an admin can attach to the right calendar(s)
 * themselves.
 *
 * The free-text country name typed on a Location has to be mapped to an ISO 3166-1 alpha-2 code
 * for the API — `COUNTRY_CODES` covers the countries a French-speaking admin is most likely to
 * type (French and English names, common misspellings/synonyms not included), not all ~195
 * countries in the world. An unrecognized name is skipped, not guessed.
 */
class CountryHolidayBuilder
{
    /**
     * French/English country name (lowercased for matching) => ISO 3166-1 alpha-2 code.
     * France itself is deliberately absent — already covered by `CalendarBuilder`.
     *
     * @var array<string, string>
     */
    private const COUNTRY_CODES = [
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
        'DE' => 'Allemagne', 'BE' => 'Belgique', 'CH' => 'Suisse', 'LU' => 'Luxembourg',
        'ES' => 'Espagne', 'IT' => 'Italie', 'PT' => 'Portugal', 'GB' => 'Royaume-Uni',
        'NL' => 'Pays-Bas', 'AT' => 'Autriche', 'IE' => 'Irlande', 'DK' => 'Danemark',
        'SE' => 'Suède', 'NO' => 'Norvège', 'FI' => 'Finlande', 'PL' => 'Pologne',
        'CZ' => 'République tchèque', 'GR' => 'Grèce', 'RO' => 'Roumanie', 'HU' => 'Hongrie',
        'US' => 'États-Unis', 'CA' => 'Canada', 'MA' => 'Maroc', 'TN' => 'Tunisie', 'DZ' => 'Algérie',
    ];

    /**
     * @param string[] $countryNames Raw free-text country values typed on Location address fields.
     *
     * @return int Number of foreign public holidays created/reused.
     */
    public function build(Config $config, array $countryNames): int
    {
        if (empty($config->fields['country_holidays_enabled'])) {
            return 0;
        }

        $codes = [];
        foreach ($countryNames as $name) {
            $key = mb_strtolower(trim($name));
            if (isset(self::COUNTRY_CODES[$key])) {
                $codes[self::COUNTRY_CODES[$key]] = true;
            }
        }

        $count = 0;
        foreach (array_keys($codes) as $code) {
            $count += $this->buildForCountry($code);
        }

        return $count;
    }

    private function buildForCountry(string $isoCode): int
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

            $this->getOrCreateHoliday($holiday['localName'], $isoCode, (int) substr($monthDay, 0, 2), (int) substr($monthDay, 3, 2));
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

    private function getOrCreateHoliday(string $localName, string $isoCode, int $month, int $day): void
    {
        $name = sprintf('%s (%s)', $localName, self::COUNTRY_LABELS[$isoCode] ?? $isoCode);
        $date = sprintf('%04d-%02d-%02d', self::HOLIDAY_REFERENCE_YEAR, $month, $day);

        $item = new Holiday();
        if ($item->getFromDBByCrit(['name' => $name, 'entities_id' => 0])) {
            return;
        }

        $item->add([
            'name' => $name,
            'entities_id' => 0,
            'is_recursive' => 1,
            'begin_date' => $date,
            'end_date' => $date,
            'is_perpetual' => 1,
        ]);
    }
}
