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

use CommonITILObject;
use OLA;
use OlaLevel;
use OlaLevelAction;
use Rule;
use RuleAction;
use RuleCriteria;
use RuleTicket;
use SLA;
use SlaLevel;
use SlaLevelAction;
use SLM;

/**
 * Turns a Config's SLA settings into a real GLPI SLM ("Niveau de service" — the named container)
 * with one TTO/TTR pair of SLA entries *per GLPI priority level* (see Config::PRIORITY_LEVELS —
 * confirmed by research this sprint that real ITSM practice defines SLAs per priority, not one
 * flat delay for every ticket), then assigns them to new tickets in the given entities.
 * Deliberately simple: flat delays per level, not the full escalation-level engine (SlaLevel) —
 * that's a distinct, considerably heavier feature to build later if actually needed. Idempotent:
 * reuses an SLM/SLA/rule of the same name instead of duplicating.
 *
 * Also builds OLA (Operational Level Agreement — the *internal* commitment between the helpdesk
 * and support teams that has to land before the SLA deadline for the SLA to actually be met) when
 * enabled, in the *same* SLM as the SLA: confirmed in GLPI core that OLA extends the same
 * LevelAgreement base class as SLA and attaches to the same `slms_id`, so one "Niveau de service"
 * naturally carries both — no second container needed.
 *
 * Unlike Calendar (a direct Entity::calendars_id field), GLPI has no per-entity "default SLA"
 * field at all — confirmed by reading Entity.php: the `slas_id_tto`/`slas_id_ttr` (and
 * `olas_id_tto`/`olas_id_ttr`) fields live on glpi_tickets, only ever set by the business-rules
 * engine (RuleTicket). So assignment here means creating a real RuleTicket ("entity is X and
 * priority is Y" → "assign this level's SLA/OLA"), not an Entity update — `priority` is a
 * documented RuleTicket criterion (RuleCommonITILObject.php), the same mechanism GLPI's own docs
 * describe for priority-based SLA assignment.
 *
 * Since Sprint 28, also builds the escalation-level engine this class's docblock used to flag as
 * deliberately out of scope: one `SlaLevel` per TTR (and one `OlaLevel` per OLA TTR, if OLA is
 * enabled) that fires a configurable percentage of the delay before the deadline — confirmed in
 * GLPI source (`Ticket.php` calls `SlaLevel::getFirstSlaLevel()`/`(new SLA)->addLevelToDo()`
 * automatically whenever an SLA/OLA is assigned to a ticket, and the native `slaticket`/
 * `olaticket` CronTasks that process due levels are active by default — no extra wiring needed,
 * same "just works once created" pattern as this class's own `RuleTicket` rows). One action is
 * raising the ticket's priority one step (`sla_escalation_enabled`, skips the already-highest
 * priority — nothing higher to escalate to).
 *
 * Since Sprint 34, also reassigns the ticket to the next support tier's `Group` (N1 → N2 → N3,
 * see `SupportTierBuilder`) when `escalation_enabled`/`$tierGroupIds` is passed in — reversing
 * Sprint 28's "reassigning to a group was left out" note: the group names are no longer invented
 * per-org guesses, they're the generic, researched N1/N2/N3 convention the user explicitly asked
 * for. Confirmed in GLPI source (`SlaLevel extends LevelAgreementLevel extends RuleTicket`, so
 * `SlaLevel::getActions()` inherits `RuleCommonITILObject`'s full action list, including
 * `_groups_id_assign`) that a `SlaLevelAction`/`OlaLevelAction` can carry this action just like the
 * existing `priority` one. Two independent hops, each with its own toggle
 * (`escalation_auto_n1_n2`/`escalation_auto_n2_n3`): N1→N2 rides the *same* before-breach level as
 * the priority raise (an extra action on it, applied to *every* priority including the max — unlike
 * the priority action, there's nothing wrong with reassigning an already-Major ticket's group); N2→N3
 * fires on a *second*, distinct level at the deadline itself (`execution_time = 0` — "still
 * unresolved when the SLA breached, escalate further"). New tickets start assigned to N1 via an
 * extra `RuleAction` on `assignOne()`'s own `RuleTicket` (same ONADD rule, no new one).
 */
