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
            if ($withIcons) {
                $this->addIcon(ProjectType::class, $id, $type['name'], $type['icon']);
            }
            $count++;
        }
        foreach (self::TASK_TYPES as $type) {
            $id = $this->getOrCreate(ProjectTaskType::class, $type['name'], $type['comment'] ?? '');
            if ($withIcons) {
                $this->addIcon(ProjectTaskType::class, $id, $type['name'], $type['icon']);
            }
            $count++;
        }

        return $count;
    }

    /**
     * @return array{project_types: array<int, array{name: string, icon: string, comment?: string}>, task_types: array<int, array{name: string, icon: string, comment?: string}>}
     */
    public static function getPreview(): array
    {
        return ['project_types' => self::PROJECT_TYPES, 'task_types' => self::TASK_TYPES];
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

    /**
     * @param class-string<ProjectType|ProjectTaskType> $class
     */
    private function addIcon(string $class, int $id, string $name, string $icon): void
    {
        $translation = new DropdownTranslation();
        if ($translation->getFromDBByCrit(['itemtype' => $class, 'items_id' => $id, 'language' => 'fr_FR', 'field' => 'name'])) {
            return;
        }

        $translation->add([
            'itemtype' => $class,
            'items_id' => $id,
            'language' => 'fr_FR',
            'field' => 'name',
            'value' => sprintf('%s %s', $icon, $name),
        ]);
    }
}
