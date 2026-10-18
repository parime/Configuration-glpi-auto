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
use Rule;
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

    /**
     * Supprimer une catégorie fait désactiver par GLPI les règles qui la visent : relancer
     * l'assistant recrée la catégorie, réaligne les règles sur elle et les réactive.
     */
    public function testRerunningRepairsRulesAfterACategoryWasDeleted(): void
    {
        global $DB;

        $this->build();
        // GLPI désactive aussi, par ricochet, d'autres règles (dont sa règle native « Import
        // category from inventory tool ») : leur état est restauré en fin de test.
        $states = [];
        foreach ($DB->request(['SELECT' => ['id', 'is_active'], 'FROM' => Rule::getTable(), 'WHERE' => ['sub_type' => ['RuleSoftwareCategory', 'RuleDictionnarySoftware']]]) as $row) {
            $states[(int) $row['id']] = (int) $row['is_active'];
        }
        try {
            $this->assertCategoryRulesAreRepaired();
        } finally {
            foreach ($states as $id => $active) {
                $DB->update(Rule::getTable(), ['is_active' => $active], ['id' => $id]);
            }
        }
    }

    private function assertCategoryRulesAreRepaired(): void
    {
        $category = new SoftwareCategory();
        $category->getFromDB(self::categoryId('Sécurité'));
        $category->delete(['id' => $category->getID()], true);
        $rule = new Rule();
        $this->assertTrue($rule->getFromDBByCrit(['name' => 'Catégorie de logiciel « Sécurité »']));
        $this->assertSame(0, (int) $rule->fields['is_active'], 'GLPI désactive la règle orpheline');

        $this->build();

        $rule->getFromDB($rule->getID());
        $this->assertSame(1, (int) $rule->fields['is_active']);
        $result = (new RuleSoftwareCategoryCollection())->processAllRules([], [], ['name' => 'Kaspersky Endpoint Security 12.3', 'manufacturers_id' => 0]);
        $this->assertSame(self::categoryId('Sécurité'), (int) ($result['softwarecategories_id'] ?? 0));
        $inventory = (new RuleDictionnarySoftwareCollection())->processAllRules([
            'name' => 'Kaspersky Endpoint Security 12.3 for Windows', 'manufacturer' => 0, 'old_version' => null, 'entities_id' => 0, '_system_category' => null,
        ]);
        $this->assertSame('Kaspersky Endpoint Security', $inventory['name'] ?? null);
        $this->assertSame(self::categoryId('Sécurité'), (int) ($inventory['softwarecategories_id'] ?? 0));
    }

    /**
     * La collection s’arrête à la première règle qui correspond : une règle qui accepte tout
     * logiciel (comme « Import category from inventory tool » de GLPI) ne doit pas passer devant.
     */
    public function testCategoryRulesComeBeforeACatchAllRule(): void
    {
        $catchAll = new Rule();
        $catchAllId = (int) $catchAll->add(['name' => 'PHPUnit attrape-tout ' . mt_rand(), 'sub_type' => 'RuleSoftwareCategory', 'match' => Rule::AND_MATCHING, 'is_active' => 1, 'ranking' => 0]);
        (new \RuleCriteria())->add(['rules_id' => $catchAllId, 'criteria' => 'name', 'condition' => Rule::REGEX_MATCH, 'pattern' => '/.*/']);
        (new \RuleAction())->add(['rules_id' => $catchAllId, 'action_type' => 'assign', 'field' => 'softwarecategories_id', 'value' => 0]);
        try {
            // Placée en tête, devant les règles de l'assistant déjà créées.
            $this->build();
            global $DB;
            $first = $DB->request(['SELECT' => ['id'], 'FROM' => Rule::getTable(), 'WHERE' => ['sub_type' => 'RuleSoftwareCategory'], 'ORDER' => 'ranking ASC', 'LIMIT' => 1])->current();
            (new RuleSoftwareCategoryCollection())->moveRule($catchAllId, (int) $first['id'], \RuleCollection::MOVE_BEFORE);
            $top = $DB->request(['SELECT' => ['id'], 'FROM' => Rule::getTable(), 'WHERE' => ['sub_type' => 'RuleSoftwareCategory'], 'ORDER' => 'ranking ASC', 'LIMIT' => 1])->current();
            $this->assertSame($catchAllId, (int) $top['id'], 'précondition : attrape-tout en tête');

            $this->build();
            $result = (new RuleSoftwareCategoryCollection())->processAllRules([], [], ['name' => 'Kaspersky Endpoint Security 12.3', 'manufacturers_id' => 0]);
            $this->assertSame(self::categoryId('Sécurité'), (int) ($result['softwarecategories_id'] ?? 0));
        } finally {
            (new \RuleSoftwareCategory())->delete(['id' => $catchAllId], true);
        }
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
