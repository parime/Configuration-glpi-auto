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
 * Issue #343 : lecture d'un plan d'adressage collé depuis un tableur ou importé en CSV
 * (site, VLAN, nom, réseau, passerelle, rôle), sans base ni GLPI.
 *
 * Chaque ligne est validée une par une : VLAN entre 1 et 4094, réseau en notation CIDR (IPv4 ou
 * IPv6) qui soit bien l'adresse du réseau, passerelle dans le réseau, aucun chevauchement entre
 * deux lignes du même tableau. Rien n'est deviné : une ligne en erreur n'est pas créée.
 */
final class AddressPlanParser
{
    public const MAX_ROWS = 2000;

    /**
     * Rôles proposés par le modèle de site, numéro de VLAN => rôle : les invités et
     * l'administration ont chacun leur réseau, isolé des postes.
     */
    public const TEMPLATE_ROLES = [
        10 => 'workstations',
        20 => 'servers',
        30 => 'voice',
        40 => 'printers',
        50 => 'admin',
        60 => 'guests',
        70 => 'dmz',
    ];

    private const COLUMNS = [
        'site'    => ['site', 'lieu', 'location', 'batiment', 'bâtiment', 'agence'],
        'vlan'    => ['vlan', 'vlan id', 'id vlan', 'numero vlan', 'numéro vlan', 'tag'],
        'name'    => ['nom', 'name', 'libelle', 'libellé', 'nom du vlan', 'description'],
        'network' => ['reseau', 'réseau', 'network', 'cidr', 'subnet', 'sous-reseau', 'sous-réseau', 'plage'],
        'gateway' => ['passerelle', 'gateway', 'gw'],
        'role'    => ['role', 'rôle', 'usage', 'type'],
    ];

    private const POSITIONAL = ['site', 'vlan', 'name', 'network', 'gateway', 'role'];

    /**
     * @return array{rows: list<array{
     *     line: int, site: string, vlan: ?int, name: string, network: string, gateway: string,
     *     role: string, errors: list<string>
     * }>, truncated: bool}
     */
    public static function parse(string $text): array
    {
        $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $delimiter = self::detectDelimiter($lines);
        $map = null;
        $rows = [];
        $truncated = false;
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = array_map('trim', str_getcsv($line, $delimiter, '"', ''));
            if ($map === null) {
                $map = self::headerMap($cells);
                if ($map !== null) {
                    continue;
                }
                $map = array_flip(array_slice(self::POSITIONAL, 0, count($cells)));
            }
            if (count($rows) >= self::MAX_ROWS) {
                $truncated = true;
                break;
            }
            $rows[] = self::row($index + 1, $cells, $map);
        }

