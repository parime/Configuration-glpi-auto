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

use Config;
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\NotificationsDisabledCheck;
use PHPUnit\Framework\TestCase;

/**
 * `use_notifications` is a single global GLPI setting shared with every other suite/real usage of
 * this instance — restored to enabled in `tearDown()` regardless of the outcome, so this test never
 * leaves the shared instance worse off than it found it (same discipline as manual verification
 * elsewhere in this plugin's own history).
 */
final class NotificationsDisabledCheckTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::setConfigurationValues('core', ['use_notifications' => 1]);
    }

    public function testReturnsNoFindingWhenNotificationsAreEnabled(): void
    {
        Config::setConfigurationValues('core', ['use_notifications' => 1]);

        $findings = (new NotificationsDisabledCheck())->analyze();

        $this->assertSame([], $findings);
    }

    public function testReturnsACriticalFixableFindingWhenNotificationsAreDisabled(): void
    {
        Config::setConfigurationValues('core', ['use_notifications' => 0]);

        $findings = (new NotificationsDisabledCheck())->analyze();

        $this->assertCount(1, $findings);
        $this->assertSame(AuditFinding::SEVERITY_CRITICAL, $findings[0]->severity);
        $this->assertTrue($findings[0]->fixable);
    }

    public function testFixReenablesNotifications(): void
    {
        Config::setConfigurationValues('core', ['use_notifications' => 0]);

        $check = new NotificationsDisabledCheck();
        $this->assertTrue($check->canFix());
        $check->fix();

        $this->assertSame([], $check->analyze());
        $values = Config::getConfigurationValues('core', ['use_notifications']);
        $this->assertSame('1', (string) $values['use_notifications']);
    }
}
