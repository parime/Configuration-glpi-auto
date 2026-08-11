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

use ProjectTaskTemplate;
use ProjectTaskType;

/**
 * Turns on `project_task_templates_enabled` into a real `ProjectTaskTemplate` library
 * (Configuration > Intitulés > Assistance > "Gabarits de tâches de projets") — reusable steps a
 * project manager attaches instead of retyping the same milestones on every project. GLPI ships
 * none by default.
 *
 * Each template's `projecttasktypes_id` is resolved by name against whatever
 * `ProjectTaxonomyBuilder` already created — same independent-resolution pattern
 * `TaskTemplateBuilder` uses against `TaskCategoryBuilder`. `description` (not `content` — a
 * different field name than `TaskTemplate`, confirmed in `glpi_projecttasktemplates`'s own schema)
 * is the template's body text.
 */
class ProjectTaskTemplateBuilder
{
    private const TEMPLATES = [
        [
            'name' => 'Cadrage initial',
            'task_type' => 'Analyse & Cadrage',
            'description' => "Définir les objectifs, le périmètre, les livrables attendus et les parties prenantes du projet.",
        ],
        [
            'name' => 'Point d\'avancement',
            'task_type' => 'Réunion & Pilotage',
            'description' => "Faire le point sur l'avancement, les blocages et les prochaines étapes avec les parties prenantes.",
        ],
        [
            'name' => 'Revue de clôture',
            'task_type' => 'Documentation',
            'description' => "Bilan du projet : objectifs atteints, écarts, retour d'expérience, documentation finale.",
        ],
    ];

    /**
     * @return int Number of project task templates created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['project_task_templates_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::TEMPLATES as $template) {
            $this->getOrCreateTemplate($template['name'], $template['description'], $this->findTaskTypeId($template['task_type']));
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, task_type: string, description: string}>
     */
    public static function getLibraryPreview(): array
    {
        return self::TEMPLATES;
    }

    private function findTaskTypeId(string $name): int
    {
        $type = new ProjectTaskType();

        return $type->getFromDBByCrit(['name' => $name]) ? (int) $type->getID() : 0;
    }

    private function getOrCreateTemplate(string $name, string $description, int $taskTypeId): int
    {
        $item = new ProjectTaskTemplate();
        if ($item->getFromDBByCrit(['name' => $name])) {
            return (int) $item->getID();
        }

        return (int) $item->add([
            'name' => $name,
            'description' => $description,
            'projecttasktypes_id' => $taskTypeId,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
    }
}
