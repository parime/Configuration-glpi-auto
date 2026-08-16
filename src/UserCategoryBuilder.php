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

use UserCategory;

/**
 * Turns on `user_categories_enabled` into real `UserCategory` rows (`glpi_usercategories`) — GLPI
 * ships this dropdown empty by default. Confirmed a genuine, non-cosmetic field before building
 * anything: `usercategories_id` on `User` is importable straight from an LDAP/AD attribute
 * (`AuthLDAP::category_field`), used as a notification-targeting criterion
 * (`NotificationTargetCommonITILObject`), and as a statistics breakdown dimension (`Stat.php`).
 *
 * A generic HR-style classification of *who someone is* to the organization — independent of GLPI
 * profiles/rights (which govern *what they can do*) — stable and universal enough to generalize,
 * same reasoning as `PendingReason`/`State`: any organization, regardless of size or sector, has
 * some mix of permanent staff and non-permanent people passing through.
 *
 * A flat, global dropdown (no `entities_id`/`is_recursive` columns on `glpi_usercategories` at
 * all, confirmed in its own schema) — same shape as `ManufacturerBuilder`'s target, unlike most
 * other builders in this plugin.
 */
class UserCategoryBuilder
{
    private const CATEGORIES = [
        ['name' => 'Employé', 'icon' => '👤'],
        ['name' => 'Prestataire externe', 'icon' => '🤝'],
        ['name' => 'Stagiaire', 'icon' => '🎓'],
        ['name' => 'Alternant', 'icon' => '📘'],
        ['name' => 'Intérimaire', 'icon' => '⏱️'],
        ['name' => 'Consultant', 'icon' => '💼'],
    ];

    /**
     * @return int Number of user categories created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['user_categories_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['user_category_icons_enabled']);
        $count = 0;
        foreach (self::CATEGORIES as $category) {
            $id = $this->getOrCreate($category['name']);
            if ($withIcons) {
                Translations::applyIcon(UserCategory::class, $id, $category['name'], $category['icon']);
            }
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, icon: string}>
     */
    public static function getCategoriesPreview(): array
    {
        return self::CATEGORIES;
    }

    private function getOrCreate(string $name): int
    {
        $category = new UserCategory();
        if ($category->getFromDBByCrit(['name' => $name])) {
            return (int) $category->getID();
        }

        return (int) $category->add(['name' => $name]);
    }
}
