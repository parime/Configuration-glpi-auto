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
use PHPUnit\Framework\TestCase;
use TaskCategory;

final class TaskCategoryBuilderTest extends TestCase
{
    private const NAMES_COUNT = 14;

    private const SAMPLE_NAME = 'Diagnostic & Analyse';

    private function buildConfig(bool $enabled, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'task_categories_enabled' => $enabled ? 1 : 0,
            'category_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new TaskCategoryBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresExactlyTheFourteenFlatCategoriesExist(): void
    {
        $count = (new TaskCategoryBuilder())->build($this->buildConfig(true));

        $this->assertSame(self::NAMES_COUNT, $count);

        $category = new TaskCategory();
        $this->assertTrue($category->getFromDBByCrit(['name' => self::SAMPLE_NAME, 'taskcategories_id' => 0]));
        $this->assertSame(0, (int) $category->fields['is_helpdeskvisible'], 'Task categories describe internal technician work, not requester-facing.');
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateCategories(): void
    {
        $builder = new TaskCategoryBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(self::NAMES_COUNT, $second);

        global $DB;
        $count = $DB->request(['FROM' => TaskCategory::getTable(), 'WHERE' => ['name' => self::SAMPLE_NAME, 'taskcategories_id' => 0]])->count();
        $this->assertSame(1, $count, 'Exactly one row must exist — no duplicate.');
    }

    public function testTogglingIconsOnThenOffUpdatesTheTranslation(): void
    {
        $builder = new TaskCategoryBuilder();
        $builder->build($this->buildConfig(true, icons: true));

        $category = new TaskCategory();
        $category->getFromDBByCrit(['name' => self::SAMPLE_NAME, 'taskcategories_id' => 0]);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => TaskCategory::class,
            'items_id' => $category->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]));
        $this->assertStringStartsWith('🔍', $translation->fields['value']);

        $builder->build($this->buildConfig(true, icons: false));

        $translation = new DropdownTranslation();
        $translation->getFromDBByCrit([
            'itemtype' => TaskCategory::class,
            'items_id' => $category->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]);
        $this->assertSame(self::SAMPLE_NAME, $translation->fields['value']);
    }
}
