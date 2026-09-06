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
use GlpiPlugin\Configurationglpiauto\TaskCategoryBuilder;
use GlpiPlugin\Configurationglpiauto\TaskTemplateBuilder;
use PHPUnit\Framework\TestCase;
use Planning;
use TaskCategory;
use TaskTemplate;

final class TaskTemplateBuilderTest extends TestCase
{
    private const NAME = 'Onboarding — Arrivée collaborateur';

    private const CATEGORY_NAME = 'Gestion des comptes utilisateurs';

    /**
     * `TaskTemplateBuilder::findCategoryId()` resolves the target category by name against
     * whatever `TaskCategoryBuilder` already created (see that class's own docblock) — it never
     * creates the category itself. On a fresh install (unlike this project's own long-lived shared
     * dev instance, where every category already exists from earlier runs) that category genuinely
     * doesn't exist yet, so this suite must build it itself rather than assume it.
     */
    protected function setUp(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['task_categories_enabled' => 1]);
        (new TaskCategoryBuilder())->build($config);
    }

    private function buildConfig(bool $enabled, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'task_templates_enabled' => $enabled ? 1 : 0,
            'task_template_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new TaskTemplateBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresAllThreeTemplatesExistLinkedToTheRightCategory(): void
    {
        $count = (new TaskTemplateBuilder())->build($this->buildConfig(true));

        $this->assertSame(3, $count);

        $category = new TaskCategory();
        $category->getFromDBByCrit(['name' => self::CATEGORY_NAME, 'taskcategories_id' => 0]);

        $template = new TaskTemplate();
        $this->assertTrue($template->getFromDBByCrit(['name' => self::NAME]));
        $this->assertSame((int) $category->getID(), (int) $template->fields['taskcategories_id']);
        $this->assertSame(Planning::TODO, (int) $template->fields['state']);
        $this->assertStringContainsString('CHECKLIST ONBOARDING', $template->fields['content']);
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateTemplates(): void
    {
        $builder = new TaskTemplateBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(3, $second);

        global $DB;
        $count = $DB->request(['FROM' => TaskTemplate::getTable(), 'WHERE' => ['name' => self::NAME]])->count();
        $this->assertSame(1, $count, 'Exactly one row must exist — no duplicate.');
    }

    public function testTogglingIconsOnThenOffUpdatesTheTranslation(): void
    {
        $builder = new TaskTemplateBuilder();
        $builder->build($this->buildConfig(true, icons: true));

        $template = new TaskTemplate();
        $template->getFromDBByCrit(['name' => self::NAME]);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => TaskTemplate::class,
            'items_id' => $template->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]));
        $this->assertStringStartsWith('🆕', $translation->fields['value']);

        $builder->build($this->buildConfig(true, icons: false));

        $translation = new DropdownTranslation();
        $translation->getFromDBByCrit([
            'itemtype' => TaskTemplate::class,
            'items_id' => $template->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]);
        $this->assertSame(self::NAME, $translation->fields['value']);
    }

    /**
     * `Translations::applyContent()` — a distinct mechanism from `applyIcon()` (field `content`,
     * not `name`), used here for the full multilingual checklist text rather than a name prefix.
     */
    public function testAppliesContentTranslationsForEveryLanguage(): void
    {
        (new TaskTemplateBuilder())->build($this->buildConfig(true));

        $template = new TaskTemplate();
        $template->getFromDBByCrit(['name' => self::NAME]);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => TaskTemplate::class,
            'items_id' => $template->getID(),
            'language' => 'en_GB',
            'field' => 'content',
        ]));
        $this->assertStringContainsString('ONBOARDING CHECKLIST', $translation->fields['value']);
    }
}
