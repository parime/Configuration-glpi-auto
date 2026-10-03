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
use GlpiPlugin\Configurationglpiauto\Compat\GlpiVersion;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\KnowbaseCategoryBuilder;
use KnowbaseItem;
use KnowbaseItem_KnowbaseItem;
use KnowbaseItemCategory;
use KnowbaseItemTranslation;
use PHPUnit\Framework\TestCase;

/**
 * `KnowbaseCategoryBuilder` deliberately reuses `CategoryBuilder`'s own 11 branch names/icons
 * instead of inventing a second taxonomy (see its own docblock) — this test picks a single branch
 * ('qualite') to isolate the "only selected branches get a matching KB category" behaviour, rather
 * than one already exercised by `CategoryBuilderTest`.
 *
 * Runs on both GLPI 11 (`KnowbaseItemCategory` + `DropdownTranslation`) and GLPI 12 (container
 * `KnowbaseItem` under the root article + `KnowbaseItemTranslation`): the helpers below hide which.
 */
final class KnowbaseCategoryBuilderTest extends TestCase
{
    private const BRANCH_NAME = 'Qualité, QHSE & Conformité';

    private function buildConfig(bool $enabled, array $branches, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'kb_categories_enabled' => $enabled ? 1 : 0,
            'category_branches' => json_encode($branches),
            'category_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    /**
     * Ids of the top-level KB "categories" named after the branch.
     *
     * @return list<int>
     */
    private function findCategoryIds(): array
    {
        global $DB;

        if (GlpiVersion::isAtLeast12()) {
            $criteria = [
                'SELECT' => 'id',
                'FROM' => KnowbaseItem::getTable(),
                'WHERE' => [
                    'name' => self::BRANCH_NAME,
                    'id' => new \Glpi\DBAL\QuerySubQuery([
                        'SELECT' => 'knowbaseitems_id',
                        'FROM' => KnowbaseItem_KnowbaseItem::getTable(),
                        'WHERE' => ['knowbaseitems_id_parent' => KnowbaseItem::getRootId()],
                    ]),
                ],
            ];
        } else {
            $criteria = [
                'SELECT' => 'id',
                'FROM' => KnowbaseItemCategory::getTable(),
                'WHERE' => ['name' => self::BRANCH_NAME, 'knowbaseitemcategories_id' => 0],
            ];
        }

        return array_map(static fn (array $row): int => (int) $row['id'], iterator_to_array($DB->request($criteria), false));
    }

    private function frenchTranslation(int $id): ?string
    {
        if (GlpiVersion::isAtLeast12()) {
            $translation = new KnowbaseItemTranslation();
            if (!$translation->getFromDBByCrit(['knowbaseitems_id' => $id, 'language' => 'fr_FR'])) {
                return null;
            }

            return $translation->fields['name'];
        }

        $translation = new DropdownTranslation();
        if (!$translation->getFromDBByCrit([
            'itemtype' => KnowbaseItemCategory::class,
            'items_id' => $id,
            'language' => 'fr_FR',
            'field' => 'name',
        ])) {
            return null;
        }

        return $translation->fields['value'];
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new KnowbaseCategoryBuilder())->build($this->buildConfig(false, ['qualite']));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresOnlyTheSelectedBranchGetsAMatchingTopLevelCategory(): void
    {
        $count = (new KnowbaseCategoryBuilder())->build($this->buildConfig(true, ['qualite']));

        $this->assertSame(1, $count);
        $ids = $this->findCategoryIds();
        $this->assertCount(1, $ids);

        if (GlpiVersion::isAtLeast12()) {
            // A GLPI 12 container article is closed by default: without this, self-service users
            // could no longer browse what used to be a category visible to everyone.
            $this->assertTrue((new \Entity_KnowbaseItem())->getFromDBByCrit([
                'knowbaseitems_id' => $ids[0],
                'entities_id' => 0,
                'is_recursive' => 1,
            ]));
        }
    }

    public function testBuildIsIdempotentAndReusesTheSameCategory(): void
    {
        $builder = new KnowbaseCategoryBuilder();
        $builder->build($this->buildConfig(true, ['qualite']));
        $idsBefore = $this->findCategoryIds();

        $second = $builder->build($this->buildConfig(true, ['qualite']));
        $this->assertSame(1, $second);

        $idsAfter = $this->findCategoryIds();
        $this->assertCount(1, $idsAfter, 'Exactly one row must exist — no duplicate.');
        $this->assertSame($idsBefore, $idsAfter);
    }

    public function testTogglingIconsOnThenOffUpdatesTheTranslation(): void
    {
        $builder = new KnowbaseCategoryBuilder();
        $builder->build($this->buildConfig(true, ['qualite'], icons: true));

        $id = $this->findCategoryIds()[0];
        $this->assertStringStartsWith('📋', (string) $this->frenchTranslation($id));

        $builder->build($this->buildConfig(true, ['qualite'], icons: false));

        $this->assertSame(self::BRANCH_NAME, $this->frenchTranslation($id));
    }
}
