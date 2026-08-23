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

namespace GlpiPlugin\Configurationglpiauto;

use CommonITILSatisfaction;
use CronTask;
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
 * Split into independently-gated groups (Sprint 26) rather than one `apply()` behind a single
 * toggle — the earlier all-or-nothing design (everything from Sprint 18 through Sprint 25 folded
 * into one `general_settings_enabled` flag) meant an admin who wanted the satisfaction survey but
 * not, say, the committee validation step had no way to say so. Each group below matches one
 * checkbox in the wizard's "Réglages généraux" step.
 *
 * `inventory_enabled` (added on explicit user request, #147) is the one group targeting the
 * `inventory` config context instead of `core` — confirmed both are legal contexts for
 * `\Config::setConfigurationValues()` by reading `\Config::update()`'s own `$allowed_context`
 * list, not assumed. Defaults to unchecked, same reasoning already applied to
 * `validation_supervisor_routing_enabled` in the wizard template: this opens a real network entry
 * point (the inventory endpoint agents post to), not just content generation, so it shouldn't be
 * silently turned on for an org that has no agents to deploy.
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

    // [itemtype, name] pairs matched against glpi_crontasks — confirmed via a fresh 11.0.8 install
    // that these three ship disabled (`state = CronTask::STATE_DISABLE`) while their matching
    // `Notification` row (Cartridges/Consumables/Software Licenses "alert" events, checked
    // separately) already ships `is_active = 1`: the notification looks fully configured, but
    // silently never fires because nothing ever triggers it. `contract`, the fourth native
    // asset-expiry crontask, already ships active and is left alone.
    private const CRONTASKS_TO_ENABLE = [
        ['itemtype' => 'CartridgeItem', 'name' => 'cartridge'],
        ['itemtype' => 'ConsumableItem', 'name' => 'consumable'],
        ['itemtype' => 'SoftwareLicense', 'name' => 'software'],
    ];

    /**
     * Applies every group whose own toggle is on. Returns whether anything was applied.
     */
    public function apply(Config $config): bool
    {
        $applied = false;

        if (!empty($config->fields['general_ui_enabled'])) {
            $this->applyGeneralUi();
            $applied = true;
        }

        if (!empty($config->fields['notifications_enabled'])) {
            $this->applyNotifications();
            $applied = true;
        }

        if (!empty($config->fields['financial_info_enabled'])) {
            \Config::setConfigurationValues('core', ['auto_create_infocoms' => 1]);
            $applied = true;
        }

        if (!empty($config->fields['project_task_states_enabled'])) {
            $mapping = $this->projectTaskStateMapping();
            if ($mapping !== []) {
                \Config::setConfigurationValues('core', $mapping);
            }
            $applied = true;
        }

        if (!empty($config->fields['satisfaction_survey_enabled'])) {
            $this->enableSatisfactionSurveys();
            $applied = true;
        }

        if (!empty($config->fields['committee_validation_enabled'])) {
            $this->ensureCommitteeValidationStep();
            $applied = true;
        }

        if (!empty($config->fields['inventory_enabled'])) {
            \Config::setConfigurationValues('inventory', ['enabled_inventory' => 1]);
            $applied = true;
        }

        return $applied;
    }

    /**
     * "Interface & ergonomie" group: button layout, search form/pagination position, homepage
     * tickets widget — cosmetic/ergonomic GLPI core defaults, unrelated to notifications or
     * ticket workflow.
     */
    private function applyGeneralUi(): void
    {
        \Config::setConfigurationValues('core', [
            // "Agencement du bouton d'action" : boutons Répondre/Observation/Solution séparés
            // plutôt que fusionnés dans un seul menu déroulant.
            'timeline_action_btn_layout' => \Config::TIMELINE_ACTION_BTN_SPLITTED,
            'show_search_form'         => 1,
            'search_pagination_on_top' => 1,
            'show_jobs_at_login'       => 1,
        ]);
    }

    /**
     * "Notifications" group: the master activation toggle (off by default on a fresh install)
     * plus the specific ticket-lifecycle events GLPI ships inactive — most notably `Ticket`/
     * `auto_reminder`, the exact notification `PendingReasonCron` fires
     * (`NotificationEvent::raiseEvent('auto_reminder', ...)`, confirmed in source) for the
     * automatic follow-ups `WaitReasonBuilder` sets up. Without it, those follow-ups get added to
     * the ticket but the requester is never actually emailed, silently defeating half that
     * feature — so this group matters even to an admin who only cares about wait reasons.
     *
     * Also activates the 3 native cron tasks (`CRONTASKS_TO_ENABLE`) that ship disabled despite
     * their matching `Notification` row already being active — same "looks configured, silently
     * does nothing" trap as `auto_reminder` above, found by comparing `glpi_crontasks.state`
     * against `glpi_notifications.is_active` on a fresh install (fourth completeness audit,
     * Sprint 35).
     */
    private function applyNotifications(): void
    {
        \Config::setConfigurationValues('core', [
            'use_notifications'     => 1,
            'notifications_mailing' => 1,
            'notifications_ajax'    => 1,
        ]);

        $notification = new Notification();
        foreach (self::NOTIFICATIONS_TO_ENABLE as $target) {
            if ($notification->getFromDBByCrit(['itemtype' => $target['itemtype'], 'event' => $target['event']])) {
                $notification->update(['id' => $notification->getID(), 'is_active' => 1]);
            }
        }

        $cronTask = new CronTask();
        foreach (self::CRONTASKS_TO_ENABLE as $target) {
            if ($cronTask->getFromDBByCrit(['itemtype' => $target['itemtype'], 'name' => $target['name']])) {
                $cronTask->update(['id' => $cronTask->getID(), 'state' => CronTask::STATE_WAITING]);
            }
        }
    }

    /**
     * The native satisfaction survey (`Entity.inquest_config`/`inquest_rate`) is technically
     * "enabled" out of the box but `inquest_rate = 0`, which GLPI's own code treats as fully
     * disabled (`Entity::getValueToDisplay()` shows "Disabled" whenever the rate is 0, regardless
     * of `inquest_config`). Only the built-in single-question (1-5 stars + optional comment)
     * survey is turned on here — a richer multi-question survey (like the one in the audited
     * production export) needs an external tool wired through `inquest_config = TYPE_EXTERNAL` +
     * `inquest_URL`, which depends on which third-party survey tool the org already uses, so it's
     * out of scope.
     */
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

    /**
     * `glpi_validationsteps` ships exactly one row ("Validation", 100% required) — a second
     * "Validation comité" option (67%, i.e. 2/3) is added here for multi-approver committee
     * decisions, confirmed as a real pattern in the audited production export.
     */
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
