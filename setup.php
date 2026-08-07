<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto Plugin Setup
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Configuration GLPI Auto.
 *
 * Configuration GLPI Auto is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * Configuration GLPI Auto is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Configuration GLPI Auto. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2026 by Parime.
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/parime/Configuration-glpi-auto
 * -------------------------------------------------------------------------
 */

/**
 * Setup class for Configuration GLPI Auto plugin
 * Handles all database setup, migration, and plugin registration
 */
class PluginConfigurationglpiautoSetup
{
    use \Glpi\Plugin\Hooks;

    /**
     * Plugin key
     */
    const PLUGIN_KEY = 'configurationglpiauto';

    /**
     * Plugin name
     */
    const PLUGIN_NAME = 'Configuration GLPI Auto';

    /**
     * Plugin version
     */
    const PLUGIN_VERSION = '1.0.0';

    /**
     * Minimum GLPI version required
     */
    const MIN_GLPI_VERSION = '11.0.0';

    /**
     * PHP version required
     */
    const PHP_VERSION = '8.2';

    /**
     * License
     */
    const LICENSE = 'GPLv3+';

    /**
     * Author
     */
    const AUTHOR = 'Parime';

    /**
     * Plugin homepage
     */
    const HOMEPAGE = 'https://github.com/parime/Configuration-glpi-auto';

    /**
     * Database tables to create
     */
    private static $tables = [
        // Configuration profiles
        'glpi_plugin_configurationglpiauto_profiles',
        'glpi_plugin_configurationglpiauto_profile_modules',
        
        // Modules
        'glpi_plugin_configurationglpiauto_modules',
        'glpi_plugin_configurationglpiauto_module_items',
        'glpi_plugin_configurationglpiauto_module_categories',
        
        // Deployments
        'glpi_plugin_configurationglpiauto_deployments',
        'glpi_plugin_configurationglpiauto_deployment_logs',
        'glpi_plugin_configurationglpiauto_deployment_items',
        
        // Blueprints
        'glpi_plugin_configurationglpiauto_blueprints',
        'glpi_plugin_configurationglpiauto_blueprint_items',
        
        // Audit
        'glpi_plugin_configurationglpiauto_audit_results',
        'glpi_plugin_configurationglpiauto_audit_findings',
        
        // Locations/Geocoding
        'glpi_plugin_configurationglpiauto_locations',
        
        // Settings
        'glpi_plugin_configurationglpiauto_settings',
    ];

    /**
     * Get plugin information
     */
    public static function getInfo()
    {
        return [
            'name' => self::PLUGIN_NAME,
            'version' => self::PLUGIN_VERSION,
            'min_glpi_version' => self::MIN_GLPI_VERSION,
            'php_version' => self::PHP_VERSION,
            'license' => self::LICENSE,
            'author' => self::AUTHOR,
            'homepage' => self::HOMEPAGE,
        ];
    }

    /**
     * Plugin installation
     */
    public static function install()
    {
        global $DB;

        // Check PHP version
        if (version_compare(PHP_VERSION, self::PHP_VERSION, '<')) {
            throw new \RuntimeException(
                sprintf(
                    'PHP version %s or higher is required. Current version: %s',
                    self::PHP_VERSION,
                    PHP_VERSION
                )
            );
        }

        // Check GLPI version
        if (!\Glpi::testVersion(self::MIN_GLPI_VERSION)) {
            throw new \RuntimeException(
                sprintf(
                    'GLPI version %s or higher is required',
                    self::MIN_GLPI_VERSION
                )
            );
        }

        // Create database tables
        self::createTables();

        // Insert default data
        self::insertDefaultData();

        // Register hooks
        self::registerHooks();

        // Install translations
        self::installTranslations();

        return true;
    }

    /**
     * Create database tables
     */
    private static function createTables()
    {
        global $DB;

        $migration_service = new \GlpiPlugin\Configurationglpiauto\Migration\MigrationService();
        $migration_service->createAllTables();
    }

