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
use GlpiPlugin\Configurationglpiauto\Audit\Checks\PrivilegedAccountsReviewCheck;
use GlpiPlugin\Configurationglpiauto\Audit\PrivilegedAccounts;
use PHPUnit\Framework\TestCase;
use Profile;
use Profile_User;
use User;

/**
 * Issue #465 : un compte fictif d'un profil fictif de l'interface standard, sans connexion depuis
 * longtemps, est signalé. Jamais de modification des comptes réels de l'instance partagée.
 */
final class PrivilegedAccountsReviewCheckTest extends TestCase
{
    private const PROFILE = 'CGA 465 central profile';
    private const USER_NAME = 'cga465-fictitious-dormant';

    private int $profileId = 0;
    private int $userId = 0;

    protected function setUp(): void
    {
        $this->profileId = (int) (new Profile())->add(['name' => self::PROFILE, 'interface' => 'central']);
        $this->assertGreaterThan(0, $this->profileId);

        $this->userId = (int) (new User())->add(['name' => self::USER_NAME, 'is_active' => 1, 'authtype' => 1]);
        $this->assertGreaterThan(0, $this->userId);
        $this->assertTrue((new User())->update([
            'id'         => $this->userId,
            'last_login' => date('Y-m-d H:i:s', strtotime('-' . (PrivilegedAccountsReviewCheck::INACTIVE_DAYS + 30) . ' days')),
        ]));
        $this->assertGreaterThan(0, (int) (new Profile_User())->add([
            'users_id'    => $this->userId,
            'profiles_id' => $this->profileId,
            'entities_id' => 0,
        ]));
    }

    protected function tearDown(): void
    {
        if ($this->userId > 0) {
            (new User())->delete(['id' => $this->userId], true);
        }
        if ($this->profileId > 0) {
            (new Profile())->delete(['id' => $this->profileId], true);
        }
    }

    public function testFictitiousAccountIsListedButNotAsSuperAdmin(): void
    {
        $found = null;
        foreach (PrivilegedAccounts::findActive() as $account) {
            if ($account['id'] === $this->userId) {
                $found = $account;
            }
            $this->assertNotSame(PrivilegedAccounts::SYSTEM_USER_NAME, $account['name']);
        }

        $this->assertNotNull($found);
        $this->assertSame([$this->profileId], $found['profiles']);
        $this->assertFalse($found['super_admin'], 'no config write right on the fictitious profile');
        $this->assertNotNull($found['last_login']);
    }

    public function testDormantAccountIsReportedWithALinkAndNeverFixable(): void
    {
        $check = new PrivilegedAccountsReviewCheck();
        $this->assertFalse($check->canFix());

        $dormant = null;
        foreach ($check->analyze() as $finding) {
            $this->assertSame($check->getKey(), $finding->checkKey);
            $this->assertFalse($finding->fixable);
            if (isset($finding->links[self::USER_NAME])) {
                $dormant = $finding;
            }
        }

        $this->assertNotNull($dormant);
        $this->assertSame(AuditFinding::SEVERITY_WARNING, $dormant->severity);
        $this->assertStringContainsString((string) $this->userId, $dormant->links[self::USER_NAME]);
    }
}