class SlaBuilder
{
    private const SLM_NAME = 'SLA standard';

    /**
     * @param array{n1: int, n2: int, n3: int}|array{}|null $tierGroupIds `SupportTierBuilder::build()`'s result, or null if escalation-to-tier is off entirely.
     * @return array<int, array{tto: int, ttr: int, ola_tto: ?int, ola_ttr: ?int}>|null Keyed by priority level (Config::PRIORITY_LEVELS).
     */
    public function build(
        Config $config,
        ?int $calendarId = null,
        ?array $tierGroupIds = null,
        bool $autoN1N2 = false,
        bool $autoN2N3 = false
    ): ?array {
        if (empty($config->fields['sla_enabled'])) {
            return null;
        }

        return $this->buildSlm(
            self::SLM_NAME,
            $config->getSlaTiers(),
            !empty($config->fields['sla_astreinte']),
            !empty($config->fields['ola_enabled']),
            $config->getOlaTiers(),
            $calendarId,
            !empty($config->fields['sla_escalation_enabled']),
            (int) ($config->fields['sla_escalation_threshold_percent'] ?? 75),
            $tierGroupIds ?: null,
            $autoN1N2,
            $autoN2N3
        );
    }

    /**
     * Same as build(), but for one client's own SLA/OLA override (see Config::sanitizeTree()'s
     * per-client `settings.sla`) instead of the plugin-wide shared settings — named after the
     * client so it doesn't collide with the shared SLM or another client's. Escalation (priority
     * *and* tier) is passed in from the caller's already-resolved values (plugin-wide default or
     * this client's own `settings.escalation` override), not read from $sla.
     *
     * @param array{enabled: bool, astreinte: bool, tiers: array<string, array{tto_hours: int, ttr_hours: int}>, ola_enabled: bool, ola_tiers: array<string, array{tto_hours: int, ttr_hours: int}>} $sla
     * @param array{n1: int, n2: int, n3: int}|array{}|null $tierGroupIds
     * @return array<int, array{tto: int, ttr: int, ola_tto: ?int, ola_ttr: ?int}>|null
     */
    public function buildFromOverride(
        string $clientName,
        array $sla,
        ?int $calendarId = null,
        bool $escalationEnabled = false,
        int $escalationThresholdPercent = 75,
        ?array $tierGroupIds = null,
        bool $autoN1N2 = false,
        bool $autoN2N3 = false
    ): ?array {
        if (empty($sla['enabled'])) {
            return null;
        }

        return $this->buildSlm(
            sprintf(__('SLA — %s', 'configurationglpiauto'), $clientName),
            $sla['tiers'],
            !empty($sla['astreinte']),
            !empty($sla['ola_enabled']),
            $sla['ola_tiers'] ?? [],
            $calendarId,
            $escalationEnabled,
            $escalationThresholdPercent,
            $tierGroupIds ?: null,
            $autoN1N2,
            $autoN2N3
        );
    }

    /**
     * @param array<int, array{tto: int, ttr: int, ola_tto: ?int, ola_ttr: ?int}> $slaIdsByPriority
     * @param int[] $entityIds
     * @param array{n1: int, n2: int, n3: int}|array{}|null $tierGroupIds
     */
    public function assignToEntities(array $slaIdsByPriority, array $entityIds, ?array $tierGroupIds = null): void
    {
        foreach ($entityIds as $entityId) {
            $this->assignOne($entityId, $slaIdsByPriority, $tierGroupIds);
        }
    }

