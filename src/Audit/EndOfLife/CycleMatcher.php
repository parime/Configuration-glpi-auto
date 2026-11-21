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

namespace GlpiPlugin\Configurationglpiauto\Audit\EndOfLife;

/**
 * Issue #467 : cycle endoflife.date d'un système ou d'un modèle, et son état de support.
 * Logique pure (sans base).
 *
 * @phpstan-type Cycle array{cycle: string, codename: string, eol: string, latest: string}
 */
final class CycleMatcher
{
    public const STATUS_EOL = 'eol';
    public const STATUS_SOON = 'soon';
    public const STATUS_SUPPORTED = 'supported';
    public const STATUS_UNKNOWN = 'unknown';

    /**
     * Cycle d'un élément : cycle imposé par la correspondance, sinon d'après la version
     * (« 22.04 », « 12.5 », « bookworm ») puis d'après le numéro du nom (« Windows 10 »).
     *
     * @param list<Cycle> $cycles
     * @return Cycle|null
     */
    public static function match(string $name, string $version, array $cycles, string $forced = ''): ?array
    {
        if ($forced !== '') {
            return self::exact(strtolower($forced), $cycles);
        }
        $version = strtolower(trim($version));
        $nameNumber = self::nameNumber($name);
        if ($nameNumber !== '' && $version !== '') {
            // Windows : « 10 » + « 22H2 » donne le cycle « 10-22h2 » (ou une variante d'édition).
            $composite = $nameNumber . '-' . self::firstToken($version);
            $found = self::exact($composite, $cycles) ?? self::prefixed($composite . '-', $cycles);
            if ($found !== null) {
                return $found;
            }
        }
        if ($version !== '') {
            $found = self::exact($version, $cycles) ?? self::exact(self::firstToken($version), $cycles)
                ?? self::numeric($version, $cycles) ?? self::codename($version, $cycles);
            if ($found !== null) {
                return $found;
            }
        }

        return $nameNumber === '' ? null : self::exact($nameNumber, $cycles);
    }

    /** Numéro isolé du nom (« Windows 11 Pro » : « 11 », « iPhone 12 » : « 12 »). */
    public static function nameNumber(string $name): string
    {
        $name = (string) preg_replace('/\([^)]*\)/', ' ', $name);

        return preg_match('/(?<![\w.-])(\d{1,4})(?![\w.-])/u', $name, $m) === 1 ? $m[1] : '';
    }

    /**
     * @param Cycle|null $cycle
     * @return array{status: string, days: ?int}
     */
    public static function status(?array $cycle, string $today, int $warningDays): array
    {
        if ($cycle === null) {
            return ['status' => self::STATUS_UNKNOWN, 'days' => null];
        }
        if ($cycle['eol'] === EndOfLifeClient::EOL_WITHOUT_DATE) {
            return ['status' => self::STATUS_EOL, 'days' => null];
        }
        if ($cycle['eol'] === '') {
            return ['status' => self::STATUS_SUPPORTED, 'days' => null];
        }
        $days = (int) round(((int) strtotime($cycle['eol'] . ' 00:00:00 UTC') - (int) strtotime(substr($today, 0, 10) . ' 00:00:00 UTC')) / 86400);
        $status = match (true) {
            $days <= 0            => self::STATUS_EOL,
            $days <= $warningDays => self::STATUS_SOON,
            default               => self::STATUS_SUPPORTED,
        };

        return ['status' => $status, 'days' => $days];
    }

    /**
     * @param list<Cycle> $cycles
     * @return Cycle|null
     */
    private static function exact(string $candidate, array $cycles): ?array
    {
        foreach ($cycles as $cycle) {
            if (strtolower($cycle['cycle']) === $candidate) {
                return $cycle;
            }
        }

        return null;
    }

    /**
     * Variantes d'édition (« 11-23h2-e », « 11-23h2-w ») : la fin de support la plus proche.
     *
     * @param list<Cycle> $cycles
     * @return Cycle|null
     */
    private static function prefixed(string $prefix, array $cycles): ?array
    {
        $rank = static fn (string $eol): string => match (true) {
            $eol === EndOfLifeClient::EOL_WITHOUT_DATE => '0',
            $eol === ''                               => '9',
            default                                   => '1' . $eol,
        };
        $best = null;
        foreach ($cycles as $cycle) {
            if (str_starts_with(strtolower($cycle['cycle']), $prefix) && ($best === null || $rank($cycle['eol']) < $rank($best['eol']))) {
                $best = $cycle;
            }
        }

        return $best;
    }

    /**
     * Plus long cycle numérique en tête de la version (« 12.5 » : « 12 », « 22.04.4 » : « 22.04 »).
     *
     * @param list<Cycle> $cycles
     * @return Cycle|null
     */
    private static function numeric(string $version, array $cycles): ?array
    {
        if (preg_match('/^v?(\d+(?:\.\d+)*)/', $version, $m) !== 1) {
            return null;
        }
        $numbers = array_map('intval', explode('.', $m[1]));
        $best = null;
        $bestLength = 0;
        foreach ($cycles as $cycle) {
            if (preg_match('/^\d+(?:\.\d+)*$/', trim($cycle['cycle'])) !== 1) {
                continue;
            }
            $cycleNumbers = array_map('intval', explode('.', trim($cycle['cycle'])));
            $length = count($cycleNumbers);
            if ($length <= count($numbers) && array_slice($numbers, 0, $length) === $cycleNumbers && $length > $bestLength) {
                $best = $cycle;
                $bestLength = $length;
            }
        }

        return $best;
    }

    /**
     * Nom de code (« bookworm », « jammy ») : le premier mot du nom de code suffit.
     *
     * @param list<Cycle> $cycles
     * @return Cycle|null
     */
    private static function codename(string $version, array $cycles): ?array
    {
        $words = array_diff(preg_split('/[^a-z]+/', $version, -1, PREG_SPLIT_NO_EMPTY) ?: [], ['lts', 'latest', 'stable']);
        foreach ($cycles as $cycle) {
            $first = (string) (preg_split('/[^a-z]+/', strtolower($cycle['codename']), -1, PREG_SPLIT_NO_EMPTY)[0] ?? '');
            if ($first !== '' && in_array($first, $words, true)) {
                return $cycle;
            }
        }

        return null;
    }

    private static function firstToken(string $version): string
    {
        return (string) (preg_split('/[\s(]+/', trim($version), -1, PREG_SPLIT_NO_EMPTY)[0] ?? '');
    }
}
