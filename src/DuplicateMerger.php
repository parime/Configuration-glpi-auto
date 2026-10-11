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

use CommonDBTM;
use Glpi\DBAL\QueryExpression;
use Manufacturer;
use Normalizer;
use RuleDictionnaryManufacturerCollection;
use Software;

/**
 * Issue #299 : doublons déjà présents en base (créés avant le blocage #298 ou avant le
 * dictionnaire #297), détectés par le mode Audit et fusionnés en un clic.
 *
 * - Fabricants / éditeurs : même marque à la casse, aux accents, à la ponctuation et aux mentions
 *   légales près (« Acer », « Acer, Inc », « ACER Inc. »), ou noms que le dictionnaire ramène à la
 *   même marque (« Hewlett-Packard » créé avant le dictionnaire, à côté de « HP »).
 * - Autres listes du blocage (modèles, types, systèmes d'exploitation) : même nom au sens de la
 *   base (casse, accents, espaces), comme le blocage. Des modèles « OptiPlex 7090 » et « OptiPlex
 *   7090 Tower » restent distincts.
 * - Logiciels : non fusionnés ici (versions et installations à réconcilier, hors du mécanisme
 *   « remplacer par » de GLPI).
 *
 * La fusion passe par la suppression native avec `_replace_by` (le choix « Remplacer par » de
 * GLPI) : toutes les références (matériels, modèles, logiciels, composants...) sont reportées sur
 * l'entrée gardée par `CommonDBTM::cleanRelationData()`, sans liste de tables à maintenir ici.
 */
final class DuplicateMerger
{
    /** Mentions légales ignorées pour comparer deux noms de fabricant. */
    private const LEGAL_SUFFIXES = [
        'inc', 'incorporated', 'ltd', 'limited', 'llc', 'corp', 'corporation', 'co', 'company',
        'gmbh', 'ag', 'sa', 'sas', 'sarl', 'plc', 'bv', 'nv', 'spa', 'srl', 'kk', 'lp',
    ];

    /**
     * @return list<array{itemtype: class-string<CommonDBTM>, keep: array{id: int, name: string}, rename_to: string|null, duplicates: list<array{id: int, name: string}>}>
     */
    public static function findGroups(): array
    {
        $groups = self::manufacturerGroups();
        foreach (DropdownUnicity::ITEMTYPES as $itemtype) {
            if ($itemtype === Manufacturer::class || $itemtype === Software::class) {
                continue;
            }
            $groups = array_merge($groups, self::sameNameGroups($itemtype));
        }

        return $groups;
    }

    /**
     * @return int nombre d'entrées en double supprimées
     */
    public static function mergeAll(): int
    {
        $merged = 0;
        foreach (self::findGroups() as $group) {
            $itemtype = $group['itemtype'];
            foreach ($group['duplicates'] as $duplicate) {
                $item = new $itemtype();
                if ($item->getFromDB($duplicate['id']) && $item->delete(['id' => $duplicate['id'], '_replace_by' => $group['keep']['id']], true)) {
                    $merged++;
                }
            }
            if ($group['rename_to'] !== null) {
                $keep = new $itemtype();
                $keep->update(['id' => $group['keep']['id'], 'name' => $group['rename_to']]);
            }
        }

        return $merged;
    }

    public static function brandKey(string $name): string
    {
        $key = mb_strtolower($name);
        if (class_exists(Normalizer::class)) {
            $key = (string) preg_replace('/\p{Mn}/u', '', (string) Normalizer::normalize($key, Normalizer::FORM_D));
        }
        $key = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $key);
        $words = array_values(array_filter(
            explode(' ', $key),
            static fn (string $word): bool => $word !== '' && !in_array($word, self::LEGAL_SUFFIXES, true)
        ));

        // « Acer, Inc » et « Acer » : la marque seule reste ; un nom fait uniquement de mentions
        // légales (improbable) garde sa forme brute plutôt que de tout regrouper sous « ».
        return $words === [] ? trim($key) : implode(' ', $words);
    }

    /**
     * @return list<array{itemtype: class-string<CommonDBTM>, keep: array{id: int, name: string}, rename_to: string|null, duplicates: list<array{id: int, name: string}>}>
     */
    private static function manufacturerGroups(): array
    {
        global $DB;

        $dictionary = new RuleDictionnaryManufacturerCollection();
        $canonical = ManufacturerDictionaryBuilder::getCanonicalNames();
        $byKey = [];
        foreach ($DB->request(['SELECT' => ['id', 'name'], 'FROM' => Manufacturer::getTable(), 'ORDER' => 'id']) as $row) {
            $name = (string) $row['name'];
            $result = $dictionary->processAllRules(['name' => $name], [], []);
            $target = (string) ($result['name'] ?? $name);
            $byKey[self::brandKey($target)][] = ['id' => (int) $row['id'], 'name' => $name, 'target' => $target];
        }

        $groups = [];
        foreach ($byKey as $members) {
            if (count($members) < 2) {
                continue;
            }
            $target = $members[0]['target'];
            // Garder le nom exact de la marque s'il existe, sinon la plus ancienne (renommée).
            $keepIndex = 0;
            foreach ($members as $index => $member) {
                if ($member['name'] === $target) {
                    $keepIndex = $index;
                    break;
                }
            }
            $keep = $members[$keepIndex];
            unset($members[$keepIndex]);
            $renameTo = $keep['name'] !== $target && in_array($target, $canonical, true) ? $target : null;
            $groups[] = [
                'itemtype' => Manufacturer::class,
                'keep' => ['id' => $keep['id'], 'name' => $keep['name']],
                'rename_to' => $renameTo,
                'duplicates' => array_values(array_map(static fn (array $m): array => ['id' => $m['id'], 'name' => $m['name']], $members)),
            ];
        }

        return $groups;
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     * @return list<array{itemtype: class-string<CommonDBTM>, keep: array{id: int, name: string}, rename_to: string|null, duplicates: list<array{id: int, name: string}>}>
     */
    private static function sameNameGroups(string $itemtype): array
    {
        global $DB;

        $table = $itemtype::getTable();
        $byName = [];
        // GROUP BY sur le nom : la collation de la base regroupe déjà casse, accents et espaces finaux.
        $duplicated = $DB->request([
            'SELECT' => ['name'],
            'FROM' => $table,
            'WHERE' => array_merge(['NOT' => ['name' => '']], $itemtype::getSystemSQLCriteria()),
            'GROUPBY' => 'name',
            'HAVING' => [new QueryExpression('COUNT(*) > 1')],
        ]);
        foreach ($duplicated as $row) {
            $byName[] = (string) $row['name'];
        }

        $groups = [];
        foreach ($byName as $name) {
            $members = [];
            foreach ($DB->request(['SELECT' => ['id', 'name'], 'FROM' => $table, 'WHERE' => ['name' => $name], 'ORDER' => 'id']) as $row) {
                $members[] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
            }
            if (count($members) < 2) {
                continue;
            }
            $groups[] = [
                'itemtype' => $itemtype,
                'keep' => array_shift($members),
                'rename_to' => null,
                'duplicates' => $members,
            ];
        }

        return $groups;
    }
}
