<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Parime
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

use DBConnection;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\Profile;
use Migration;

/**
 * Handles plugin install/uninstall. Kept out of hook.php so it can evolve independently, same
 * split as the sibling glpi-vulnerability-manager plugin.
 */
final class Installer
{
    private const PROFILES_TABLE = 'glpi_plugin_configurationglpiauto_profiles';

    private const CONFIGS_TABLE = 'glpi_plugin_configurationglpiauto_configs';

    public function install(Migration $migration): bool
    {
        global $DB;

        $migration->setVersion(PLUGIN_CONFIGURATIONGLPIAUTO_VERSION);

        if (!$DB->tableExists(self::PROFILES_TABLE)) {
            $charset   = DBConnection::getDefaultCharset();
            $collation = DBConnection::getDefaultCollation();
            $keySign   = DBConnection::getDefaultPrimaryKeySignOption();

            $query = "CREATE TABLE `" . self::PROFILES_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `description` text,
                `type` varchar(32) NOT NULL DEFAULT 'custom',
                `is_active` tinyint NOT NULL DEFAULT 1,
                `sort_order` int NOT NULL DEFAULT 0,
                `comment` text,
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `type` (`type`),
                KEY `is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());

            $this->insertDefaultProfiles();
        }

        if (!$DB->tableExists(self::CONFIGS_TABLE)) {
            $charset   = DBConnection::getDefaultCharset();
            $collation = DBConnection::getDefaultCollation();
            $keySign   = DBConnection::getDefaultPrimaryKeySignOption();

            $query = "CREATE TABLE `" . self::CONFIGS_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `entity_mode` varchar(32) NOT NULL DEFAULT 'mono',
                `entity_levels` int NOT NULL DEFAULT 1,
                `level_labels` text,
                `configurationprofiles_id` int {$keySign} NOT NULL DEFAULT 0,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());

            (new Config())->add(Config::getDefaults() + ['id' => 1]);
        } else {
            // Upgrade path for instances installed before the wizard's profile-selection step
            // (Sprint 4) added this column — addField() is idempotent, unlike the raw CREATE
            // TABLE above, same pattern already used on the sibling glpi-vulnerability-manager
            // plugin.
            $migration->addField(
                self::CONFIGS_TABLE,
                'configurationprofiles_id',
                'integer',
                ['value' => 0]
            );
        }

        Profile::install($migration);

        $migration->executeMigration();

        return true;
    }

    public function uninstall(Migration $migration): bool
    {
        global $DB;

        $DB->doQuery("DROP TABLE IF EXISTS `" . self::PROFILES_TABLE . "`");
        $DB->doQuery("DROP TABLE IF EXISTS `" . self::CONFIGS_TABLE . "`");

        Profile::uninstall();

        return true;
    }

    private function insertDefaultProfiles(): void
    {
        $defaultProfiles = [
            ['name' => 'Installation minimale', 'type' => 'minimal', 'sort_order' => 1],
            ['name' => 'PME', 'type' => 'sme', 'sort_order' => 2],
            ['name' => 'ETI', 'type' => 'eti', 'sort_order' => 3],
            ['name' => 'Grande entreprise', 'type' => 'enterprise', 'sort_order' => 4],
            ['name' => 'MSP', 'type' => 'msp', 'sort_order' => 5],
            ['name' => 'ISO 27001', 'type' => 'iso27001', 'sort_order' => 6],
            ['name' => 'ITIL', 'type' => 'itil', 'sort_order' => 7],
            ['name' => 'Personnalisé', 'type' => 'custom', 'sort_order' => 8],
        ];

        foreach ($defaultProfiles as $profileData) {
            (new ConfigurationProfile())->add($profileData + ['is_active' => 1]);
        }
    }
}
