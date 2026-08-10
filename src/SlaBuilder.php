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
use Rule;
use RuleAction;
use RuleCriteria;
use RuleTicket;
use SLA;
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
 */
class SlaBuilder
{
    private const SLM_NAME = 'SLA standard';

    /**
     * @return array<int, array{tto: int, ttr: int, ola_tto: ?int, ola_ttr: ?int}>|null Keyed by priority level (Config::PRIORITY_LEVELS).
     */
    public function build(Config $config, ?int $calendarId = null): ?array
    {
        if (empty($config->fields['sla_enabled'])) {
            return null;
        }

        return $this->buildSlm(
            self::SLM_NAME,
            $config->getSlaTiers(),
            !empty($config->fields['sla_astreinte']),
            !empty($config->fields['ola_enabled']),
            $config->getOlaTiers(),
            $calendarId
        );
    }

    /**
     * Same as build(), but for one client's own SLA/OLA override (see Config::sanitizeTree()'s
     * per-client `settings.sla`) instead of the plugin-wide shared settings — named after the
     * client so it doesn't collide with the shared SLM or another client's.
     *
     * @param array{enabled: bool, astreinte: bool, tiers: array<string, array{tto_hours: int, ttr_hours: int}>, ola_enabled: bool, ola_tiers: array<string, array{tto_hours: int, ttr_hours: int}>} $sla
     * @return array<int, array{tto: int, ttr: int, ola_tto: ?int, ola_ttr: ?int}>|null
     */
    public function buildFromOverride(string $clientName, array $sla, ?int $calendarId = null): ?array
    {
        if (empty($sla['enabled'])) {
            return null;
        }

        return $this->buildSlm(
            sprintf(__('SLA — %s', 'configurationglpiauto'), $clientName),
            $sla['tiers'],
            !empty($sla['astreinte']),
            !empty($sla['ola_enabled']),
            $sla['ola_tiers'] ?? [],
            $calendarId
        );
    }

    /**
     * @param array<int, array{tto: int, ttr: int, ola_tto: ?int, ola_ttr: ?int}> $slaIdsByPriority
     * @param int[] $entityIds
     */
    public function assignToEntities(array $slaIdsByPriority, array $entityIds): void
    {
        foreach ($entityIds as $entityId) {
            $this->assignOne($entityId, $slaIdsByPriority);
        }
    }

    /**
     * Per-client variant of assignToEntities(): different SLAs per entity instead of the same
     * set for all of them.
     *
     * @param array<int, array<int, array{tto: int, ttr: int, ola_tto: ?int, ola_ttr: ?int}>> $entityIdToSlaIds
     */
    public function assignMap(array $entityIdToSlaIds): void
    {
        foreach ($entityIdToSlaIds as $entityId => $slaIdsByPriority) {
            $this->assignOne($entityId, $slaIdsByPriority);
        }
    }

    /**
     * Creates one RuleTicket per (entity × priority level) — "this entity AND this priority" →
     * "assign this level's SLA (and OLA, if present)" — idempotent by rule name.
     *
     * @param array<int, array{tto: int, ttr: int, ola_tto: ?int, ola_ttr: ?int}> $slaIdsByPriority
     */
    private function assignOne(int $entityId, array $slaIdsByPriority): void
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
        }
    }

    /**
     * @param array<string, array{tto_hours: int, ttr_hours: int}> $tiers
     * @param array<string, array{tto_hours: int, ttr_hours: int}> $olaTiers
     * @return array<int, array{tto: int, ttr: int, ola_tto: ?int, ola_ttr: ?int}>
     */
    private function buildSlm(string $name, array $tiers, bool $astreinte, bool $olaEnabled, array $olaTiers, ?int $calendarId): array
    {
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

            $result[$priority] = ['tto' => $ttoId, 'ttr' => $ttrId, 'ola_tto' => $olaTtoId, 'ola_ttr' => $olaTtrId];
        }

        return $result;
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
