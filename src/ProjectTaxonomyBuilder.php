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
        ['name' => 'Interne', 'comment' => 'Initiative ou amélioration portée par l\'organisation elle-même'],
        ['name' => 'Client / Prestation', 'comment' => 'Projet réalisé pour le compte d\'un client'],
        ['name' => 'Infrastructure', 'comment' => 'Datacenter, réseau, serveurs, systèmes'],
        ['name' => 'Déploiement / Migration', 'comment' => 'Mise en place ou bascule d\'un outil, d\'un système'],
        ['name' => 'R&D / Innovation'],
    ];

    private const TASK_TYPES = [
        ['name' => 'Analyse & Cadrage'],
        ['name' => 'Conception'],
        ['name' => 'Développement'],
        ['name' => 'Tests & Recette'],
        ['name' => 'Déploiement'],
        ['name' => 'Documentation'],
        ['name' => 'Réunion & Pilotage'],
        ['name' => 'Formation'],
    ];

    /**
     * @return int Number of project types + task types created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['project_taxonomy_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::PROJECT_TYPES as $type) {
            $this->getOrCreate(ProjectType::class, $type['name'], $type['comment'] ?? '');
            $count++;
        }
        foreach (self::TASK_TYPES as $type) {
            $this->getOrCreate(ProjectTaskType::class, $type['name'], $type['comment'] ?? '');
            $count++;
        }

        return $count;
    }

    /**
     * @return array{project_types: array<int, array{name: string, comment?: string}>, task_types: array<int, array{name: string, comment?: string}>}
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
}
