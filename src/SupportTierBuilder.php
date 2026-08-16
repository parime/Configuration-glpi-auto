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

use Group;

/**
 * Turns on `escalation_enabled` into 3 real technician `Group` rows ("Support N1", "Support N2",
 * "Support N3") — the standard 3-tier ITSM support convention confirmed via web research (InvGate,
 * Giva, TOPdesk, Buchanan...) to be the most common in practice, N1 = first contact, N2 =
 * intermediate/specialist, N3 = advanced/vendor-facing. `is_assign => 1` is mandatory: confirmed in
 * GLPI source (`RuleCommonITILObject::getActions()`) that the `_groups_id_assign` action's
 * `condition` requires it, otherwise the group silently isn't offered as an assignment target.
 *
 * A fourth conceptual level, "N0" (self-service — knowledge base, service catalog, chatbot, no
 * human team), is offered in the wizard as a *label* only (`escalation_includes_n0`) pointing back
 * at this same plugin's own `ServiceCatalogBuilder`/`HelpdeskFormBuilder` — never a fourth `Group`
 * here, since there's no technician team behind it to assign tickets to.
 *
 * Deliberately global (`entities_id => 0`, `is_recursive => 1`), not one set of 3 groups per
 * top-level entity/client: in a real MSP, the same technicians usually handle N1/N2/N3 across every
 * client, only the entity/SLA scoping differs — matches the reasoning already applied by
 * `RuleRightBuilder` for its own scope choices. What *does* vary per client is whether escalation
 * is active and which hops are automatic (`SlaBuilder`'s `$tierGroupIds`/`$autoN1N2`/`$autoN2N3`
 * params), read from `Config::getEscalationSettings()`'s shared value or a per-client
 * `settings.escalation` override.
 *
 * Icons (optional, `support_tier_icons_enabled`) follow the same `DropdownTranslation` mechanism
 * as every other icon feature in this plugin — `Group extends CommonTreeDropdown` (confirmed in
 * GLPI source), so it's eligible. A traffic-light progression (🟢/🟡/🔴) rather than an arbitrary
 * pick: the point is scanning an assignment dropdown for severity at a glance, and green→red is
 * the one color convention that needs no legend.
 */
class SupportTierBuilder
{
    private const TIERS = [
        'n1' => ['name' => 'Support N1', 'icon' => '🟢'],
        'n2' => ['name' => 'Support N2', 'icon' => '🟡'],
        'n3' => ['name' => 'Support N3', 'icon' => '🔴'],
    ];

    /**
     * @return array{n1: int, n2: int, n3: int}|array{} Empty if disabled.
     */
    public function build(Config $config): array
    {
        if (empty($config->fields['escalation_enabled'])) {
            return [];
        }

        $withIcons = !empty($config->fields['support_tier_icons_enabled']);
        $ids = [];
        foreach (self::TIERS as $key => $tier) {
            $id = $this->getOrCreate($tier['name']);
            if ($withIcons) {
                Translations::applyIcon(Group::class, $id, $tier['name'], $tier['icon']);
            }
            $ids[$key] = $id;
        }

        return $ids;
    }

    /**
     * @return array<string, array{name: string, icon: string}> tier key => name/icon, for the wizard's read-only preview.
     */
    public static function getTiersPreview(): array
    {
        return self::TIERS;
    }

    private function getOrCreate(string $name): int
    {
        $group = new Group();
        if ($group->getFromDBByCrit(['name' => $name, 'entities_id' => 0])) {
            return (int) $group->getID();
        }

        return (int) $group->add([
            'name' => $name,
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_assign' => 1,
        ]);
    }

}
