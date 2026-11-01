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

namespace GlpiPlugin\Configurationglpiauto\Install;

use CommonTreeDropdown;

/**
 * Issue #390 : les objets créés par une version précédente portent encore un nom avec tiret
 * cadratin. Sans renommage, l'assistant ne les retrouverait plus par leur nom et en créerait un
 * second. Seuls les noms qui commencent par un nom d'origine du plugin (EmDashNames) sont
 * renommés : un nom choisi par l'administrateur reste tel quel.
 */
final class EmDashRename
{
    private const DASH = "\u{2014}";

    /** @var list<array{0: string, 1: string}>|null */
    private static ?array $rules = null;

    /**
     * Nouveau nom, ou null si le nom ne vient pas du plugin.
     */
    public static function rename(string $name): ?string
    {
        if (!str_contains($name, self::DASH)) {
            return null;
        }
        foreach (self::rules() as [$regex, $replacement]) {
            if (preg_match($regex, $name, $matches) === 1) {
                $new = preg_replace_callback(
                    '/%(\d+)\$[sd]|%[sd]|\{rest\}|\{lead\}/',
                    static function (array $token) use ($matches): string {
                        return match ($token[0]) {
                            '{rest}' => $matches['rest'],
                            '{lead}' => $matches['lead'],
                            default  => $matches['p' . ($token[1] ?? '')] ?? '',
                        };
                    },
                    $replacement,
                );
                return $new ?? null;
            }
        }
        return null;
    }

    /**
     * Renomme les lignes concernées de toutes les tables de GLPI et de ce plugin qui ont un nom,
     * ainsi que les traductions de ces noms.
     */
    public static function migrate(): int
    {
        global $DB;

        $renamed = 0;
        foreach ($DB->listTables('glpi\_%') as $row) {
            $table = (string) $row['TABLE_NAME'];
            if (str_starts_with($table, 'glpi_plugin_') && !str_starts_with($table, 'glpi_plugin_configurationglpiauto_')) {
                continue;
            }
            if (!$DB->fieldExists($table, 'id', false)) {
                continue;
            }
            $itemtype = getItemTypeForTable($table);
            // Un gabarit (projet...) est retrouvé par son template_name, pas par son nom.
            foreach ($table === 'glpi_dropdowntranslations' ? ['value'] : ['name', 'template_name'] as $field) {
                if (!$DB->fieldExists($table, $field, false)) {
                    continue;
                }
                foreach ($DB->request(['SELECT' => ['id', $field], 'FROM' => $table, 'WHERE' => [$field => ['LIKE', '%' . self::DASH . '%']]]) as $data) {
                    $new = self::rename((string) $data[$field]);
                    if ($new === null) {
                        continue;
                    }
                    // Arborescence : la mise à jour par l'objet recalcule le nom complet et celui
                    // des enfants.
                    if ($field === 'name' && is_a($itemtype, CommonTreeDropdown::class, true)) {
                        $item = new $itemtype();
                        $item->update(['id' => (int) $data['id'], $field => $new]);
                    } else {
                        $DB->update($table, [$field => $new], ['id' => (int) $data['id']]);
                    }
                    $renamed++;
                }
            }
        }
        return $renamed;
    }

    /**
     * @return list<array{0: string, 1: string}> expression régulière et remplacement
     */
    private static function rules(): array
    {
        if (self::$rules !== null) {
            return self::$rules;
        }
        $pairs = EmDashNames::PAIRS;
        // Le nom d'origine le plus long d'abord : le plus précis l'emporte.
        usort($pairs, static fn (array $a, array $b): int => mb_strlen($b[0]) <=> mb_strlen($a[0]));
        self::$rules = [];
        foreach ($pairs as [$old, $new]) {
            $parts = preg_split('/(%(?:\d+\$)?[sd])/', $old, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$old];
            $regex = '';
            $position = 0;
            foreach ($parts as $index => $part) {
                if ($index % 2 === 0) {
                    $regex .= preg_quote($part, '/');
                    continue;
                }
                $position++;
                $key = preg_match('/^%(\d+)\$/', $part, $m) === 1 ? $m[1] : (string) $position;
                $regex .= '(?<p' . $key . '>.+?)';
            }
            // Les %s sans position deviennent %1$s, %2$s... dans le remplacement.
            $position = 0;
            $replacement = (string) preg_replace_callback('/%(\d+\$)?[sd]/', static function (array $token) use (&$position): string {
                $position++;
                return isset($token[1]) ? $token[0] : '%' . $position . '$s';
            }, $new);
            // Un nom peut être précédé de l'icône de l'option « icônes » (« 🆕 Onboarding... »).
            self::$rules[] = ['/^(?<lead>(?:[^\p{L}\p{N}\s]+\s+)?)' . $regex . '(?<rest>.*)$/su', '{lead}' . $replacement . '{rest}'];
        }
        return self::$rules;
    }
}
