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
use GlpiPlugin\Configurationglpiauto\StateBuilder;
use PHPUnit\Framework\TestCase;
use State;

/**
 * `Config::getStateNames()` (not tested directly here, but exercised through it) individually
 * selects which of the 14 states get built — this suite deliberately selects a single, otherwise
 * untouched state to isolate that filtering behaviour, rather than relying on the defaults (which
 * select all 14 and are already exercised as a setup dependency across many other test files).
 */
final class StateBuilderTest extends TestCase
{
    private const STATE_NAME = 'Externe';

    private const VISIBLE_FOR = ['Computer', 'Phone', 'SoftwareLicense', 'Line', 'Contract', 'Unmanaged', 'Monitor', 'Peripheral', 'Printer'];

    private function buildConfig(bool $enabled, array $names, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'state_enabled' => $enabled ? 1 : 0,
            'state_names' => json_encode($names),
            'state_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsEmptyArrayWhenDisabled(): void
    {
        $names = (new StateBuilder())->build($this->buildConfig(false, [self::STATE_NAME]));

        $this->assertSame([], $names);
    }

    public function testBuildOnlyCreatesTheSelectedState(): void
    {
        $names = (new StateBuilder())->build($this->buildConfig(true, [self::STATE_NAME]));

        $this->assertSame([self::STATE_NAME], $names);

        $state = new State();
        $this->assertTrue($state->getFromDBByCrit(['name' => self::STATE_NAME]));
        $this->assertSame(0, (int) $state->fields['entities_id']);
        $this->assertSame(1, (int) $state->fields['is_recursive']);
    }

    public function testBuildSetsVisibilityForExactlyTheExpectedItemtypes(): void
    {
        (new StateBuilder())->build($this->buildConfig(true, [self::STATE_NAME]));

        $state = new State();
        $state->getFromDBByCrit(['name' => self::STATE_NAME]);

        global $DB;
        foreach (self::VISIBLE_FOR as $itemtype) {
            $count = $DB->request([
                'FROM' => 'glpi_dropdownvisibilities',
                'WHERE' => ['itemtype' => State::class, 'items_id' => $state->getID(), 'visible_itemtype' => $itemtype, 'is_visible' => 1],
            ])->count();
            $this->assertSame(1, $count, "'$itemtype' must be marked visible for this state.");
        }
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateStateOrVisibilityRows(): void
    {
        $builder = new StateBuilder();
        $builder->build($this->buildConfig(true, [self::STATE_NAME]));
        $second = $builder->build($this->buildConfig(true, [self::STATE_NAME]));

        $this->assertSame([self::STATE_NAME], $second);

        global $DB;
        $this->assertSame(1, $DB->request(['FROM' => State::getTable(), 'WHERE' => ['name' => self::STATE_NAME]])->count());

        // Scoped to exactly the itemtypes StateBuilder itself declares (self::VISIBLE_FOR) rather
        // than every row for this state : GLPI core independently maintains its own
        // DropdownVisibility rows for this state against other, unrelated itemtypes (e.g. this
        // plugin's own custom asset types), which is no more this test's concern than it is
        // StateBuilder's own responsibility to deduplicate.
        $state = new State();
        $state->getFromDBByCrit(['name' => self::STATE_NAME]);
        foreach (self::VISIBLE_FOR as $itemtype) {
            $count = $DB->request([
                'FROM' => 'glpi_dropdownvisibilities',
                'WHERE' => ['itemtype' => State::class, 'items_id' => $state->getID(), 'visible_itemtype' => $itemtype],
            ])->count();
            $this->assertSame(1, $count, "A second run must not duplicate the visibility row for '$itemtype'.");
        }
    }

    public function testTogglingIconsOnThenOffUpdatesTheTranslation(): void
    {
        $builder = new StateBuilder();
        $builder->build($this->buildConfig(true, [self::STATE_NAME], icons: true));

        $state = new State();
        $state->getFromDBByCrit(['name' => self::STATE_NAME]);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => State::class,
            'items_id' => $state->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]));
        $this->assertStringStartsWith('🔗', $translation->fields['value']);

        $builder->build($this->buildConfig(true, [self::STATE_NAME], icons: false));

        $translation = new DropdownTranslation();
        $translation->getFromDBByCrit([
            'itemtype' => State::class,
            'items_id' => $state->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]);
        $this->assertSame(self::STATE_NAME, $translation->fields['value']);
    }
}
