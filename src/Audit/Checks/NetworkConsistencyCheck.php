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

namespace GlpiPlugin\Configurationglpiauto\Audit\Checks;

use GlpiPlugin\Configurationglpiauto\AddressPlan\NetworkConsistency;
use GlpiPlugin\Configurationglpiauto\Audit\AuditCheckInterface;
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\ParkAudit;
use IPNetwork;
use Vlan;

/**
 * Issue #344 : cohérence du réseau, à partir de l'inventaire (adresses IP remontées) et du plan
 * d'adressage déclaré (réseaux IP et VLAN, cf. issue #343) :
 * - même adresse IP portée par deux éléments différents ;
 * - réseaux qui se recouvrent sans être rattachés l'un à l'autre (même plage dans deux entités,
 *   souvent un partenaire relié par VPN) ;
 * - VLAN sans réseau IP associé ;
 * - adresses inventoriées hors de tout réseau déclaré.
 *
 * Chaque constat renvoie vers les éléments concernés. Rien n'est corrigé : l'équipe décide (règle
 * commune).
 */
final class NetworkConsistencyCheck implements AuditCheckInterface
{
    private const MAX_LINKS = 10;

    public function getKey(): string
    {
        return 'network_consistency';
    }

    public function getDomain(): string
    {
        return __('Réseau', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        $addresses = self::inventoriedAddresses();
        $networks = self::declaredNetworks();
        $findings = [];

        $duplicates = NetworkConsistency::duplicateAddresses(array_map(
            static fn (array $a): array => ['address' => $a['address'], 'owner' => $a['owner']],
            $addresses
        ));
        if ($duplicates !== []) {
            $urls = array_column($addresses, 'url', 'owner');
            $links = [];
            foreach ($duplicates as $owners) {
                foreach ($owners as $owner) {
                    $links[$owner] = $urls[$owner] ?? '';
                }
            }
            $findings[] = $this->finding(
                AuditFinding::SEVERITY_WARNING,
                sprintf(
                    _n('%d adresse IP portée par plusieurs éléments', '%d adresses IP portées par plusieurs éléments', count($duplicates), 'configurationglpiauto'),
                    count($duplicates)
                ),
                ParkAudit::summarize(array_map(
                    static fn (string $address, array $owners): string => $address . ' : ' . implode(', ', $owners),
                    array_keys($duplicates),
                    $duplicates
                )),
                __('Une même adresse sur deux équipements provoque des coupures difficiles à diagnostiquer. Vérifiez l\'attribution (réservation DHCP, adresse fixe recopiée, ancien équipement encore inventorié) ; une adresse virtuelle partagée volontairement (cluster, VRRP) peut être ignorée.', 'configurationglpiauto'),
                $links
            );
        }

        $byId = array_column($networks, null, 'id');
        $pairs = NetworkConsistency::overlappingNetworks(array_map(
            static fn (array $n): array => ['id' => $n['id'], 'cidr' => $n['cidr'], 'ancestors' => $n['ancestors']],
            $networks
        ));
        if ($pairs !== []) {
            $links = [];
            foreach ($pairs as [$a, $b]) {
                $links[$byId[$a]['label']] = $byId[$a]['url'];
                $links[$byId[$b]['label']] = $byId[$b]['url'];
            }
            $findings[] = $this->finding(
                AuditFinding::SEVERITY_WARNING,
                sprintf(
                    _n('%d paire de réseaux IP qui se chevauchent', '%d paires de réseaux IP qui se chevauchent', count($pairs), 'configurationglpiauto'),
                    count($pairs)
                ),
                ParkAudit::summarize(array_map(
                    static fn (array $pair): string => sprintf(
                        __('%1$s et %2$s', 'configurationglpiauto'),
                        $byId[$pair[0]]['label'],
                        $byId[$pair[1]]['label']
                    ),
                    $pairs
                )),
                __('Deux sites ou un partenaire relié par VPN qui utilisent la même plage ne peuvent pas communiquer correctement. Renumérotez l\'un des réseaux, ou mettez en place une traduction d\'adresses, puis corrigez le plan d\'adressage.', 'configurationglpiauto'),
                $links
            );
        }

        $orphans = self::vlansWithoutNetwork();
        if ($orphans !== []) {
            $findings[] = $this->finding(
                AuditFinding::SEVERITY_INFO,
                sprintf(
                    _n('%d VLAN sans réseau IP associé', '%d VLAN sans réseau IP associé', count($orphans), 'configurationglpiauto'),
                    count($orphans)
                ),
                ParkAudit::summarize(array_keys($orphans)),
                __('Associez chaque VLAN à son réseau IP (onglet VLAN du réseau, ou plan d\'adressage) pour que le schéma réseau reste complet.', 'configurationglpiauto'),
                $orphans
            );
        }

        if ($networks !== []) {
            $outside = NetworkConsistency::outsideAddresses(
                array_column($addresses, 'address'),
                array_column($networks, 'cidr')
            );
            if ($outside !== []) {
                $ownerByAddress = array_column($addresses, 'owner', 'address');
                $urls = array_column($addresses, 'url', 'owner');
                $links = [];
                foreach (array_slice($outside, 0, self::MAX_LINKS) as $address) {
                    $owner = $ownerByAddress[$address] ?? '';
                    $links[$owner] = $urls[$owner] ?? '';
                }
                $findings[] = $this->finding(
                    AuditFinding::SEVERITY_INFO,
                    sprintf(
                        _n('%d adresse inventoriée hors de tout réseau déclaré', '%d adresses inventoriées hors de tout réseau déclaré', count($outside), 'configurationglpiauto'),
                        count($outside)
                    ),
                    ParkAudit::summarize(array_map(
                        static fn (string $address): string => $address . ' (' . ($ownerByAddress[$address] ?? '?') . ')',
                        $outside
                    )),
                    __('Le plan d\'adressage est incomplet, ou l\'équipement est dans un réseau inattendu. Déclarez le réseau manquant (plan d\'adressage) ou vérifiez l\'équipement.', 'configurationglpiauto'),
                    $links
                );
            }
        }

        return $findings;
    }

    public function canFix(): bool
    {
        return false;
    }

    public function fix(): void
    {
    }

    /**
     * @param array<string, string> $links
     */
    private function finding(string $severity, string $title, string $description, string $recommendation, array $links): AuditFinding
    {
        return new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: $severity,
            title: $title,
            description: $description,
            recommendation: $recommendation,
            links: array_slice(array_filter($links), 0, self::MAX_LINKS, true)
        );
    }

