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

namespace GlpiPlugin\Configurationglpiauto\Blueprint;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Système de Blueprints (issue #113) : sérialise/désérialise un instantané complet du singleton
 * `Config` (~135 champs) vers/depuis un format JSON portable — indépendant de GLPI, testable en
 * pur PHP (`tests/Unit/`), contrairement au reste de ce plugin qui étend des classes GLPI core.
 *
 * Un Blueprint capture l'INTENTION de configuration (ce que `front/wizard.php` appliquerait via
 * les `*Builder`), jamais les lignes GLPI réellement créées (états, SLA...) : celles-ci ont des
 * identifiants propres à chaque instance, les transférer tels quels n'aurait pas de sens —
 * réimporter le même `Config` puis relancer l'assistant reproduit le même résultat.
 */
final class BlueprintSerializer
{
    public const FORMAT_VERSION = 1;

    /**
     * Champs `Config` stockés en base comme chaîne JSON — décodés en JSON imbriqué réel dans le
     * fichier exporté (plus lisible/portable qu'une chaîne JSON dans une chaîne JSON), puis
     * ré-encodés à l'import vers la forme attendue par `Config`.
     */
    private const JSON_ENCODED_FIELDS = [
        'entity_tree', 'calendar_days', 'calendar_day_hours', 'sla_tiers',
        'ola_tiers', 'category_branches', 'state_names', 'ldap_function_rights',
    ];

    /**
     * Purement propres à CETTE ligne `Config`/CETTE instance — jamais exportés, jamais réimportés
     * tels quels (un `id`/`configurationprofiles_id` importé référencerait une ligne qui n'existe
     * pas forcément sur l'instance de destination).
     */
    private const INSTANCE_SPECIFIC_FIELDS = ['id', 'configurationprofiles_id', 'date_creation', 'date_mod'];

    /**
     * @param array<string, mixed> $configFields `Config::getConfig()->fields` (ou toute ligne
     *     `Config` déjà chargée).
     * @return array{
     *     format_version: int, plugin_version: string, exported_at: string, profile_name: string,
     *     config: array<string, mixed>
     * }
     */
    public static function export(array $configFields, string $profileName, string $pluginVersion): array
    {
        $config = [];

        foreach ($configFields as $key => $value) {
            if (in_array($key, self::INSTANCE_SPECIFIC_FIELDS, true)) {
                continue;
            }

            if (in_array($key, self::JSON_ENCODED_FIELDS, true)) {
                $decoded = json_decode((string) $value, true);
                $config[$key] = is_array($decoded) ? $decoded : [];
                continue;
            }

            $config[$key] = $value;
        }

        return [
            'format_version' => self::FORMAT_VERSION,
            'plugin_version' => $pluginVersion,
            'exported_at'    => (new DateTimeImmutable())->format(DATE_ATOM),
            'profile_name'   => $profileName,
            'config'         => $config,
        ];
    }

    /**
     * @param array<string, mixed> $decodedJson Contenu décodé (`json_decode(..., true)`) du
     *     fichier Blueprint importé.
     * @param array<string, mixed> $currentDefaults `Config::getDefaults()` de la version
     *     actuellement installée du plugin — chaque champ absent du Blueprint importé (par
     *     exemple exporté par une version plus ancienne de ce plugin, avant l'ajout d'un nouveau
     *     réglage) retombe sur sa vraie valeur par défaut, jamais `null`/vide.
     * @return array<string, mixed> Champs prêts pour `Config::update()`.
     * @throws InvalidArgumentException Si le fichier n'est pas un Blueprint valide dans un format
     *     reconnu — jamais une supposition silencieuse sur un contenu qui ne ressemble pas à un
     *     Blueprint.
     */
    public static function import(array $decodedJson, array $currentDefaults): array
    {
        if (!isset($decodedJson['format_version']) || (int) $decodedJson['format_version'] !== self::FORMAT_VERSION) {
            throw new InvalidArgumentException(
                'Ce fichier ne semble pas être un Blueprint valide (format_version manquant ou non reconnu).'
            );
        }

        if (!isset($decodedJson['config']) || !is_array($decodedJson['config'])) {
            throw new InvalidArgumentException('Ce fichier ne contient aucune section "config" exploitable.');
        }

        $imported = $decodedJson['config'];
        $result = $currentDefaults;

        foreach (self::INSTANCE_SPECIFIC_FIELDS as $field) {
            unset($imported[$field]);
        }

        foreach ($currentDefaults as $key => $defaultValue) {
            if (!array_key_exists($key, $imported)) {
                continue;
            }

            if (in_array($key, self::JSON_ENCODED_FIELDS, true)) {
                $result[$key] = json_encode($imported[$key]);
                continue;
            }

            $result[$key] = $imported[$key];
        }

        return $result;
    }
}
