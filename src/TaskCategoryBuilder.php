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
use TaskCategory;

/**
 * Turns on `task_categories_enabled` into a flat `TaskCategory` list (Configuration > Intitulés >
 * Assistance > "Catégories de tâches") — describes what *kind of work* a technician's task was
 * (diagnostic, install, config, escalation...), independent of `ITILCategory` (`CategoryBuilder`,
 * which describes what the *ticket* is about). GLPI ships none of these by default. Flat, not a
 * tree like `ITILCategory` — inspired by a real production GLPI export's own task-category list,
 * which was itself flat; nothing about "kind of technician work" naturally nests the way topical
 * ticket categories (IT > Poste de travail > Portable) do.
 *
 * Same icon mechanism as `CategoryBuilder`/`StateBuilder`: plain Unicode emoji via
 * `DropdownTranslation` (fr_FR, field `name`), never HTML in `name` itself.
 */
class TaskCategoryBuilder
{
    private const CATEGORIES = [
        ['icon' => '🔍', 'name' => 'Diagnostic & Analyse', 'comment' => 'Analyse de la situation, collecte d\'informations, reproduction du problème'],
        ['icon' => '📥', 'name' => 'Installation logicielle', 'comment' => 'Installation, mise à jour ou suppression d\'un logiciel'],
        ['icon' => '📦', 'name' => 'Déploiement matériel', 'comment' => 'Livraison, montage, remplacement ou retrait d\'un équipement'],
        ['icon' => '⚙️', 'name' => 'Configuration système', 'comment' => 'Paramétrage OS, réseau, comptes locaux'],
        ['icon' => '🧰', 'name' => 'Maintenance préventive', 'comment' => 'Nettoyage, vérifications périodiques, mises à jour planifiées'],
        ['icon' => '💬', 'name' => 'Support & Assistance utilisateur', 'comment' => 'Accompagnement direct de l\'utilisateur, prise en main à distance'],
        ['icon' => '🎓', 'name' => 'Formation', 'comment' => 'Session de formation ou de sensibilisation à un outil'],
        ['icon' => '🪜', 'name' => 'Escalade fournisseur / Éditeur', 'comment' => 'Ouverture d\'un ticket support chez le fournisseur ou éditeur'],
        ['icon' => '🛡️', 'name' => 'Sécurité & Audit', 'comment' => 'Analyse de vulnérabilité, remédiation, audit de conformité'],
        ['icon' => '📚', 'name' => 'Documentation', 'comment' => 'Rédaction ou mise à jour d\'une procédure, d\'un guide ou d\'une base de connaissances'],
        ['icon' => '🗓️', 'name' => 'Coordination & Réunion', 'comment' => 'Échanges internes, points de synchronisation, réunions projet'],
        ['icon' => '✅', 'name' => 'Test & Validation', 'comment' => 'Recette fonctionnelle, tests de non-régression, validation avant mise en production'],
        ['icon' => '👤', 'name' => 'Gestion des comptes utilisateurs', 'comment' => 'Création, modification, arrivée/départ de collaborateur'],
        ['icon' => '🔑', 'name' => 'Réinitialisation mot de passe'],
    ];

    /**
     * @return int Number of task categories created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['task_categories_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['category_icons_enabled']);

        $count = 0;
        foreach (self::CATEGORIES as $node) {
            $count += $this->buildNode($node, $withIcons);
        }

        return $count;
    }

    /**
     * @return array<int, array{icon: string, name: string, comment?: string}>
     */
    public static function getCategoriesPreview(): array
    {
        return self::CATEGORIES;
    }

    private function buildNode(array $node, bool $withIcons): int
    {
        $item = new TaskCategory();
        $crit = ['name' => $node['name'], 'taskcategories_id' => 0];
        if (!$item->getFromDBByCrit($crit)) {
            $id = $item->add($crit + [
                'comment' => $node['comment'] ?? '',
                'entities_id' => 0,
                'is_recursive' => 1,
                'is_helpdeskvisible' => 0,
            ]);
            $item->getFromDB($id);
        }
        $itemId = (int) $item->getID();

        if ($withIcons) {
            $translation = new DropdownTranslation();
            $transCrit = ['itemtype' => TaskCategory::class, 'items_id' => $itemId, 'language' => 'fr_FR', 'field' => 'name'];
            if (!$translation->getFromDBByCrit($transCrit)) {
                $translation->add($transCrit + ['value' => sprintf('%s %s', $node['icon'], $node['name'])]);
            }
        }

        return 1;
    }
}
