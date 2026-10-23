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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration\Audit;

use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\DictionaryRulesCheck;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\DictionaryRules;
use GlpiPlugin\Configurationglpiauto\SoftwareCategoryBuilder;
use GlpiPlugin\Configurationglpiauto\SoftwareDictionaryBuilder;
use PHPUnit\Framework\TestCase;
use Rule;
use SoftwareCategory;

/**
 * Issue #323 : règles de l'assistant désactivées ou orphelines signalées par l'audit.
 */
final class DictionaryRulesCheckTest extends TestCase
{
    private function build(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['software_dictionary_enabled' => 1, 'software_categories_enabled' => 1]);
        $dictionary = new SoftwareDictionaryBuilder();
        $dictionary->build($config);
        (new SoftwareCategoryBuilder())->build($config, SoftwareDictionaryBuilder::getRegexByLabel(), $dictionary->getRuleIds());
    }

    /**
     * L'instance de test est partagée : l'état des règles est restauré, et l'assistant relancé
     * recrée la catégorie supprimée.
     */
    private function withRestoredRules(callable $test): void
    {
        global $DB;

        $this->build();
        $states = [];
        foreach ($DB->request(['SELECT' => ['id', 'is_active'], 'FROM' => Rule::getTable(), 'WHERE' => ['sub_type' => array_keys(DictionaryRules::PLUGIN_RULE_PREFIXES)]]) as $row) {
            $states[(int) $row['id']] = (int) $row['is_active'];
        }
        try {
            $test();
        } finally {
            $this->build();
            foreach ($states as $id => $active) {
                $DB->update(Rule::getTable(), ['is_active' => $active], ['id' => $id]);
            }
        }
    }

    public function testOnlyPluginRulesAreJudged(): void
    {
        $this->assertTrue(DictionaryRules::isPluginRule('RuleSoftwareCategory', SoftwareCategoryBuilder::RULE_PREFIX . 'Sécurité »'));
        $this->assertTrue(DictionaryRules::isPluginRule('RuleDictionnaryManufacturer', 'Fabricant — normalisation « Dell »'));
        $this->assertFalse(DictionaryRules::isPluginRule('RuleSoftwareCategory', 'Import category from inventory tool'));
        $this->assertFalse(DictionaryRules::isPluginRule('RuleDictionnarySoftware', 'Fabricant — normalisation « Dell »'), 'Préfixe d\'un autre type de règle.');
    }

    public function testADeletedCategoryMakesItsRulesOrphan(): void
    {
        $this->withRestoredRules(function (): void {
            $category = new SoftwareCategory();
            $this->assertTrue($category->getFromDBByCrit(['name' => 'Sécurité', 'softwarecategories_id' => 0]));
            $category->delete(['id' => $category->getID()], true);

            $broken = DictionaryRulesCheck::findBrokenRules();
            $orphans = array_column($broken['orphan'], 'name');
            $this->assertContains(SoftwareCategoryBuilder::RULE_PREFIX . 'Sécurité »', $orphans);
            $this->assertNotContains(SoftwareCategoryBuilder::RULE_PREFIX . 'Sécurité »', array_column($broken['inactive'], 'name'), 'Orpheline, pas seulement désactivée.');

            $findings = (new DictionaryRulesCheck())->analyze();
            $warning = array_values(array_filter($findings, static fn (AuditFinding $f): bool => $f->severity === AuditFinding::SEVERITY_WARNING));
            $this->assertCount(1, $warning);
            $this->assertNotEmpty($warning[0]->links);
            foreach ($warning[0]->links as $url) {
                $this->assertStringContainsString('id=', $url);
            }
        });
    }

    public function testADisabledDictionaryRuleIsReportedAsInformation(): void
    {
        $this->withRestoredRules(function (): void {
            global $DB;
            $first = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => Rule::getTable(),
                'WHERE'  => ['sub_type' => 'RuleDictionnarySoftware', 'name' => ['LIKE', 'Logiciel — normalisation « %']],
                'LIMIT'  => 1,
            ])->current();
            $rule = new Rule();
            $this->assertTrue($rule->getFromDB((int) ($first['id'] ?? 0)));
            $rule->update(['id' => $rule->getID(), 'is_active' => 0]);

            $this->assertContains((string) $rule->fields['name'], array_column(DictionaryRulesCheck::findBrokenRules()['inactive'], 'name'));
            $severities = array_map(static fn (AuditFinding $f): string => $f->severity, (new DictionaryRulesCheck())->analyze());
            $this->assertContains(AuditFinding::SEVERITY_INFO, $severities);
        });
    }

    public function testNoAutomaticFix(): void
    {
        $this->assertFalse((new DictionaryRulesCheck())->canFix());
    }
}
