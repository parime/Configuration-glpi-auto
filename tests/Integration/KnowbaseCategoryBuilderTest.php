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
use GlpiPlugin\Configurationglpiauto\KnowbaseCategoryBuilder;
use KnowbaseItemCategory;
use PHPUnit\Framework\TestCase;

/**
 * `KnowbaseCategoryBuilder` deliberately reuses `CategoryBuilder`'s own 11 branch names/icons
 * instead of inventing a second taxonomy (see its own docblock) — this test picks a single branch
 * ('qualite') to isolate the "only selected branches get a matching KB category" behaviour, rather
 * than one already exercised by `CategoryBuilderTest`.
 */
final class KnowbaseCategoryBuilderTest extends TestCase
{
    private const BRANCH_NAME = 'Qualité, QHSE & Conformité';

    private function buildConfig(bool $enabled, array $branches, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'kb_categories_enabled' => $enabled ? 1 : 0,
            'category_branches' => json_encode($branches),
            'category_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new KnowbaseCategoryBuilder())->build($this->buildConfig(false, ['qualite']));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresOnlyTheSelectedBranchGetsAMatchingTopLevelCategory(): void
    {
        $count = (new KnowbaseCategoryBuilder())->build($this->buildConfig(true, ['qualite']));

        $this->assertSame(1, $count);

        $category = new KnowbaseItemCategory();
        $this->assertTrue($category->getFromDBByCrit(['name' => self::BRANCH_NAME, 'knowbaseitemcategories_id' => 0]));
    }

    public function testBuildIsIdempotentAndReusesTheSameCategory(): void
    {
        $builder = new KnowbaseCategoryBuilder();
        $builder->build($this->buildConfig(true, ['qualite']));

        $category = new KnowbaseItemCategory();
        $category->getFromDBByCrit(['name' => self::BRANCH_NAME, 'knowbaseitemcategories_id' => 0]);
        $idBefore = (int) $category->getID();

        $second = $builder->build($this->buildConfig(true, ['qualite']));
        $this->assertSame(1, $second);

        $category = new KnowbaseItemCategory();
        $category->getFromDBByCrit(['name' => self::BRANCH_NAME, 'knowbaseitemcategories_id' => 0]);
        $this->assertSame($idBefore, (int) $category->getID());

        global $DB;
        $matches = $DB->request([
            'FROM' => KnowbaseItemCategory::getTable(),
            'WHERE' => ['name' => self::BRANCH_NAME, 'knowbaseitemcategories_id' => 0],
        ])->count();
        $this->assertSame(1, $matches, 'Exactly one row must exist — no duplicate.');
    }

    public function testTogglingIconsOnThenOffUpdatesTheTranslation(): void
    {
        $builder = new KnowbaseCategoryBuilder();
        $builder->build($this->buildConfig(true, ['qualite'], icons: true));

        $category = new KnowbaseItemCategory();
        $category->getFromDBByCrit(['name' => self::BRANCH_NAME, 'knowbaseitemcategories_id' => 0]);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => KnowbaseItemCategory::class,
            'items_id' => $category->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]));
        $this->assertStringStartsWith('📋', $translation->fields['value']);

        $builder->build($this->buildConfig(true, ['qualite'], icons: false));

        $translation = new DropdownTranslation();
        $translation->getFromDBByCrit([
            'itemtype' => KnowbaseItemCategory::class,
            'items_id' => $category->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]);
        $this->assertSame(self::BRANCH_NAME, $translation->fields['value']);
    }
}