    /**
     * Insert default data
     */
    private static function insertDefaultData()
    {
        // Insert default configuration profiles
        $default_profiles = [
            [
                'name' => 'Installation minimale',
                'description' => 'Configuration minimale pour une installation basique',
                'type' => 'minimal',
                'is_active' => 1,
                'sort_order' => 1,
            ],
            [
                'name' => 'PME',
                'description' => 'Configuration adaptée aux PME (Petites et Moyennes Entreprises)',
                'type' => 'sme',
                'is_active' => 1,
                'sort_order' => 2,
            ],
            [
                'name' => 'ETI',
                'description' => 'Configuration adaptée aux ETI (Entreprises de Taille Intermédiaire)',
                'type' => 'eti',
                'is_active' => 1,
                'sort_order' => 3,
            ],
            [
                'name' => 'Grande entreprise',
                'description' => 'Configuration complète pour les grandes entreprises',
                'type' => 'enterprise',
                'is_active' => 1,
                'sort_order' => 4,
            ],
            [
                'name' => 'MSP',
                'description' => 'Configuration pour les MSP (Managed Service Providers)',
                'type' => 'msp',
                'is_active' => 1,
                'sort_order' => 5,
            ],
            [
                'name' => 'ISO 27001',
                'description' => 'Configuration conforme aux normes ISO 27001',
                'type' => 'iso27001',
                'is_active' => 1,
                'sort_order' => 6,
            ],
            [
                'name' => 'ITIL',
                'description' => 'Configuration basée sur les bonnes pratiques ITIL',
                'type' => 'itil',
                'is_active' => 1,
                'sort_order' => 7,
            ],
            [
                'name' => 'Personnalisé',
                'description' => 'Configuration personnalisée selon vos besoins spécifiques',
                'type' => 'custom',
                'is_active' => 1,
                'sort_order' => 8,
            ],
        ];

        foreach ($default_profiles as $profile) {
            $profile_entity = new \GlpiPlugin\Configurationglpiauto\Entity\ConfigurationProfile();
            $profile_entity->add($profile);
        }

        // Insert default modules
        $default_modules = self::getDefaultModules();
        foreach ($default_modules as $module) {
            $module_entity = new \GlpiPlugin\Configurationglpiauto\Entity\Module();
            $module_entity->add($module);
        }

        // Insert default settings
        $default_settings = [
            'geocoding_provider' => 'openstreetmap',
            'geocoding_api_key' => '',
            'backup_before_deploy' => 1,
            'dry_run_default' => 1,
            'notification_email' => '',
            'max_execution_time' => 300,
            'debug_mode' => 0,
        ];

        foreach ($default_settings as $key => $value) {
            \Config::setConfigurationValues(
                self::PLUGIN_KEY,
                [$key => $value]
            );
        }
    }

    /**
     * Get default modules
     */
    private static function getDefaultModules()
    {
        return [
            // Configuration générale
            [
                'name' => 'Paramètres généraux',
                'key' => 'general_settings',
                'category' => 'configuration',
                'description' => 'Configuration des paramètres généraux de GLPI',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 1,
            ],
            [
                'name' => 'Tickets',
                'key' => 'tickets',
                'category' => 'configuration',
                'description' => 'Configuration des tickets',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 2,
            ],
            [
                'name' => 'Problèmes',
                'key' => 'problems',
                'category' => 'configuration',
                'description' => 'Configuration des problèmes',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 3,
            ],
            [
                'name' => 'Changements',
                'key' => 'changes',
                'category' => 'configuration',
                'description' => 'Configuration des changements',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 4,
            ],
            [
                'name' => 'Tâches',
                'key' => 'tasks',
                'category' => 'configuration',
                'description' => 'Configuration des tâches',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 5,
            ],
            [
                'name' => 'Notifications',
                'key' => 'notifications',
                'category' => 'configuration',
                'description' => 'Configuration des notifications',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 6,
            ],
            [
                'name' => 'Emails',
                'key' => 'emails',
                'category' => 'configuration',
                'description' => 'Configuration des emails',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 7,
            ],

            // Calendriers
            [
                'name' => 'Création',
                'key' => 'calendars_creation',
                'category' => 'calendars',
                'description' => 'Création des calendriers',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 10,
            ],
            [
                'name' => 'Horaires',
                'key' => 'calendars_schedules',
                'category' => 'calendars',
                'description' => 'Configuration des horaires',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 11,
            ],
            [
                'name' => 'Jours fériés',
                'key' => 'calendars_holidays',
                'category' => 'calendars',
                'description' => 'Import des jours fériés',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 12,
            ],

            // SLA
            [
                'name' => 'SLA',
                'key' => 'sla',
                'category' => 'sla',
                'description' => 'Configuration des SLA',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 20,
            ],
            [
                'name' => 'OLA',
                'key' => 'ola',
                'category' => 'sla',
                'description' => 'Configuration des OLA',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 21,
            ],
            [
                'name' => 'Escalades',
                'key' => 'escalations',
                'category' => 'sla',
                'description' => 'Configuration des escalades',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 22,
            ],

            // Entités
            [
                'name' => 'Assistant de création',
                'key' => 'entities_wizard',
                'category' => 'entities',
                'description' => 'Assistant de création des entités',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 30,
            ],
            [
                'name' => 'Arborescence',
                'key' => 'entities_hierarchy',
                'category' => 'entities',
                'description' => 'Création de l\'arborescence des entités',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 31,
            ],

            // Catalogue de services
            [
                'name' => 'Création complète',
                'key' => 'service_catalog_creation',
                'category' => 'service_catalog',
                'description' => 'Création complète du catalogue de services',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 40,
            ],

            // Templates
            [
                'name' => 'Templates de tickets',
                'key' => 'ticket_templates',
                'category' => 'templates',
                'description' => 'Création des templates de tickets',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 50,
            ],
            [
                'name' => 'Templates de problèmes',
                'key' => 'problem_templates',
                'category' => 'templates',
                'description' => 'Création des templates de problèmes',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 51,
            ],
            [
                'name' => 'Templates de changements',
                'key' => 'change_templates',
                'category' => 'templates',
                'description' => 'Création des templates de changements',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 52,
            ],

            // Actifs personnalisés
            [
                'name' => 'Actifs personnalisés',
                'key' => 'custom_assets',
                'category' => 'assets',
                'description' => 'Création des actifs personnalisés',
                'type' => 'plugin',
                'plugin_dependency' => ['genericobjects', 'fields'],
                'is_active' => 0,
                'sort_order' => 60,
            ],

            // Branding
            [
                'name' => 'Branding',
                'key' => 'branding',
                'category' => 'appearance',
                'description' => 'Personnalisation graphique (logo, couleurs, favicon)',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 70,
            ],

            // Plugins
            [
                'name' => 'Détection des plugins',
                'key' => 'plugins_detection',
                'category' => 'plugins',
                'description' => 'Détection automatique des plugins installés',
                'type' => 'plugin',
                'is_active' => 1,
                'sort_order' => 80,
            ],
            [
                'name' => 'Configuration automatique',
                'key' => 'plugins_auto_config',
                'category' => 'plugins',
                'description' => 'Configuration automatique des plugins détectés',
                'type' => 'plugin',
                'is_active' => 1,
                'sort_order' => 81,
            ],

            // Audit
            [
                'name' => 'Mode Audit',
                'key' => 'audit_mode',
                'category' => 'audit',
                'description' => 'Analyse d\'une instance existante et détection des problèmes',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 90,
            ],

            // Blueprints
            [
                'name' => 'Export Blueprints',
                'key' => 'blueprints_export',
                'category' => 'blueprints',
                'description' => 'Export de la configuration sous forme de Blueprint JSON',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 100,
            ],
            [
                'name' => 'Import Blueprints',
                'key' => 'blueprints_import',
                'category' => 'blueprints',
                'description' => 'Import de Blueprints pour appliquer des configurations prédéfinies',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 101,
            ],

            // Lieux
            [
                'name' => 'Assistant de lieux',
                'key' => 'locations_wizard',
                'category' => 'locations',
                'description' => 'Assistant intelligent pour la création des lieux avec géocodage',
                'type' => 'core',
                'is_active' => 1,
                'sort_order' => 110,
            ],
        ];
    }

