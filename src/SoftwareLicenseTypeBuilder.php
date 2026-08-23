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

use SoftwareLicenseType;

/**
 * Turns on `software_license_types_enabled` into real `SoftwareLicenseType` rows
 * (`glpi_softwarelicensetypes`) — confirmed empty on a fresh install, same as every other native
 * "Types" table this plugin already seeds (`AssetTypeBuilder`). Deliberately left out of that
 * class and given its own (see `AssetTypeBuilder`'s docblock): unlike the 26 tables there, this one
 * is a `CommonTreeDropdown` (`softwarelicensetypes_id`/`level`/`ancestors_cache`/`completename`
 * columns, confirmed via `DESCRIBE`), so it needs `add()` calls shaped like `CategoryBuilder`'s
 * tree-building, not a flat `add()` bolted onto a `CommonDropdown` loop.
 *
 * Content is a flat list (every node's parent is `0`, the tree root) rather than an actual
 * multi-level hierarchy — standard software asset management (SAM) practice categorizes a license
 * by its *acquisition/usage model* (OEM, volume, subscription...), which is a single dimension, not
 * a topic tree like `ITILCategory`'s. `CommonTreeDropdown`'s parent/child machinery still applies
 * (that's what the class *is* in GLPI core), it's just not exercised beyond one level here — an
 * admin can always nest their own sub-types afterward if they need to.
 *
 * No `entities_id`/`is_recursive` distinction to make beyond the usual root-entity, recursive
 * scoping every other global dropdown in this plugin already uses (confirmed via the same
 * `DESCRIBE`: this table does carry those columns, like six of `AssetTypeBuilder`'s types).
 *
 * Reviewed for gaps per #146: added "Perpétuelle" (the single most basic SAM distinction —
 * time-unlimited vs. the "Abonnement (SaaS)" entry already present — oddly missing before) and
 * "Académique / Éducation" (a genuinely distinct acquisition channel with its own pricing/renewal
 * terms, not just a variant of an existing entry). `build()` already re-syncs `TYPES` against an
 * existing installation on every run (no `isNew` gate), so these reach an admin who ran the wizard
 * before, not just fresh installs.
 */
class SoftwareLicenseTypeBuilder
{
    /**
     * @var array<int, array{name: string, icon: string}>
     */
    private const TYPES = [
        ['name' => 'OEM', 'icon' => '🏭'],
        ['name' => 'Volume / Contrat entreprise', 'icon' => '📦'],
        ['name' => 'Boîte / Retail', 'icon' => '🛒'],
        ['name' => 'Perpétuelle', 'icon' => '♾️'],
        ['name' => 'Abonnement (SaaS)', 'icon' => '☁️'],
        ['name' => 'Open Source / Gratuite', 'icon' => '🆓'],
        ['name' => 'Essai / Évaluation', 'icon' => '⏱️'],
        ['name' => 'Site', 'icon' => '🏢'],
        ['name' => 'Concurrente', 'icon' => '🔀'],
        ['name' => 'Nommée', 'icon' => '👤'],
        ['name' => 'Académique / Éducation', 'icon' => '🎓'],
        ['name' => 'Don / Occasion', 'icon' => '♻️'],
    ];

    /**
     * @return int Number of license types created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['software_license_types_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['software_license_type_icons_enabled']);
        $count = 0;
        foreach (self::TYPES as $type) {
            $this->getOrCreateType($type, $withIcons);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, icon: string}>
     */
    public static function getTypesPreview(): array
    {
        return self::TYPES;
    }

    private function getOrCreateType(array $type, bool $withIcons): void
    {
        $item = new SoftwareLicenseType();
        $crit = ['name' => $type['name'], 'softwarelicensetypes_id' => 0];
        if (!$item->getFromDBByCrit($crit)) {
            $id = $item->add($crit + [
                'entities_id' => 0,
                'is_recursive' => 1,
            ]);
            $item->getFromDB($id);
        }

        // Always called (see StateBuilder for the reasoning) so unchecking icons after a prior run
        // actually strips them instead of leaving old rows stuck.
        Translations::applyIcon(SoftwareLicenseType::class, (int) $item->getID(), $type['name'], $withIcons ? $type['icon'] : '');
    }
}
