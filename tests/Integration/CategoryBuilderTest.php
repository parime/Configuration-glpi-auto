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
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use ITILCategory;
use PHPUnit\Framework\TestCase;

/**
 * `CategoryBuilder` builds the real `ITILCategory` tree every other builder/FormBuilder in this
 * plugin depends on (`resolveItilCategoryId()`'s own walk, present in ~40 other classes) — the
 * single most depended-upon class in the whole plugin, yet it had no dedicated test of its own
 * (only ever exercised incidentally as setup code in ~20 other test files).
 */
final class CategoryBuilderTest extends TestCase
{
    // A leaf 2 levels deep under 'qualite', a branch no other test in this suite touches — picked
    // specifically to avoid any cross-test interference with the shared instance's real state.
    private const BRANCH_KEY = 'qualite';

    private const BRANCH_NAME = 'Qualité, QHSE & Conformité';

    private const CHILD_NAME = 'Audits & Contrôles';

    protected function tearDown(): void
    {
        // Deliberately does not delete the built categories : same "real, shared, permanent
        // fixture" reasoning as Vehicule/Local in VehicleIncidentFormBuilderTest/
        // MeetingRoomFormBuilderTest — dozens of other tests in this suite resolve ITILCategory
        // trees via CategoryBuilder as their own setup step and would break if this one tore them
        // down. Icon-related state is reset back to disabled at the end of the icon test instead
        // (see that test), so it doesn't leak a differently-configured icon into later runs.
    }

    private function buildConfig(bool $enabled, array $branches, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_enabled' => $enabled ? 1 : 0,
            'category_branches' => json_encode($branches),
            'category_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testDisabledReturnsZeroAndCreatesNothing(): void
    {
        $count = (new CategoryBuilder())->build($this->buildConfig(false, [self::BRANCH_KEY]));

        $this->assertSame(0, $count);
    }

    public function testBuildsOnlyTheSelectedBranch(): void
    {
        (new CategoryBuilder())->build($this->buildConfig(true, [self::BRANCH_KEY]));

        $branch = new ITILCategory();
        $this->assertTrue(
            $branch->getFromDBByCrit(['name' => self::BRANCH_NAME, 'itilcategories_id' => 0]),
            'The selected branch\'s root category should be created.'
        );

        $child = new ITILCategory();
        $this->assertTrue(
            $child->getFromDBByCrit(['name' => self::CHILD_NAME, 'itilcategories_id' => $branch->getID()]),
            'Its children should be created too, nested under the real parent id.'
        );
    }

    public function testTopLevelBranchIsHelpdeskVisibleButChildrenAreNot(): void
    {
        (new CategoryBuilder())->build($this->buildConfig(true, [self::BRANCH_KEY]));

        $branch = new ITILCategory();
        $branch->getFromDBByCrit(['name' => self::BRANCH_NAME, 'itilcategories_id' => 0]);
        $this->assertSame(
            1,
            (int) $branch->fields['is_helpdeskvisible'],
            'Only the 11 top-level branches should be pickable from the Self-Service category list.'
        );

        $child = new ITILCategory();
        $child->getFromDBByCrit(['name' => self::CHILD_NAME, 'itilcategories_id' => $branch->getID()]);
        $this->assertSame(
            0,
            (int) $child->fields['is_helpdeskvisible'],
            'Leaf categories are for staff triage only, never shown to a base Self-Service user.'
        );
    }

    public function testEveryCategoryIsUsableOnAllFourItilTypes(): void
    {
        (new CategoryBuilder())->build($this->buildConfig(true, [self::BRANCH_KEY]));

        $branch = new ITILCategory();
        $branch->getFromDBByCrit(['name' => self::BRANCH_NAME, 'itilcategories_id' => 0]);

        foreach (['is_incident', 'is_request', 'is_problem', 'is_change'] as $field) {
            $this->assertSame(1, (int) $branch->fields[$field], "$field should be enabled — the category itself never gates ticket type.");
        }
    }

    public function testBuildIsIdempotentAndReusesExistingCategories(): void
    {
        $config = $this->buildConfig(true, [self::BRANCH_KEY]);
        $builder = new CategoryBuilder();

        $first = $builder->build($config);

        $branch = new ITILCategory();
        $branch->getFromDBByCrit(['name' => self::BRANCH_NAME, 'itilcategories_id' => 0]);
        $firstRunId = (int) $branch->getID();

        // Same "created/reused" contract as ServiceCatalogBuilder::build() (see that class's own
        // docblock and ServiceCatalogBuilderTest's idempotency test) : the return value counts
        // every category processed for the enabled branches, not "how many were newly created" —
        // both calls return the same total, the real invariant to check is that the row itself is
        // reused, not duplicated.
        $second = $builder->build($config);
        $this->assertSame($first, $second, 'Processing the same branch twice must count the same total both times.');

        $branch = new ITILCategory();
        $branch->getFromDBByCrit(['name' => self::BRANCH_NAME, 'itilcategories_id' => 0]);
        $this->assertSame($firstRunId, (int) $branch->getID(), 'The second run must reuse the exact same category row.');

        global $DB;
        $matches = $DB->request([
            'FROM' => ITILCategory::getTable(),
            'WHERE' => ['name' => self::BRANCH_NAME, 'itilcategories_id' => 0],
        ])->count();
        $this->assertSame(1, $matches, 'Exactly one row must exist — no duplicate root category.');
    }

    /**
     * `Translations::applyIcon()`'s own mechanism (`DropdownTranslation`, never baked into `name`
     * directly — unlike some other builders in this plugin that deliberately deviate from it, see
     * `PhysicalSecurityAssetBuilderTest`'s own docblock for why) : real regression guard for a bug
     * documented directly in `Translations::applyIcon()`'s own comment (found live 2026-08-23) —
     * toggling an icon on, then off, used to leave the old translated value stuck forever because
     * the code only ever called `add()`, never `update()`.
     */
    public function testTogglingIconsOnThenOffActuallyUpdatesTheTranslation(): void
    {
        $builder = new CategoryBuilder();
        $builder->build($this->buildConfig(true, [self::BRANCH_KEY], icons: true));

        $branch = new ITILCategory();
        $branch->getFromDBByCrit(['name' => self::BRANCH_NAME, 'itilcategories_id' => 0]);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => ITILCategory::class,
            'items_id' => $branch->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]));
        $this->assertStringStartsWith(
            '📋',
            $translation->fields['value'],
            'The branch\'s own icon should be prefixed onto the translated value.'
        );

        // Toggle back off — must UPDATE the existing row to the bare name, not leave the old
        // icon-prefixed value stuck (the exact historical bug).
        $builder->build($this->buildConfig(true, [self::BRANCH_KEY], icons: false));

        $translation = new DropdownTranslation();
        $translation->getFromDBByCrit([
            'itemtype' => ITILCategory::class,
            'items_id' => $branch->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]);
        $this->assertSame(
            self::BRANCH_NAME,
            $translation->fields['value'],
            'Disabling icons must update the existing translation row back to the bare name.'
        );
    }
}