    /**
     * Per-client variant of assignToEntities(): different SLAs per entity instead of the same
     * set for all of them. `$entityIdToTierGroupIds` is similarly per-entity (a client's own
     * `settings.escalation` override can opt out of N1-assignment even where the shared config has
     * it on) — entities missing from it fall back to `$defaultTierGroupIds`.
     *
     * @param array<int, array<int, array{tto: int, ttr: int, ola_tto: ?int, ola_ttr: ?int}>> $entityIdToSlaIds
     * @param array{n1: int, n2: int, n3: int}|array{}|null $defaultTierGroupIds
     * @param array<int, array{n1: int, n2: int, n3: int}|array{}|null> $entityIdToTierGroupIds
     */
    public function assignMap(array $entityIdToSlaIds, ?array $defaultTierGroupIds = null, array $entityIdToTierGroupIds = []): void
    {
        foreach ($entityIdToSlaIds as $entityId => $slaIdsByPriority) {
            $tierGroupIds = array_key_exists($entityId, $entityIdToTierGroupIds) ? $entityIdToTierGroupIds[$entityId] : $defaultTierGroupIds;
            $this->assignOne($entityId, $slaIdsByPriority, $tierGroupIds);
        }
    }

    /**
     * Creates one RuleTicket per (entity × priority level) — "this entity AND this priority" →
     * "assign this level's SLA (and OLA, if present)" — idempotent by rule name.
     *
     * @param array<int, array{tto: int, ttr: int, ola_tto: ?int, ola_ttr: ?int}> $slaIdsByPriority
     * @param array{n1: int, n2: int, n3: int}|array{}|null $tierGroupIds
     */
    private function assignOne(int $entityId, array $slaIdsByPriority, ?array $tierGroupIds = null): void
    {
        foreach ($slaIdsByPriority as $priority => $ids) {
            $ruleName = sprintf('SLA standard — entité #%d — %s', $entityId, CommonITILObject::getPriorityName($priority));

            $rule = new RuleTicket();
            if ($rule->getFromDBByCrit(['name' => $ruleName])) {
                continue;
            }

            $rulesId = $rule->add([
                'name' => $ruleName,
                'sub_type' => RuleTicket::class,
                'match' => Rule::AND_MATCHING,
                'condition' => RuleTicket::ONADD,
                'is_active' => 1,
                // Without this, GLPI's RuleCollection only evaluates the rule for its own
                // entities_id (root, 0) — never for a ticket created in any sub-entity like the
                // ones this plugin creates. Confirmed by creating a real ticket in a sub-entity
                // and finding slas_id_tto/slas_id_ttr stayed 0 without this flag.
                'is_recursive' => 1,
            ]);

            (new RuleCriteria())->add([
                'rules_id' => $rulesId,
                'criteria' => 'entities_id',
                'condition' => Rule::PATTERN_IS,
                'pattern' => $entityId,
            ]);

            (new RuleCriteria())->add([
                'rules_id' => $rulesId,
                'criteria' => 'priority',
                'condition' => Rule::PATTERN_IS,
                'pattern' => $priority,
            ]);

            (new RuleAction())->add([
                'rules_id' => $rulesId,
                'action_type' => 'assign',
                'field' => 'slas_id_tto',
                'value' => $ids['tto'],
            ]);

            (new RuleAction())->add([
                'rules_id' => $rulesId,
                'action_type' => 'assign',
                'field' => 'slas_id_ttr',
                'value' => $ids['ttr'],
            ]);

            if ($ids['ola_tto'] !== null) {
                (new RuleAction())->add([
                    'rules_id' => $rulesId,
                    'action_type' => 'assign',
                    'field' => 'olas_id_tto',
                    'value' => $ids['ola_tto'],
                ]);
            }

            if ($ids['ola_ttr'] !== null) {
                (new RuleAction())->add([
                    'rules_id' => $rulesId,
                    'action_type' => 'assign',
                    'field' => 'olas_id_ttr',
                    'value' => $ids['ola_ttr'],
                ]);
            }

            if (!empty($tierGroupIds['n1'])) {
                (new RuleAction())->add([
                    'rules_id' => $rulesId,
                    'action_type' => 'assign',
                    'field' => '_groups_id_assign',
                    'value' => $tierGroupIds['n1'],
                ]);
            }
        }
    }

