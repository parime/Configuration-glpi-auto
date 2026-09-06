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
use GlpiPlugin\Configurationglpiauto\SupportTierBuilder;
use Group;
use PHPUnit\Framework\TestCase;

final class SupportTierBuilderTest extends TestCase
{
    private const NAMES = ['Support N1', 'Support N2', 'Support N3'];

    private function buildConfig(bool $enabled, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'escalation_enabled' => $enabled ? 1 : 0,
            'support_tier_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsEmptyArrayWhenEscalationIsDisabled(): void
    {
        $result = (new SupportTierBuilder())->build($this->buildConfig(false));

        $this->assertSame([], $result);
    }

    public function testBuildEnsuresTheThreeTierGroupsExistAndAreAssignable(): void
    {
        $result = (new SupportTierBuilder())->build($this->buildConfig(true));

        $this->assertSame(['n1', 'n2', 'n3'], array_keys($result));

        foreach (self::NAMES as $i => $name) {
            $key = ['n1', 'n2', 'n3'][$i];
            $group = new Group();
            $this->assertTrue($group->getFromDB($result[$key]));
            $this->assertSame($name, $group->fields['name']);
            $this->assertSame(0, (int) $group->fields['entities_id']);
            $this->assertSame(1, (int) $group->fields['is_recursive']);
            $this->assertSame(
                1,
                (int) $group->fields['is_assign'],
                'is_assign must be set, or RuleCommonITILObject silently refuses to offer this group as an assignment target.'
            );
        }
    }

    public function testBuildIsIdempotentAndReusesTheSameGroups(): void
    {
        $builder = new SupportTierBuilder();

        $first = $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame($first, $second, 'Re-running must resolve to the exact same group ids, not create new ones.');

        global $DB;
        foreach (self::NAMES as $name) {
            $count = $DB->request(['FROM' => Group::getTable(), 'WHERE' => ['name' => $name, 'entities_id' => 0]])->count();
            $this->assertSame(1, $count, "Exactly one '$name' group must exist — no duplicate.");
        }
    }

    /**
     * Same `Translations::applyIcon()` regression guard as `CategoryBuilderTest` — toggling the
     * icon on then off must update the existing translation, not leave the old value stuck.
     */
    public function testTogglingIconsOnThenOffUpdatesTheTranslation(): void
    {
        $builder = new SupportTierBuilder();
        $result = $builder->build($this->buildConfig(true, icons: true));

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => Group::class,
            'items_id' => $result['n1'],
            'language' => 'fr_FR',
            'field' => 'name',
        ]));
        $this->assertStringStartsWith('🟢', $translation->fields['value']);

        $builder->build($this->buildConfig(true, icons: false));

        $translation = new DropdownTranslation();
        $translation->getFromDBByCrit([
            'itemtype' => Group::class,
            'items_id' => $result['n1'],
            'language' => 'fr_FR',
            'field' => 'name',
        ]);
        $this->assertSame('Support N1', $translation->fields['value']);
    }
}
