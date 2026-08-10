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

use ProjectState;

/**
 * Applies a set of instance-wide GLPI core general settings (`glpi_configs`, context `core`) that
 * ship with unhelpful defaults out of the box. Not a per-entity/per-client concept — writes
 * directly through GLPI core's own `\Config::setConfigurationValues()` API (not raw SQL) so the
 * write goes through GLPI's normal cache/session invalidation.
 *
 * Referenced explicitly with a leading `\` throughout: this plugin has its own
 * `GlpiPlugin\Configurationglpiauto\Config` class in the current namespace, so a bare `Config::`
 * call here would resolve to the wrong class.
 *
 * Also covers the "Statuts des tâches" project bucket mapping (`projecttask_unstarted_states_id`/
 * `_inprogress_states_id`/`_completed_states_id`) — GLPI ships exactly 3 native `ProjectState` rows
 * out of the box ("New", "Processing", "Closed", confirmed on a fresh 11.0.8 install) but leaves
 * this mapping unset, so project task progress tracking silently does nothing until an admin wires
 * it up by hand.
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
        ] + $this->projectTaskStateMapping());

        return true;
    }

    /**
     * Maps GLPI's 3 native `ProjectState` rows to the "unstarted/in progress/completed" buckets
     * used for project task progress tracking. Matched by exact name, not hardcoded IDs — an
     * admin could have reordered/recreated them before running the wizard; if any of the 3 native
     * names isn't found (non-fresh instance, renamed rows), the mapping is skipped rather than
     * writing a wrong/guessed ID.
     *
     * @return array<string, int>
     */
    private function projectTaskStateMapping(): array
    {
        $ids = [];
        $state = new ProjectState();
        foreach (['New' => 'unstarted', 'Processing' => 'inprogress', 'Closed' => 'completed'] as $name => $bucket) {
            if (!$state->getFromDBByCrit(['name' => $name])) {
                return [];
            }
            $ids["projecttask_{$bucket}_states_id"] = (int) $state->getID();
        }

        return $ids;
    }
}
