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
use GlpiPlugin\Configurationglpiauto\EntityBuilder;
use PHPUnit\Framework\TestCase;

final class EntityBuilderTest extends TestCase
{
    private const TREE = [
        ['name' => 'Client A', 'children' => [
            ['name' => 'Site Nord', 'children' => []],
            ['name' => 'Site Sud', 'children' => [
                ['name' => 'Atelier', 'children' => []],
            ]],
        ]],
        ['name' => 'Client B', 'children' => []],
    ];

    private function buildConfig(array $tree): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'entity_tree' => json_encode($tree),
        ]);

        return $config;
    }

    public function testEmptyTreeCreatesNothing(): void
    {
        $results = (new EntityBuilder())->build($this->buildConfig([]));

        $this->assertSame([], $results);
    }

    public function testBuildCreatesEveryNodeAtAnyDepthUnderTheRightParent(): void
    {
        $results = (new EntityBuilder())->build($this->buildConfig(self::TREE));

        $this->assertCount(2, $results);
        $this->assertSame('Client A', $results[0]['name']);
        $this->assertSame(3, $results[0]['count']);
        $this->assertSame('Client B', $results[1]['name']);
        $this->assertSame(0, $results[1]['count']);

        $clientA = new Entity();
        $this->assertTrue($clientA->getFromDBByCrit(['name' => 'Client A', 'entities_id' => 0]));
        $this->assertSame((int) $clientA->getID(), $results[0]['entities_id']);

        $siteSud = new Entity();
        $this->assertTrue($siteSud->getFromDBByCrit(['name' => 'Site Sud', 'entities_id' => $clientA->getID()]));

        $atelier = new Entity();
        $this->assertTrue($atelier->getFromDBByCrit(['name' => 'Atelier', 'entities_id' => $siteSud->getID()]));
    }

    public function testBuildIsIdempotentAndReturnsTheSameEntityIds(): void
    {
        $builder = new EntityBuilder();
        $first = $builder->build($this->buildConfig(self::TREE));
        $second = $builder->build($this->buildConfig(self::TREE));

        $this->assertSame($first, $second);

        $siteSud = new Entity();
        $siteSud->getFromDBByCrit(['name' => 'Site Sud', 'entities_id' => $first[0]['entities_id']]);

        global $DB;
        // Scoped to this specific parent — "Atelier" isn't a unique-enough name on its own to
        // assume no other test elsewhere in the suite creates its own unrelated entity of the same
        // name under a different parent.
        $count = $DB->request(['FROM' => Entity::getTable(), 'WHERE' => ['name' => 'Atelier', 'entities_id' => $siteSud->getID()]])->count();
        $this->assertSame(1, $count, 'Exactly one "Atelier" entity must exist under "Site Sud" — no duplicate.');
    }

    public function testDescribeSummarizesNamesAndDescendantCounts(): void
    {
        $results = [
            ['name' => 'Client A', 'entities_id' => 1, 'count' => 3],
            ['name' => 'Client B', 'entities_id' => 2, 'count' => 0],
            ['name' => 'Client C', 'entities_id' => 3, 'count' => 1],
        ];

        $this->assertSame(
            'Client A (3 sous-entités) ; Client B ; Client C (1 sous-entité)',
            EntityBuilder::describe($results)
        );
    }

    public function testTopEntityIdsExtractsOnlyTheTopLevelIds(): void
    {
        $results = [
            ['name' => 'Client A', 'entities_id' => 5, 'count' => 3],
            ['name' => 'Client B', 'entities_id' => 9, 'count' => 0],
        ];

        $this->assertSame([5, 9], EntityBuilder::topEntityIds($results));
    }
}
