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
 * Only runs if the third-party "VIP" plugin (pluginsGLPI/vip, marketplace key `vip`) is active —
 * never a hard dependency, same reasoning as SatisfactionSurveyBuilder. Not a technician/support
 * group — the plugin flags priority *requesters* (executives, shareholders, any stakeholder whose
 * tickets should stand out), badging their tickets for the support team's attention. Confirmed live
 * on a real install: the plugin extends the native `Group` object with one row per group in its own
 * `glpi_plugin_vip_groups` table (`id` mirrors `glpi_groups.id`, no auto-increment — its own
 * Group::processMassiveActionsForOneItemtype() inserts with an explicit `id`, never lets MySQL
 * generate one), holding an `isvip` flag plus a display `name`/`vip_color`/`vip_icon` used to
 * badge tickets from VIP-flagged groups' members.
 *
 * The plugin's own `plugin_vip_install()` already grants the `plugin_vip` right to existing
 * profiles (`Profile::initProfile()`) and its rule engine (`RuleVip`, "assign VIP group from LDAP
 * criteria") is inherently per-organisation (depends on the customer's own AD/LDAP OU layout) —
 * same reasoning already applied to excluding LDAP diagnostics from this plugin's generalist
 * scope, so neither is touched here.
 *
 * What *is* safely generalisable: seeding one native "VIP" Group and flagging it `isvip=1`, so the
 * feature is immediately visible and usable (the Group tab, the ticket badge) the moment the admin
 * adds real members — mirrors the same "seed a sensible starting point, let the admin customise"
 * pattern already used by RSSFeedBuilder/SupportTierBuilder, not org-specific guesswork.
 */
class VipBuilder
{
    private const GROUP_NAME = 'VIP';

    public function build(Config $config): int
    {
        if (empty($config->fields['vip_group_enabled'])) {
            return 0;
        }

        if (!self::isThirdPartyPluginActive()) {
            return 0;
        }

        $groupId = $this->getOrCreateGroup();
        $this->markAsVip($groupId);

        return 1;
    }

    public static function isThirdPartyPluginActive(): bool
    {
        return class_exists('Plugin') && \Plugin::isPluginActive('vip');
    }

    private function getOrCreateGroup(): int
    {
        $group = new Group();
        if ($group->getFromDBByCrit(['name' => self::GROUP_NAME, 'entities_id' => 0])) {
            return (int) $group->getID();
        }

        return (int) $group->add([
            'name' => self::GROUP_NAME,
            'entities_id' => 0,
            'is_recursive' => 1,
            'comment' => 'Groupe cree automatiquement par Configuration GLPI Auto : ajoutez-y les '
                . 'personnes ou groupes prioritaires (direction, actionnaires...) dont les tickets '
                . 'doivent etre mis en evidence. Ne remplace pas un groupe de techniciens.',
        ]);
    }

    private function markAsVip(int $groupId): void
    {
        global $DB;

        $existing = $DB->request(['FROM' => 'glpi_plugin_vip_groups', 'WHERE' => ['id' => $groupId]]);
        if (count($existing) > 0) {
            $DB->update('glpi_plugin_vip_groups', ['isvip' => 1, 'name' => self::GROUP_NAME], ['id' => $groupId]);

            return;
        }

        // Primary key mirrors glpi_groups.id, no auto-increment on this table — must be provided
        // explicitly on insert (confirmed in the third-party plugin's own Group::
        // processMassiveActionsForOneItemtype()).
        $DB->insert('glpi_plugin_vip_groups', [
            'id' => $groupId,
            'name' => self::GROUP_NAME,
            'isvip' => 1,
            'vip_color' => '#ff0000',
            'vip_icon' => 'ti-vip',
        ]);
    }
}
