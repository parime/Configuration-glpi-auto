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

use BusinessCriticity;
use DocumentCategory;
use DropdownTranslation;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\DocumentManagementBuilder;
use PHPUnit\Framework\TestCase;

final class DocumentManagementBuilderTest extends TestCase
{
    private const DOCUMENT_CATEGORY_NAMES = ['Public', 'Interne', 'Confidentiel', 'Diffusion restreinte'];

    private const BUSINESS_CRITICITY_NAMES = ['Critique', 'Élevée', 'Moyenne', 'Faible'];

    private function buildConfig(bool $enabled, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'document_management_enabled' => $enabled ? 1 : 0,
            'document_management_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new DocumentManagementBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildCreatesTheFourDocumentCategoriesAndFourBusinessCriticities(): void
    {
        $count = (new DocumentManagementBuilder())->build($this->buildConfig(true));

        $this->assertSame(8, $count);
        foreach (self::DOCUMENT_CATEGORY_NAMES as $name) {
            $this->assertTrue((new DocumentCategory())->getFromDBByCrit(['name' => $name, 'documentcategories_id' => 0]), "'$name' must exist.");
        }
        foreach (self::BUSINESS_CRITICITY_NAMES as $name) {
            $this->assertTrue((new BusinessCriticity())->getFromDBByCrit(['name' => $name, 'businesscriticities_id' => 0]), "'$name' must exist.");
        }
    }

    public function testBuildIsIdempotentAndDoesNotDuplicate(): void
    {
        $builder = new DocumentManagementBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(8, $second);

        global $DB;
        $this->assertSame(1, $DB->request(['FROM' => DocumentCategory::getTable(), 'WHERE' => ['name' => 'Public', 'documentcategories_id' => 0]])->count());
        $this->assertSame(1, $DB->request(['FROM' => BusinessCriticity::getTable(), 'WHERE' => ['name' => 'Critique', 'businesscriticities_id' => 0]])->count());
    }

    public function testTogglingIconsOnThenOffUpdatesTheTranslation(): void
    {
        $builder = new DocumentManagementBuilder();
        $builder->build($this->buildConfig(true, icons: true));

        $category = new DocumentCategory();
        $category->getFromDBByCrit(['name' => 'Confidentiel', 'documentcategories_id' => 0]);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => DocumentCategory::class,
            'items_id' => $category->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]));
        $this->assertStringStartsWith('🔒', $translation->fields['value']);

        $builder->build($this->buildConfig(true, icons: false));

        $translation = new DropdownTranslation();
        $translation->getFromDBByCrit([
            'itemtype' => DocumentCategory::class,
            'items_id' => $category->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]);
        $this->assertSame('Confidentiel', $translation->fields['value']);
    }
}
