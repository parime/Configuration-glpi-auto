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
use GlpiPlugin\Configurationglpiauto\LineOperatorBuilder;
use LineOperator;
use PHPUnit\Framework\TestCase;

/**
 * Real regression guard for the bug documented directly in the class's own docblock : omitting
 * `mcc`/`mnc` explicitly used to silently create only the first operator and drop the other three
 * on `glpi_lineoperators`' real `UNIQUE(mcc, mnc)` index, with no visible error anywhere. This test
 * asserts the exact mcc/mnc pair each operator actually got, not just that 4 rows exist.
 */
final class LineOperatorBuilderTest extends TestCase
{
    private const EXPECTED = [
        'Orange' => 1,
        'SFR' => 10,
        'Bouygues Telecom' => 20,
        'Free' => 15,
    ];

    private const MCC_FRANCE = 208;

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'line_operators_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new LineOperatorBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresAllFourOperatorsExistWithDistinctMncValues(): void
    {
        $count = (new LineOperatorBuilder())->build($this->buildConfig(true));

        $this->assertSame(4, $count);

        foreach (self::EXPECTED as $name => $mnc) {
            $operator = new LineOperator();
            $this->assertTrue($operator->getFromDBByCrit(['name' => $name, 'entities_id' => 0]), "$name must exist.");
            $this->assertSame(self::MCC_FRANCE, (int) $operator->fields['mcc']);
            $this->assertSame($mnc, (int) $operator->fields['mnc'], "$name must keep its own distinct MNC, not collide on the unique(mcc,mnc) index.");
        }
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateOperators(): void
    {
        $builder = new LineOperatorBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(4, $second);

        global $DB;
        foreach (array_keys(self::EXPECTED) as $name) {
            $count = $DB->request(['FROM' => LineOperator::getTable(), 'WHERE' => ['name' => $name, 'entities_id' => 0]])->count();
            $this->assertSame(1, $count, "Exactly one '$name' operator must exist — no duplicate.");
        }
    }
}
