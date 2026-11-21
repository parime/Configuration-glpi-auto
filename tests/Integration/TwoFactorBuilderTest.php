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

use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\PrivilegedAccountsWithout2faCheck;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\TwoFactorBuilder;
use PHPUnit\Framework\TestCase;
use Profile;
use Profile_User;
use User;

/**
 * Issue #464 : obligation de double authentification sur les profils choisis et contrôle d'audit
 * des comptes à privilèges. Travaille uniquement sur des profils et un compte fictifs créés ici
 * (jamais sur les profils natifs de l'instance de test partagée), supprimés en fin de test.
 */
final class TwoFactorBuilderTest extends TestCase
{
    private const CENTRAL_PROFILE = 'CGA 464 central profile';
    private const OTHER_CENTRAL_PROFILE = 'CGA 464 untouched central profile';
    private const HELPDESK_PROFILE = 'CGA 464 helpdesk profile';
    private const USER_NAME = 'cga464-fictitious-tech';

    /** @var array<string, int> */
    private array $profileIds = [];
    private int $userId = 0;

    protected function setUp(): void
    {
        if (!TwoFactorBuilder::isAvailable()) {
            $this->markTestSkipped('Native two-factor authentication is not available on this GLPI version.');
        }

        $profiles = [
            self::CENTRAL_PROFILE       => 'central',
            self::OTHER_CENTRAL_PROFILE => 'central',
            self::HELPDESK_PROFILE      => 'helpdesk',
        ];
        foreach ($profiles as $name => $interface) {
            $id = (int) (new Profile())->add(['name' => $name, 'interface' => $interface]);
            $this->assertGreaterThan(0, $id);
            $this->profileIds[$name] = $id;
        }

        $this->userId = (int) (new User())->add(['name' => self::USER_NAME, 'is_active' => 1, 'authtype' => 1]);
        $this->assertGreaterThan(0, $this->userId);
        $this->assertGreaterThan(0, (int) (new Profile_User())->add([
            'users_id'    => $this->userId,
            'profiles_id' => $this->profileIds[self::CENTRAL_PROFILE],
            'entities_id' => 0,
        ]));
    }

    protected function tearDown(): void
    {
        if ($this->userId > 0) {
            (new User())->delete(['id' => $this->userId], true);
        }
        foreach ($this->profileIds as $id) {
            (new Profile())->delete(['id' => $id], true);
        }
        $this->profileIds = [];
        $this->userId = 0;
    }

    /**
     * @param list<string> $names
     */
    private function config(bool $enabled, array $names): Config
    {
        $config = new Config();
        $config->fields = ['twofa_enforcement_enabled' => $enabled ? 1 : 0, 'twofa_profiles' => json_encode($names)];

        return $config;
    }

    private function enforced(string $name): int
    {
        $profile = new Profile();
        $this->assertTrue($profile->getFromDB($this->profileIds[$name]));

        return (int) $profile->fields['2fa_enforced'];
    }

    /**
     * @return array{id: int, name: string, profiles: list<int>, covered: bool}|null
     */
    private function fictitiousUser(): ?array
    {
        foreach (TwoFactorBuilder::findPrivilegedUsersWithout2fa() as $user) {
            if ($user['id'] === $this->userId) {
                return $user;
            }
        }

        return null;
    }

    public function testNothingChangesWhileTheMainSwitchIsOff(): void
    {
        $this->assertSame(0, (new TwoFactorBuilder())->apply($this->config(false, [self::CENTRAL_PROFILE])));
        $this->assertSame(0, $this->enforced(self::CENTRAL_PROFILE));
    }

    public function testOnlyTheChosenCentralProfilesAreEnforced(): void
    {
        $updated = (new TwoFactorBuilder())->apply(
            $this->config(true, [self::CENTRAL_PROFILE, self::HELPDESK_PROFILE, 'Profile that does not exist'])
        );

        $this->assertSame(1, $updated);
        $this->assertSame(1, $this->enforced(self::CENTRAL_PROFILE));
        $this->assertSame(0, $this->enforced(self::OTHER_CENTRAL_PROFILE), 'unchosen profile left alone');
        $this->assertSame(0, $this->enforced(self::HELPDESK_PROFILE), 'simplified interface profile never changed');

        $this->assertSame(
            0,
            (new TwoFactorBuilder())->apply($this->config(true, [self::CENTRAL_PROFILE])),
            'already enforced: not counted again'
        );
    }

    public function testUncheckingAProfileNeverRemovesAnExistingObligation(): void
    {
        (new Profile())->update(['id' => $this->profileIds[self::OTHER_CENTRAL_PROFILE], '2fa_enforced' => 1]);

        (new TwoFactorBuilder())->apply($this->config(true, []));

        $this->assertSame(1, $this->enforced(self::OTHER_CENTRAL_PROFILE));
    }

    public function testPreviewListsCentralProfilesOnlyWithTheirAccountsWithoutTwoFactor(): void
    {
        $preview = [];
        foreach (TwoFactorBuilder::getProfilesPreview() as $row) {
            $preview[$row['name']] = $row;
        }

        $this->assertArrayHasKey(self::CENTRAL_PROFILE, $preview);
        $this->assertArrayNotHasKey(self::HELPDESK_PROFILE, $preview);
        $this->assertSame(1, $preview[self::CENTRAL_PROFILE]['users_without_2fa']);
        $this->assertFalse($preview[self::CENTRAL_PROFILE]['enforced']);
        $this->assertFalse($preview[self::CENTRAL_PROFILE]['recommended']);
    }

    public function testAuditReportsThePrivilegedAccountUntilItsProfileIsEnforced(): void
    {
        global $CFG_GLPI;

        if ((int) ($CFG_GLPI['2fa_enforced'] ?? 0) === 1) {
            $this->markTestSkipped('Two-factor authentication is already enforced instance-wide.');
        }

        $user = $this->fictitiousUser();
        $this->assertNotNull($user);
        $this->assertFalse($user['covered']);

        $check = new PrivilegedAccountsWithout2faCheck();
        $findings = $check->analyze();
        $this->assertCount(1, $findings);
        $this->assertSame(AuditFinding::SEVERITY_WARNING, $findings[0]->severity);
        $this->assertFalse($findings[0]->fixable);
        $this->assertFalse($check->canFix());

        (new TwoFactorBuilder())->apply($this->config(true, [self::CENTRAL_PROFILE]));

        $user = $this->fictitiousUser();
        $this->assertNotNull($user, 'still without a configured application');
        $this->assertTrue($user['covered'], 'GLPI will require it at next login');
    }
}
