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

use GlpiPlugin\Configurationglpiauto\Audit\Checks\UncategorizedSoftwareCheck;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\SoftwareCategoryBuilder;
use GlpiPlugin\Configurationglpiauto\SoftwareDictionaryBuilder;
use PHPUnit\Framework\TestCase;
use RuleDictionnarySoftwareCollection;
use RuleSoftwareCategoryCollection;
use Software;
use SoftwareCategory;

/**
 * Issue #304 : catégories des logiciels courants, à l'inventaire, à la création et pour l'existant.
 */
final class SoftwareCategoryBuilderTest extends TestCase
{
    private function build(bool $categories = true): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['software_dictionary_enabled' => 1, 'software_categories_enabled' => $categories ? 1 : 0]);
        $dictionary = new SoftwareDictionaryBuilder();
        $dictionary->build($config);
        (new SoftwareCategoryBuilder())->build($config, SoftwareDictionaryBuilder::getRegexByLabel(), $dictionary->getRuleIds());
    }

    private static function categoryId(string $name): int
    {
        $category = new SoftwareCategory();

        return $category->getFromDBByCrit(['name' => $name, 'softwarecategories_id' => 0]) ? (int) $category->getID() : 0;
    }

    public function testEveryDictionarySoftwareHasACategory(): void
    {
        $this->assertSame([], SoftwareCategoryBuilder::unmappedLabels(SoftwareDictionaryBuilder::getPreview()));
    }

    public function testNothingIsCreatedWhenDisabled(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['software_categories_enabled' => 0]);

        $this->assertSame(0, (new SoftwareCategoryBuilder())->build($config, SoftwareDictionaryBuilder::getRegexByLabel(), []));
    }

    public function testTheInventoryGetsTheCategoryFromTheDictionary(): void
    {
        $this->build();

        $result = (new RuleDictionnarySoftwareCollection())->processAllRules([
            'name' => 'Mozilla Firefox (x64 fr)', 'manufacturer' => 0, 'old_version' => null, 'entities_id' => 0, '_system_category' => null,
        ]);

        $this->assertSame('Mozilla Firefox', $result['name'] ?? null);
        $this->assertSame(self::categoryId('Navigateurs'), (int) ($result['softwarecategories_id'] ?? 0));
    }

    public function testCategoryRulesClassifyAManuallyCreatedSoftware(): void
    {
        $this->build();

        $result = (new RuleSoftwareCategoryCollection())->processAllRules([], [], ['name' => 'Kaspersky Endpoint Security 12.3', 'manufacturers_id' => 0]);

        $this->assertSame(self::categoryId('Sécurité'), (int) ($result['softwarecategories_id'] ?? 0));
    }

    public function testTheAuditClassifiesOnlySoftwareWithoutCategory(): void
    {
        global $DB;
        $this->build();
        $suffix = mt_rand(1000, 9999);
        $manual = self::categoryId('Utilitaires');
        $uncategorized = (int) (new Software())->add(['name' => '7-Zip ' . $suffix, 'entities_id' => 0]);
        $chosen = (int) (new Software())->add(['name' => 'VLC media player ' . $suffix, 'entities_id' => 0]);
        // Créés hors règles (import, version précédente) : catégorie forcée en base.
        $DB->update(Software::getTable(), ['softwarecategories_id' => 0], ['id' => $uncategorized]);
        $DB->update(Software::getTable(), ['softwarecategories_id' => $manual], ['id' => $chosen]);

        $this->assertContains($uncategorized, array_column(UncategorizedSoftwareCheck::findClassifiable(), 'id'));
        (new UncategorizedSoftwareCheck())->fix();

        $software = new Software();
        $software->getFromDB($uncategorized);
        $this->assertSame(self::categoryId('Utilitaires'), (int) $software->fields['softwarecategories_id']);
        $software->getFromDB($chosen);
        $this->assertSame($manual, (int) $software->fields['softwarecategories_id'], 'catégorie choisie à la main conservée');
    }
}
