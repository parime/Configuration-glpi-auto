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

use Profile;
use Ticket;
use TicketTemplate;
use TicketTemplateHiddenField;
use TicketTemplateMandatoryField;

/**
 * Creates two `TicketTemplate` rows and wires them to GLPI's default profiles via
 * `glpi_profiles.tickettemplates_id` (a native per-profile override, confirmed in `Profile.php`'s
 * `$helpdesk_rights`/`$common_fields`): a minimal one (title + description only, everything else
 * hidden) for the profiles with no elevated rights (Self-Service, Read-Only), and a complete one
 * (category + urgency mandatory, nothing hidden) for every other profile — matching the explicit
 * split requested: base users enter the least possible, staff qualify the ticket.
 *
 * Field numbers (`num` in `glpi_tickettemplate{mandatory,hidden}fields`) are GLPI SearchOption IDs,
 * not arbitrary indexes — confirmed via `ITILTemplate::getAllowedFields()`, which resolves them the
 * same way (`getSearchOptionIDByField()`), so they're looked up here instead of hardcoded, except
 * for the handful GLPI itself hardcodes in that same method (`_users_id_assign` etc.).
 */
class TicketTemplateBuilder
{
    private const SIMPLIFIED_NAME = 'Ticket simplifié (libre-service)';

    private const COMPLETE_NAME = 'Ticket complet (support)';

    // Exact GLPI default profile names — matched by name, not by right/interface, so a renamed or
    // custom profile isn't silently swept into the wrong bucket.
    private const SIMPLIFIED_PROFILES = ['Self-Service', 'Read-Only'];

    public function apply(Config $config): bool
    {
        if (empty($config->fields['ticket_template_enabled'])) {
            return false;
        }

        $ticket = new Ticket();
        $table = $ticket->getTable();

        $so = [
            'content'             => $ticket->getSearchOptionIDByField('field', 'content', $table),
            'itilcategories_id'   => $ticket->getSearchOptionIDByField('field', 'completename', 'glpi_itilcategories'),
            'urgency'             => $ticket->getSearchOptionIDByField('field', 'urgency', $table),
            'impact'              => $ticket->getSearchOptionIDByField('field', 'impact', $table),
            'priority'            => $ticket->getSearchOptionIDByField('field', 'priority', $table),
            'status'              => $ticket->getSearchOptionIDByField('field', 'status', $table),
            'locations_id'        => $ticket->getSearchOptionIDByField('field', 'completename', 'glpi_locations'),
            'date'                => $ticket->getSearchOptionIDByField('field', 'date', $table),
            'actiontime'          => $ticket->getSearchOptionIDByField('field', 'actiontime', $table),
            'time_to_resolve'     => $ticket->getSearchOptionIDByField('field', 'time_to_resolve', $table),
            '_suppliers_id_assign' => $ticket->getSearchOptionIDByField('field', 'name', 'glpi_suppliers'),
            // Hardcoded the same way GLPI's own ITILTemplate::getAllowedFields() hardcodes them —
            // no SearchOption lookup resolves these (they're actor pseudo-fields, not real columns).
            '_users_id_assign'    => 5,
            '_groups_id_assign'   => 8,
            '_users_id_observer'  => 66,
            '_groups_id_observer' => 65,
        ];

        $simplifiedId = $this->getOrCreateTemplate(self::SIMPLIFIED_NAME);
        $this->ensureMandatory($simplifiedId, $so['content']);
        foreach ([
            'itilcategories_id', 'urgency', 'impact', 'priority', 'status', 'locations_id',
            'date', 'actiontime', 'time_to_resolve',
            '_users_id_assign', '_groups_id_assign', '_suppliers_id_assign',
            '_users_id_observer', '_groups_id_observer',
        ] as $key) {
            $this->ensureHidden($simplifiedId, $so[$key]);
        }

        $completeId = $this->getOrCreateTemplate(self::COMPLETE_NAME);
        foreach (['content', 'itilcategories_id', 'urgency'] as $key) {
            $this->ensureMandatory($completeId, $so[$key]);
        }

        $this->assignToProfiles($simplifiedId, $completeId);

        return true;
    }

    private function getOrCreateTemplate(string $name): int
    {
        $template = new TicketTemplate();
        if ($template->getFromDBByCrit(['name' => $name, 'entities_id' => 0])) {
            return (int) $template->getID();
        }

        return (int) $template->add([
            'name'         => $name,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);
    }

    private function ensureMandatory(int $templateId, int $num): void
    {
        if ($num < 0) {
            return;
        }
        $field = new TicketTemplateMandatoryField();
        if (!$field->getFromDBByCrit(['tickettemplates_id' => $templateId, 'num' => $num])) {
            $field->add(['tickettemplates_id' => $templateId, 'num' => $num]);
        }
    }

    private function ensureHidden(int $templateId, int $num): void
    {
        if ($num < 0) {
            return;
        }
        $field = new TicketTemplateHiddenField();
        if (!$field->getFromDBByCrit(['tickettemplates_id' => $templateId, 'num' => $num])) {
            $field->add(['tickettemplates_id' => $templateId, 'num' => $num]);
        }
    }

    /**
     * Every existing profile gets pointed at one of the two templates — re-run safe (always sets
     * the same value), same "point of entry, not final" philosophy as the rest of the wizard: an
     * admin can still override a given profile's template by hand afterward.
     */
    private function assignToProfiles(int $simplifiedId, int $completeId): void
    {
        $profile = new Profile();
        foreach ($profile->find() as $row) {
            $templateId = in_array($row['name'], self::SIMPLIFIED_PROFILES, true) ? $simplifiedId : $completeId;
            $profile->update(['id' => $row['id'], 'tickettemplates_id' => $templateId]);
        }
    }
}
