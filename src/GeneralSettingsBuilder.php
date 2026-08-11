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

use CommonITILSatisfaction;
use Entity;
use Notification;
use ProjectState;
use ValidationStep;

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
 *
 * Since Sprint 25, also covers three more good-practice defaults GLPI leaves off, found while
 * auditing a real production GLPI export:
 * - A handful of `glpi_notifications` rows ship `is_active = 0` — most notably `Ticket`/
 *   `auto_reminder`, the exact notification `PendingReasonCron` fires
 *   (`NotificationEvent::raiseEvent('auto_reminder', ...)`, confirmed in source) for the automatic
 *   follow-ups `WaitReasonBuilder` (Sprint 24) sets up — without it, those follow-ups get added to
 *   the ticket but the requester is never actually emailed, silently defeating half the feature.
 * - The native satisfaction survey (`Entity.inquest_config`/`inquest_rate`) is technically
 *   "enabled" out of the box but `inquest_rate = 0`, which GLPI's own code treats as fully
 *   disabled (`Entity::getValueToDisplay()` shows "Disabled" whenever the rate is 0, regardless of
 *   `inquest_config`). Only the built-in single-question (1-5 stars + optional comment) survey is
 *   turned on here — a richer multi-question survey (like the one in the audited production
 *   export) needs an external tool wired through `inquest_config = TYPE_EXTERNAL` + `inquest_URL`,
 *   which depends on which third-party survey tool the org already uses, so it's out of scope.
 * - `glpi_validationsteps` ships exactly one row ("Validation", 100% required) — a second
 *   "Validation comité" option (67%, i.e. 2/3) is added for multi-approver committee decisions,
 *   confirmed as a real pattern in the same production export.
 */
class GeneralSettingsBuilder
{
    // [itemtype, event] pairs matched against glpi_notifications — confirmed via a fresh 11.0.8
    // install which specific ones ship inactive (KnowbaseItem new/update/delete notifications
    // deliberately left alone: a content-management concern, not core ticket-lifecycle
    // communication, and more subjective whether an org wants them).
    private const NOTIFICATIONS_TO_ENABLE = [
        ['itemtype' => 'Ticket', 'event' => 'update'],
        ['itemtype' => 'Ticket', 'event' => 'add_document'],
        ['itemtype' => 'Ticket', 'event' => 'auto_reminder'],
        ['itemtype' => 'Change', 'event' => 'add_document'],
        ['itemtype' => 'Problem', 'event' => 'add_document'],
    ];

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

        $this->enableNotifications();
        $this->enableSatisfactionSurveys();
        $this->ensureCommitteeValidationStep();

        return true;
    }

    private function enableNotifications(): void
    {
        $notification = new Notification();
        foreach (self::NOTIFICATIONS_TO_ENABLE as $target) {
            if ($notification->getFromDBByCrit(['itemtype' => $target['itemtype'], 'event' => $target['event']])) {
                $notification->update(['id' => $notification->getID(), 'is_active' => 1]);
            }
        }
    }

    private function enableSatisfactionSurveys(): void
    {
        (new Entity())->update([
            'id' => 0,
            'inquest_config' => CommonITILSatisfaction::TYPE_INTERNAL,
            'inquest_rate' => 100,
            'inquest_delay' => 1,
            'inquest_duration' => 30,
            'inquest_config_change' => CommonITILSatisfaction::TYPE_INTERNAL,
            'inquest_rate_change' => 100,
            'inquest_delay_change' => 1,
            'inquest_duration_change' => 30,
        ]);
    }

    private function ensureCommitteeValidationStep(): void
    {
        $step = new ValidationStep();
        if (!$step->getFromDBByCrit(['name' => 'Validation comité (2/3)'])) {
            $step->add([
                'name' => 'Validation comité (2/3)',
                'minimal_required_validation_percent' => 67,
            ]);
        }
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
