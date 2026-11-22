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

namespace GlpiPlugin\Configurationglpiauto\Tests\Unit;

use GlpiPlugin\Configurationglpiauto\Audit\SessionSecurity;
use PHPUnit\Framework\TestCase;

/**
 * Issue #470 : classement pur des réglages et des sessions (comptes fictifs, date fixe).
 */
final class SessionSecurityTest extends TestCase
{
    private const NOW = 1_800_000_000;

    /**
     * @return array{users_id: int, name: string, created_at: ?string}
     */
    private function session(int $userId, string $name, ?int $hoursAgo): array
    {
        return [
            'users_id'   => $userId,
            'name'       => $name,
            'created_at' => $hoursAgo === null ? null : date('Y-m-d H:i:s', self::NOW - $hoursAgo * 3600),
        ];
    }

    public function testSafeDefaultsAreCompliant(): void
    {
        $statuses = SessionSecurity::evaluateSettings([
            'reauth_disabled'      => false,
            'session_cron_enabled' => true,
            'remember_seconds'     => SessionSecurity::REMEMBER_MAX_DAYS * 86400,
            'history_days'         => 90,
        ]);

        $this->assertSame(
            ['reauth' => 'ok', 'session_cron' => 'ok', 'remember' => 'ok', 'history' => 'ok'],
            $statuses
        );
    }

    public function testRiskySettingsAreFlagged(): void
    {
        $statuses = SessionSecurity::evaluateSettings([
            'reauth_disabled'      => true,
            'session_cron_enabled' => false,
            'remember_seconds'     => 30 * 86400,
            'history_days'         => 0,
        ]);

        $this->assertSame(SessionSecurity::STATUS_DANGER, $statuses['reauth']);
        $this->assertSame(SessionSecurity::STATUS_WARNING, $statuses['session_cron']);
        $this->assertSame(SessionSecurity::STATUS_INFO, $statuses['remember']);
        $this->assertSame(SessionSecurity::STATUS_WARNING, $statuses['history']);
    }

    public function testRememberMeDisabledIsCompliant(): void
    {
        $statuses = SessionSecurity::evaluateSettings([
            'reauth_disabled'      => false,
            'session_cron_enabled' => true,
            'remember_seconds'     => 0,
            'history_days'         => 30,
        ]);

        $this->assertSame(SessionSecurity::STATUS_OK, $statuses['remember']);
    }

    public function testPrivilegedAccountsUseTheShorterThreshold(): void
    {
        $admin = SessionSecurity::ADMIN_SESSION_HOURS;
        $old = SessionSecurity::OLD_SESSION_DAYS * 24;
        $groups = SessionSecurity::classifySessions([
            $this->session(1, 'admin-recent', $admin - 1),
            $this->session(2, 'admin-long', $admin + 1),
            $this->session(3, 'user-two-days', $admin + 24),
            $this->session(4, 'user-old', $old + 1),
            $this->session(5, 'user-no-date', null),
        ], [1, 2], self::NOW);

        $this->assertSame(['admin-long'], array_column($groups['admin'], 'name'));
        $this->assertSame(['user-old'], array_column($groups['old'], 'name'));
    }

    public function testEachAccountIsListedOnceWithItsOldestSession(): void
    {
        $old = SessionSecurity::OLD_SESSION_DAYS * 24;
        $groups = SessionSecurity::classifySessions([
            $this->session(7, 'user-many', $old + 10),
            $this->session(7, 'user-many', $old + 50),
            $this->session(8, 'user-other', $old + 20),
        ], [], self::NOW);

        $this->assertSame([], $groups['admin']);
        $this->assertSame(['user-many', 'user-other'], array_column($groups['old'], 'name'), 'oldest first');
        $this->assertSame(2, $groups['old'][0]['sessions']);
        $this->assertSame(date('Y-m-d H:i:s', self::NOW - ($old + 50) * 3600), $groups['old'][0]['oldest']);
    }
}
