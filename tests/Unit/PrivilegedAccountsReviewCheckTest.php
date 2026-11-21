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

use GlpiPlugin\Configurationglpiauto\Audit\Checks\PrivilegedAccountsReviewCheck;
use PHPUnit\Framework\TestCase;

/**
 * Issue #465 : classement pur des comptes à privilèges (comptes fictifs, date de référence fixe).
 */
final class PrivilegedAccountsReviewCheckTest extends TestCase
{
    private const NOW = 1_800_000_000;

    /**
     * @return array{id: int, name: string, authtype: int, last_login: ?string, date_creation: ?string, super_admin: bool}
     */
    private function account(string $name, ?int $lastLoginDaysAgo, ?int $createdDaysAgo = null, bool $superAdmin = false, int $authtype = 1): array
    {
        $date = static fn (?int $days): ?string => $days === null ? null : date('Y-m-d H:i:s', self::NOW - $days * 86400);

        return [
            'id'            => crc32($name),
            'name'          => $name,
            'authtype'      => $authtype,
            'last_login'    => $date($lastLoginDaysAgo),
            'date_creation' => $date($createdDaysAgo),
            'super_admin'   => $superAdmin,
        ];
    }

    /**
     * @param list<array<string, mixed>> $accounts
     * @return list<string>
     */
    private static function names(array $accounts): array
    {
        return array_map(static fn (array $a): string => (string) $a['name'], $accounts);
    }

    public function testSuperAdminsAreReportedOnlyAboveTheThreshold(): void
    {
        $accounts = [];
        for ($i = 1; $i <= PrivilegedAccountsReviewCheck::MAX_SUPER_ADMINS; $i++) {
            $accounts[] = $this->account("admin$i", 1, null, true);
        }
        $this->assertSame([], PrivilegedAccountsReviewCheck::classify($accounts, false, self::NOW)['super_admins']);

        $accounts[] = $this->account('admin-extra', 1, null, true);
        $this->assertCount(
            PrivilegedAccountsReviewCheck::MAX_SUPER_ADMINS + 1,
            PrivilegedAccountsReviewCheck::classify($accounts, false, self::NOW)['super_admins']
        );
    }

    public function testInactiveAndNeverLoggedAccounts(): void
    {
        $result = PrivilegedAccountsReviewCheck::classify([
            $this->account('recent', 10),
            $this->account('dormant', PrivilegedAccountsReviewCheck::INACTIVE_DAYS + 1),
            $this->account('new-never', null, 5),
            $this->account('old-never', null, PrivilegedAccountsReviewCheck::NEVER_LOGGED_GRACE_DAYS + 1),
            $this->account('install-never', null, null),
        ], false, self::NOW);

        $this->assertSame(['dormant'], self::names($result['inactive']));
        $this->assertSame(['old-never', 'install-never'], self::names($result['never_logged']));
    }

    public function testLocalAccountsOnlyWithAnActiveDirectoryAndBeyondTheEmergencyAccount(): void
    {
        $accounts = [
            $this->account('local-a', 1),
            $this->account('ldap-b', 1, null, false, 3),
        ];
        $this->assertSame([], PrivilegedAccountsReviewCheck::classify($accounts, true, self::NOW)['local'], 'one emergency account is fine');

        $accounts[] = $this->account('local-c', 1);
        $this->assertSame([], PrivilegedAccountsReviewCheck::classify($accounts, false, self::NOW)['local'], 'no directory');
        $this->assertSame(['local-a', 'local-c'], self::names(PrivilegedAccountsReviewCheck::classify($accounts, true, self::NOW)['local']));
    }
}
