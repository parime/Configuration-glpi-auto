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

use DBConnection;
use DropdownTranslation;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\Profile;
use GlpiPlugin\Configurationglpiauto\StateBuilder;
use Migration;

/**
 * Handles plugin install/uninstall. Kept out of hook.php so it can evolve independently, same
 * split as the sibling glpi-vulnerability-manager plugin.
 */
final class Installer
{
    private const PROFILES_TABLE = 'glpi_plugin_configurationglpiauto_profiles';

    private const CONFIGS_TABLE = 'glpi_plugin_configurationglpiauto_configs';

    private const FUELTYPES_TABLE = 'glpi_plugin_configurationglpiauto_fueltypes';

    public function install(Migration $migration): bool
    {
        global $DB;

        $migration->setVersion(PLUGIN_CONFIGURATIONGLPIAUTO_VERSION);

        $slaTiersSeed = null;
        $generalSettingsSeed = null;

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
                `calendar_day_hours` text,
                `calendar_lunch_break_enabled` tinyint NOT NULL DEFAULT 0,
                `calendar_lunch_begin` varchar(5) NOT NULL DEFAULT '12:00',
                `calendar_lunch_end` varchar(5) NOT NULL DEFAULT '13:00',
                `calendar_holidays_enabled` tinyint NOT NULL DEFAULT 0,
                `branding_enabled` tinyint NOT NULL DEFAULT 0,
                `branding_primary_color` varchar(7) NOT NULL DEFAULT '#206bc4',
                `custom_palette_enabled` tinyint NOT NULL DEFAULT 0,
                `native_palette` varchar(32) NOT NULL DEFAULT '',
                `branding_per_client_enabled` tinyint NOT NULL DEFAULT 0,
                `sla_enabled` tinyint NOT NULL DEFAULT 0,
                `sla_tiers` text,
                `sla_astreinte` tinyint NOT NULL DEFAULT 0,
                `sla_escalation_enabled` tinyint NOT NULL DEFAULT 0,
                `sla_escalation_threshold_percent` int NOT NULL DEFAULT 75,
                `escalation_enabled` tinyint NOT NULL DEFAULT 0,
                `escalation_includes_n0` tinyint NOT NULL DEFAULT 0,
                `escalation_auto_n1_n2` tinyint NOT NULL DEFAULT 1,
                `escalation_auto_n2_n3` tinyint NOT NULL DEFAULT 1,
                `support_tier_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `ola_enabled` tinyint NOT NULL DEFAULT 0,
                `ola_tiers` text,
                `category_enabled` tinyint NOT NULL DEFAULT 0,
                `category_branches` text,
                `category_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `state_enabled` tinyint NOT NULL DEFAULT 0,
                `state_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `state_names` text,
                `general_ui_enabled` tinyint NOT NULL DEFAULT 0,
                `notifications_enabled` tinyint NOT NULL DEFAULT 0,
                `financial_info_enabled` tinyint NOT NULL DEFAULT 0,
                `project_task_states_enabled` tinyint NOT NULL DEFAULT 0,
                `satisfaction_survey_enabled` tinyint NOT NULL DEFAULT 0,
                `committee_validation_enabled` tinyint NOT NULL DEFAULT 0,
                `inventory_enabled` tinyint NOT NULL DEFAULT 0,
                `ticket_template_enabled` tinyint NOT NULL DEFAULT 0,
                `ticket_template_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `helpdesk_form_hide_fields` tinyint NOT NULL DEFAULT 0,
                `service_catalog_enabled` tinyint NOT NULL DEFAULT 0,
                `abroad_mission_form_enabled` tinyint NOT NULL DEFAULT 0,
                `wait_reasons_enabled` tinyint NOT NULL DEFAULT 0,
                `ldap_rights_enabled` tinyint NOT NULL DEFAULT 0,
                `ldap_rights_group_template` varchar(255) NOT NULL DEFAULT 'GLPI_{ENTITY}',
                `ldap_rights_profile` varchar(255) NOT NULL DEFAULT 'Admin',
                `ldap_function_rights` text,
                `task_categories_enabled` tinyint NOT NULL DEFAULT 0,
                `task_templates_enabled` tinyint NOT NULL DEFAULT 0,
                `task_template_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `solution_library_enabled` tinyint NOT NULL DEFAULT 0,
                `solution_type_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `solution_template_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `followup_library_enabled` tinyint NOT NULL DEFAULT 0,
                `followup_library_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `validation_templates_enabled` tinyint NOT NULL DEFAULT 0,
                `validation_template_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `change_problem_templates_enabled` tinyint NOT NULL DEFAULT 0,
                `change_problem_template_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `locations_enabled` tinyint NOT NULL DEFAULT 0,
                `manufacturers_enabled` tinyint NOT NULL DEFAULT 0,
                `manufacturer_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `manufacturer_dictionary_enabled` tinyint NOT NULL DEFAULT 0,
                `kb_categories_enabled` tinyint NOT NULL DEFAULT 0,
                `document_management_enabled` tinyint NOT NULL DEFAULT 0,
                `document_management_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `planning_events_enabled` tinyint NOT NULL DEFAULT 0,
                `planning_events_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `project_taxonomy_enabled` tinyint NOT NULL DEFAULT 0,
                `project_taxonomy_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `project_task_templates_enabled` tinyint NOT NULL DEFAULT 0,
                `project_task_template_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `entity_logos_enabled` tinyint NOT NULL DEFAULT 0,
                `wait_reason_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `notification_branding_enabled` tinyint NOT NULL DEFAULT 0,
                `location_geocoding_enabled` tinyint NOT NULL DEFAULT 0,
                `location_geocoding_endpoint` varchar(255) NOT NULL DEFAULT 'https://nominatim.openstreetmap.org',
                `project_templates_enabled` tinyint NOT NULL DEFAULT 0,
                `request_type_translations_enabled` tinyint NOT NULL DEFAULT 0,
                `request_type_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `entity_native_address_enabled` tinyint NOT NULL DEFAULT 0,
                `user_categories_enabled` tinyint NOT NULL DEFAULT 0,
                `user_category_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `field_unicity_enabled` tinyint NOT NULL DEFAULT 0,
                `rss_feeds_enabled` tinyint NOT NULL DEFAULT 0,
                `line_operators_enabled` tinyint NOT NULL DEFAULT 0,
                `asset_types_enabled` tinyint NOT NULL DEFAULT 0,
                `asset_type_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `country_holidays_enabled` tinyint NOT NULL DEFAULT 0,
                `satisfaction_plugin_survey_enabled` tinyint NOT NULL DEFAULT 0,
                `vip_group_enabled` tinyint NOT NULL DEFAULT 0,
                `tag_library_enabled` tinyint NOT NULL DEFAULT 0,
                `validation_supervisor_routing_enabled` tinyint NOT NULL DEFAULT 0,
                `fire_safety_assets_enabled` tinyint NOT NULL DEFAULT 0,
                `fire_safety_asset_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `physical_security_assets_enabled` tinyint NOT NULL DEFAULT 0,
                `physical_security_asset_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `certificate_types_enabled` tinyint NOT NULL DEFAULT 0,
                `certificate_type_icons_enabled` tinyint NOT NULL DEFAULT 0,
                `recurring_ticket_library_enabled` tinyint NOT NULL DEFAULT 0,
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
            $migration->addField(self::CONFIGS_TABLE, 'calendar_holidays_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'branding_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'branding_primary_color', 'string', ['value' => '#206bc4']);
            $migration->addField(self::CONFIGS_TABLE, 'custom_palette_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'native_palette', 'string', ['value' => '']);
            $migration->addField(self::CONFIGS_TABLE, 'sla_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'sla_astreinte', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'sla_escalation_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'sla_escalation_threshold_percent', 'integer', ['value' => 75]);
            $migration->addField(self::CONFIGS_TABLE, 'escalation_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'escalation_includes_n0', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'escalation_auto_n1_n2', 'bool', ['value' => 1]);
            $migration->addField(self::CONFIGS_TABLE, 'escalation_auto_n2_n3', 'bool', ['value' => 1]);
            $migration->addField(self::CONFIGS_TABLE, 'support_tier_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'ola_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'ola_tiers', 'text');
            $migration->addField(self::CONFIGS_TABLE, 'state_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'state_icons_enabled', 'bool', ['value' => 0]);
            // Explicit default (unlike category_branches's precedent, which addField()s with no
            // value and so comes back empty on upgrade): state_enabled alone used to create all 14
            // states unconditionally, so an upgrading install needs state_names seeded to "all 14"
            // to keep behaving the same on its next wizard run, not silently start creating zero.
            $migration->addField(self::CONFIGS_TABLE, 'state_names', 'text', ['value' => json_encode(StateBuilder::getStateNames(), JSON_UNESCAPED_UNICODE)]);
            $migration->addField(self::CONFIGS_TABLE, 'category_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'category_branches', 'text');
            $migration->addField(self::CONFIGS_TABLE, 'category_icons_enabled', 'bool', ['value' => 0]);

            // general_settings_enabled (single all-or-nothing toggle) replaced by 6 independently
            // gated groups (Sprint 26) — an admin could not, e.g., accept the satisfaction survey
            // without also getting the committee validation step. Read the old singleton's value
            // first (while the column still exists) so upgrading doesn't silently turn every group
            // off for an instance that had it on; the actual backfill happens after
            // executeMigration() below, same pattern as sla_tiers above.
            if ($DB->fieldExists(self::CONFIGS_TABLE, 'general_settings_enabled')) {
                $row = $DB->request(self::CONFIGS_TABLE, ['id' => 1])->current();
                if ($row !== null) {
                    $generalSettingsSeed = (int) $row['general_settings_enabled'];
                }
            }
            $migration->addField(self::CONFIGS_TABLE, 'general_ui_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'notifications_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'financial_info_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'project_task_states_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'satisfaction_survey_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'committee_validation_enabled', 'bool', ['value' => 0]);
            $migration->dropField(self::CONFIGS_TABLE, 'general_settings_enabled');

            $migration->addField(self::CONFIGS_TABLE, 'ticket_template_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'ticket_template_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'helpdesk_form_hide_fields', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'service_catalog_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'wait_reasons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'ldap_rights_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'ldap_rights_group_template', 'string', ['value' => 'GLPI_{ENTITY}']);
            $migration->addField(self::CONFIGS_TABLE, 'ldap_rights_profile', 'string', ['value' => 'Admin']);
            $migration->addField(self::CONFIGS_TABLE, 'ldap_function_rights', 'text', ['value' => '[]']);
            $migration->addField(self::CONFIGS_TABLE, 'task_categories_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'task_templates_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'task_template_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'solution_library_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'solution_type_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'solution_template_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'followup_library_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'followup_library_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'validation_templates_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'validation_template_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'change_problem_templates_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'change_problem_template_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'locations_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'manufacturers_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'manufacturer_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'manufacturer_dictionary_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'kb_categories_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'document_management_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'document_management_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'planning_events_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'planning_events_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'branding_per_client_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'project_taxonomy_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'project_taxonomy_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'project_task_templates_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'project_task_template_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'entity_logos_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'wait_reason_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'notification_branding_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'location_geocoding_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'location_geocoding_endpoint', 'string', ['value' => 'https://nominatim.openstreetmap.org']);
            $migration->addField(self::CONFIGS_TABLE, 'project_templates_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'request_type_translations_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'entity_native_address_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'user_categories_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'user_category_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'field_unicity_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'rss_feeds_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'line_operators_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'asset_types_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'asset_type_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'country_holidays_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'satisfaction_plugin_survey_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'vip_group_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'tag_library_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'validation_supervisor_routing_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'calendar_day_hours', 'text');
            $migration->addField(self::CONFIGS_TABLE, 'calendar_lunch_break_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'calendar_lunch_begin', 'string', ['value' => '12:00']);
            $migration->addField(self::CONFIGS_TABLE, 'calendar_lunch_end', 'string', ['value' => '13:00']);
            $migration->addField(self::CONFIGS_TABLE, 'software_license_types_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'software_license_type_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'fire_safety_assets_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'fire_safety_asset_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'physical_security_assets_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'physical_security_asset_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'request_type_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'inventory_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'certificate_types_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'certificate_type_icons_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'recurring_ticket_library_enabled', 'bool', ['value' => 0]);
            $migration->addField(self::CONFIGS_TABLE, 'abroad_mission_form_enabled', 'bool', ['value' => 0]);
        }

        // Flat CommonDropdown table, GLPI has no native "fuel type" concept — same minimal shape
        // as glpi_manufacturers (id/name/comment/dates), no entities_id: a fuel type is a universal
        // reference value, not scoped to any one entity. Target of VehicleAssetBuilder's
        // "Type de carburant" DropdownType custom field.
        if (!$DB->tableExists(self::FUELTYPES_TABLE)) {
            $charset   = DBConnection::getDefaultCharset();
            $collation = DBConnection::getDefaultCollation();
            $keySign   = DBConnection::getDefaultPrimaryKeySignOption();

            $query = "CREATE TABLE `" . self::FUELTYPES_TABLE . "` (
                `id` int {$keySign} NOT NULL AUTO_INCREMENT,
                `name` varchar(255) DEFAULT NULL,
                `comment` text,
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}";

            $DB->doQuery($query) or die($DB->error());
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

        // Sprint 16's "one category per ITIL type" (Incidents/Demandes/Problèmes/Changements) was
        // replaced in Sprint 17 by a real topical category tree — Ticket already has a native
        // `type` field for Incident/Demande, and Problem/Change are already their own GLPI object
        // types, so a category per type never added anything. ITILCategory has no is_active flag
        // to soft-disable with, so this just hides the 4 old root categories from ticket creation
        // (is_helpdeskvisible=0) instead of deleting real GLPI objects that could already have
        // tickets attached — restricted to root-level (itilcategories_id=0) exact-name matches to
        // avoid touching anything an admin created themselves with the same name.
        $DB->update(
            'glpi_itilcategories',
            ['is_helpdeskvisible' => 0],
            ['itilcategories_id' => 0, 'name' => ['Incidents', 'Demandes', 'Problèmes', 'Changements']]
        );

        // Renamed to a vendor-neutral name (user feedback: not every organization is on
        // Microsoft 365) — restricted to this exact, distinctive name so nothing an admin created
        // independently gets touched. `add()`/`update()` on this category elsewhere always match
        // by current name, so an already-created row has to be renamed here too, not just in
        // CategoryBuilder::CATEGORIES, or the next wizard run would create a duplicate instead of
        // reusing it.
        $DB->update(
            'glpi_itilcategories',
            ['name' => 'Messagerie & Collaboration'],
            ['name' => 'Microsoft 365 / Workspace']
        );
        // The icon translation's value is only set once at creation (CategoryBuilder::buildNode()
        // never updates it on reuse) — has to be fixed up here too, or it keeps showing the old
        // name text next to the (unchanged) icon.
        $renamedCategory = $DB->request('glpi_itilcategories', ['name' => 'Messagerie & Collaboration'])->current();
        if ($renamedCategory !== null) {
            $DB->update(
                'glpi_dropdowntranslations',
                ['value' => '🟧 Messagerie & Collaboration'],
                [
                    'itemtype' => 'ITILCategory',
                    'items_id' => $renamedCategory['id'],
                    'field' => 'name',
                    'value' => '🟧 Microsoft 365 / Workspace',
                ]
            );
            // GLPI auto-derives a second "completename" translation (breadcrumb path, e.g. "IT &
            // SI > Microsoft 365 / Workspace") from the "name" one whenever it's created — fixing
            // the "name" row above doesn't touch it, it has to be explicitly regenerated.
            DropdownTranslation::regenerateAllCompletenameTranslationsFor('ITILCategory', $renamedCategory['id']);
        }
        // Same vendor-neutral rename for the matching Service Catalog form (Sprint 23).
        $DB->update(
            'glpi_forms_forms',
            ['name' => "Demande d'accès à un espace collaboratif d'équipe"],
            ['name' => 'Demande d\'accès à un espace Teams / SharePoint']
        );

        // Service Catalog rubrics/forms created before ServiceCatalogBuilder set `illustration`
        // (Sprint 23's first version) are stuck with GLPI's generic default icon
        // (IllustrationManager::DEFAULT_ILLUSTRATION) forever, since it's only set at creation —
        // backfilled here, guarded to not overwrite anything an admin already customized by hand.
        $branchIllustrations = [
            '💻 IT & SI' => 'asset-desktop-1',
            '🏢 Bâtiment & Moyens Généraux' => 'building',
            '🚗 Flotte Automobile & Mobilité' => 'car',
            '👤 Ressources Humaines' => 'group',
            '🛒 Achats & Logistique' => 'order-supplies',
            '🔐 Sécurité & Protection des Personnes' => 'security',
            '🧹 Services Généraux & Vie au Travail' => 'inventory',
            '📄 Administratif, Juridique & Finance' => 'legal',
            '📢 Communication & Marketing' => 'presentation',
            '📋 Qualité, QHSE & Conformité' => 'diagnostic',
            '⚙️ Maintenance Industrielle & Technique' => 'factory',
        ];
        foreach ($branchIllustrations as $categoryName => $illustration) {
            $DB->update(
                'glpi_forms_categories',
                ['illustration' => $illustration],
                ['name' => $categoryName, 'forms_categories_id' => 0, 'illustration' => '']
            );
            $formCategory = $DB->request('glpi_forms_categories', ['name' => $categoryName, 'forms_categories_id' => 0])->current();
            if ($formCategory !== null) {
                $DB->update(
                    'glpi_forms_forms',
                    ['illustration' => $illustration],
                    ['forms_categories_id' => $formCategory['id'], 'illustration' => '']
                );
            }
        }

        Profile::install($migration);

        $migration->executeMigration();

        if ($slaTiersSeed !== null) {
            $DB->update(self::CONFIGS_TABLE, ['sla_tiers' => json_encode($slaTiersSeed)], ['id' => 1]);
        }

        if ($generalSettingsSeed !== null) {
            $DB->update(self::CONFIGS_TABLE, [
                'general_ui_enabled' => $generalSettingsSeed,
                'notifications_enabled' => $generalSettingsSeed,
                'financial_info_enabled' => $generalSettingsSeed,
                'project_task_states_enabled' => $generalSettingsSeed,
                'satisfaction_survey_enabled' => $generalSettingsSeed,
                'committee_validation_enabled' => $generalSettingsSeed,
            ], ['id' => 1]);
        }

        // Retroactive fix for a real bug found live: CalendarBuilder never set entities_id/
        // is_recursive when creating a calendar, silently defaulting to root + *not* recursive —
        // every calendar this plugin ever created was invisible from any sub-entity's own admin
        // context, even though the entity's calendars_id still correctly pointed to it. Scoped by
        // name prefix (this plugin's own naming, "Horaires standard"/"Horaires — <name>") rather
        // than blindly touching every row in glpi_calendars, which could include calendars this
        // plugin never created (GLPI's own native "Default" among them).
        $DB->update('glpi_calendars', ['is_recursive' => 1], [
            'entities_id' => 0,
            'is_recursive' => 0,
            ['OR' => [
                'name' => 'Horaires standard',
                ['name' => ['LIKE', 'Horaires — %']],
            ]],
        ]);

        return true;
    }

    public function uninstall(Migration $migration): bool
    {
        global $DB;

        $DB->doQuery("DROP TABLE IF EXISTS `" . self::PROFILES_TABLE . "`");
        $DB->doQuery("DROP TABLE IF EXISTS `" . self::CONFIGS_TABLE . "`");
        $DB->doQuery("DROP TABLE IF EXISTS `" . self::FUELTYPES_TABLE . "`");

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
