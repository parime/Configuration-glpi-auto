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

use FieldUnicity;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\FieldUnicityBuilder;
use PHPUnit\Framework\TestCase;

final class FieldUnicityBuilderTest extends TestCase
{
    private const ITEMTYPES = [
        'Computer', 'Monitor', 'NetworkEquipment', 'Peripheral', 'Phone', 'Printer',
        'Rack', 'Enclosure', 'PDU', 'SoftwareLicense', 'Certificate', 'Item_DeviceSimcard',
    ];

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'field_unicity_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new FieldUnicityBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresExactlyTheTwelveRulesExistAndRefuseDuplicateSerials(): void
    {
        $count = (new FieldUnicityBuilder())->build($this->buildConfig(true));

        $this->assertSame(12, $count);

        foreach (self::ITEMTYPES as $itemtype) {
            $rule = new FieldUnicity();
            $this->assertTrue($rule->getFromDBByCrit(['itemtype' => $itemtype, 'entities_id' => 0]), "$itemtype must have a rule.");
            $this->assertSame('serial', $rule->fields['fields']);
            $this->assertSame(1, (int) $rule->fields['is_active']);
            $this->assertSame(1, (int) $rule->fields['action_refuse'], 'Must block the duplicate outright, not just notify.');
            $this->assertSame(0, (int) $rule->fields['action_notify']);
        }
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateRules(): void
    {
        $builder = new FieldUnicityBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(12, $second);

        global $DB;
        foreach (self::ITEMTYPES as $itemtype) {
            $count = $DB->request(['FROM' => FieldUnicity::getTable(), 'WHERE' => ['itemtype' => $itemtype, 'entities_id' => 0]])->count();
            $this->assertSame(1, $count, "Exactly one rule for $itemtype must exist — no duplicate.");
        }
    }
}
