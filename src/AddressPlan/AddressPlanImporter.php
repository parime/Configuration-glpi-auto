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

use IPAddress;
use IPNetmask;
use IPNetwork;
use IPNetwork_Vlan;
use Location;
use Session;
use Vlan;

/**
 * Issue #343 : création du plan d'adressage avec les objets natifs de GLPI, `Vlan`
 * (Configuration > Intitulés > VLAN) et `IPNetwork` (réseau IP), reliés entre eux et rattachés à
 * l'entité du site. Rien d'autre n'est créé ni modifié.
 *
 * Idempotent : un VLAN existe déjà si son numéro est connu dans l'entité du site, un réseau s'il
 * a la même adresse et le même masque dans cette entité (même recherche que GLPI, qui refuse les
 * doublons). Relancer l'import met à jour le nom au lieu de dupliquer.
 */
final class AddressPlanImporter
{
    public const ACTION_CREATE = 'create';

    public const ACTION_UPDATE = 'update';

    public const ACTION_SAME = 'same';

    /**
     * Ajoute à chaque ligne le site résolu et l'action prévue pour le VLAN et pour le réseau.
     *
     * @param list<array{line: int, site: string, vlan: ?int, name: string, network: string,
     *     gateway: string, role: string, errors: list<string>}> $rows
     * @return list<array<string, mixed>>
     */
    public static function preview(array $rows): array
    {
        $sites = [];
        foreach ($rows as $index => $row) {
            $key = mb_strtolower($row['site']);
            if ($row['site'] !== '' && !array_key_exists($key, $sites)) {
                $sites[$key] = self::resolveSite($row['site']);
            }
            $site = $sites[$key] ?? null;
            if ($row['site'] !== '' && $site === null) {
                $row['errors'][] = 'site_unknown';
            }
            $row['entities_id'] = $site['entities_id'] ?? null;
            $row['site_label'] = $site['label'] ?? $row['site'];
            $row['vlan_action'] = null;
            $row['network_action'] = null;
            if ($site !== null && $row['errors'] === []) {
                $vlan = $row['vlan'] !== null ? self::findVlan($site['entities_id'], $row['vlan']) : null;
                $row['vlan_action'] = $row['vlan'] === null ? null : self::action($vlan, $row['name']);
                $network = $row['network'] !== '' ? self::findNetwork($site['entities_id'], $row['network']) : null;
                $row['network_action'] = $row['network'] === '' ? null : self::action($network, $row['name']);
            }
            $rows[$index] = $row;
        }

        return $rows;
    }

    /**
     * Crée ou met à jour les VLAN et réseaux des lignes valides, et les relie.
     *
     * @param list<array<string, mixed>> $rows Lignes issues de preview().
     * @return array{vlans_created: int, vlans_updated: int, networks_created: int,
     *     networks_updated: int, errors: list<string>}
     */
    public static function import(array $rows): array
    {
        $result = ['vlans_created' => 0, 'vlans_updated' => 0, 'networks_created' => 0, 'networks_updated' => 0, 'errors' => []];
        foreach ($rows as $row) {
            if ($row['errors'] !== [] || $row['entities_id'] === null) {
                continue;
            }
            $entitiesId = (int) $row['entities_id'];
            $comment = trim(sprintf(
                __('Plan d\'adressage — site : %1$s, rôle : %2$s', 'configurationglpiauto'),
                $row['site_label'],
                $row['role'] !== '' ? (self::roleNames()[$row['role']] ?? $row['role']) : '-'
            ));

            $vlansId = 0;
            if ($row['vlan'] !== null) {
                $vlan = new Vlan();
                $existing = self::findVlan($entitiesId, (int) $row['vlan']);
                if ($existing === null) {
                    $vlansId = (int) $vlan->add([
                        'name'        => $row['name'],
                        'tag'         => (int) $row['vlan'],
                        'entities_id' => $entitiesId,
                        'comment'     => $comment,
                    ]);
                    $result['vlans_created'] += $vlansId > 0 ? 1 : 0;
                } else {
                    $vlansId = (int) $existing['id'];
                    if ($existing['name'] !== $row['name']) {
                        $vlan->update(['id' => $vlansId, 'name' => $row['name']]);
                        $result['vlans_updated']++;
                    }
                }
            }

            if ($row['network'] === '') {
                continue;
            }
            $network = new IPNetwork();
            $existing = self::findNetwork($entitiesId, (string) $row['network']);
            if ($existing === null) {
                $networksId = (int) $network->add([
                    'name'        => $row['name'],
                    'network'     => $row['network'],
                    'gateway'     => $row['gateway'],
                    'addressable' => 1,
                    'entities_id' => $entitiesId,
                    'comment'     => $comment,
                ]);
                if ($networksId <= 0) {
                    $result['errors'][] = sprintf(__('Ligne %1$d : GLPI a refusé le réseau %2$s.', 'configurationglpiauto'), $row['line'], $row['network']);
                    continue;
                }
                $result['networks_created']++;
            } else {
                $networksId = (int) $existing['id'];
                if ($existing['name'] !== $row['name']) {
                    $network->update(['id' => $networksId, 'name' => $row['name']]);
                    $result['networks_updated']++;
                }
            }

            $link = new IPNetwork_Vlan();
            if ($vlansId > 0 && !$link->getFromDBByCrit(['ipnetworks_id' => $networksId, 'vlans_id' => $vlansId])) {
                $link->add(['ipnetworks_id' => $networksId, 'vlans_id' => $vlansId]);
            }
        }

        return $result;
    }

