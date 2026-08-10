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
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\EntityBuilder;
use GlpiPlugin\Configurationglpiauto\GeneralSettingsBuilder;
use GlpiPlugin\Configurationglpiauto\HelpdeskFormBuilder;
use GlpiPlugin\Configurationglpiauto\SlaBuilder;
use GlpiPlugin\Configurationglpiauto\StateBuilder;
use GlpiPlugin\Configurationglpiauto\TicketTemplateBuilder;

Session::checkRight(Config::$rightname, READ);

if (isset($_POST['finish'])) {
    Session::checkRight(Config::$rightname, UPDATE);

    $config = Config::getConfig();
    $config->update($_POST + ['id' => $config->getID()]);

    $created = (new EntityBuilder())->build($config);
    $entityIds = EntityBuilder::topEntityIds($created) ?: [0];

    // One pass over the top-level nodes (= MSP clients), pairing each EntityBuilder result with
    // its matching entity_tree node (same order/index) to check for a per-client calendar/SLA
    // override (Config::sanitizeTree()'s `settings.calendar`/`settings.sla`). No override on a
    // node → falls back to the plugin-wide shared calendar/SLA, built once and reused (lazy) —
    // same net effect as before this existed. Mono-entité/empty tree ($created empty) has no tree
    // node to pair with, so it always uses the shared path against the root entity, unchanged.
    $tree = $config->getEntityTree();
    $calendarBuilder = new CalendarBuilder();
    $slaBuilder = new SlaBuilder();

    // Built eagerly (not lazily on first use) so every client that falls back to the shared
    // calendar/SLA gets the *same* pairing regardless of which order clients are processed in —
    // build() is idempotent and a cheap no-op when calendar_enabled/sla_enabled is off.
    $sharedCalendarId = $calendarBuilder->build($config);
    $sharedSlaIds = $slaBuilder->build($config, $sharedCalendarId);

    $calendarMap = [];
    $slaMap = [];
    $perClientCount = 0;

    foreach (($created ?: [['name' => '', 'entities_id' => 0]]) as $i => $result) {
        $calendarOverride = $tree[$i]['settings']['calendar'] ?? null;
        $slaOverride = $tree[$i]['settings']['sla'] ?? null;
        if ($calendarOverride !== null || $slaOverride !== null) {
            $perClientCount++;
        }

        $calendarId = $calendarOverride !== null
            ? $calendarBuilder->buildFromOverride($result['name'], $calendarOverride, !empty($config->fields['calendar_holidays_enabled']))
            : $sharedCalendarId;
        if ($calendarId !== null) {
            $calendarMap[$result['entities_id']] = $calendarId;
        }

        $slaIds = $slaOverride !== null
            ? $slaBuilder->buildFromOverride($result['name'], $slaOverride, $calendarId)
            : $sharedSlaIds;
        if ($slaIds !== null) {
            $slaMap[$result['entities_id']] = $slaIds;
        }
    }

    if ($calendarMap !== []) {
        $calendarBuilder->assignMap($calendarMap);
    }
    if ($slaMap !== []) {
        $slaBuilder->assignMap($slaMap);
    }

    $olaBuilt = false;
    foreach ($slaMap as $slaIdsByPriority) {
        foreach ($slaIdsByPriority as $ids) {
            if ($ids['ola_tto'] !== null) {
                $olaBuilt = true;
                break 2;
            }
        }
    }

    $categoriesCreated = (new CategoryBuilder())->build($config);
    $statesCreated = (new StateBuilder())->build($config);

    $brandingApplied = (new BrandingBuilder())->apply($config, $entityIds);
    $generalSettingsApplied = (new GeneralSettingsBuilder())->apply($config);
    $ticketTemplatesApplied = (new TicketTemplateBuilder())->apply($config);
    $helpdeskFormApplied = (new HelpdeskFormBuilder())->apply($config);

    $messages = [];
    $messages[] = empty($created)
        ? __('Aucune entité à créer (mode mono-entité, ou arborescence vide).', 'configurationglpiauto')
        : sprintf(__('Structure créée : %s.', 'configurationglpiauto'), EntityBuilder::describe($created));
    if ($calendarMap !== []) {
        $messages[] = !empty($config->fields['calendar_holidays_enabled'])
            ? __('Calendrier créé et assigné, avec les jours fériés français.', 'configurationglpiauto')
            : __('Calendrier créé et assigné.', 'configurationglpiauto');
    }
    if ($slaMap !== []) {
        $messages[] = __('SLA créés et assignés.', 'configurationglpiauto');
    }
    if ($olaBuilt) {
        $messages[] = __('OLA (engagements internes) créés et assignés.', 'configurationglpiauto');
    }
    if ($perClientCount > 0) {
        $messages[] = sprintf(__('%d client(s) avec des réglages personnalisés.', 'configurationglpiauto'), $perClientCount);
    }
    if ($categoriesCreated > 0) {
        $messages[] = sprintf(__('%d catégories de tickets créées.', 'configurationglpiauto'), $categoriesCreated);
    }
    if ($statesCreated !== []) {
        $messages[] = sprintf(__('%d statuts d\'éléments créés.', 'configurationglpiauto'), count($statesCreated));
    }
    if ($brandingApplied) {
        $messages[] = __('Personnalisation graphique appliquée.', 'configurationglpiauto');
    }
    if ($generalSettingsApplied) {
        $messages[] = __('Réglages généraux GLPI appliqués.', 'configurationglpiauto');
    }
    if ($ticketTemplatesApplied) {
        $messages[] = __('Modèles de tickets créés et assignés aux profils.', 'configurationglpiauto');
    }
    if ($helpdeskFormApplied) {
        $messages[] = __('Champs masqués sur les formulaires de création en libre-service.', 'configurationglpiauto');
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

$priorityLabels = [];
foreach (Config::PRIORITY_LEVELS as $priority) {
    $priorityLabels[$priority] = CommonITILObject::getPriorityName($priority);
}

\Glpi\Application\View\TemplateRenderer::getInstance()->display('@configurationglpiauto/wizard.html.twig', [
    'config'           => $config->fields,
    'profiles'         => $profiles,
    'profile_defaults' => $profileDefaults,
    'modes'            => Config::getModes(),
    'max_levels'       => Config::MAX_LEVELS,
    'entity_tree'      => $config->getEntityTree(),
    'sla_tiers'        => $config->getSlaTiers(),
    'ola_tiers'        => $config->getOlaTiers(),
    'priority_levels'  => Config::PRIORITY_LEVELS,
    'priority_labels'  => $priorityLabels,
    'category_branches' => $config->getCategoryBranches(),
    'categories_preview' => CategoryBuilder::getCategoriesPreview(),
    'states_preview'   => StateBuilder::getStatesPreview(),
    'csrf_token'       => Session::getNewCSRFToken(),
]);

Html::footer();
