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
use GlpiPlugin\Configurationglpiauto\Audit\Checks\DefaultCredentialsCheck;
use PHPUnit\Framework\TestCase;

/**
 * Deliberately never mutates the real `glpi`/`tech`/`normal`/`post-only` accounts here: this suite
 * runs against the same shared, persistent instance as every other Integration test in this
 * plugin (and this plugin's own `tests/integration-bootstrap.php` logs in as `glpi`/`glpi` to run
 * every other test) — changing one of these passwords, even temporarily, would risk locking out
 * the whole suite or a real user depending on execution order/timing. What IS safely verifiable
 * without touching any real credential: the check never claims to be fixable, and any finding it
 * does return (state-dependent, not forced either way) is well-formed.
 */
final class DefaultCredentialsCheckTest extends TestCase
{
    public function testIsNeverFixable(): void
    {
        $this->assertFalse((new DefaultCredentialsCheck())->canFix());
    }

    public function testAnyReturnedFindingIsWellFormedAndNeverFixable(): void
    {
        $check = new DefaultCredentialsCheck();
        $findings = $check->analyze();

        $this->assertLessThanOrEqual(1, count($findings));

        foreach ($findings as $finding) {
            $this->assertSame($check->getKey(), $finding->checkKey);
            $this->assertSame(AuditFinding::SEVERITY_CRITICAL, $finding->severity);
            $this->assertFalse($finding->fixable);
            $this->assertNotSame('', trim($finding->recommendation));
            $this->assertNotSame('', trim($finding->description));
        }
    }
}
