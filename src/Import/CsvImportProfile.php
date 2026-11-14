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

namespace GlpiPlugin\Configurationglpiauto\Import;

/**
 * Issue #430 : modèles de correspondance de l'import CSV générique (nom de l'outil d'origine,
 * colonne de chaque champ par son en-tête, séparateur des commentaires), réutilisables pour le
 * fichier suivant du même outil, exportables et importables en JSON.
 */
final class CsvImportProfile
{
    public const TABLE = 'glpi_plugin_configurationglpiauto_csvimportprofiles';

    /** Marque du fichier JSON exporté. */
    public const FORMAT = 'configurationglpiauto-csv-import-profile';

    /**
     * Réglages d'un modèle, réduits aux valeurs connues.
     *
     * @param array<mixed> $settings
     * @return array{source_name: string, columns: array<string, string>, separator: string, separator_custom: string}
     */
    public static function normalize(array $settings): array
    {
        $columns = [];
        foreach ((array) ($settings['columns'] ?? []) as $field => $label) {
            if (isset(CsvColumns::FIELDS[$field]) && is_scalar($label) && trim((string) $label) !== '') {
                $columns[(string) $field] = mb_substr(trim((string) $label), 0, 255);
            }
        }
        $separator = (string) ($settings['separator'] ?? 'none');

        return [
            'source_name'      => mb_substr(trim((string) ($settings['source_name'] ?? '')), 0, 100),
            'columns'          => $columns,
            'separator'        => in_array($separator, CsvColumns::SEPARATORS, true) ? $separator : 'none',
            'separator_custom' => mb_substr((string) ($settings['separator_custom'] ?? ''), 0, 20),
        ];
    }

    /**
     * Modèles enregistrés, par nom.
     *
     * @return list<array{id: int, name: string, settings: array{source_name: string, columns: array<string, string>,
     *     separator: string, separator_custom: string}}>
     */
    public static function all(): array
    {
        global $DB;

        $profiles = [];
        foreach ($DB->request(['FROM' => self::TABLE, 'ORDER' => 'name']) as $row) {
            $profiles[] = [
                'id'       => (int) $row['id'],
                'name'     => (string) $row['name'],
                'settings' => self::normalize((array) json_decode((string) $row['settings'], true)),
            ];
        }

        return $profiles;
    }

    /**
     * @return array{id: int, name: string, settings: array{source_name: string, columns: array<string, string>,
     *     separator: string, separator_custom: string}}|null
     */
    public static function get(int $id): ?array
    {
        foreach (self::all() as $profile) {
            if ($profile['id'] === $id) {
                return $profile;
            }
        }

        return null;
    }

    /**
     * Enregistre un modèle ; un modèle du même nom est remplacé.
     *
     * @param array<mixed> $settings
     * @return int identifiant du modèle, 0 si le nom est vide
     */
    public static function save(string $name, array $settings): int
    {
        global $DB;

        $name = mb_substr(trim($name), 0, 255);
        if ($name === '') {
            return 0;
        }
        $json = (string) json_encode(self::normalize($settings), JSON_UNESCAPED_UNICODE);
        $existing = $DB->request(['SELECT' => ['id'], 'FROM' => self::TABLE, 'WHERE' => ['name' => $name]])->current();
        if ($existing !== null) {
            $DB->update(self::TABLE, ['settings' => $json, 'date_mod' => date('Y-m-d H:i:s')], ['id' => $existing['id']]);

            return (int) $existing['id'];
        }
        $DB->insert(self::TABLE, [
            'name'          => $name,
            'settings'      => $json,
            'date_creation' => date('Y-m-d H:i:s'),
            'date_mod'      => date('Y-m-d H:i:s'),
        ]);

        return (int) $DB->insertId();
    }

    public static function delete(int $id): void
    {
        global $DB;

        $DB->delete(self::TABLE, ['id' => $id]);
    }

    /** Fichier JSON d'un modèle, à importer sur une autre instance. */
    public static function export(int $id): ?string
    {
        $profile = self::get($id);
        if ($profile === null) {
            return null;
        }

        return (string) json_encode(
            ['format' => self::FORMAT, 'version' => 1, 'name' => $profile['name']] + $profile['settings'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Modèle lu dans un fichier JSON exporté.
     *
     * @return array{name: string, settings: array{source_name: string, columns: array<string, string>,
     *     separator: string, separator_custom: string}}|null null si ce n'est pas un modèle exporté
     */
    public static function parseExport(string $json): ?array
    {
        $data = json_decode(CsvReader::toUtf8($json), true);
        if (!is_array($data) || ($data['format'] ?? '') !== self::FORMAT || trim((string) ($data['name'] ?? '')) === '') {
            return null;
        }
        $settings = self::normalize($data);

        return $settings['columns'] === [] ? null : ['name' => trim((string) $data['name']), 'settings' => $settings];
    }
}
