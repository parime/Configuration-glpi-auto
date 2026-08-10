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

namespace GlpiPlugin\Configurationglpiauto;

/**
 * Applies a set of instance-wide GLPI core general settings (`glpi_configs`, context `core`) that
 * ship with unhelpful defaults out of the box. Not a per-entity/per-client concept — writes
 * directly through GLPI core's own `\Config::setConfigurationValues()` API (not raw SQL) so the
 * write goes through GLPI's normal cache/session invalidation.
 *
 * Referenced explicitly with a leading `\` throughout: this plugin has its own
 * `GlpiPlugin\Configurationglpiauto\Config` class in the current namespace, so a bare `Config::`
 * call here would resolve to the wrong class.
 */
class GeneralSettingsBuilder
{
    /**
     * Applies the general settings if `general_settings_enabled` is on. Returns whether anything
     * was applied.
     */
    public function apply(Config $config): bool
    {
        if (empty($config->fields['general_settings_enabled'])) {
            return false;
        }

        \Config::setConfigurationValues('core', [
            // Master "Activer les notifications" toggle, off by default on a fresh install.
            'use_notifications'        => 1,
            'notifications_mailing'    => 1,
            'notifications_ajax'       => 1,
            // "Agencement du bouton d'action" : boutons Répondre/Observation/Solution séparés
            // plutôt que fusionnés dans un seul menu déroulant.
            'timeline_action_btn_layout' => \Config::TIMELINE_ACTION_BTN_SPLITTED,
            'show_search_form'         => 1,
            'search_pagination_on_top' => 1,
            'show_jobs_at_login'       => 1,
            'auto_create_infocoms'     => 1,
        ]);

        return true;
    }
}
