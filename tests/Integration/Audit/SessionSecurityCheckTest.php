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

use GlpiPlugin\Configurationglpiauto\Audit\AuditControlCatalog;
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\SessionSecurityCheck;
use GlpiPlugin\Configurationglpiauto\Audit\SessionSecurity;
use GlpiPlugin\Configurationglpiauto\Compat\GlpiVersion;
use PHPUnit\Framework\TestCase;
use Profile;
use Profile_User;
use User;

/**
 * Issue #470 : sous GLPI 12, une session fictive ouverte depuis deux jours par un compte fictif à
 * privilèges est signalée ; sous GLPI 11, le contrôle est sans objet. Aucune session réelle de
 * l'instance partagée n'est touchée.
 */
final class SessionSecurityCheckTest extends TestCase
{
    private const PROFILE = 'CGA 470 central profile';
    private const USER_NAME = 'cga470-fictitious-admin';
    private const SESSION_UID = 'cga470-fictitious-session';

    private int $profileId = 0;
    private int $userId = 0;

    protected function setUp(): void
    {
        $this->profileId = (int) (new Profile())->add(['name' => self::PROFILE, 'interface' => 'central']);
        $this->assertGreaterThan(0, $this->profileId);

        $this->userId = (int) (new User())->add(['name' => self::USER_NAME, 'is_active' => 1, 'authtype' => 1]);
        $this->assertGreaterThan(0, $this->userId);
        $this->assertGreaterThan(0, (int) (new Profile_User())->add([
            'users_id'    => $this->userId,
            'profiles_id' => $this->profileId,
            'entities_id' => 0,
        ]));
    }

    protected function tearDown(): void
    {
        global $DB;

        if (SessionSecurity::isAvailable()) {
            $DB->delete(SessionSecurity::TABLE, ['login_session_uid' => self::SESSION_UID]);
        }
        if ($this->userId > 0) {
            (new User())->delete(['id' => $this->userId], true);
        }
        if ($this->profileId > 0) {
            (new Profile())->delete(['id' => $this->profileId], true);
        }
    }

    public function testAvailabilityFollowsTheGlpiVersion(): void
    {
        $this->assertSame(GlpiVersion::isAtLeast12(), SessionSecurity::isAvailable());

        $evaluation = AuditControlCatalog::evaluate();
        $this->assertArrayHasKey('session_security', $evaluation['statuses']);
        if (!SessionSecurity::isAvailable()) {
            $this->assertSame('na', $evaluation['statuses']['session_security']);
            $this->assertContains(AuditControlCatalog::label('session_security'), $evaluation['not_applicable']);
            $this->assertSame([], (new SessionSecurityCheck())->analyze());
            $this->assertSame(['available' => false], SessionSecurity::view());
        } else {
            $this->assertNotSame('na', $evaluation['statuses']['session_security']);
            $view = SessionSecurity::view();
            $this->assertTrue($view['available']);
            $this->assertArrayHasKey('reauth', $view['statuses']);
        }
    }

    public function testLongPrivilegedSessionIsReportedWithALinkAndNeverFixable(): void
    {
        global $DB;

        if (!SessionSecurity::isAvailable()) {
            $this->markTestSkipped('Session tracking only exists from GLPI 12.');
        }

        $twoDaysAgo = date('Y-m-d H:i:s', time() - (SessionSecurity::ADMIN_SESSION_HOURS + 24) * 3600);
        $this->assertTrue((bool) $DB->insert(SessionSecurity::TABLE, [
            'users_id'          => $this->userId,
            'login_session_uid' => self::SESSION_UID,
            'session_file'      => 'sess_' . self::SESSION_UID,
            'ip_address'        => '192.0.2.10',
            'user_agent'        => 'Fictitious browser',
            'auth_type'         => 1,
            'created_at'        => $twoDaysAgo,
            'last_activity_at'  => $twoDaysAgo,
        ]));

        $check = new SessionSecurityCheck();
        $this->assertFalse($check->canFix());

        $reported = null;
        foreach ($check->analyze() as $finding) {
            $this->assertSame($check->getKey(), $finding->checkKey);
            $this->assertFalse($finding->fixable);
            if (isset($finding->links[self::USER_NAME])) {
                $reported = $finding;
            }
        }

        $this->assertNotNull($reported, 'the fictitious long session is reported');
        $this->assertSame(AuditFinding::SEVERITY_WARNING, $reported->severity);
        $this->assertStringContainsString('id=' . $this->userId, $reported->links[self::USER_NAME]);
        $this->assertStringContainsString('forcetab=User$5', $reported->links[self::USER_NAME]);
    }
}
