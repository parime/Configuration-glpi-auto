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

use Infocom;
use Item_SoftwareVersion;
use Software;
use SoftwareLicense;
use SoftwareVersion;

/**
 * Issue #305 : logiciels en double déjà en base — même nom (au sens de la base : casse, accents,
 * espaces), même éditeur, même entité. Le blocage (#298) empêche d'en créer de nouveaux ; le
 * contrôle d'audit DuplicateSoftwareCheck fusionne ceux qui existent.
 *
 * GLPI déclare les versions et licences d'un logiciel « à gérer manuellement » (préfixe « _ » dans
 * inc/relation.constant.php) : le « Remplacer par » de la suppression ne les déplace pas, et la
 * purge les effacerait avec leurs installations. La fusion les reporte donc d'abord elle-même :
 * 1. une version du doublon qui existe aussi sous le logiciel gardé (même nom) : ses installations
 *    et licences passent sur la version gardée — une machine qui a déjà la version gardée perd
 *    seulement la ligne en double (contrainte d'unicité) — puis la version vide est supprimée ;
 * 2. les autres versions et les licences changent simplement de logiciel ;
 * 3. le doublon est supprimé avec `_replace_by` : documents, tickets, contrats... suivent.
 */
final class SoftwareMerger
{
    /**
     * @return list<array{keep: array{id: int, name: string}, duplicates: list<array{id: int, name: string}>, publisher: string, installations: int}>
     */
    public static function findGroups(): array
    {
        global $DB;

        $table = Software::getTable();
        $keys = $DB->request([
            'SELECT'  => ['name', 'manufacturers_id', 'entities_id'],
            'FROM'    => $table,
            'WHERE'   => ['is_deleted' => 0, 'is_template' => 0, 'NOT' => ['name' => '']],
            'GROUPBY' => ['name', 'manufacturers_id', 'entities_id'],
            'HAVING'  => [new \Glpi\DBAL\QueryExpression('COUNT(*) > 1')],
        ]);

        $groups = [];
        foreach ($keys as $key) {
            $members = [];
            $rows = $DB->request([
                'SELECT' => ['id', 'name'],
                'FROM'   => $table,
                'WHERE'  => [
                    'name'             => $key['name'],
                    'manufacturers_id' => $key['manufacturers_id'],
                    'entities_id'      => $key['entities_id'],
                    'is_deleted'       => 0,
                    'is_template'      => 0,
                ],
                'ORDER'  => 'id',
            ]);
            foreach ($rows as $row) {
                $members[] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
            }
            if (count($members) < 2) {
                continue;
            }
            $keep = array_shift($members);
            $groups[] = [
                'keep'          => $keep,
                'duplicates'    => $members,
                'publisher'     => (int) $key['manufacturers_id'] > 0 ? (string) \Dropdown::getDropdownName('glpi_manufacturers', (int) $key['manufacturers_id']) : '',
                'installations' => self::countInstallations(array_column($members, 'id')),
            ];
        }

        return $groups;
    }

    /**
     * @param list<int> $softwareIds
     */
    private static function countInstallations(array $softwareIds): int
    {
        global $DB;

        if ($softwareIds === []) {
            return 0;
        }

        return (int) $DB->request([
            'COUNT'      => 'c',
            'FROM'       => Item_SoftwareVersion::getTable(),
            'INNER JOIN' => [SoftwareVersion::getTable() => ['FKEY' => [Item_SoftwareVersion::getTable() => 'softwareversions_id', SoftwareVersion::getTable() => 'id']]],
            'WHERE'      => [SoftwareVersion::getTable() . '.softwares_id' => $softwareIds],
        ])->current()['c'];
    }

    /**
     * @return int nombre de logiciels en double fusionnés
     */
    public static function mergeAll(): int
    {
        $merged = 0;
        foreach (self::findGroups() as $group) {
            foreach ($group['duplicates'] as $duplicate) {
                if (self::merge($duplicate['id'], $group['keep']['id'])) {
                    $merged++;
                }
            }
        }

        return $merged;
    }

    public static function merge(int $duplicateId, int $keepId): bool
    {
        global $DB;

        $versions = SoftwareVersion::getTable();
        $installs = Item_SoftwareVersion::getTable();
        $licenses = SoftwareLicense::getTable();

        $keptVersions = [];
        foreach ($DB->request(['SELECT' => ['id', 'name'], 'FROM' => $versions, 'WHERE' => ['softwares_id' => $keepId]]) as $row) {
            $keptVersions[mb_strtolower((string) $row['name'])] = (int) $row['id'];
        }

        foreach (iterator_to_array($DB->request(['SELECT' => ['id', 'name'], 'FROM' => $versions, 'WHERE' => ['softwares_id' => $duplicateId]]), false) as $version) {
            $target = $keptVersions[mb_strtolower((string) $version['name'])] ?? null;
            if ($target === null) {
                $DB->update($versions, ['softwares_id' => $keepId], ['id' => (int) $version['id']]);
                continue;
            }
            // Même version des deux côtés : installations et licences passent sur la version gardée.
            foreach (iterator_to_array($DB->request(['SELECT' => ['id', 'itemtype', 'items_id'], 'FROM' => $installs, 'WHERE' => ['softwareversions_id' => (int) $version['id']]]), false) as $install) {
                $already = countElementsInTable($installs, ['itemtype' => $install['itemtype'], 'items_id' => (int) $install['items_id'], 'softwareversions_id' => $target]) > 0;
                if ($already) {
                    $DB->delete($installs, ['id' => (int) $install['id']]);
                } else {
                    $DB->update($installs, ['softwareversions_id' => $target], ['id' => (int) $install['id']]);
                }
            }
            $DB->update($licenses, ['softwareversions_id_buy' => $target], ['softwareversions_id_buy' => (int) $version['id']]);
            $DB->update($licenses, ['softwareversions_id_use' => $target], ['softwareversions_id_use' => (int) $version['id']]);
            $DB->delete($versions, ['id' => (int) $version['id']]);
        }
        $DB->update($licenses, ['softwares_id' => $keepId], ['softwares_id' => $duplicateId]);

        // Une seule fiche Gestion par élément : celle du doublon cède la place.
        if (countElementsInTable(Infocom::getTable(), ['itemtype' => Software::class, 'items_id' => $keepId]) > 0) {
            $DB->delete(Infocom::getTable(), ['itemtype' => Software::class, 'items_id' => $duplicateId]);
        }

        $software = new Software();
        if (!$software->getFromDB($duplicateId)) {
            return false;
        }

        return (bool) $software->delete(['id' => $duplicateId, '_replace_by' => $keepId], true);
    }
}
