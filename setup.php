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

use Glpi\Plugin\Hooks;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\FuelType;

// Hard runtime requirement: GLPI does not autoload plugin src/ classes on its own.
// `composer install --no-dev` must be run after cloning, and any release package must bundle
// vendor/ — same constraint documented on the sibling glpi-vulnerability-manager plugin.
require_once __DIR__ . '/vendor/autoload.php';

define('PLUGIN_CONFIGURATIONGLPIAUTO_VERSION', '0.66.1');
define('PLUGIN_CONFIGURATIONGLPIAUTO_MIN_GLPI', '11.0.0');
define('PLUGIN_CONFIGURATIONGLPIAUTO_MAX_GLPI', '11.99.99');
define('PLUGIN_CONFIGURATIONGLPIAUTO_MIN_PHP', '8.2.0');

/**
 * Called by GLPI on every page load once the plugin is active. Registers hooks.
 */
function plugin_init_configurationglpiauto(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['configurationglpiauto'] = true;

    if (!Plugin::isPluginActive('configurationglpiauto')) {
        return;
    }

    // Format attendu par le coeur GLPI : une liste plate de classes par categorie (pas
    // ['types'=>[...],'icon'=>...]) — meme piege deja documente sur remise-glpi.
    //
    // Categorie 'config' (menu natif "Configuration" de GLPI), pas 'admin' : ce CRUD ne gere que
    // les modeles de profil de configuration propres au plugin (donnees utilisees par l'etape 1
    // de l'assistant), rien qui touche Utilisateurs/Groupes/Entites/Regles — le classer dans
    // "Administration" melangeait un ecran propre au plugin avec les reglages natifs de GLPI.
    // Retour utilisateur direct sur ce placement, corrige ici.
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['configurationglpiauto'] = [
        'config' => [ConfigurationProfile::class],
    ];

    // Icone "Configurer" sur la ligne du plugin dans Configuration > Plugins. Pointe vers
    // l'assistant complet (meme page que le menu principal, cf. ConfigurationProfile::
    // getSearchURL()) — l'ancien formulaire a une seule page (front/config.php, entity_mode +
    // entity_tree seulement, sans calendrier/SLA/personnalisation) a ete retire pour ne pas
    // laisser deux points d'entree incoherents (Sprint 11).
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['configurationglpiauto'] = 'front/wizard.php';

    Plugin::registerClass(ConfigurationProfile::class);
    Plugin::registerClass(Config::class);
    Plugin::registerClass(FuelType::class);
}

/**
 * Plugin metadata displayed in GLPI's plugin list.
 */
function plugin_version_configurationglpiauto(): array
{
    return [
        'name'         => 'Configuration GLPI Auto',
        'version'      => PLUGIN_CONFIGURATIONGLPIAUTO_VERSION,
        'author'       => 'Vincent GUILLOTTE',
        'license'      => 'GPLv3',
        'homepage'     => 'https://github.com/parime/Configuration-glpi-auto',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_CONFIGURATIONGLPIAUTO_MIN_GLPI,
                'max' => PLUGIN_CONFIGURATIONGLPIAUTO_MAX_GLPI,
            ],
            'php' => [
                'min' => PLUGIN_CONFIGURATIONGLPIAUTO_MIN_PHP,
            ],
        ],
    ];
}

/**
 * Checked by GLPI before allowing activation. Must not assume GLPI's own autoloading has run for
 * plugin classes yet, hence the explicit require above.
 */
function plugin_configurationglpiauto_check_prerequisites(): bool
{
    if (version_compare(PHP_VERSION, PLUGIN_CONFIGURATIONGLPIAUTO_MIN_PHP, '<')) {
        echo sprintf('Cette version du plugin nécessite PHP %s minimum.', PLUGIN_CONFIGURATIONGLPIAUTO_MIN_PHP);
        return false;
    }

    if (defined('GLPI_VERSION') && version_compare(GLPI_VERSION, PLUGIN_CONFIGURATIONGLPIAUTO_MIN_GLPI, '<')) {
        echo sprintf('Cette version du plugin nécessite GLPI %s minimum.', PLUGIN_CONFIGURATIONGLPIAUTO_MIN_GLPI);
        return false;
    }

    return true;
}

/**
 * Checked by GLPI to verify the plugin's own configuration is valid (none required yet).
 */
function plugin_configurationglpiauto_check_config(bool $verbose = false): bool
{
    return true;
}
