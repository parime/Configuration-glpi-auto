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

use Rule;
use RuleAction;
use RuleCriteria;
use RuleTicket;
use SLA;
use SLM;

/**
 * Turns a Config's SLA settings (enabled/time-to-own/time-to-resolve) into a real GLPI SLM
 * ("Niveau de service" — the named container) with two real SLA entries under it (time to own,
 * time to resolve), then assigns them to new tickets in the given entities. Deliberately simple:
 * two flat delays, not the full escalation-level engine (SlaLevel) — that's a distinct,
 * considerably heavier feature to build later if actually needed. Idempotent: reuses an SLM/SLA/
 * rule of the same name instead of duplicating.
 *
 * Unlike Calendar (a direct Entity::calendars_id field), GLPI has no per-entity "default SLA"
 * field at all — confirmed by reading Entity.php: the `slas_id_tto`/`slas_id_ttr` fields live on
 * glpi_tickets, only ever set by the business-rules engine (RuleTicket). So assignment here means
 * creating a real RuleTicket ("entity is X" → "assign this SLA"), not an Entity update.
 */
class SlaBuilder
{
    private const SLM_NAME = 'SLA standard';

    /**
     * @param int[] $entityIds
     * @return array{tto: int, ttr: int}|null
     */
    public function build(Config $config, ?int $calendarId = null): ?array
    {
        if (empty($config->fields['sla_enabled'])) {
            return null;
        }

        $slm = new SLM();
        if (!$slm->getFromDBByCrit(['name' => self::SLM_NAME])) {
            $id = $slm->add([
                'name' => self::SLM_NAME,
                'calendars_id' => $calendarId ?? 0,
                'use_ticket_calendar' => 0,
            ]);
            $slm->getFromDB($id);
        }
        $slmId = (int) $slm->getID();

        $ttoId = $this->getOrCreateSla(
            $slmId,
            SLM::TTO,
            __('Prise en charge standard', 'configurationglpiauto'),
            (int) $config->fields['sla_tto_hours']
        );
        $ttrId = $this->getOrCreateSla(
            $slmId,
            SLM::TTR,
            __('Résolution standard', 'configurationglpiauto'),
            (int) $config->fields['sla_ttr_hours']
        );

        return ['tto' => $ttoId, 'ttr' => $ttrId];
    }

    /**
     * Creates one RuleTicket per entity ("this entity" → "assign these SLAs on ticket
     * creation"), idempotent by rule name.
     *
     * @param int[] $entityIds
     */
    public function assignToEntities(array $slaIds, array $entityIds): void
    {
        foreach ($entityIds as $entityId) {
            $ruleName = sprintf('SLA standard — entité #%d', $entityId);

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
            ]);

            (new RuleCriteria())->add([
                'rules_id' => $rulesId,
                'criteria' => 'entities_id',
                'condition' => Rule::PATTERN_IS,
                'pattern' => $entityId,
            ]);

            (new RuleAction())->add([
                'rules_id' => $rulesId,
                'action_type' => 'assign',
                'field' => 'slas_id_tto',
                'value' => $slaIds['tto'],
            ]);

            (new RuleAction())->add([
                'rules_id' => $rulesId,
                'action_type' => 'assign',
                'field' => 'slas_id_ttr',
                'value' => $slaIds['ttr'],
            ]);
        }
    }

    private function getOrCreateSla(int $slmId, int $type, string $name, int $hours): int
    {
        $sla = new SLA();
        if ($sla->getFromDBByCrit(['slms_id' => $slmId, 'type' => $type])) {
            return (int) $sla->getID();
        }

        $id = $sla->add([
            'slms_id' => $slmId,
            'name' => $name,
            'type' => $type,
            'number_time' => $hours,
            'definition_time' => 'hour',
        ]);

        return (int) $id;
    }
}