        return ['rows' => self::markOverlaps($rows), 'truncated' => $truncated];
    }

    /**
     * Plan type pour un nouveau site, à partir d'un réseau IPv4 en /16 : un /24 par rôle, dont le
     * troisième octet reprend le numéro de VLAN, passerelle en .1. À relire et modifier avant création.
     */
    /**
     * @param array<string, string> $names Nom affiché de chaque rôle (traduit par l'appelant).
     */
    public static function template(string $site, string $base, array $names = []): ?string
    {
        $parsed = self::parseCidr($base);
        if ($parsed === null || $parsed['version'] !== 4 || $parsed['prefix'] !== 16 || !$parsed['aligned']) {
            return null;
        }
        [$a, $b] = explode('.', $parsed['address']);
        $site = str_replace(["\t", "\n", "\r"], ' ', trim($site));
        $lines = ["site\tvlan\tnom\treseau\tpasserelle\trole"];
        foreach (self::TEMPLATE_ROLES as $vlan => $role) {
            $name = str_replace(["\t", "\n", "\r"], ' ', $names[$role] ?? $role);
            $lines[] = implode("\t", [$site, $vlan, $name, "$a.$b.$vlan.0/24", "$a.$b.$vlan.1", $role]);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{version: int, address: string, prefix: int, aligned: bool, network: string,
     *     bytes: string, mask: string}|null
     */
    public static function parseCidr(string $cidr): ?array
    {
        if (preg_match('#^\s*([0-9A-Fa-f:.]+)\s*/\s*(\d{1,3})\s*$#', $cidr, $m) !== 1) {
            return null;
        }
        $bytes = @inet_pton($m[1]);
        if ($bytes === false) {
            return null;
        }
        $bits = strlen($bytes) * 8;
        $prefix = (int) $m[2];
        if ($prefix < 1 || $prefix > $bits) {
            return null;
        }
        $mask = self::mask($prefix, strlen($bytes));
        $network = $bytes & $mask;

        return [
            'version' => $bits === 32 ? 4 : 6,
            'address' => (string) inet_ntop($bytes),
            'prefix'  => $prefix,
            'aligned' => $network === $bytes,
            'network' => inet_ntop($network) . '/' . $prefix,
            'bytes'   => $network,
            'mask'    => $mask,
        ];
    }

    public static function contains(string $cidr, string $address): bool
    {
        $network = self::parseCidr($cidr);
        $bytes = @inet_pton(trim($address));
        if ($network === null || $bytes === false || strlen($bytes) !== strlen($network['bytes'])) {
            return false;
        }

        return ($bytes & $network['mask']) === $network['bytes'];
    }

    public static function overlaps(string $first, string $second): bool
    {
        $a = self::parseCidr($first);
        $b = self::parseCidr($second);
        if ($a === null || $b === null || $a['version'] !== $b['version']) {
            return false;
        }
        $mask = $a['prefix'] <= $b['prefix'] ? $a['mask'] : $b['mask'];

        return ($a['bytes'] & $mask) === ($b['bytes'] & $mask);
    }

    /**
     * @param list<string> $cells
     * @param array<string, int> $map
     * @return array{line: int, site: string, vlan: ?int, name: string, network: string, gateway: string,
     *     role: string, errors: list<string>}
     */
    private static function row(int $line, array $cells, array $map): array
    {
        $get = static fn (string $key): string => isset($map[$key]) ? (string) ($cells[$map[$key]] ?? '') : '';
        $errors = [];

        $site = $get('site');
        if ($site === '') {
            $errors[] = 'site_missing';
        }
        $vlanText = $get('vlan');
        $vlan = preg_match('/^\d{1,4}$/', $vlanText) === 1 ? (int) $vlanText : null;
        if ($vlanText !== '' && ($vlan === null || $vlan < 1 || $vlan > 4094)) {
            $errors[] = 'vlan_range';
            $vlan = null;
        }

        $network = '';
        $cidr = $get('network');
        $parsed = self::parseCidr($cidr);
        if ($cidr === '' && $vlan === null) {
            $errors[] = 'nothing_to_create';
        } elseif ($cidr !== '' && $parsed === null) {
            $errors[] = 'cidr_invalid';
        } elseif ($parsed !== null && !$parsed['aligned']) {
            $errors[] = 'cidr_not_network:' . $parsed['network'];
        } elseif ($parsed !== null) {
            $network = $parsed['network'];
        }

        $gateway = $get('gateway');
        if ($gateway !== '' && ($network === '' || !self::contains($network, $gateway))) {
            $errors[] = $network === '' && $cidr === '' ? 'gateway_without_network' : 'gateway_outside';
        }

        $name = $get('name');
        if ($name === '') {
            $name = $vlan !== null ? 'VLAN ' . $vlan : $network;
        }

        return [
            'line'    => $line,
            'site'    => $site,
            'vlan'    => $vlan,
            'name'    => $name,
            'network' => $network,
            'gateway' => $gateway,
            'role'    => $get('role'),
            'errors'  => $errors,
        ];
    }

    /**
     * @param list<array{line: int, site: string, vlan: ?int, name: string, network: string,
     *     gateway: string, role: string, errors: list<string>}> $rows
     * @return list<array{line: int, site: string, vlan: ?int, name: string, network: string,
     *     gateway: string, role: string, errors: list<string>}>
     */
    private static function markOverlaps(array $rows): array
    {
        foreach ($rows as $i => $row) {
            if ($row['network'] === '') {
                continue;
            }
            foreach ($rows as $j => $other) {
                if ($j <= $i || $other['network'] === '' || !self::overlaps($row['network'], $other['network'])) {
                    continue;
                }
                $rows[$j]['errors'][] = 'overlap:' . $row['line'];
            }
        }

        return $rows;
    }

    /**
     * @param list<string> $cells
     * @return array<string, int>|null
     */
    private static function headerMap(array $cells): ?array
    {
        $map = [];
        foreach ($cells as $index => $cell) {
            $label = mb_strtolower(trim($cell));
            foreach (self::COLUMNS as $key => $aliases) {
                if (!isset($map[$key]) && in_array($label, $aliases, true)) {
                    $map[$key] = $index;
                }
            }
        }

        return isset($map['network']) || isset($map['vlan']) ? $map : null;
    }

    /**
     * @param list<string> $lines
     */
    private static function detectDelimiter(array $lines): string
    {
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $counts = ["\t" => substr_count($line, "\t"), ';' => substr_count($line, ';'), ',' => substr_count($line, ',')];
            arsort($counts);

            return (string) array_key_first($counts);
        }

        return "\t";
    }

    private static function mask(int $prefix, int $length): string
    {
        $mask = '';
        for ($i = 0; $i < $length; $i++) {
            $bits = max(0, min(8, $prefix - $i * 8));
            $mask .= chr($bits === 0 ? 0 : (0xFF << (8 - $bits)) & 0xFF);
        }

        return $mask;
    }
}
