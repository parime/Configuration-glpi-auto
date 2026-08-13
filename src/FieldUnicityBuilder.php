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

use FieldUnicity;

/**
 * Turns a Config's field-uniqueness setting into real `FieldUnicity` rows (`glpi_fieldunicities`)
 * — a native GLPI mechanism, empty by default on a fresh install, that blocks (or notifies on)
 * saving a second asset with the same value on a chosen field. Scoped here to the single most
 * universal, uncontroversial case: serial number uniqueness on the six serializable hardware asset
 * types GLPI ships (`$CFG_GLPI['unicity_types']` also lists Budget/Contact/Contract/Software/
 * Supplier/User/Certificate/Rack/... — left alone, since "unique serial number" doesn't apply to
 * those the same way and picking fields for them is an org-specific judgment call, not a universal
 * default).
 *
 * `action_refuse = 1` (block the duplicate outright) rather than `action_notify` — the latter would
 * need a companion `NotificationTargetFieldUnicity` template wired up to actually alert anyone
 * (same gotcha class as `WaitReasonBuilder`'s linked templates), and "silently record a duplicate
 * but also send an email" is a weaker default than "don't record it" for something errors are this
 * cheap to feed back on (the technician just retypes the real serial).
 *
 * `entities_id = 0` + `is_recursive = 1` (root, cascading to every entity) rather than one row per
 * top-level tree node — unlike calendars/SLA, there's no meaningful per-site variant of "should two
 * computers share a serial number" to override.
 *
 * `FieldUnicity::$can_be_translated` is `false` (confirmed in GLPI core) — no icon toggle here,
 * same reasoning as `ProjectTemplateBuilder` for non-`CommonDropdown` itemtypes: `DropdownTranslation`
 * rows would never be read back.
 */
class FieldUnicityBuilder
{
    private const RULES = [
        ['itemtype' => 'Computer', 'label' => 'Ordinateurs'],
        ['itemtype' => 'Monitor', 'label' => 'Écrans'],
        ['itemtype' => 'NetworkEquipment', 'label' => 'Matériel réseau'],
        ['itemtype' => 'Peripheral', 'label' => 'Périphériques'],
        ['itemtype' => 'Phone', 'label' => 'Téléphones'],
        ['itemtype' => 'Printer', 'label' => 'Imprimantes'],
    ];

    /**
     * @return int Number of unicity rules created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['field_unicity_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::RULES as $rule) {
            $this->getOrCreateRule($rule);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{itemtype: string, label: string}>
     */
    public static function getRulesPreview(): array
    {
        return self::RULES;
    }

    private function getOrCreateRule(array $rule): void
    {
        $name = 'Numéro de série unique — ' . $rule['label'];

        $unicity = new FieldUnicity();
        if ($unicity->getFromDBByCrit(['itemtype' => $rule['itemtype'], 'entities_id' => 0])) {
            return;
        }

        $unicity->add([
            'name' => $name,
            'itemtype' => $rule['itemtype'],
            'fields' => 'serial',
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_active' => 1,
            'action_refuse' => 1,
            'action_notify' => 0,
            'comment' => 'Bloque la création/modification d\'un élément si son numéro de série existe déjà sur un autre élément du même type.',
        ]);
    }
}
