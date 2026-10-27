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

/**
 * Issue #339 : quand le plugin jumeau assetsign-glpi est actif, renseigne sa configuration racine
 * avec ce que l'assistant connaît déjà ou demande une seule fois :
 * - le nom de l'entreprise, en tête des PDF de remise (par défaut, le nom de la première entité de
 *   l'arborescence de l'assistant) ;
 * - le lien de la charte informatique, prérequis des campagnes de charte d'assetsign.
 *
 * Même contrat que AssetsignTriggerBuilder : écriture via `$DB` sur la ligne racine
 * (`entities_id = 0`) de `glpi_plugin_assetsign_configs`, jamais de `use` d'une classe d'un plugin
 * qui peut être absent. L'activation des campagnes de charte reste dans assetsign : elle se règle
 * avec ses délais et relances.
 *
 * Non destructif : seul un réglage encore vide dans assetsign est renseigné.
 */
class AssetsignSetupBuilder
{
    public const TABLE = 'glpi_plugin_assetsign_configs';

    /**
     * Pure : ce que l'assistant écrira dans la ligne racine d'assetsign.
     *
     * @param array<string, mixed> $assetsignRow ligne racine actuelle de la config assetsign
     * @return array<string, string> colonne assetsign => valeur à écrire
     */
    public static function plan(array $assetsignRow, string $companyName, string $charterUrl): array
    {
        $changes = [];
        if (trim((string) ($assetsignRow['company_name'] ?? '')) === '' && trim($companyName) !== '') {
            $changes['company_name'] = trim($companyName);
        }
        if (trim((string) ($assetsignRow['charter_url'] ?? '')) === '' && self::isValidUrl($charterUrl)) {
            $changes['charter_url'] = trim($charterUrl);
        }

        return $changes;
    }

    /**
     * Même règle que la configuration d'assetsign (Config::sanitizeCharterUrl()) : une adresse web.
     */
    public static function isValidUrl(string $url): bool
    {
        $url = trim($url);

        return $url !== '' && \Toolbox::isValidWebUrl($url);
    }

    /**
     * Nom proposé par défaut : la première entité de l'arborescence de l'assistant.
     */
    public static function defaultCompanyName(Config $config): string
    {
        $tree = $config->getEntityTree();

        return trim((string) ($tree[0]['name'] ?? ''));
    }

    /**
     * @return int nombre de réglages renseignés dans assetsign
     */
    public function build(Config $config): int
    {
        global $DB;

        if (empty($config->fields['assetsign_setup_enabled']) || !self::isAvailable()) {
            return 0;
        }
        $row = $DB->request(['FROM' => self::TABLE, 'WHERE' => ['entities_id' => 0]])->current();
        if ($row === null) {
            return 0;
        }
        $companyName = trim((string) ($config->fields['assetsign_company_name'] ?? ''));
        $changes = self::plan(
            $row,
            $companyName !== '' ? $companyName : self::defaultCompanyName($config),
            (string) ($config->fields['assetsign_charter_url'] ?? '')
        );
        if ($changes !== []) {
            $DB->update(self::TABLE, $changes, ['entities_id' => 0]);
        }

        return count($changes);
    }

    public static function isAvailable(): bool
    {
        global $DB;

        return class_exists('Plugin') && \Plugin::isPluginActive('assetsign') && $DB->tableExists(self::TABLE);
    }
}
