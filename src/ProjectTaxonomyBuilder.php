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

use ProjectState;
use ProjectTaskType;
use ProjectType;

/**
 * Turns on `project_taxonomy_enabled` into real `ProjectType`/`ProjectTaskType` lists
 * (Configuration > Intitulés > Assistance > "Types de projet" / "Types de tâche de projet") — GLPI
 * ships neither by default. Both are flat name+comment dropdowns, no tree.
 *
 * `ProjectType` (what *kind* of project) and `ProjectTaskType` (what *kind* of task within a
 * project) are a genuinely different concern from `TaskCategoryBuilder`'s `TaskCategory`, which
 * only applies to tasks on tickets/changes/problems — `ProjectTask` is its own object type with
 * its own type dropdown.
 *
 * Unlike most other builders in this plugin, neither list was present in the audited production
 * export (its own "statut de projet" export only had GLPI's 3 native `ProjectState` rows,
 * unmodified — no project type/task type customization to draw from), so this is generalized from
 * standard PM practice rather than real org data — a starting point, same as everywhere else.
 *
 * `ProjectState` (added on explicit user request, #155): the 3 native rows have no "on hold" or
 * "cancelled" concept at all (confirmed via `DESCRIBE` + reading the actual 3 rows, not assumed) —
 * both common in real project tracking. Colors chosen distinct from the native 3 (green/orange/red)
 * so all 6 stay visually distinguishable on the same Kanban board; "Annulé" gets `is_finished = 1`
 * like "Closed" (both stop counting as active work). No icon applied here (unlike `ProjectType`/
 * `ProjectTaskType`): `ProjectState` renders as a colored badge in GLPI's UI, not next to an emoji
 * slot the way a plain dropdown does.
 */
class ProjectTaxonomyBuilder
{
    private const PROJECT_TYPES = [
        ['name' => 'Interne', 'icon' => '🏠', 'comment' => 'Initiative ou amélioration portée par l\'organisation elle-même'],
        ['name' => 'Client / Prestation', 'icon' => '🤝', 'comment' => 'Projet réalisé pour le compte d\'un client'],
        ['name' => 'Infrastructure', 'icon' => '🖥️', 'comment' => 'Datacenter, réseau, serveurs, systèmes'],
        ['name' => 'Déploiement / Migration', 'icon' => '🚀', 'comment' => 'Mise en place ou bascule d\'un outil, d\'un système'],
        ['name' => 'R&D / Innovation', 'icon' => '💡'],
    ];

    // GLPI ships only 3 native ProjectState rows (New/Processing/Closed, confirmed via DESCRIBE +
    // direct row read — not assumed): no "on hold" or "cancelled" concept at all, both extremely
    // common in real project tracking (a project genuinely paused vs. actively progressing is a
    // different state a Kanban/report needs to distinguish, same for a project stopped before
    // completion vs. one that finished). Added on explicit user request (#155). Colors deliberately
    // distinct from the 3 native ones (green/orange/red) so all 6 stay visually distinguishable on
    // the same Kanban board. `is_finished` marks "Annulé" the same way "Closed" already is (both
    // stop counting as active work), confirmed real by DESCRIBE (`glpi_projectstates.is_finished`).
    private const PROJECT_STATES = [
        ['name' => 'En pause', 'color' => '#9e9e9e', 'is_finished' => 0],
        ['name' => 'En attente de validation', 'color' => '#9c27b0', 'is_finished' => 0],
        ['name' => 'Annulé', 'color' => '#424242', 'is_finished' => 1],
    ];

    private const TASK_TYPES = [
        ['name' => 'Analyse & Cadrage', 'icon' => '🔍'],
        ['name' => 'Conception', 'icon' => '📐'],
        ['name' => 'Développement', 'icon' => '💻'],
        ['name' => 'Tests & Recette', 'icon' => '✅'],
        ['name' => 'Déploiement', 'icon' => '🚀'],
        ['name' => 'Documentation', 'icon' => '📄'],
        ['name' => 'Réunion & Pilotage', 'icon' => '🗓️'],
        ['name' => 'Formation', 'icon' => '🎓'],
    ];

    /**
     * @return int Number of project types + task types created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['project_taxonomy_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['project_taxonomy_icons_enabled']);
        $count = 0;
        foreach (self::PROJECT_TYPES as $type) {
            $id = $this->getOrCreate(ProjectType::class, $type['name'], $type['comment'] ?? '');
            // Always called (see StateBuilder::build() for the reasoning) so unchecking icons after
            // a prior run actually strips them instead of leaving old rows stuck.
            Translations::applyIcon(ProjectType::class, $id, $type['name'], $withIcons ? $type['icon'] : '');
            $count++;
        }
        foreach (self::TASK_TYPES as $type) {
            $id = $this->getOrCreate(ProjectTaskType::class, $type['name'], $type['comment'] ?? '');
            // Always called (see StateBuilder::build() for the reasoning) so unchecking icons after
            // a prior run actually strips them instead of leaving old rows stuck.
            Translations::applyIcon(ProjectTaskType::class, $id, $type['name'], $withIcons ? $type['icon'] : '');
            $count++;
        }
        foreach (self::PROJECT_STATES as $state) {
            $this->getOrCreateProjectState($state['name'], $state['color'], $state['is_finished']);
            $count++;
        }

        return $count;
    }

    /**
     * @return array{project_types: array<int, array{name: string, icon: string, comment?: string}>, task_types: array<int, array{name: string, icon: string, comment?: string}>, project_states: array<int, array{name: string, color: string, is_finished: int}>}
     */
    public static function getPreview(): array
    {
        return [
            'project_types' => self::PROJECT_TYPES,
            'task_types' => self::TASK_TYPES,
            'project_states' => self::PROJECT_STATES,
        ];
    }

    /**
     * @param class-string<ProjectType|ProjectTaskType> $class
     */
    private function getOrCreate(string $class, string $name, string $comment): int
    {
        $item = new $class();
        if ($item->getFromDBByCrit(['name' => $name])) {
            return (int) $item->getID();
        }

        return (int) $item->add(['name' => $name, 'comment' => $comment]);
    }

    private function getOrCreateProjectState(string $name, string $color, int $isFinished): int
    {
        $item = new ProjectState();
        if ($item->getFromDBByCrit(['name' => $name])) {
            return (int) $item->getID();
        }

        return (int) $item->add(['name' => $name, 'color' => $color, 'is_finished' => $isFinished]);
    }
}
