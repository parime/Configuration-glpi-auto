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

namespace GlpiPlugin\Configurationglpiauto\AddressPlan;

/**
 * Issue #344 : règles pures des contrôles de cohérence du réseau (sans base ni GLPI) :
 * adresses en double, réseaux qui se chevauchent, adresses hors de tout réseau déclaré.
 */
final class NetworkConsistency
{
    /** Adresses jamais signalées : boucle locale, non attribuée, lien local. */
    private const IGNORED_RANGES = ['127.0.0.0/8', '0.0.0.0/8', '169.254.0.0/16', '::1/128', '::/128', 'fe80::/10'];

    public static function isIgnorable(string $address): bool
    {
        $address = trim($address);
        if ($address === '' || @inet_pton($address) === false) {
            return true;
        }
        foreach (self::IGNORED_RANGES as $range) {
            if (AddressPlanParser::contains($range, $address)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Longueur de préfixe d'un masque textuel (« 255.255.255.0 » => 24), null s'il n'est pas
     * contigu.
     */
    public static function prefixOf(string $netmask): ?int
    {
        $bytes = @inet_pton(trim($netmask));
        if ($bytes === false) {
            return null;
        }
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return preg_match('/^(1*)0*$/', $bits, $m) === 1 && $m[1] !== '' ? strlen($m[1]) : null;
    }

    /**
     * Adresses portées par plusieurs éléments différents.
     *
     * @param list<array{address: string, owner: string}> $addresses
     * @return array<string, list<string>> Adresse => éléments qui la portent.
     */
    public static function duplicateAddresses(array $addresses): array
    {
        $owners = [];
        foreach ($addresses as $entry) {
            if (self::isIgnorable($entry['address'])) {
                continue;
            }
            $key = (string) inet_ntop((string) inet_pton(trim($entry['address'])));
            $owners[$key][$entry['owner']] = true;
        }
        $duplicates = [];
        foreach ($owners as $address => $set) {
            if (count($set) > 1) {
                $duplicates[$address] = array_keys($set);
            }
        }
        ksort($duplicates, SORT_NATURAL);

        return $duplicates;
    }

    /**
     * Réseaux qui se recouvrent sans être rattachés l'un à l'autre dans l'arborescence des réseaux
     * de GLPI : même plage déclarée dans deux entités, ou recouvrement entre deux sites.
     *
     * @param list<array{id: int, cidr: string, ancestors: list<int>}> $networks
     * @return list<array{0: int, 1: int}>
     */
    public static function overlappingNetworks(array $networks): array
    {
        $pairs = [];
        $count = count($networks);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $networks[$i];
                $b = $networks[$j];
                if (
                    in_array($a['id'], $b['ancestors'], true) || in_array($b['id'], $a['ancestors'], true)
                    || !AddressPlanParser::overlaps($a['cidr'], $b['cidr'])
                ) {
                    continue;
                }
                $pairs[] = [$a['id'], $b['id']];
            }
        }

        return $pairs;
    }

    /**
     * Adresses qui n'appartiennent à aucun réseau déclaré de leur version d'IP. Une version sans
     * aucun réseau déclaré (IPv6 jamais documentée, par exemple) n'est pas contrôlée.
     *
     * @param list<string> $addresses
     * @param list<string> $cidrs
     * @return list<string>
     */
    public static function outsideAddresses(array $addresses, array $cidrs): array
    {
        $versions = [];
        foreach ($cidrs as $cidr) {
            $parsed = AddressPlanParser::parseCidr($cidr);
            if ($parsed !== null) {
                $versions[$parsed['version']] = true;
            }
        }
        $outside = [];
        foreach (array_unique($addresses) as $address) {
            if (self::isIgnorable($address) || !isset($versions[str_contains($address, ':') ? 6 : 4])) {
                continue;
            }
            $inside = false;
            foreach ($cidrs as $cidr) {
                if (AddressPlanParser::contains($cidr, $address)) {
                    $inside = true;
                    break;
                }
            }
            if (!$inside) {
                $outside[] = $address;
            }
        }
        sort($outside, SORT_NATURAL);

        return $outside;
    }
}