    /**
     * Lieu GLPI correspondant au site : nom complet (« Siège > Bâtiment A ») ou nom court, s'il
     * est unique parmi les entités actives.
     *
     * @return array{locations_id: int, entities_id: int, label: string}|null
     */
    public static function resolveSite(string $site): ?array
    {
        global $DB;

        $site = trim($site);
        foreach (['completename', 'name'] as $field) {
            $rows = iterator_to_array($DB->request([
                'SELECT' => ['id', 'entities_id', 'completename'],
                'FROM'   => Location::getTable(),
                'WHERE'  => [$field => $site] + getEntitiesRestrictCriteria(Location::getTable(), '', '', true),
                'LIMIT'  => 2,
            ]), false);
            if (count($rows) === 1) {
                return [
                    'locations_id' => (int) $rows[0]['id'],
                    'entities_id'  => (int) $rows[0]['entities_id'],
                    'label'        => (string) $rows[0]['completename'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private static function findVlan(int $entitiesId, int $tag): ?array
    {
        global $DB;

        $row = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => Vlan::getTable(),
            'WHERE'  => ['entities_id' => $entitiesId, 'tag' => $tag],
            'LIMIT'  => 1,
        ])->current();

        return $row !== null ? ['id' => (int) $row['id'], 'name' => (string) $row['name']] : null;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private static function findNetwork(int $entitiesId, string $cidr): ?array
    {
        $parsed = AddressPlanParser::parseCidr($cidr);
        if ($parsed === null) {
            return null;
        }
        $ids = IPNetwork::searchNetworks('equals', [
            'address' => new IPAddress($parsed['address']),
            'netmask' => new IPNetmask((string) $parsed['prefix'], $parsed['version']),
        ], $entitiesId, false);
        foreach ((array) $ids as $id) {
            $network = new IPNetwork();
            if ($network->getFromDB((int) $id) && (int) $network->fields['entities_id'] === $entitiesId) {
                return ['id' => (int) $id, 'name' => (string) $network->fields['name']];
            }
        }

        return null;
    }

    /**
     * @param array{id: int, name: string}|null $existing
     */
    private static function action(?array $existing, string $name): string
    {
        if ($existing === null) {
            return self::ACTION_CREATE;
        }

        return $existing['name'] === $name ? self::ACTION_SAME : self::ACTION_UPDATE;
    }

    public static function canImport(): bool
    {
        return IPNetwork::canCreate() && Vlan::canCreate() && Session::haveRight(IPNetwork::$rightname, UPDATE);
    }

    /**
     * Nom de chaque rôle du modèle de site, dans la langue de la personne connectée.
     *
     * @return array<string, string>
     */
    public static function roleNames(): array
    {
        return [
            'workstations' => __('Postes de travail', 'configurationglpiauto'),
            'servers'      => __('Serveurs', 'configurationglpiauto'),
            'voice'        => __('Téléphonie (voix)', 'configurationglpiauto'),
            'printers'     => __('Imprimantes et équipements', 'configurationglpiauto'),
            'admin'        => __('Administration', 'configurationglpiauto'),
            'guests'       => __('Invités', 'configurationglpiauto'),
            'dmz'          => __('DMZ', 'configurationglpiauto'),
        ];
    }

    /**
     * Lieux proposés pour le modèle de site (nom complet), dans les entités actives.
     *
     * @return list<string>
     */
    public static function siteNames(): array
    {
        global $DB;

        $names = [];
        foreach (
            $DB->request([
                'SELECT' => ['completename'],
                'FROM'   => Location::getTable(),
                'WHERE'  => getEntitiesRestrictCriteria(Location::getTable(), '', '', true),
                'ORDER'  => 'completename',
                'LIMIT'  => 500,
            ]) as $row
        ) {
            $names[] = (string) $row['completename'];
        }

        return $names;
    }

    /**
     * Ajoute à chaque ligne ses messages d'erreur lisibles.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function withMessages(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $rows[$index]['messages'] = array_map([self::class, 'errorMessage'], $row['errors']);
        }

        return $rows;
    }

    public static function errorMessage(string $code): string
    {
        [$key, $detail] = array_pad(explode(':', $code, 2), 2, '');

        return match ($key) {
            'site_missing'            => __('Site manquant.', 'configurationglpiauto'),
            'site_unknown'            => __('Site inconnu : créez d\'abord ce lieu dans GLPI (étape « Lieux » de l\'assistant).', 'configurationglpiauto'),
            'vlan_range'              => __('Numéro de VLAN hors de la plage 1 à 4094.', 'configurationglpiauto'),
            'nothing_to_create'       => __('Ni VLAN ni réseau sur cette ligne.', 'configurationglpiauto'),
            'cidr_invalid'            => __('Réseau illisible : notation attendue 192.0.2.0/24.', 'configurationglpiauto'),
            'cidr_not_network'        => sprintf(__('Ce n\'est pas l\'adresse du réseau : vouliez-vous %s ?', 'configurationglpiauto'), $detail),
            'gateway_outside'         => __('La passerelle n\'est pas dans ce réseau.', 'configurationglpiauto'),
            'gateway_without_network' => __('Passerelle indiquée sans réseau.', 'configurationglpiauto'),
            'overlap'                 => sprintf(__('Chevauche le réseau de la ligne %s.', 'configurationglpiauto'), $detail),
            default                   => $code,
        };
    }
}
