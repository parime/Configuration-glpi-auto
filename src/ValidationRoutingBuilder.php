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

use Rule;
use RuleAction;
use RuleCriteria;
use RuleTicket;
use Ticket;

/**
 * Automatic approval routing to the requester's own manager ("N+1") — confirmed real and native,
 * no third-party plugin needed: `glpi_users.users_id_supervisor` is a real core column, and
 * `RuleTicket`'s `responsible_id_validate` action (label "Send an approval request — Supervisor of
 * the requester", confirmed in `RuleCommonITILObject::getActions()`) resolves it at ticket-add time
 * via `CommonITILObject::manageValidationAdd()`'s `'requester_responsible'` case — reads exactly
 * `$user->fields['users_id_supervisor']`.
 *
 * Deliberately NOT `users_id_validate_requester_supervisor` — despite the near-identical name, that
 * action targets the *manager of the requester's group* (`Group_User` rows flagged `is_manager`),
 * a different, group-based mechanism unrelated to the individual N+1 hierarchy this feature is
 * about.
 *
 * Scoped to `type = Ticket::DEMAND_TYPE` only (not Incident) — approval workflows are standard ITSM
 * practice for requests (purchases, access, changes a manager should sign off on), not for incidents
 * needing an urgent fix. Global (`is_recursive = 1`), same reasoning as this plugin's other
 * instance-wide rules.
 *
 * Default OFF (opt-in), unlike most of this plugin's toggles: this one actively changes ticket
 * workflow (every matching ticket gets a mandatory approval step) rather than just seeding content,
 * same "real behavior change, not just scaffolding" exception already applied to branding. It also
 * genuinely depends on `users_id_supervisor` being populated (LDAP import mapping or manual entry) —
 * verified live (see CHANGELOG) what actually happens for a requester with no supervisor set before
 * shipping, rather than assuming.
 */
class ValidationRoutingBuilder
{
    private const RULE_NAME = 'Validation automatique — supérieur hiérarchique du demandeur';

    public function build(Config $config): int
    {
        if (empty($config->fields['validation_supervisor_routing_enabled'])) {
            return 0;
        }

        $rule = new RuleTicket();
        if ($rule->getFromDBByCrit(['name' => self::RULE_NAME])) {
            return 0;
        }

        $rulesId = $rule->add([
            'name' => self::RULE_NAME,
            'sub_type' => RuleTicket::class,
            'match' => Rule::AND_MATCHING,
            'condition' => RuleTicket::ONADD,
            'is_active' => 1,
            'is_recursive' => 1,
        ]);

        (new RuleCriteria())->add([
            'rules_id' => $rulesId,
            'criteria' => 'type',
            'condition' => Rule::PATTERN_IS,
            'pattern' => Ticket::DEMAND_TYPE,
        ]);

        (new RuleAction())->add([
            'rules_id' => $rulesId,
            'action_type' => 'add_validation',
            'field' => 'responsible_id_validate',
            'value' => 1,
        ]);

        return 1;
    }
}