    /**
     * @param array<string, array{tto_hours: int, ttr_hours: int}> $tiers
     * @param array<string, array{tto_hours: int, ttr_hours: int}> $olaTiers
     * @param array{n1: int, n2: int, n3: int}|array{}|null $tierGroupIds
     * @return array<int, array{tto: int, ttr: int, ola_tto: ?int, ola_ttr: ?int}>
     */
    private function buildSlm(
        string $name,
        array $tiers,
        bool $astreinte,
        bool $olaEnabled,
        array $olaTiers,
        ?int $calendarId,
        bool $escalationEnabled = false,
        int $escalationThresholdPercent = 75,
        ?array $tierGroupIds = null,
        bool $autoN1N2 = false,
        bool $autoN2N3 = false
    ): array {
        // Astreinte = couverture 24h/24, 7j/7 : GLPI interprete calendars_id=0 comme "pas de
        // calendrier", donc le SLA continue de courir en dehors des horaires ouvres — c'est le
        // meme mecanisme que le cas "aucun calendrier construit" ci-dessous, juste voulu cette
        // fois plutot que par defaut.
        $slmCalendarId = $astreinte ? 0 : ($calendarId ?? 0);

        $slm = new SLM();
        if (!$slm->getFromDBByCrit(['name' => $name])) {
            $id = $slm->add([
                'name' => $name,
                'calendars_id' => $slmCalendarId,
                'use_ticket_calendar' => 0,
            ]);
            $slm->getFromDB($id);
        }
        $slmId = (int) $slm->getID();

        $result = [];
        foreach (Config::PRIORITY_LEVELS as $priority) {
            $tier = $tiers[(string) $priority] ?? ['tto_hours' => 4, 'ttr_hours' => 48];
            $label = CommonITILObject::getPriorityName($priority);

            $ttoId = $this->getOrCreateLevelAgreement(SLA::class, $slmId, SLM::TTO, sprintf(__('Prise en charge — %s', 'configurationglpiauto'), $label), (int) $tier['tto_hours']);
            $ttrId = $this->getOrCreateLevelAgreement(SLA::class, $slmId, SLM::TTR, sprintf(__('Résolution — %s', 'configurationglpiauto'), $label), (int) $tier['ttr_hours']);

            $olaTtoId = null;
            $olaTtrId = null;
            if ($olaEnabled) {
                $olaTier = $olaTiers[(string) $priority] ?? ['tto_hours' => 1, 'ttr_hours' => 2];
                $olaTtoId = $this->getOrCreateLevelAgreement(OLA::class, $slmId, SLM::TTO, sprintf(__('OLA prise en charge — %s', 'configurationglpiauto'), $label), (int) $olaTier['tto_hours']);
                $olaTtrId = $this->getOrCreateLevelAgreement(OLA::class, $slmId, SLM::TTR, sprintf(__('OLA résolution — %s', 'configurationglpiauto'), $label), (int) $olaTier['ttr_hours']);
            }

            if ($escalationEnabled || $tierGroupIds !== null) {
                $this->ensureEscalationLevel(SlaLevel::class, $ttrId, (int) $tier['ttr_hours'], $priority, $escalationEnabled, $escalationThresholdPercent, $tierGroupIds, $autoN1N2, $autoN2N3);
                if ($olaTtrId !== null) {
                    $olaTier = $olaTiers[(string) $priority] ?? ['tto_hours' => 1, 'ttr_hours' => 2];
                    $this->ensureEscalationLevel(OlaLevel::class, $olaTtrId, (int) $olaTier['ttr_hours'], $priority, $escalationEnabled, $escalationThresholdPercent, $tierGroupIds, $autoN1N2, $autoN2N3);
                }
            }

            $result[$priority] = ['tto' => $ttoId, 'ttr' => $ttrId, 'ola_tto' => $olaTtoId, 'ola_ttr' => $olaTtrId];
        }

        return $result;
    }

