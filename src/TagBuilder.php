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

/**
 * Only runs if the third-party "Tag" plugin (pluginsGLPI/tag, marketplace key `tag`) is active —
 * never a hard dependency, same reasoning as SatisfactionSurveyBuilder/VipBuilder. Confirmed via
 * `remise-glpi`'s own README/ARCHITECTURE/CHANGELOG (fetched via `gh api`, matches were incidental
 * French words containing the substring "tag" — "partagé", "déclenchement"...) that it has no
 * overlapping tagging/labelling feature, per the user's own initial concern.
 *
 * Confirmed live on a real install: `glpi_plugin_tag_tags` (`PluginTagTag extends CommonDropdown`)
 * is a plain, empty-on-fresh-install dropdown table — `name`/`comment`/`color`/`is_active`, plus a
 * `type_menu` column holding a JSON array of itemtype strings that restricts which object types a
 * tag is selectable on (`PluginTagTag::canItemtype()`: an empty/null `type_menu` means the tag is
 * usable everywhere). Assignment itself lives in a separate polymorphic link table
 * (`glpi_plugin_tag_tagitems`, `itemtype`/`items_id`/`plugin_tag_tags_id`) this builder never
 * touches — seeding tag *definitions* is generalisable, seeding which real ticket/asset gets which
 * tag is not (same reasoning as leaving RuleVip's per-organisation criteria untouched in
 * VipBuilder).
 *
 * Seeds a small set of universally useful global tags (`type_menu` left null) — not org-specific
 * guesswork, the same "seed a sensible starting point, let the admin customise/rename/add more"
 * pattern already used by RSSFeedBuilder/AssetTypeBuilder/WaitReasonBuilder.
 */
class TagBuilder
{
    private const TAGS = [
        ['name' => 'Prioritaire', 'color' => '#dc3545', 'comment' => 'Élément à traiter en priorité.'],
        ['name' => 'Urgent', 'color' => '#fd7e14', 'comment' => 'Nécessite une action immédiate.'],
        ['name' => 'À vérifier', 'color' => '#ffc107', 'comment' => 'Information ou état à confirmer.'],
        ['name' => 'Obsolète', 'color' => '#6c757d', 'comment' => 'Élément dépassé, à réviser ou remplacer.'],
        ['name' => 'Garantie active', 'color' => '#198754', 'comment' => 'Encore couvert par une garantie constructeur ou fournisseur.'],
        ['name' => 'Confidentiel', 'color' => '#6f42c1', 'comment' => 'Contenu sensible, à traiter avec discrétion.'],
    ];

    public function build(Config $config): int
    {
        if (empty($config->fields['tag_library_enabled'])) {
            return 0;
        }

        if (!self::isThirdPartyPluginActive()) {
            return 0;
        }

        global $DB;
        $created = 0;
        foreach (self::TAGS as $tag) {
            $existing = $DB->request([
                'FROM' => 'glpi_plugin_tag_tags',
                'WHERE' => ['name' => $tag['name'], 'entities_id' => 0],
            ]);
            if (count($existing) > 0) {
                continue;
            }

            $DB->insert('glpi_plugin_tag_tags', [
                'entities_id' => 0,
                'is_recursive' => 1,
                'is_active' => 1,
                'name' => $tag['name'],
                'comment' => $tag['comment'],
                'color' => $tag['color'],
            ]);
            $created++;
        }

        return $created;
    }

    public static function isThirdPartyPluginActive(): bool
    {
        return class_exists('Plugin') && \Plugin::isPluginActive('tag');
    }
}
