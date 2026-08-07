<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto Plugin
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

use GlpiPlugin\Configurationglpiauto\Console\Commands\ConfigurationCommand;
use GlpiPlugin\Configurationglpiauto\Console\Commands\AuditCommand;
use GlpiPlugin\Configurationglpiauto\Console\Commands\BlueprintCommand;
use GlpiPlugin\Configurationglpiauto\Controller\WizardController;
use GlpiPlugin\Configurationglpiauto\Service\ConfigurationService;
use GlpiPlugin\Configurationglpiauto\Service\AuditService;
use GlpiPlugin\Configurationglpiauto\Service\BlueprintService;
use GlpiPlugin\Configurationglpiauto\Service\NotificationService;
use GlpiPlugin\Configurationglpiauto\Provider\GeocodingProvider;

// Define plugin version
function plugin_configurationglpiauto_version() {
    return ['number' => '1.0.0', 'name' => 'Configuration GLPI Auto'];
}

// Plugin init - Register plugin
function plugin_init_configurationglpiauto() {
    global $PLUGIN_HOOKS;

    // Load plugin translations
    $PLUGIN_HOOKS['translate']['configurationglpiauto'] = 'plugins/configurationglpiauto';

    // Register plugin menu entries
    $PLUGIN_HOOKS['menu_toadd']['configurationglpiauto'] = 'plugin_configurationglpiauto_menu';

    // Register plugin config page
    $PLUGIN_HOOKS['config_page']['configurationglpiauto'] = 'front/wizard.php';

    // Register CSS and JS
    $PLUGIN_HOOKS['add_css']['configurationglpiauto'] = ['plugins/configurationglpiauto/css/wizard.css'];
    $PLUGIN_HOOKS['add_javascript']['configurationglpiauto'] = ['plugins/configurationglpiauto/js/wizard.js'];

    // Register AJAX handlers
    $PLUGIN_HOOKS['ajax']['configurationglpiauto'] = 'plugin_configurationglpiauto_ajax';

    // Register console commands
    $PLUGIN_HOOKS['cli']['configurationglpiauto'] = 'plugin_configurationglpiauto_cli';

    // Register migration handlers
    $PLUGIN_HOOKS['migrate']['configurationglpiauto'] = 'plugin_configurationglpiauto_migrate';

    // Register item types
    $PLUGIN_HOOKS['item_types']['configurationglpiauto'] = [
        'GlpiPlugin\\Configurationglpiauto\\Entity\\ConfigurationProfile',
        'GlpiPlugin\\Configurationglpiauto\\Entity\\DeploymentLog',
        'GlpiPlugin\\Configurationglpiauto\\Entity\\Blueprint',
        'GlpiPlugin\\Configurationglpiauto\\Entity\\AuditResult',
    ];
}

// Plugin menu entries
function plugin_configurationglpiauto_menu() {
    $menu = [];

    // Main menu entry
    $menu['title'] = __('Configuration GLPI Auto', 'configurationglpiauto');
    $menu['page'] = '/front/wizard.php';
    $menu['links']['search'] = '/front/wizard.php';
    $menu['links']['config'] = '/front/config.php';

    // Add to Plugins menu
    $menu['plugins']['configurationglpiauto']['title'] = __('Configuration Auto', 'configurationglpiauto');
    $menu['plugins']['configurationglpiauto']['links']['wizard'] = '/front/wizard.php';
    $menu['plugins']['configurationglpiauto']['links']['profiles'] = '/front/profile.php';
    $menu['plugins']['configurationglpiauto']['links']['blueprints'] = '/front/blueprint.php';
    $menu['plugins']['configurationglpiauto']['links']['audit'] = '/front/audit.php';
    $menu['plugins']['configurationglpiauto']['links']['logs'] = '/front/logs.php';
    $menu['plugins']['configurationglpiauto']['links']['settings'] = '/front/config.php';

    return $menu;
}

// AJAX handler
function plugin_configurationglpiauto_ajax() {
    global $CFG_GLPI;

    // Handle AJAX requests for the wizard
    if (isset($_POST['action']) && isset($_POST['plugin'])) {
        switch ($_POST['action']) {
            case 'get_wizard_step':
                $controller = new WizardController();
                $controller->getWizardStep($_POST);
                break;

            case 'save_configuration':
                $controller = new WizardController();
                $controller->saveConfiguration($_POST);
                break;

            case 'validate_step':
                $controller = new WizardController();
                $controller->validateStep($_POST);
                break;

            case 'deploy_configuration':
                $service = new ConfigurationService();
                $service->deployConfiguration($_POST);
                break;

            case 'geocode_address':
                $provider = new GeocodingProvider();
                $provider->geocodeAddress($_POST);
                break;

            case 'export_blueprint':
                $service = new BlueprintService();
                $service->exportBlueprint($_POST);
                break;

            case 'import_blueprint':
                $service = new BlueprintService();
                $service->importBlueprint($_POST);
                break;

            case 'run_audit':
                $service = new AuditService();
                $service->runAudit($_POST);
                break;
        }
    }
}

// CLI commands registration
function plugin_configurationglpiauto_cli() {
    // Register console commands
    ConsoleCommand::register();
    AuditCommand::register();
    BlueprintCommand::register();
}