    /**
     * Up to two escalation levels on a TTR SLA/OLA row:
     * - The "before breach" level, at `$thresholdPercent`% of the delay elapsed, created whenever
     *   `$escalationEnabled` (priority-raise, skips the already-highest priority — nothing higher
     *   to escalate to) or `$autoN1N2` (N1 → N2 group reassignment, applied to *every* priority —
     *   unlike the priority raise, reassigning an already-Major ticket's group is still useful).
     * - A second, "at breach" level (`execution_time = 0`), created only if `$autoN2N3`: "still
     *   unresolved when the SLA/OLA actually breached → escalate further to N3", every priority.
     *
     * Both idempotent by (parent id, name).
     *
     * @param class-string<SlaLevel|OlaLevel> $class
     * @param array{n1: int, n2: int, n3: int}|array{}|null $tierGroupIds
     */
    private function ensureEscalationLevel(
        string $class,
        int $agreementId,
        int $ttrHours,
        int $priority,
        bool $escalationEnabled,
        int $thresholdPercent,
        ?array $tierGroupIds,
        bool $autoN1N2,
        bool $autoN2N3
    ): void {
        $maxPriority = max(Config::PRIORITY_LEVELS);
        $raisePriority = $escalationEnabled && $priority < $maxPriority;
        $assignN2 = $tierGroupIds !== null && $autoN1N2 && !empty($tierGroupIds['n2']);
        $assignN3 = $tierGroupIds !== null && $autoN2N3 && !empty($tierGroupIds['n3']);

        if ($raisePriority || $assignN2) {
            // Negative = before the deadline (confirmed in GLPI's LevelAgreementLevel::
            // getExecutionTimes(): negative values are labelled "- N hour(s)/day(s)", fired that
            // long before the TTO/TTR date). $thresholdPercent% elapsed = (100-$thresholdPercent)%
            // of the delay still remaining when this fires.
            $executionTime = -(int) round($ttrHours * 3600 * ((100 - $thresholdPercent) / 100));
            $levelId = $this->getOrCreateLevel($class, $agreementId, sprintf(__('Escalade — %s', 'configurationglpiauto'), CommonITILObject::getPriorityName($priority)), $executionTime);

            if ($raisePriority) {
                $this->addLevelAction($class, $levelId, 'priority', $priority + 1);
            }
            if ($assignN2) {
                $this->addLevelAction($class, $levelId, '_groups_id_assign', $tierGroupIds['n2']);
            }
        }

        if ($assignN3) {
            $levelId = $this->getOrCreateLevel($class, $agreementId, sprintf(__('Escalade N3 — %s', 'configurationglpiauto'), CommonITILObject::getPriorityName($priority)), 0);
            $this->addLevelAction($class, $levelId, '_groups_id_assign', $tierGroupIds['n3']);
        }
    }

    /**
     * @param class-string<SlaLevel|OlaLevel> $class
     */
    private function getOrCreateLevel(string $class, int $agreementId, string $name, int $executionTime): int
    {
        $fkField = $class === SlaLevel::class ? 'slas_id' : 'olas_id';

        $level = new $class();
        if ($level->getFromDBByCrit([$fkField => $agreementId, 'name' => $name])) {
            return (int) $level->getID();
        }

        return (int) $level->add([
            'name' => $name,
            $fkField => $agreementId,
            'execution_time' => $executionTime,
            'match' => Rule::AND_MATCHING,
            'is_active' => 1,
            'is_recursive' => 1,
        ]);
    }

    /**
     * @param class-string<SlaLevel|OlaLevel> $class
     */
    private function addLevelAction(string $class, int $levelId, string $field, int $value): void
    {
        $actionClass = $class === SlaLevel::class ? SlaLevelAction::class : OlaLevelAction::class;
        $actionFkField = $class === SlaLevel::class ? 'slalevels_id' : 'olalevels_id';

        $action = new $actionClass();
        if ($action->getFromDBByCrit([$actionFkField => $levelId, 'field' => $field])) {
            return;
        }

        $action->add([
            $actionFkField => $levelId,
            'action_type' => 'assign',
            'field' => $field,
            'value' => $value,
        ]);
    }

    /**
     * @param class-string<SLA|OLA> $class
     */
    private function getOrCreateLevelAgreement(string $class, int $slmId, int $type, string $name, int $hours): int
    {
        $agreement = new $class();
        if ($agreement->getFromDBByCrit(['slms_id' => $slmId, 'type' => $type, 'name' => $name])) {
            return (int) $agreement->getID();
        }

        $id = $agreement->add([
            'slms_id' => $slmId,
            'name' => $name,
            'type' => $type,
            'number_time' => $hours,
            'definition_time' => 'hour',
        ]);

        return (int) $id;
    }
}
