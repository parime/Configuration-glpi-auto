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

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Configurationglpiauto\AddressPlan\AddressPlanImporter;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\FrontUrl;

Session::checkRight(ConfigurationProfile::$rightname, READ);

Html::header(ConfigurationProfile::getTypeName(2), FrontUrl::get('profile.php'), 'config', ConfigurationProfile::class);

// Search::show() alone generates no create link (same pitfall documented on remise-glpi). Chaque
// bouton n'apparaît qu'avec les droits que sa page exige.
TemplateRenderer::getInstance()->display('@configurationglpiauto/profile_actions.html.twig', [
    'wizard_url'       => FrontUrl::get('wizard.php'),
    // Issue #112 : audit de toute l'instance, exige aussi le droit natif 'config' en lecture.
    'audit_url'        => Session::haveRight(Config::$rightname, READ) && Session::haveRight('config', READ)
        ? FrontUrl::get('audit.php') : null,
    // Issue #113 : import d'un Blueprint.
    'blueprint_url'    => ConfigurationProfile::canCreate() ? FrontUrl::get('blueprint_import.php') : null,
    // Issue #345 : import de tickets Jira (CSV).
    'jira_url'         => Session::haveRight(Config::$rightname, UPDATE) && Session::haveRight(Ticket::$rightname, CREATE)
        ? FrontUrl::get('jiraimport.php') : null,
    // Issue #343 : plan d'adressage (réseaux IP et VLAN par site).
    'address_plan_url' => Session::haveRight(Config::$rightname, UPDATE) && AddressPlanImporter::canImport()
        ? FrontUrl::get('addressplan.php') : null,
    // Issue #114 : historique et retour arrière.
    'history_url'      => FrontUrl::get('history.php'),
    'add_url'          => ConfigurationProfile::canCreate() ? ConfigurationProfile::getFormURL() : null,
]);

Search::show(ConfigurationProfile::class);

Html::footer();
