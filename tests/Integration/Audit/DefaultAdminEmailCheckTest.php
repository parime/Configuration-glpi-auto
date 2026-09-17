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
use GlpiPlugin\Configurationglpiauto\Audit\Checks\DefaultAdminEmailCheck;
use PHPUnit\Framework\TestCase;

/**
 * `admin_email` is a single global GLPI setting shared with the rest of the instance — its real
 * value is captured in `setUp()` and restored in `tearDown()`, so this test never leaves the
 * shared instance's admin email different from how it found it.
 */
final class DefaultAdminEmailCheckTest extends TestCase
{
    private string $originalValue;

    protected function setUp(): void
    {
        $values = Config::getConfigurationValues('core', ['admin_email']);
        $this->originalValue = (string) ($values['admin_email'] ?? '');
    }

    protected function tearDown(): void
    {
        Config::setConfigurationValues('core', ['admin_email' => $this->originalValue]);
    }

    public function testReturnsNoFindingWhenARealAddressIsConfigured(): void
    {
        Config::setConfigurationValues('core', ['admin_email' => 'rssi@example.test']);

        $this->assertSame([], (new DefaultAdminEmailCheck())->analyze());
    }

    public function testReturnsAFindingWhenStillTheFactoryDefault(): void
    {
        Config::setConfigurationValues('core', ['admin_email' => 'admsys@localhost']);

        $findings = (new DefaultAdminEmailCheck())->analyze();

        $this->assertCount(1, $findings);
        $this->assertFalse($findings[0]->fixable);
    }

    public function testReturnsAFindingWhenEmpty(): void
    {
        Config::setConfigurationValues('core', ['admin_email' => '']);

        $findings = (new DefaultAdminEmailCheck())->analyze();

        $this->assertCount(1, $findings);
    }

    public function testIsNeverAutomaticallyFixable(): void
    {
        // A real admin email cannot be guessed on the organization's behalf — see class docblock.
        $this->assertFalse((new DefaultAdminEmailCheck())->canFix());
    }
}
