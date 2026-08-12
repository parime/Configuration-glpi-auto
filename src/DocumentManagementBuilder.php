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

use BusinessCriticity;
use DocumentCategory;

/**
 * Turns on `document_management_enabled` into real `DocumentCategory`/`BusinessCriticity` lists
 * (Configuration > Intitulés > Gestion) — GLPI ships neither by default. Third intitulé in that
 * same "Gestion" group, `DocumentType` (Configuration > Intitulés > Gestion > "Types de documents"),
 * is deliberately left untouched: confirmed on a fresh GLPI 11.0.8 install it already ships 73
 * native rows (every common file-extension type) — already sufficient, same "verified, nothing to
 * build" conclusion as `RequestType`/`ProjectState` elsewhere in this plugin.
 *
 * The two lists cover genuinely different things, confirmed by checking what actually references
 * each foreign key in GLPI core rather than assuming from the label alone:
 * - `DocumentCategory` (`glpi_documents.documentcategories_id`) classifies an uploaded *document*
 *   itself. `glpi_documents` has no native confidentiality/sensitivity field at all — this is the
 *   closest GLPI-native mechanism to one, so the list here follows the standard ISO 27001 (Annex
 *   A.8.2) information-classification scale (Public/Interne/Confidentiel/Diffusion restreinte)
 *   rather than a generic "kind of document" taxonomy that would just duplicate `DocumentType`.
 * - `BusinessCriticity` (`glpi_infocoms.businesscriticities_id`, confirmed via
 *   `information_schema.COLUMNS` — the only other table referencing it) rates the business impact
 *   of an *asset* (via its `Infocom`/financial-info record), not a document — a standard 4-level
 *   severity scale, same traffic-light convention as `SupportTierBuilder`'s N1/N2/N3 groups.
 *
 * Both are flat (no natural sub-tree for either scale) `CommonTreeDropdown`s. `DocumentCategory`'s
 * own table has no `entities_id`/`is_recursive` columns (confirmed via `DESCRIBE`) — a global,
 * non-entity-scoped dropdown, unlike `BusinessCriticity` which is entity-scoped like most other
 * intitulés this plugin creates.
 */
class DocumentManagementBuilder
{
    private const DOCUMENT_CATEGORIES = [
        ['icon' => '🌐', 'name' => 'Public', 'comment' => 'Diffusion libre, sans restriction.'],
        ['icon' => '🏢', 'name' => 'Interne', 'comment' => 'Usage interne, non destiné à une diffusion externe.'],
        ['icon' => '🔒', 'name' => 'Confidentiel', 'comment' => 'Accès restreint aux personnes habilitées (besoin d\'en connaître).'],
        ['icon' => '🚫', 'name' => 'Diffusion restreinte', 'comment' => 'Accès nominatif uniquement — le niveau le plus sensible.'],
    ];

    private const BUSINESS_CRITICITIES = [
        ['icon' => '🔴', 'name' => 'Critique', 'comment' => 'Indisponibilité impactant directement l\'activité, tolérance zéro.'],
        ['icon' => '🟠', 'name' => 'Élevée', 'comment' => 'Impact significatif, correction à traiter en priorité.'],
        ['icon' => '🟡', 'name' => 'Moyenne', 'comment' => 'Impact modéré, délai de correction normal.'],
        ['icon' => '🟢', 'name' => 'Faible', 'comment' => 'Impact limité, aucune urgence de traitement.'],
    ];

    /**
     * @return int Number of document categories + business criticities created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['document_management_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['document_management_icons_enabled']);
        $count = 0;

        foreach (self::DOCUMENT_CATEGORIES as $node) {
            $item = new DocumentCategory();
            $crit = ['name' => $node['name'], 'documentcategories_id' => 0];
            if (!$item->getFromDBByCrit($crit)) {
                $id = $item->add($crit + ['comment' => $node['comment']]);
                $item->getFromDB($id);
            }
            if ($withIcons) {
                Translations::applyIcon(DocumentCategory::class, (int) $item->getID(), $node['name'], $node['icon']);
            }
            $count++;
        }

        foreach (self::BUSINESS_CRITICITIES as $node) {
            $item = new BusinessCriticity();
            $crit = ['name' => $node['name'], 'businesscriticities_id' => 0];
            if (!$item->getFromDBByCrit($crit)) {
                $id = $item->add($crit + ['comment' => $node['comment'], 'entities_id' => 0, 'is_recursive' => 1]);
                $item->getFromDB($id);
            }
            if ($withIcons) {
                Translations::applyIcon(BusinessCriticity::class, (int) $item->getID(), $node['name'], $node['icon']);
            }
            $count++;
        }

        return $count;
    }

    /**
     * @return array{document_categories: array<int, array{icon: string, name: string, comment: string}>, business_criticities: array<int, array{icon: string, name: string, comment: string}>}
     */
    public static function getPreview(): array
    {
        return [
            'document_categories' => self::DOCUMENT_CATEGORIES,
            'business_criticities' => self::BUSINESS_CRITICITIES,
        ];
    }
}
