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

use Entity;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\EntityInventoryRuleBuilder;
use PHPUnit\Framework\TestCase;
use Rule;
use RuleCriteria;
use RuleImportEntity;
use RuleImportEntityCollection;

/**
 * Issue #307 : rattacher les machines inventoriées à leur entité par plage IP ou tag d'agent.
 */
final class EntityInventoryRuleBuilderTest extends TestCase
{
    /**
     * Les plages IP des tests sont fixes : une règle laissée par un passage précédent capterait la
     * machine avant celle du test. Les règles « PHPUnit » sont purgées avant et après chaque test.
     */
    protected function setUp(): void
    {
        self::purgeTestRules();
    }

    protected function tearDown(): void
    {
        self::purgeTestRules();
    }

    private static function purgeTestRules(): void
    {
        global $DB;

        $rows = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => Rule::getTable(),
            'WHERE'  => ['sub_type' => RuleImportEntity::class, 'name' => ['LIKE', '%PHPUnit%']],
        ]);
        foreach ($rows as $row) {
            (new RuleImportEntity())->delete(['id' => (int) $row['id']], true);
        }
    }

    private function entity(string $name): int
    {
        return (int) (new Entity())->add(['name' => $name . ' ' . mt_rand(100000, 999999), 'entities_id' => 0]);
    }

    /**
     * Même entrée que l'inventaire (Glpi\Inventory\MainAsset\MainAsset::prepareAllRulesInput()).
     *
     * @param array<string, mixed> $input
     */
    private static function entityFor(array $input): ?int
    {
        $result = (new RuleImportEntityCollection())->processAllRules($input + ['_auto' => 1, 'itemtype' => 'Computer'], [], []);

        return isset($result['entities_id']) ? (int) $result['entities_id'] : null;
    }

    public function testOnlyValidIpv4RangesAreKept(): void
    {
        $this->assertSame(
            ['192.168.10.0/24', '10.1.0.0/16'],
            EntityInventoryRuleBuilder::parseRanges("192.168.10.0/24, 10.1.0.0/16 ; 300.1.1.0/24 192.168.1.0 10.0.0.0/40 192.168.10.0/24")
        );
    }

    public function testAMachineIsAssignedByIpRangeOrByTag(): void
    {
        $lyon = $this->entity('PHPUnit Site Lyon');
        $tag = 'PHPUNIT-LYON-' . mt_rand(1000, 9999);
        (new EntityInventoryRuleBuilder())->ensureRule($lyon, 'PHPUnit Site Lyon ' . $lyon, ['10.66.0.0/16'], $tag);

        $this->assertSame($lyon, self::entityFor(['ip' => ['10.66.4.25']]));
        $this->assertSame($lyon, self::entityFor(['tag' => $tag]));
        $this->assertNotSame($lyon, self::entityFor(['ip' => ['10.67.4.25']]), 'hors de la plage');
    }

    public function testRebuildingAddsMissingCriteriaWithoutDuplicates(): void
    {
        global $DB;
        $site = $this->entity('PHPUnit Site Nantes');
        $builder = new EntityInventoryRuleBuilder();
        $rulesId = $builder->ensureRule($site, 'PHPUnit Site Nantes ' . $site, ['10.70.0.0/16'], '');
        $builder->ensureRule($site, 'PHPUnit Site Nantes ' . $site, ['10.70.0.0/16', '10.71.0.0/16'], 'PHPUNIT-NANTES');

        $this->assertSame(1, $DB->request(['FROM' => Rule::getTable(), 'WHERE' => ['sub_type' => RuleImportEntity::class, 'name' => ['LIKE', '%Nantes ' . $site . '%']]])->count());
        $this->assertSame(3, countElementsInTable(RuleCriteria::getTable(), ['rules_id' => $rulesId]));
    }

    public function testRulesComeBeforeTheAdministratorOwnRules(): void
    {
        $catchAll = new Rule();
        $catchAllId = (int) $catchAll->add(['name' => 'PHPUnit règle admin ' . mt_rand(), 'sub_type' => RuleImportEntity::class, 'match' => Rule::OR_MATCHING, 'is_active' => 1, 'entities_id' => 0]);
        $site = $this->entity('PHPUnit Site Lille');

        $rulesId = (new EntityInventoryRuleBuilder())->ensureRule($site, 'PHPUnit Site Lille ' . $site, ['10.80.0.0/16'], '');

        $mine = new Rule();
        $mine->getFromDB($rulesId);
        $catchAll->getFromDB($catchAllId);
        $this->assertLessThan((int) $catchAll->fields['ranking'], (int) $mine->fields['ranking']);
    }

    public function testBuildFollowsTheWizardEntityTree(): void
    {
        $name = 'PHPUnit Arbre ' . mt_rand(100000, 999999);
        $entityId = (int) (new Entity())->add(['name' => $name, 'entities_id' => 0]);
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'entity_inventory_rules_enabled' => 1,
            'entity_tree' => json_encode([['name' => $name, 'children' => []]]),
        ]);

        $count = (new EntityInventoryRuleBuilder())->build($config, ['0' => ['ranges' => ['10.90.0.0/16'], 'tag' => '']]);

        $this->assertSame(1, $count);
        $this->assertSame($entityId, self::entityFor(['ip' => ['10.90.1.1']]));
    }
}
