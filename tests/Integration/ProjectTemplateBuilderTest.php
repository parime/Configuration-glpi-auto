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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration;

use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ProjectTaxonomyBuilder;
use GlpiPlugin\Configurationglpiauto\ProjectTemplateBuilder;
use PHPUnit\Framework\TestCase;
use Project;
use ProjectTask;
use ProjectTaskType;
use ProjectType;

final class ProjectTemplateBuilderTest extends TestCase
{
    private const TEMPLATE_NAME = '🚀 Déploiement standard';

    private const PROJECT_TYPE_NAME = 'Déploiement / Migration';

    /**
     * `ProjectTemplateBuilder::findTypeId()` resolves each template's project/task types by name
     * against whatever `ProjectTaxonomyBuilder` already created — it never creates those itself.
     */
    protected function setUp(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['project_taxonomy_enabled' => 1]);
        (new ProjectTaxonomyBuilder())->build($config);
    }

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'project_templates_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new ProjectTemplateBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildCreatesBothTemplateProjectsWithTheirTasks(): void
    {
        $count = (new ProjectTemplateBuilder())->build($this->buildConfig(true));

        $this->assertSame(2, $count);

        $projectType = new ProjectType();
        $projectType->getFromDBByCrit(['name' => self::PROJECT_TYPE_NAME]);

        $project = new Project();
        $this->assertTrue($project->getFromDBByCrit(['is_template' => 1, 'template_name' => self::TEMPLATE_NAME]));
        $this->assertSame(self::TEMPLATE_NAME, $project->fields['name']);
        $this->assertSame((int) $projectType->getID(), (int) $project->fields['projecttypes_id']);
        $this->assertSame(0, (int) $project->fields['entities_id']);
        $this->assertSame(1, (int) $project->fields['is_recursive']);

        $taskType = new ProjectTaskType();
        $taskType->getFromDBByCrit(['name' => 'Analyse & Cadrage']);

        $task = new ProjectTask();
        $this->assertTrue($task->getFromDBByCrit(['projects_id' => $project->getID(), 'name' => 'Analyse & Cadrage']));
        $this->assertSame((int) $taskType->getID(), (int) $task->fields['projecttasktypes_id']);
    }

    /**
     * Regression guard: a template project/task created while `ProjectTaxonomyBuilder` hadn't run
     * yet used to stay stuck with `projecttypes_id`/`projecttasktypes_id = 0` forever —
     * `getOrCreateTemplate()`/`getOrCreateTask()` found the existing row by name and returned
     * early without ever revisiting its type FK.
     */
    public function testASecondRunHealsAPreviouslyUnresolvedProjectAndTaskType(): void
    {
        $builder = new ProjectTemplateBuilder();
        $builder->build($this->buildConfig(true));

        $project = new Project();
        $project->getFromDBByCrit(['is_template' => 1, 'template_name' => self::TEMPLATE_NAME]);
        $project->update(['id' => $project->getID(), 'projecttypes_id' => 0]);

        $task = new ProjectTask();
        $task->getFromDBByCrit(['projects_id' => $project->getID(), 'name' => 'Analyse & Cadrage']);
        $task->update(['id' => $task->getID(), 'projecttasktypes_id' => 0]);

        $builder->build($this->buildConfig(true));

        $projectType = new ProjectType();
        $projectType->getFromDBByCrit(['name' => self::PROJECT_TYPE_NAME]);
        $taskType = new ProjectTaskType();
        $taskType->getFromDBByCrit(['name' => 'Analyse & Cadrage']);

        $project = new Project();
        $project->getFromDBByCrit(['is_template' => 1, 'template_name' => self::TEMPLATE_NAME]);
        $this->assertSame((int) $projectType->getID(), (int) $project->fields['projecttypes_id']);

        $task = new ProjectTask();
        $task->getFromDBByCrit(['projects_id' => $project->getID(), 'name' => 'Analyse & Cadrage']);
        $this->assertSame((int) $taskType->getID(), (int) $task->fields['projecttasktypes_id']);
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateProjectsOrTasks(): void
    {
        $builder = new ProjectTemplateBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(2, $second);

        global $DB;
        $projectCount = $DB->request(['FROM' => Project::getTable(), 'WHERE' => ['is_template' => 1, 'template_name' => self::TEMPLATE_NAME]])->count();
        $this->assertSame(1, $projectCount, 'Exactly one template project must exist — no duplicate.');

        $project = new Project();
        $project->getFromDBByCrit(['is_template' => 1, 'template_name' => self::TEMPLATE_NAME]);
        $taskCount = $DB->request(['FROM' => ProjectTask::getTable(), 'WHERE' => ['projects_id' => $project->getID(), 'name' => 'Analyse & Cadrage']])->count();
        $this->assertSame(1, $taskCount, 'Exactly one task must exist — no duplicate.');
    }
}
