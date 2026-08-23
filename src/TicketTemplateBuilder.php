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

use Profile;
use TicketTemplate;
use TicketTemplateHiddenField;
use TicketTemplateMandatoryField;

/**
 * Creates two `TicketTemplate` rows and wires them to GLPI's default profiles via
 * `glpi_profiles.tickettemplates_id` (a native per-profile override, confirmed in `Profile.php`'s
 * `$helpdesk_rights`/`$common_fields`): a minimal one (title + description, and a category picker
 * restricted to top-level branches only) for the profiles with no elevated rights (Self-Service,
 * Read-Only), and a complete one (category + urgency mandatory, nothing hidden) for every other
 * profile — matching the explicit split requested: base users enter the least possible, staff
 * qualify the ticket.
 *
 * Field numbers (`num` in `glpi_tickettemplate{mandatory,hidden}fields`) are GLPI SearchOption IDs.
 * Resolved via `TicketTemplate::getAllowedFields(true)` — the exact same method GLPI's own "Champs
 * masqués" admin tab uses to build its field list (confirmed by reading `ITILTemplateField::
 * showForITILTemplate()`) — rather than re-deriving `getSearchOptionIDByField()` calls by hand:
 * an earlier version of this class hand-resolved a handful of fields and missed that
 * `TicketTemplate::getExtraAllowedFields()` (not `Ticket`'s own search options) is where SLA/OLA
 * fields (`slas_id_tto`/`_ttr`, `olas_id_tto`/`_ttr`, `time_to_own`, `internal_time_to_own`/
 * `_resolve`) live — calling the real method instead of guessing avoids that class of mistake.
 */
class TicketTemplateBuilder
{
    private const SIMPLIFIED_NAME = 'Ticket simplifié (libre-service)';

    private const COMPLETE_NAME = 'Ticket complet (support)';

    // Exact GLPI default profile names — matched by name, not by right/interface, so a renamed or
    // custom profile isn't silently swept into the wrong bucket.
    private const SIMPLIFIED_PROFILES = ['Self-Service', 'Read-Only'];

    // Fields hidden on the simplified template: everything a base user filing "title + description"
    // doesn't need to see — qualification (urgency/impact/priority/status/location), service-level
    // display (SLA/OLA due dates, both external and internal), staff-only durations, and
    // assignment/observer actor fields. `itilcategories_id` is deliberately NOT here — it stays
    // visible, but restricted to the 11 top-level branches via `is_helpdeskvisible`
    // (`CategoryBuilder`), not via this hidden-field mechanism.
    private const HIDDEN_FOR_SIMPLIFIED = [
        'urgency', 'impact', 'priority', 'status', 'locations_id',
        'date', 'actiontime', 'time_to_resolve', 'time_to_own',
        'slas_id_tto', 'slas_id_ttr', 'olas_id_tto', 'olas_id_ttr',
        'internal_time_to_own', 'internal_time_to_resolve',
        '_users_id_assign', '_groups_id_assign', '_suppliers_id_assign',
        '_users_id_observer', '_groups_id_observer',
    ];

    // Fields mandatory on the complete template: the ITIL-minimum for correct routing/prioritizing.
    private const MANDATORY_FOR_COMPLETE = ['content', 'itilcategories_id', 'urgency'];

    public function apply(Config $config): bool
    {
        if (empty($config->fields['ticket_template_enabled'])) {
            return false;
        }

        // Name => SearchOption ID, the same authoritative map GLPI's own hidden/mandatory-field
        // admin tabs are built from (base fields + Ticket-specific ones, including SLA/OLA).
        $so = array_flip(TicketTemplate::getAllowedFields(true));

        $simplifiedId = $this->getOrCreateTemplate(self::SIMPLIFIED_NAME);
        $this->ensureMandatory($simplifiedId, $so['content'] ?? -1);
        foreach (self::HIDDEN_FOR_SIMPLIFIED as $key) {
            $this->ensureHidden($simplifiedId, $so[$key] ?? -1);
        }
        // Un-hides `itilcategories_id` if an earlier run of this builder (Sprint 19) hid it before
        // category access was scoped down to top-level branches instead.
        $this->ensureNotHidden($simplifiedId, $so['itilcategories_id'] ?? -1);

        $completeId = $this->getOrCreateTemplate(self::COMPLETE_NAME);
        foreach (self::MANDATORY_FOR_COMPLETE as $key) {
            $this->ensureMandatory($completeId, $so[$key] ?? -1);
        }

        // Always called (see StateBuilder::build() for the reasoning) so unchecking icons after a
        // prior run actually strips them instead of leaving old rows stuck.
        $withIcons = !empty($config->fields['ticket_template_icons_enabled']);
        Translations::applyIcon(TicketTemplate::class, $simplifiedId, self::SIMPLIFIED_NAME, $withIcons ? '📝' : '');
        Translations::applyIcon(TicketTemplate::class, $completeId, self::COMPLETE_NAME, $withIcons ? '🛠️' : '');

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

    private function ensureNotHidden(int $templateId, int $num): void
    {
        if ($num < 0) {
            return;
        }
        $field = new TicketTemplateHiddenField();
        if ($field->getFromDBByCrit(['tickettemplates_id' => $templateId, 'num' => $num])) {
            $field->delete(['id' => $field->getID()]);
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
