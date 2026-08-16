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

namespace GlpiPlugin\Configurationglpiauto\Tests\Unit;

use GlpiPlugin\Configurationglpiauto\EntityBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Covers only EntityBuilder's two pure static helpers (describe(), topEntityIds()) — every other
 * class in this plugin extends/instantiates a GLPI core class (CommonDBTM, Entity, Calendar...)
 * that only exists inside a running GLPI instance, so it can't be unit-tested outside one.
 * CalendarBuilder/AssetTypeBuilder/ValidationRoutingBuilder now have real coverage instead under
 * tests/Integration (a live GLPI Kernel boot, see tests/integration-bootstrap.php); everything
 * else not yet covered there is still validated manually/with Playwright against the real
 * docker-compose.test.yml stack (see CHANGELOG.md for what's been validated that way).
 */
final class EntityBuilderTest extends TestCase
{
    public function testDescribeSeparatesTopLevelNodesAndCountsDescendants(): void
    {
        $results = [
            ['name' => 'Client A', 'entities_id' => 12, 'count' => 3],
            ['name' => 'Client B', 'entities_id' => 34, 'count' => 0],
        ];

        $this->assertSame(
            'Client A (3 sous-entités) ; Client B',
            EntityBuilder::describe($results)
        );
    }

    public function testDescribeSingularWhenExactlyOneDescendant(): void
    {
        $results = [['name' => 'Client A', 'entities_id' => 12, 'count' => 1]];

        $this->assertSame('Client A (1 sous-entité)', EntityBuilder::describe($results));
    }

    public function testDescribeOnEmptyResultsIsEmptyString(): void
    {
        $this->assertSame('', EntityBuilder::describe([]));
    }

    public function testTopEntityIdsExtractsEachNodesOwnId(): void
    {
        $results = [
            ['name' => 'Client A', 'entities_id' => 12, 'count' => 3],
            ['name' => 'Client B', 'entities_id' => 34, 'count' => 0],
        ];

        $this->assertSame([12, 34], EntityBuilder::topEntityIds($results));
    }

    public function testTopEntityIdsOnEmptyResultsIsEmptyArray(): void
    {
        $this->assertSame([], EntityBuilder::topEntityIds([]));
    }
}
