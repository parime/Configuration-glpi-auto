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

use Entity_KnowbaseItem;
use Glpi\DBAL\QuerySubQuery;
use KnowbaseItem;
use KnowbaseItem_KnowbaseItem;
use KnowbaseItemCategory;
use KnowbaseItemTranslation;

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
        if (Compat\GlpiVersion::isAtLeast12()) {
            return $this->getOrCreateContainerArticle($name, $withIcon ? $icon : '');
        }

        $item = new KnowbaseItemCategory();
        $crit = ['name' => $name, 'knowbaseitemcategories_id' => 0];
        if (!$item->getFromDBByCrit($crit)) {
            $id = $item->add($crit + ['entities_id' => 0, 'is_recursive' => 1]);
            $item->getFromDB($id);
        }
        $itemId = (int) $item->getID();

        // Always called (see StateBuilder::build() for the reasoning) so unchecking icons after a
        // prior run actually strips them instead of leaving old rows stuck.
        Translations::applyIcon(KnowbaseItemCategory::class, $itemId, $name, $withIcon ? $icon : '');

        return $itemId;
    }

    /**
     * GLPI 12 removed `KnowbaseItemCategory`: its own 11→12 migration turns every category into
     * an empty "container" article (`answer` '', not FAQ) linked under the KB root article through
     * `KnowbaseItem_KnowbaseItem`. Same shape here, so a branch looks exactly like a migrated
     * category. One difference: a recursive root-entity visibility, since a migrated container is
     * closed by default and self-service users could no longer browse it — a GLPI 11 category was
     * visible to everyone. Icons move from `DropdownTranslation` to `KnowbaseItemTranslation`.
     */
    private function getOrCreateContainerArticle(string $name, string $icon): int
    {
        global $DB;

        $rootId = KnowbaseItem::getRootId();

        $existing = $DB->request([
            'SELECT' => 'id',
            'FROM'   => KnowbaseItem::getTable(),
            'WHERE'  => [
                'name' => $name,
                'id'   => new QuerySubQuery([
                    'SELECT' => 'knowbaseitems_id',
                    'FROM'   => KnowbaseItem_KnowbaseItem::getTable(),
                    'WHERE'  => ['knowbaseitems_id_parent' => $rootId],
                ]),
            ],
            'LIMIT'  => 1,
        ])->current();

        if ($existing !== null) {
            $itemId = (int) $existing['id'];
        } else {
            $itemId = (int) (new KnowbaseItem())->add([
                'name'         => $name,
                'answer'       => '',
                'is_faq'       => 0,
                'entities_id'  => 0,
                'is_recursive' => 1,
            ]);
            // add() attaches a parentless article to the root itself; kept as a safety net.
            if (!KnowbaseItem_KnowbaseItem::isParentOf($rootId, $itemId)) {
                (new KnowbaseItem_KnowbaseItem())->add([
                    'knowbaseitems_id'        => $itemId,
                    'knowbaseitems_id_parent' => $rootId,
                ]);
            }
        }

        $visibility = ['knowbaseitems_id' => $itemId, 'entities_id' => 0, 'is_recursive' => 1];
        if (!(new Entity_KnowbaseItem())->getFromDBByCrit($visibility)) {
            (new Entity_KnowbaseItem())->add($visibility);
        }

        // Always called, same reason as the DropdownTranslation branch above.
        foreach (Translations::namesByLanguage($name) as $language => $text) {
            $value = trim(sprintf('%s %s', $icon, $text));
            $translation = new KnowbaseItemTranslation();
            $crit = ['knowbaseitems_id' => $itemId, 'language' => $language];
            if (!$translation->getFromDBByCrit($crit)) {
                $translation->add($crit + ['name' => $value, 'answer' => '']);
            } elseif ($translation->fields['name'] !== $value) {
                $translation->update(['id' => (int) $translation->getID(), 'name' => $value]);
            }
        }

        return $itemId;
    }
}
