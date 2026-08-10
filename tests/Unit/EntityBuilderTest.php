<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Parime
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
 * that only exists inside a running GLPI instance, so it can't be unit-tested outside one; those
 * are validated manually/with Playwright against the real docker-compose.test.yml stack instead
 * (see CHANGELOG.md for what's been validated that way).
 */
final class EntityBuilderTest extends TestCase
{
    public function testDescribeJoinsBranchNamesAndSeparatesBranches(): void
    {
        $branches = [
            ['names' => ['Client A', 'Site', 'Service'], 'entities_id' => 12],
            ['names' => ['Client B', 'Site', 'Service'], 'entities_id' => 34],
        ];

        $this->assertSame(
            'Client A > Site > Service ; Client B > Site > Service',
            EntityBuilder::describe($branches)
        );
    }

    public function testDescribeOnEmptyBranchesIsEmptyString(): void
    {
        $this->assertSame('', EntityBuilder::describe([]));
    }

    public function testTopEntityIdsExtractsEachBranchsTopId(): void
    {
        $branches = [
            ['names' => ['Client A'], 'entities_id' => 12],
            ['names' => ['Client B'], 'entities_id' => 34],
        ];

        $this->assertSame([12, 34], EntityBuilder::topEntityIds($branches));
    }

    public function testTopEntityIdsOnEmptyBranchesIsEmptyArray(): void
    {
        $this->assertSame([], EntityBuilder::topEntityIds([]));
    }
}
