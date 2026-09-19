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

use Config;
use GlpiPlugin\Configurationglpiauto\SmtpBuilder;
use PHPUnit\Framework\TestCase;

/**
 * `smtp_*` under the `core` context is GLPI's own real, shared mail configuration — a much bigger
 * blast radius than this plugin's own tables (every notification email this instance ever sends
 * depends on it). Every test here captures the real values in `setUp()` and restores them in
 * `tearDown()` regardless of outcome, same discipline `ConfigTest`/`BlueprintWorkflowTest` already
 * apply to this plugin's own singleton — this suite must never leave the shared instance's actual
 * mail configuration different from what it found.
 */
final class SmtpBuilderTest extends TestCase
{
    private const KEYS = ['smtp_mode', 'smtp_host', 'smtp_port', 'smtp_sender', 'smtp_check_certificate', 'smtp_username', 'smtp_passwd'];

    private array $original = [];

    protected function setUp(): void
    {
        $this->original = Config::getConfigurationValues('core', self::KEYS);
    }

    protected function tearDown(): void
    {
        Config::setConfigurationValues('core', $this->original);
    }

    public function testDisabledStepChangesNothingAndReturnsFalse(): void
    {
        $result = (new SmtpBuilder())->build(['smtp_enabled' => 0, 'smtp_host' => 'should-not-be-written.example.test']);

        $this->assertFalse($result);
        $values = Config::getConfigurationValues('core', ['smtp_host']);
        $this->assertNotSame('should-not-be-written.example.test', $values['smtp_host']);
    }

    public function testEnabledStepWritesHostPortAndModeToRealGlpiConfig(): void
    {
        $result = (new SmtpBuilder())->build([
            'smtp_enabled' => 1,
            'smtp_mode' => MAIL_SMTP,
            'smtp_host' => 'smtp.cga-test.example.test',
            'smtp_port' => '2525',
            'smtp_sender' => 'noreply@cga-test.example.test',
            'smtp_check_certificate' => 1,
            'smtp_username' => 'cga-test-user',
        ]);

        $this->assertTrue($result);
        $values = Config::getConfigurationValues('core', self::KEYS);
        $this->assertSame((string) MAIL_SMTP, (string) $values['smtp_mode']);
        $this->assertSame('smtp.cga-test.example.test', $values['smtp_host']);
        $this->assertSame('2525', (string) $values['smtp_port']);
        $this->assertSame('noreply@cga-test.example.test', $values['smtp_sender']);
        $this->assertSame('cga-test-user', $values['smtp_username']);
    }

    public function testAnEmptyPasswordNeverOverwritesTheExistingStoredSecret(): void
    {
        // Seed a real (encrypted) password first, exactly as a genuine save would.
        (new SmtpBuilder())->build(['smtp_enabled' => 1, 'smtp_host' => 'h', 'smtp_passwd' => 'CgaRealSecret123']);
        $seeded = Config::getConfigurationValues('core', ['smtp_passwd']);
        $this->assertNotEmpty($seeded['smtp_passwd'], 'Precondition: a real password must have been stored.');

        // A later save with an empty password field (the admin didn't touch it) must not clear it.
        (new SmtpBuilder())->build(['smtp_enabled' => 1, 'smtp_host' => 'h', 'smtp_passwd' => '']);

        $after = Config::getConfigurationValues('core', ['smtp_passwd']);
        $this->assertSame($seeded['smtp_passwd'], $after['smtp_passwd']);
    }

    public function testStoredPasswordIsEncryptedNeverThePlaintextEnteredValue(): void
    {
        (new SmtpBuilder())->build(['smtp_enabled' => 1, 'smtp_host' => 'h', 'smtp_passwd' => 'CgaPlaintextSecret']);

        $values = Config::getConfigurationValues('core', ['smtp_passwd']);
        $this->assertNotSame('CgaPlaintextSecret', $values['smtp_passwd'], 'Config::setConfigurationValues() must have encrypted it via GLPIKey.');
    }

    public function testExplicitBlankPasswordFlagActuallyClearsTheStoredSecret(): void
    {
        (new SmtpBuilder())->build(['smtp_enabled' => 1, 'smtp_host' => 'h', 'smtp_passwd' => 'CgaSecretToClear']);
        $seeded = Config::getConfigurationValues('core', ['smtp_passwd']);
        $this->assertNotEmpty($seeded['smtp_passwd'], 'Precondition: a real password must have been stored.');

        (new SmtpBuilder())->build(['smtp_enabled' => 1, 'smtp_host' => 'h', '_blank_smtp_passwd' => 1]);

        $after = Config::getConfigurationValues('core', ['smtp_passwd']);
        $this->assertEmpty($after['smtp_passwd']);
    }
}
