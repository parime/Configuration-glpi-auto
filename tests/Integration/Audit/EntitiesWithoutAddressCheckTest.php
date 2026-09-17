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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration\Audit;

use Entity;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\EntitiesWithoutAddressCheck;
use PHPUnit\Framework\TestCase;

/**
 * Uses its own uniquely-named throwaway entity (deleted in `tearDown()`) rather than the fixtures
 * other suites create (e.g. `EntityAddressBuilderTest`'s "Client Adresse"/"Site Nord") — this test
 * genuinely purges its entity afterward, unlike those idempotent-reuse fixtures meant to persist
 * across runs, so it must not share one.
 */
final class EntitiesWithoutAddressCheckTest extends TestCase
{
    private const NAME = 'CGA-Audit-Test-Entity-Sans-Adresse';

    private int $entityId = 0;

    protected function tearDown(): void
    {
        if ($this->entityId > 0) {
            (new Entity())->delete(['id' => $this->entityId], true);
        }
    }

    public function testFlagsAnEntityWithNoAddressAtAll(): void
    {
        $entity = new Entity();
        $this->entityId = (int) $entity->add(['name' => self::NAME, 'entities_id' => 0]);

        $findings = (new EntitiesWithoutAddressCheck())->analyze();

        $this->assertCount(1, $findings);
        $this->assertStringContainsString(self::NAME, $findings[0]->description);
        $this->assertFalse($findings[0]->fixable);
    }

    public function testDoesNotFlagAnEntityWithARealAddress(): void
    {
        $entity = new Entity();
        $this->entityId = (int) $entity->add([
            'name' => self::NAME,
            'entities_id' => 0,
            'address' => '1 rue de la Mairie',
        ]);

        $findings = (new EntitiesWithoutAddressCheck())->analyze();

        foreach ($findings as $finding) {
            $this->assertStringNotContainsString(self::NAME, $finding->description);
        }
    }
}
