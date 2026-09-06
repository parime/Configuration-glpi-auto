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
use GlpiPlugin\Configurationglpiauto\UserCategoryBuilder;
use PHPUnit\Framework\TestCase;
use UserCategory;

final class UserCategoryBuilderTest extends TestCase
{
    private const NAMES = ['Employé', 'Prestataire externe', 'Stagiaire', 'Alternant', 'Intérimaire', 'Consultant'];

    private function buildConfig(bool $enabled, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'user_categories_enabled' => $enabled ? 1 : 0,
            'user_category_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new UserCategoryBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresExactlyTheSixCategoriesExist(): void
    {
        $count = (new UserCategoryBuilder())->build($this->buildConfig(true));

        $this->assertSame(6, $count);
        foreach (self::NAMES as $name) {
            $this->assertTrue((new UserCategory())->getFromDBByCrit(['name' => $name]), "'$name' must exist.");
        }
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateCategories(): void
    {
        $builder = new UserCategoryBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(6, $second);

        global $DB;
        foreach (self::NAMES as $name) {
            $count = $DB->request(['FROM' => UserCategory::getTable(), 'WHERE' => ['name' => $name]])->count();
            $this->assertSame(1, $count, "Exactly one '$name' category must exist — no duplicate.");
        }
    }

    public function testTogglingIconsOnThenOffUpdatesTheTranslation(): void
    {
        $builder = new UserCategoryBuilder();
        $builder->build($this->buildConfig(true, icons: true));

        $category = new UserCategory();
        $category->getFromDBByCrit(['name' => 'Employé']);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => UserCategory::class,
            'items_id' => $category->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]));
        $this->assertStringStartsWith('👤', $translation->fields['value']);

        $builder->build($this->buildConfig(true, icons: false));

        $translation = new DropdownTranslation();
        $translation->getFromDBByCrit([
            'itemtype' => UserCategory::class,
            'items_id' => $category->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]);
        $this->assertSame('Employé', $translation->fields['value']);
    }
}
