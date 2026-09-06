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

use DropdownTranslation;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ProjectTaskTemplateBuilder;
use GlpiPlugin\Configurationglpiauto\ProjectTaxonomyBuilder;
use PHPUnit\Framework\TestCase;
use ProjectTaskTemplate;
use ProjectTaskType;

final class ProjectTaskTemplateBuilderTest extends TestCase
{
    private const NAME = 'Cadrage initial';

    private const TASK_TYPE_NAME = 'Analyse & Cadrage';

    /**
     * `ProjectTaskTemplateBuilder::findTaskTypeId()` resolves each template's task type by name
     * against whatever `ProjectTaxonomyBuilder` already created — it never creates the type itself.
     */
    protected function setUp(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['project_taxonomy_enabled' => 1]);
        (new ProjectTaxonomyBuilder())->build($config);
    }

    private function buildConfig(bool $enabled, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'project_task_templates_enabled' => $enabled ? 1 : 0,
            'project_task_template_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new ProjectTaskTemplateBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresAllThreeTemplatesExistLinkedToTheRightTaskType(): void
    {
        $count = (new ProjectTaskTemplateBuilder())->build($this->buildConfig(true));

        $this->assertSame(3, $count);

        $taskType = new ProjectTaskType();
        $taskType->getFromDBByCrit(['name' => self::TASK_TYPE_NAME]);

        $template = new ProjectTaskTemplate();
        $this->assertTrue($template->getFromDBByCrit(['name' => self::NAME]));
        $this->assertSame((int) $taskType->getID(), (int) $template->fields['projecttasktypes_id']);
        $this->assertStringContainsString('objectifs', $template->fields['description']);
        $this->assertSame(0, (int) $template->fields['entities_id']);
        $this->assertSame(1, (int) $template->fields['is_recursive']);
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateTemplates(): void
    {
        $builder = new ProjectTaskTemplateBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(3, $second);

        global $DB;
        $count = $DB->request(['FROM' => ProjectTaskTemplate::getTable(), 'WHERE' => ['name' => self::NAME]])->count();
        $this->assertSame(1, $count, 'Exactly one row must exist — no duplicate.');
    }

    /**
     * Regression guard: a template created while `ProjectTaxonomyBuilder` hadn't run yet (wizard
     * steps are independent and can run in any order) used to stay stuck with
     * `projecttasktypes_id = 0` forever — `getOrCreateTemplate()` found the existing row by name
     * and returned early without ever revisiting its type FK.
     */
    public function testASecondRunHealsAPreviouslyUnresolvedTaskType(): void
    {
        $builder = new ProjectTaskTemplateBuilder();
        $builder->build($this->buildConfig(true));

        $template = new ProjectTaskTemplate();
        $template->getFromDBByCrit(['name' => self::NAME]);
        $template->update(['id' => $template->getID(), 'projecttasktypes_id' => 0]);

        $builder->build($this->buildConfig(true));

        $taskType = new ProjectTaskType();
        $taskType->getFromDBByCrit(['name' => self::TASK_TYPE_NAME]);

        $template = new ProjectTaskTemplate();
        $template->getFromDBByCrit(['name' => self::NAME]);
        $this->assertSame((int) $taskType->getID(), (int) $template->fields['projecttasktypes_id']);
    }

    public function testTogglingIconsOnThenOffUpdatesTheTranslation(): void
    {
        $builder = new ProjectTaskTemplateBuilder();
        $builder->build($this->buildConfig(true, icons: true));

        $template = new ProjectTaskTemplate();
        $template->getFromDBByCrit(['name' => self::NAME]);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => ProjectTaskTemplate::class,
            'items_id' => $template->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]));
        $this->assertStringStartsWith('🎯', $translation->fields['value']);

        $builder->build($this->buildConfig(true, icons: false));

        $translation = new DropdownTranslation();
        $translation->getFromDBByCrit([
            'itemtype' => ProjectTaskTemplate::class,
            'items_id' => $template->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]);
        $this->assertSame(self::NAME, $translation->fields['value']);
    }
}
