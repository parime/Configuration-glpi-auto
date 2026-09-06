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

use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ManufacturerDictionaryBuilder;
use PHPUnit\Framework\TestCase;
use Rule;
use RuleAction;
use RuleCriteria;
use RuleDictionnaryManufacturer;

final class ManufacturerDictionaryBuilderTest extends TestCase
{
    private const RULE_NAME = 'Fabricant — normalisation « HP »';

    private const EXPECTED_VARIANTS = ['Hewlett-Packard', 'Hewlett Packard', 'HP Inc.', 'HP Inc', 'Hewlett-Packard Company'];

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'manufacturer_dictionary_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new ManufacturerDictionaryBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildCreatesExactlyTwentyNineRulesEachWithItsRealVariantsAndAssignAction(): void
    {
        $count = (new ManufacturerDictionaryBuilder())->build($this->buildConfig(true));

        $this->assertSame(29, $count);

        $rule = new Rule();
        $this->assertTrue($rule->getFromDBByCrit(['name' => self::RULE_NAME]));
        $this->assertSame(RuleDictionnaryManufacturer::class, $rule->fields['sub_type']);
        $this->assertSame(Rule::OR_MATCHING, $rule->fields['match']);
        $this->assertSame(1, (int) $rule->fields['is_active']);

        $patterns = [];
        foreach ((new RuleCriteria())->find(['rules_id' => $rule->getID(), 'criteria' => 'name']) as $row) {
            $this->assertSame(Rule::PATTERN_IS, (int) $row['condition'], 'Must be an exact match, not a substring match.');
            $patterns[] = $row['pattern'];
        }
        sort($patterns);
        $expected = self::EXPECTED_VARIANTS;
        sort($expected);
        $this->assertSame($expected, $patterns);

        $action = new RuleAction();
        $this->assertTrue($action->getFromDBByCrit(['rules_id' => $rule->getID(), 'field' => 'name']));
        $this->assertSame('assign', $action->fields['action_type']);
        $this->assertSame('HP', $action->fields['value']);
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateCriteria(): void
    {
        $builder = new ManufacturerDictionaryBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(29, $second);

        $rule = new Rule();
        $rule->getFromDBByCrit(['name' => self::RULE_NAME]);

        global $DB;
        $this->assertSame(1, $DB->request(['FROM' => Rule::getTable(), 'WHERE' => ['name' => self::RULE_NAME]])->count());
        $this->assertSame(
            count(self::EXPECTED_VARIANTS),
            $DB->request(['FROM' => RuleCriteria::getTable(), 'WHERE' => ['rules_id' => $rule->getID(), 'criteria' => 'name']])->count(),
            'A second run must not duplicate any criteria row.'
        );
    }
}
