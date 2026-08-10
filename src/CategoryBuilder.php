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

use ITILCategory;

/**
 * Turns a Config's category setting into 4 real GLPI ITILCategory rows, one per ITIL ticket type
 * (Incident/Request/Problem/Change — GLPI's `is_incident`/`is_request`/`is_problem`/`is_change`
 * flags on the same object, not 4 separate concepts). Not a per-entity/per-client concept, unlike
 * calendar/SLA — a category tree is shared across the whole instance, so this is instance-wide
 * (`entities_id => 0`, `is_recursive => 1`) same as CategoryBuilder's sibling StateBuilder.
 * Deliberately just the 4 starting categories, not a full custom tree builder like entities: the
 * ITIL 4 categorization is universal enough to be a safe default, the admin renames/extends
 * natively in GLPI afterward. Idempotent: reuses an existing category of the same name.
 */
class CategoryBuilder
{
    private const CATEGORIES = [
        ['name' => 'Incidents', 'is_incident' => 1, 'is_request' => 0, 'is_problem' => 0, 'is_change' => 0],
        ['name' => 'Demandes', 'is_incident' => 0, 'is_request' => 1, 'is_problem' => 0, 'is_change' => 0],
        ['name' => 'Problèmes', 'is_incident' => 0, 'is_request' => 0, 'is_problem' => 1, 'is_change' => 0],
        ['name' => 'Changements', 'is_incident' => 0, 'is_request' => 0, 'is_problem' => 0, 'is_change' => 1],
    ];

    /**
     * @return array<int, array{name: string, is_incident: int, is_request: int, is_problem: int, is_change: int}>
     */
    public static function getCategoriesPreview(): array
    {
        return self::CATEGORIES;
    }

    /**
     * @return string[] Names of the categories created/reused, for the confirmation message.
     */
    public function build(Config $config): array
    {
        if (empty($config->fields['category_enabled'])) {
            return [];
        }

        $names = [];
        foreach (self::CATEGORIES as $category) {
            $item = new ITILCategory();
            if (!$item->getFromDBByCrit(['name' => $category['name']])) {
                $item->add($category + ['entities_id' => 0, 'is_recursive' => 1]);
            }
            $names[] = $category['name'];
        }

        return $names;
    }
}