// Migration handler
function plugin_configurationglpiauto_migrate() {
    $migration_service = new \GlpiPlugin\Configurationglpiauto\Migration\MigrationService();
    return $migration_service->handleMigrations();
}

// Database relations
function plugin_configurationglpiauto_getDatabaseRelations() {
    return [
        'glpi_plugin_configurationglpiauto_profiles' => [
            'glpi_plugin_configurationglpiauto_modules' => 'plugin_configurationglpiauto_profiles_id',
            'glpi_plugin_configurationglpiauto_deployments' => 'plugin_configurationglpiauto_profiles_id',
        ],
        'glpi_plugin_configurationglpiauto_modules' => [
            'glpi_plugin_configurationglpiauto_module_items' => 'plugin_configurationglpiauto_modules_id',
        ],
        'glpi_plugin_configurationglpiauto_deployments' => [
            'glpi_plugin_configurationglpiauto_deployment_logs' => 'plugin_configurationglpiauto_deployments_id',
            'glpi_plugin_configurationglpiauto_deployment_items' => 'plugin_configurationglpiauto_deployments_id',
        ],
        'glpi_plugin_configurationglpiauto_blueprints' => [
            'glpi_plugin_configurationglpiauto_blueprint_items' => 'plugin_configurationglpiauto_blueprints_id',
        ],
        'glpi_plugin_configurationglpiauto_audit_results' => [
            'glpi_plugin_configurationglpiauto_audit_findings' => 'plugin_configurationglpiauto_audit_results_id',
        ],
    ];
}

// Dropdown tables
function plugin_configurationglpiauto_getDropdown() {
    return [
        'GlpiPlugin\\Configurationglpiauto\\Entity\\ConfigurationProfile' => __s('Configuration Profiles', 'configurationglpiauto'),
        'GlpiPlugin\\Configurationglpiauto\\Entity\\DeploymentStatus' => __s('Deployment Status', 'configurationglpiauto'),
    ];
}

// Plugin activation
function plugin_configurationglpiauto_install() {
    // Create database tables
    $migration_service = new \GlpiPlugin\Configurationglpiauto\Migration\MigrationService();
    $migration_service->install();
    
    // Initialize default configurations
    $config_service = new ConfigurationService();
    $config_service->initializeDefaultConfigurations();
    
    // Register hooks
    $hook_service = new \GlpiPlugin\Configurationglpiauto\Service\HookService();
    $hook_service->registerHooks();
    
    return true;
}

// Plugin uninstall
function plugin_configurationglpiauto_uninstall() {
    // Clean up database tables
    $migration_service = new \GlpiPlugin\Configurationglpiauto\Migration\MigrationService();
    $migration_service->uninstall();
    
    // Remove hooks
    $hook_service = new \GlpiPlugin\Configurationglpiauto\Service\HookService();
    $hook_service->unregisterHooks();
    
    return true;
}

// Plugin upgrade
function plugin_configurationglpiauto_upgrade() {
    $migration_service = new \GlpiPlugin\Configurationglpiauto\Migration\MigrationService();
    return $migration_service->upgrade();
}

// Search options for plugin items
function plugin_configurationglpiauto_getAddSearchOptions($itemtype) {
    $search_options = [];
    
    if ($itemtype == 'GlpiPlugin\\Configurationglpiauto\\Entity\\ConfigurationProfile') {
        $search_options[1000]['table'] = 'glpi_plugin_configurationglpiauto_profiles';
        $search_options[1000]['field'] = 'name';
        $search_options[1000]['name'] = __('Profile Name', 'configurationglpiauto');
        $search_options[1000]['datatype'] = 'itemlink';
        $search_options[1000]['itemlink_type'] = 'GlpiPlugin\\Configurationglpiauto\\Entity\\ConfigurationProfile';
    }
    
    return $search_options;
}

// Plugin specific values to display
function plugin_configurationglpiauto_giveItem($type, $ID, $data, $num) {
    $searchopt = &\Search::getOptions($type);
    $table = $searchopt[$ID]['table'];
    $field = $searchopt[$ID]['field'];

    switch ($table . '.' . $field) {
        case 'glpi_plugin_configurationglpiauto_profiles.name':
            return \Dropdown::getDropdownName(
                'GlpiPlugin\\Configurationglpiauto\\Entity\\ConfigurationProfile',
                $data['id']
            );
        
        default:
            return '';
    }
}

// Massive actions for plugin items
function plugin_configurationglpiauto_MassiveActions($itemtype) {
    $actions = [];
    
    if ($itemtype == 'GlpiPlugin\\Configurationglpiauto\\Entity\\ConfigurationProfile') {
        $actions['delete'] = __s('Delete permanently', 'configurationglpiauto');
        $actions['export'] = __s('Export as blueprint', 'configurationglpiauto');
    }
    
    return $actions;
}

// Plugin notifications
function plugin_configurationglpiauto_addDefaultWhere($itemtype, $ID, $data) {
    // Add specific conditions for plugin notifications
    return '';
}

// Plugin specific configurations
function plugin_configurationglpiauto_getConfig() {
    return [
        'version' => '1.0.0',
        'min_glpi_version' => '11.0.0',
        'php_version' => '8.2',
        'license' => 'GPLv3+',
        'author' => 'Parime',
        'homepage' => 'https://github.com/parime/Configuration-glpi-auto',
    ];
}
