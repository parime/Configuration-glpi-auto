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

        $slaTiersSeed = null;

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
                `entity_tree` text,
                `configurationprofiles_id` int {$keySign} NOT NULL DEFAULT 0,
                `calendar_enabled` tinyint NOT NULL DEFAULT 0,
                `calendar_name` varchar(255) NOT NULL DEFAULT 'Horaires standard',
                `calendar_days` text,
                `calendar_begin` varchar(5) NOT NULL DEFAULT '08:00',
                `calendar_end` varchar(5) NOT NULL DEFAULT '18:00',
                `branding_enabled` tinyint NOT NULL DEFAULT 0,
                `branding_primary_color` varchar(7) NOT NULL DEFAULT '#206bc4',
                `sla_enabled` tinyint NOT NULL DEFAULT 0,
                `sla_tiers` text,
                `sla_astreinte` tinyint NOT NULL DEFAULT 0,
                `ola_enabled` tinyint NOT NULL DEFAULT 0,
                `ola_tiers` text,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());

            (new Config())->add(Config::getDefaults() + ['id' => 1]);
        } else {
            // sla_tto_hours/sla_ttr_hours (flat) replaced by sla_tiers (JSON, one row per
            // priority level, Sprint 14) — read the old singleton's flat value first (while the
            // columns still exist) so upgrading doesn't silently lose the existing setting; the
            // actual UPDATE happens after executeMigration() below, once sla_tiers physically
            // exists to write into.
            if ($DB->fieldExists(self::CONFIGS_TABLE, 'sla_tto_hours') && $DB->fieldExists(self::CONFIGS_TABLE, 'sla_ttr_hours')) {
                $row = $DB->request(self::CONFIGS_TABLE, ['id' => 1])->current();
                if ($row !== null) {
                    $slaTiersSeed = array_fill_keys(
                        array_map('strval', Config::PRIORITY_LEVELS),
                        ['tto_hours' => max(1, (int) $row['sla_tto_hours']), 'ttr_hours' => max(1, (int) $row['sla_ttr_hours'])]
                    );
                }
            }
            $migration->dropField(self::CONFIGS_TABLE, 'sla_tto_hours');
            $migration->dropField(self::CONFIGS_TABLE, 'sla_ttr_hours');
            $migration->addField(self::CONFIGS_TABLE, 'sla_tiers', 'text');

            // Upgrade path for instances installed before these columns existed — addField() is
            // idempotent, unlike the raw CREATE TABLE above, same pattern already used on the
            // sibling glpi-vulnerability-manager plugin.
            $migration->addField(
                self::CONFIGS_TABLE,
                'configurationprofiles_id',
                'integer',
                ['value' => 0]
            );
            // entity_levels/level_labels/top_level_names (uniform-tree model) replaced by
            // entity_tree (arbitrary per-node tree, Sprint 9) — dropField() is idempotent
            // (no-op if the column is already gone), same as addField().
            $migration->dropField(self::CONFIGS_TABLE, 'entity_levels');
            $migration->dropField(self::CONFIGS_TABLE, 'level_labels');
            $migration->dropField(self::CONFIGS_TABLE, 'top_level_names');
            $migration->addField(self::CONFIGS_TABLE, 'entity_tree', 'text');
            $migration->addField(self::CONFIGS_TABLE, 'calendar_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'calendar_name', 'string', ['value' => 'Horaires standard']);
            $migration->addField(self::CONFIGS_TABLE, 'calendar_days', 'text');
            $migration->addField(self::CONFIGS_TABLE, 'calendar_begin', 'string', ['value' => '08:00']);
            $migration->addField(self::CONFIGS_TABLE, 'calendar_end', 'string', ['value' => '18:00']);
            $migration->addField(self::CONFIGS_TABLE, 'branding_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'branding_primary_color', 'string', ['value' => '#206bc4']);
            $migration->addField(self::CONFIGS_TABLE, 'sla_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'sla_astreinte', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'ola_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'ola_tiers', 'text');
        }

        // ITIL/ISO27001 ne sont pas des tailles d'organisation, ce sont des cadres de bonnes
        // pratiques que n'importe quel profil peut suivre — retires de la liste des profils
        // proposes (Sprint 11, voir ConfigurationProfile::getSuggestedDefaults()). Desactivation,
        // pas suppression : une Config existante pourrait encore pointer dessus via
        // configurationprofiles_id, et l'etape 1 du wizard filtre deja is_active=1 donc l'effet
        // visible est immediat sans perte de donnees.
        $DB->update(self::PROFILES_TABLE, ['is_active' => 0], ['type' => ['iso27001', 'itil']]);

        // PME/ETI/Grande entreprise renvoyaient des suggestions identiques une fois taille et
        // cadre de bonnes pratiques distingues (Sprint 11) — trois options avec des sigles
        // differents qui font la meme chose, c'est trompeur. Fusionnees en une seule option en
        // francais clair (Sprint 12), meme logique de desactivation que ci-dessus.
        $DB->update(self::PROFILES_TABLE, ['is_active' => 0], ['type' => ['sme', 'eti', 'enterprise']]);
        $DB->update(self::PROFILES_TABLE, ['name' => 'Installation simple', 'description' => 'Un seul site, pas de sous-structure'], ['type' => 'minimal']);
        $DB->update(self::PROFILES_TABLE, ['name' => 'Plusieurs entreprises clientes', 'description' => 'Vous gérez GLPI pour le compte d\'autres entreprises'], ['type' => 'msp']);
        if (!(new ConfigurationProfile())->getFromDBByCrit(['type' => 'multi_site'])) {
            (new ConfigurationProfile())->add([
                'name' => 'Plusieurs sites ou services',
                'type' => 'multi_site',
                'description' => 'Une seule entreprise, plusieurs équipes ou sites',
                'sort_order' => 2,
                'is_active' => 1,
            ]);
        }

        // Sprint 14 bug fix: SlaBuilder-created rules were missing is_recursive=1, so GLPI's
        // RuleCollection only ever evaluated them for the rule's own entities_id (root, 0) — never
        // for a ticket created in any sub-entity, which is the only kind of entity this plugin
        // ever actually assigns an SLA to. Confirmed by creating a real ticket in a sub-entity and
        // finding slas_id_tto/slas_id_ttr stayed 0. Fixes rules created by earlier sprints too;
        // new ones are already created correctly (see SlaBuilder::assignOne()).
        $DB->update('glpi_rules', ['is_recursive' => 1], ['sub_type' => 'RuleTicket', 'name' => ['LIKE', 'SLA standard%']]);

        Profile::install($migration);

        $migration->executeMigration();

        if ($slaTiersSeed !== null) {
            $DB->update(self::CONFIGS_TABLE, ['sla_tiers' => json_encode($slaTiersSeed)], ['id' => 1]);
        }

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
            ['name' => 'Installation simple', 'type' => 'minimal', 'sort_order' => 1, 'description' => 'Un seul site, pas de sous-structure'],
            ['name' => 'Plusieurs sites ou services', 'type' => 'multi_site', 'sort_order' => 2, 'description' => 'Une seule entreprise, plusieurs équipes ou sites'],
            ['name' => 'Plusieurs entreprises clientes', 'type' => 'msp', 'sort_order' => 3, 'description' => 'Vous gérez GLPI pour le compte d\'autres entreprises'],
            ['name' => 'Personnalisé', 'type' => 'custom', 'sort_order' => 4, 'description' => 'Je configure tout moi-même'],
        ];

        foreach ($defaultProfiles as $profileData) {
            (new ConfigurationProfile())->add($profileData + ['is_active' => 1]);
        }
    }
}
