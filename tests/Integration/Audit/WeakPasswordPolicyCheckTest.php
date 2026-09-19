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
use GlpiPlugin\Configurationglpiauto\Audit\Checks\WeakPasswordPolicyCheck;
use PHPUnit\Framework\TestCase;

/**
 * Unlike `DefaultCredentialsCheckTest`, the 5 `password_*` keys read here are a single global GLPI
 * `Config` setting shared with every other suite/real usage of this instance — safe to force in
 * both directions and restored in `tearDown()` regardless of outcome, same discipline as
 * `NotificationsDisabledCheckTest`.
 */
final class WeakPasswordPolicyCheckTest extends TestCase
{
    private const KEYS = [
        'password_min_length',
        'password_need_number',
        'password_need_letter',
        'password_need_caps',
        'password_need_symbol',
    ];

    private array $originalValues;

    protected function setUp(): void
    {
        $this->originalValues = Config::getConfigurationValues('core', self::KEYS);
    }

    protected function tearDown(): void
    {
        Config::setConfigurationValues('core', $this->originalValues);
    }

    public function testReturnsNoFindingWhenPolicyIsStrong(): void
    {
        Config::setConfigurationValues('core', [
            'password_min_length'  => 12,
            'password_need_number' => 1,
            'password_need_letter' => 1,
            'password_need_caps'   => 1,
            'password_need_symbol' => 1,
        ]);

        $findings = (new WeakPasswordPolicyCheck())->analyze();

        $this->assertSame([], $findings);
    }

    public function testReturnsAWarningWhenMinimumLengthIsTooShort(): void
    {
        Config::setConfigurationValues('core', [
            'password_min_length'  => 4,
            'password_need_number' => 1,
            'password_need_letter' => 1,
            'password_need_caps'   => 1,
            'password_need_symbol' => 1,
        ]);

        $findings = (new WeakPasswordPolicyCheck())->analyze();

        $this->assertCount(1, $findings);
        $this->assertSame(AuditFinding::SEVERITY_WARNING, $findings[0]->severity);
        $this->assertFalse($findings[0]->fixable);
        $this->assertStringContainsString('8', $findings[0]->description);
    }

    public function testReturnsAWarningWhenNoComplexityIsRequired(): void
    {
        Config::setConfigurationValues('core', [
            'password_min_length'  => 12,
            'password_need_number' => 0,
            'password_need_letter' => 0,
            'password_need_caps'   => 0,
            'password_need_symbol' => 0,
        ]);

        $findings = (new WeakPasswordPolicyCheck())->analyze();

        $this->assertCount(1, $findings);
        $this->assertSame(AuditFinding::SEVERITY_WARNING, $findings[0]->severity);
        $this->assertFalse($findings[0]->fixable);
    }

    public function testIsNeverFixable(): void
    {
        $this->assertFalse((new WeakPasswordPolicyCheck())->canFix());
    }
}
