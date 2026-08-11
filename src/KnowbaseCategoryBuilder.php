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

use DropdownTranslation;
use KnowbaseItemCategory;

/**
 * Turns on `kb_categories_enabled` into a real `KnowbaseItemCategory` tree (Configuration >
 * Intitulés > Outils > "Catégories de la base de connaissances") — GLPI ships none by default,
 * and a self-service user has nowhere to browse before filing a ticket. Reuses
 * `CategoryBuilder::getCategoriesPreview()`'s 11 top-level branch names/icons instead of a second
 * invented taxonomy: a requester expects the knowledge base to be organized the same way as the
 * ticket categories they already see when filing a request, and only the branches actually
 * selected in step 5 (`Config::getCategoryBranches()`) get a matching KB category — same
 * filtering an admin without a vehicle fleet or industrial maintenance already applies there.
 *
 * Flat (top level only, no sub-tree) — the ticket category tree goes 3 levels deep for triage
 * precision, but a KB table of contents that deep would be more to browse than to read.
 */
class KnowbaseCategoryBuilder
{
    /**
     * @return int Number of KB categories created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['kb_categories_enabled'])) {
            return 0;
        }

        $branches = $config->getCategoryBranches();

        $count = 0;
        foreach (CategoryBuilder::getCategoriesPreview() as $branch) {
            if (!in_array($branch['key'], $branches, true)) {
                continue;
            }
            $this->getOrCreate($branch['name'], $branch['icon'], !empty($config->fields['category_icons_enabled']));
            $count++;
        }

        return $count;
    }

    private function getOrCreate(string $name, string $icon, bool $withIcon): int
    {
        $item = new KnowbaseItemCategory();
        $crit = ['name' => $name, 'knowbaseitemcategories_id' => 0];
        if (!$item->getFromDBByCrit($crit)) {
            $id = $item->add($crit + ['entities_id' => 0, 'is_recursive' => 1]);
            $item->getFromDB($id);
        }
        $itemId = (int) $item->getID();

        if ($withIcon) {
            $translation = new DropdownTranslation();
            $transCrit = ['itemtype' => KnowbaseItemCategory::class, 'items_id' => $itemId, 'language' => 'fr_FR', 'field' => 'name'];
            if (!$translation->getFromDBByCrit($transCrit)) {
                $translation->add($transCrit + ['value' => sprintf('%s %s', $icon, $name)]);
            }
        }

        return $itemId;
    }
}