    /**
     * Register plugin hooks
     */
    private static function registerHooks()
    {
        $hook_service = new \GlpiPlugin\Configurationglpiauto\Service\HookService();
        $hook_service->registerAllHooks();
    }

    /**
     * Install translations
     */
    private static function installTranslations()
    {
        // Copy translation files
        $locales_path = GLPI_PLUGIN_DOC_DIR . '/' . self::PLUGIN_KEY . '/locales';
        $target_path = GLPI_LOCAL_DIR . '/' . self::PLUGIN_KEY;

        if (is_dir($locales_path) && !is_dir($target_path)) {
            \Glpi\Toolbox::copyr($locales_path, $target_path);
        }
    }

    /**
     * Plugin uninstallation
     */
    public static function uninstall()
    {
        global $DB;

        // Remove database tables
        $migration_service = new \GlpiPlugin\Configurationglpiauto\Migration\MigrationService();
        $migration_service->dropAllTables();

        // Remove plugin configurations
        \Config::deleteConfigurationValues(self::PLUGIN_KEY);

        // Unregister hooks
        self::unregisterHooks();

        // Remove translations
        self::uninstallTranslations();

        return true;
    }

    /**
     * Unregister plugin hooks
     */
    private static function unregisterHooks()
    {
        $hook_service = new \GlpiPlugin\Configurationglpiauto\Service\HookService();
        $hook_service->unregisterAllHooks();
    }

    /**
     * Uninstall translations
     */
    private static function uninstallTranslations()
    {
        $target_path = GLPI_LOCAL_DIR . '/' . self::PLUGIN_KEY;
        if (is_dir($target_path)) {
            \Glpi\Toolbox::deleteDir($target_path);
        }
    }

    /**
     * Plugin upgrade
     */
    public static function upgrade()
    {
        $migration_service = new \GlpiPlugin\Configurationglpiauto\Migration\MigrationService();
        return $migration_service->runMigrations();
    }

    /**
     * Check if plugin is installed
     */
    public static function isInstalled()
    {
        return \Plugin::isPluginInstalled(self::PLUGIN_KEY);
    }

    /**
     * Check if plugin is activated
     */
    public static function isActivated()
    {
        return \Plugin::isPluginActivated(self::PLUGIN_KEY);
    }

    /**
     * Get plugin directory
     */
    public static function getPluginDir()
    {
        return GLPI_PLUGIN_DOC_DIR . '/' . self::PLUGIN_KEY;
    }

    /**
     * Get plugin web directory
     */
    public static function getWebDir()
    {
        return PLUGINS_WEB_DIR . '/' . self::PLUGIN_KEY;
    }
}