    /**
     * Adresses remontées par l'inventaire, avec l'élément qui les porte.
     *
     * @return list<array{address: string, owner: string, url: string}>
     */
    private static function inventoriedAddresses(): array
    {
        global $DB;

        $rows = [];
        $idsByType = [];
        foreach (
            $DB->request([
                'SELECT' => ['name', 'mainitemtype', 'mainitems_id'],
                'FROM'   => 'glpi_ipaddresses',
                'WHERE'  => ['is_deleted' => 0, 'mainitems_id' => ['>', 0]],
            ]) as $row
        ) {
            $itemtype = (string) $row['mainitemtype'];
            if (!is_a($itemtype, \CommonDBTM::class, true)) {
                continue;
            }
            $rows[] = $row;
            $idsByType[$itemtype][(int) $row['mainitems_id']] = true;
        }

        $names = [];
        foreach ($idsByType as $itemtype => $ids) {
            foreach (
                $DB->request([
                    'SELECT' => ['id', 'name'],
                    'FROM'   => $itemtype::getTable(),
                    'WHERE'  => ['id' => array_keys($ids)],
                ]) as $item
            ) {
                $names[$itemtype][(int) $item['id']] = (string) $item['name'];
            }
        }

        $addresses = [];
        foreach ($rows as $row) {
            $itemtype = (string) $row['mainitemtype'];
            $id = (int) $row['mainitems_id'];
            if (!isset($names[$itemtype][$id])) {
                continue;
            }
            $addresses[] = [
                'address' => (string) $row['name'],
                'owner'   => $itemtype::getTypeName(1) . ' ' . ($names[$itemtype][$id] !== '' ? $names[$itemtype][$id] : '#' . $id),
                'url'     => $itemtype::getFormURLWithID($id),
            ];
        }

        return $addresses;
    }

    /**
     * @return list<array{id: int, cidr: string, ancestors: list<int>, label: string, url: string}>
     */
    private static function declaredNetworks(): array
    {
        global $DB;

        $networks = [];
        foreach (
            $DB->request([
                'SELECT' => ['id', 'name', 'entities_id', 'address', 'netmask', 'ancestors_cache'],
                'FROM'   => IPNetwork::getTable(),
            ]) as $row
        ) {
            $prefix = NetworkConsistency::prefixOf((string) $row['netmask']);
            if ($prefix === null || (string) $row['address'] === '') {
                continue;
            }
            $ancestors = json_decode((string) ($row['ancestors_cache'] ?? ''), true);
            $cidr = $row['address'] . '/' . $prefix;
            $networks[] = [
                'id'        => (int) $row['id'],
                'cidr'      => $cidr,
                'ancestors' => is_array($ancestors) ? array_map('intval', array_values($ancestors)) : [],
                'label'     => sprintf(
                    '%1$s %2$s (%3$s)',
                    $row['name'],
                    $cidr,
                    \Dropdown::getDropdownName('glpi_entities', (int) $row['entities_id'])
                ),
                'url'       => IPNetwork::getFormURLWithID((int) $row['id']),
            ];
        }

        return $networks;
    }

    /**
     * @return array<string, string> Libellé du VLAN => URL.
     */
    private static function vlansWithoutNetwork(): array
    {
        global $DB;

        $orphans = [];
        foreach (
            $DB->request([
                'SELECT'    => [Vlan::getTable() . '.id', Vlan::getTable() . '.name', Vlan::getTable() . '.tag'],
                'FROM'      => Vlan::getTable(),
                'LEFT JOIN' => [
                    'glpi_ipnetworks_vlans' => ['FKEY' => ['glpi_ipnetworks_vlans' => 'vlans_id', Vlan::getTable() => 'id']],
                ],
                'WHERE'     => ['glpi_ipnetworks_vlans.id' => null],
                'ORDER'     => Vlan::getTable() . '.tag',
            ]) as $row
        ) {
            $orphans[sprintf('%1$s (VLAN %2$d)', $row['name'], $row['tag'])] = Vlan::getFormURLWithID((int) $row['id']);
        }

        return $orphans;
    }
}
