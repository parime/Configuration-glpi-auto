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

use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\AuditService;
use LogicException;
use PHPUnit\Framework\TestCase;

final class AuditServiceTest extends TestCase
{
    public function testRunAllReturnsOnlyAuditFindingInstances(): void
    {
        $findings = AuditService::runAll();

        foreach ($findings as $finding) {
            $this->assertInstanceOf(AuditFinding::class, $finding);
        }

        // No assertion on count/content here: this suite runs against a shared, persistent
        // instance (same convention as every other Integration test in this plugin) whose exact
        // state (notifications, entities, states...) depends on which other suites already ran —
        // each individual check has its own dedicated test for its real detection logic.
        $this->assertIsArray($findings);
    }

    public function testFixThrowsOnAnUnknownCheckKey(): void
    {
        $this->expectException(LogicException::class);

        AuditService::fix('this_check_does_not_exist');
    }

    public function testFixThrowsWhenTheCheckIsNotFixable(): void
    {
        $this->expectException(LogicException::class);

        // entities_without_address never offers an automatic fix (see its own docblock) —
        // AuditService::fix() must refuse rather than silently no-op.
        AuditService::fix('entities_without_address');
    }
}
