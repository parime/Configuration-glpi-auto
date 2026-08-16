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

use Project;
use ProjectTask;
use ProjectTaskType;
use ProjectType;

/**
 * Turns on `project_templates_enabled` into real `Project` rows with `is_template=1` — a gap
 * confirmed by reading GLPI's own source rather than assumed: `Project` uses the generic
 * `CommonDBTM` templating fields (`is_template`/`template_name`/`projecttemplates_id`, the same
 * mechanism `Computer`/`NetworkEquipment` use, not `ITILTemplate`), and
 * `Project::getCloneRelations()` explicitly includes `ProjectTask::class` — meaning GLPI's own
 * "create from template" flow (the template picker CommonDBTM shows when adding a new `Project`)
 * already clones every `ProjectTask` attached to a template project automatically. GLPI ships this
 * machinery but no template plugged into it, same gap `TaskTemplateBuilder` closes for tickets.
 *
 * Each template's `projecttypes_id`/`projecttasktypes_id` are resolved by name against whatever
 * `ProjectTaxonomyBuilder` already created — same independent-resolution pattern
 * `ProjectTaskTemplateBuilder` already uses. Two templates, not one, covering the two shapes of
 * project this plugin's other content already assumes exist (`ProjectTaxonomyBuilder`'s "Interne"
 * vs "Déploiement / Migration" types): a full delivery lifecycle, and a light internal cycle
 * reusing the same three milestones as `ProjectTaskTemplateBuilder`'s own library for consistency.
 * No dates on any task — a real schedule is inherently specific to each project, nothing generic to
 * invent here.
 *
 * No icon *toggle* here unlike most other builders in this plugin — checked against GLPI's own
 * source before copying that pattern over: `Project` isn't a `CommonDropdown`, and the only two
 * places `DropdownTranslation::getTranslatedValue()` is ever called in GLPI core are
 * `AbstractITILChildTemplate` (the 18 followup/task/solution templates, a genuinely different
 * class hierarchy) and `Dropdown::getDropdownValue()`'s generic AJAX search. The template picker
 * `CommonDBTM` itself shows when adding a new `Project` (`CommonDBTM::getFromDBByCrit(['is_template'
 * => 1])`, ordered by `template_name`) builds its own list straight from `$data["template_name"]`,
 * bypassing that lookup entirely — so a `Translations::applyIcon()` call here would write rows
 * nothing ever reads. The icon is embedded directly in `name`/`template_name` instead: no
 * multi-language layer exists for `Project` to plug into either way, so there is nothing an
 * "icons" toggle could actually turn on or off beyond that.
 */
class ProjectTemplateBuilder
{
    private const TEMPLATES = [
        [
            'name' => 'Déploiement standard',
            'icon' => '🚀',
            'project_type' => 'Déploiement / Migration',
            'content' => 'Modèle de projet pour un déploiement ou une migration : cadrage, conception, mise en œuvre, tests et clôture.',
            'tasks' => [
                ['name' => 'Analyse & Cadrage', 'task_type' => 'Analyse & Cadrage', 'content' => 'Définir les objectifs, le périmètre, les livrables attendus et les parties prenantes.'],
                ['name' => 'Conception', 'task_type' => 'Conception', 'content' => 'Concevoir la solution cible et valider les choix techniques.'],
                ['name' => 'Mise en œuvre', 'task_type' => 'Développement', 'content' => 'Réaliser le déploiement ou la migration.'],
                ['name' => 'Tests & Recette', 'task_type' => 'Tests & Recette', 'content' => 'Vérifier le bon fonctionnement et faire valider par les parties prenantes.'],
                ['name' => 'Déploiement en production', 'task_type' => 'Déploiement', 'content' => 'Basculer en production et communiquer aux utilisateurs concernés.'],
                ['name' => 'Documentation & Clôture', 'task_type' => 'Documentation', 'content' => 'Documenter la solution livrée et clôturer le projet.'],
            ],
        ],
        [
            'name' => 'Projet interne — cycle court',
            'icon' => '🏠',
            'project_type' => 'Interne',
            'content' => 'Modèle léger pour une initiative interne ou une amélioration continue.',
            'tasks' => [
                ['name' => 'Cadrage initial', 'task_type' => 'Analyse & Cadrage', 'content' => 'Définir les objectifs, le périmètre, les livrables attendus et les parties prenantes du projet.'],
                ['name' => 'Point d\'avancement', 'task_type' => 'Réunion & Pilotage', 'content' => 'Faire le point sur l\'avancement, les blocages et les prochaines étapes avec les parties prenantes.'],
                ['name' => 'Revue de clôture', 'task_type' => 'Documentation', 'content' => 'Bilan du projet : objectifs atteints, écarts, retour d\'expérience, documentation finale.'],
            ],
        ],
    ];

    /**
     * @return int Number of template projects created/reused (their linked tasks aren't counted
     *             separately — they're an inherent part of each template, not an independent unit).
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['project_templates_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::TEMPLATES as $template) {
            $projectId = $this->getOrCreateTemplate(
                $template['icon'] . ' ' . $template['name'],
                $template['content'],
                $this->findTypeId(ProjectType::class, $template['project_type']),
            );
            foreach ($template['tasks'] as $task) {
                $this->getOrCreateTask(
                    $projectId,
                    $task['name'],
                    $task['content'],
                    $this->findTypeId(ProjectTaskType::class, $task['task_type']),
                );
            }
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, icon: string, project_type: string, content: string, tasks: array<int, array{name: string, task_type: string, content: string}>}>
     */
    public static function getPreview(): array
    {
        return self::TEMPLATES;
    }

    /**
     * @param class-string<ProjectType|ProjectTaskType> $class
     */
    private function findTypeId(string $class, string $name): int
    {
        $type = new $class();

        return $type->getFromDBByCrit(['name' => $name]) ? (int) $type->getID() : 0;
    }

    private function getOrCreateTemplate(string $name, string $content, int $projectTypeId): int
    {
        $project = new Project();
        $crit = ['is_template' => 1, 'template_name' => $name];
        if ($project->getFromDBByCrit($crit)) {
            return (int) $project->getID();
        }

        return (int) $project->add($crit + [
            'name' => $name,
            'content' => $content,
            'projecttypes_id' => $projectTypeId,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
    }

    private function getOrCreateTask(int $projectId, string $name, string $content, int $taskTypeId): void
    {
        $task = new ProjectTask();
        $crit = ['projects_id' => $projectId, 'name' => $name];
        if ($task->getFromDBByCrit($crit)) {
            return;
        }

        $task->add($crit + [
            'content' => $content,
            'projecttasktypes_id' => $taskTypeId,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
    }
}
