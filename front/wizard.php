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

use GlpiPlugin\Configurationglpiauto\BrandingBuilder;
use GlpiPlugin\Configurationglpiauto\CalendarBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\EntityBuilder;
use GlpiPlugin\Configurationglpiauto\SlaBuilder;

Session::checkRight(Config::$rightname, READ);

if (isset($_POST['finish'])) {
    Session::checkRight(Config::$rightname, UPDATE);

    $config = Config::getConfig();
    $config->update($_POST + ['id' => $config->getID()]);

    $created = (new EntityBuilder())->build($config);
    $entityIds = EntityBuilder::topEntityIds($created) ?: [0];

    $calendarId = (new CalendarBuilder())->build($config);
    if ($calendarId !== null) {
        (new CalendarBuilder())->assignToEntities($calendarId, $entityIds);
    }

    $slaIds = (new SlaBuilder())->build($config, $calendarId);
    if ($slaIds !== null) {
        (new SlaBuilder())->assignToEntities($slaIds, $entityIds);
    }

    $brandingApplied = (new BrandingBuilder())->apply($config, $entityIds);

    $messages = [];
    $messages[] = empty($created)
        ? __('Aucune entité à créer (mode mono-entité, ou arborescence vide).', 'configurationglpiauto')
        : sprintf(__('Structure créée : %s.', 'configurationglpiauto'), EntityBuilder::describe($created));
    if ($calendarId !== null) {
        $messages[] = __('Calendrier créé et assigné.', 'configurationglpiauto');
    }
    if ($slaIds !== null) {
        $messages[] = __('SLA créés et assignés.', 'configurationglpiauto');
    }
    if ($brandingApplied) {
        $messages[] = __('Personnalisation graphique appliquée.', 'configurationglpiauto');
    }
    Session::addMessageAfterRedirect(implode(' ', $messages));

    Html::redirect(ConfigurationProfile::getSearchURL());
}

Html::header(__('Assistant de configuration', 'configurationglpiauto'), $_SERVER['PHP_SELF'], 'admin', ConfigurationProfile::class);

$config = Config::getConfig();
$profiles = (new ConfigurationProfile())->find(['is_active' => 1], ['sort_order ASC']);

$profileDefaults = [];
foreach ($profiles as $profile) {
    $profileDefaults[$profile['id']] = ConfigurationProfile::getSuggestedDefaults($profile['type']);
}

\Glpi\Application\View\TemplateRenderer::getInstance()->display('@configurationglpiauto/wizard.html.twig', [
    'config'           => $config->fields,
    'profiles'         => $profiles,
    'profile_defaults' => $profileDefaults,
    'modes'            => Config::getModes(),
    'max_levels'       => Config::MAX_LEVELS,
    'entity_tree'      => $config->getEntityTree(),
    'csrf_token'       => Session::getNewCSRFToken(),
]);

Html::footer();
