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

use CronTask;
use GlpiPlugin\Configurationglpiauto\Audit\AuditWatchCron;
use GlpiPlugin\Configurationglpiauto\Config;
use PHPUnit\Framework\TestCase;

/**
 * Calls `AuditWatchCron::cronAuditwatch()` directly, never through GLPI's real scheduler (same
 * convention as every `*Builder` test in this plugin — the wizard/cron dispatch layer itself is
 * never exercised in tests, only the logic it calls). The real critical-findings state of the
 * shared instance is never forced either way here (same discipline as `CountBasedChecksTest`/
 * `DefaultCredentialsCheckTest`) — what IS deterministic regardless of that state: persistence
 * round-trips correctly, and the exact same critical set never counts as "new" twice in a row.
 */
final class AuditWatchCronTest extends TestCase
{
    private ?string $originalState;

    protected function setUp(): void
    {
        $this->originalState = Config::getConfig()->fields['audit_watch_state'] ?? null;
    }

    protected function tearDown(): void
    {
        $config = Config::getConfig();
        $config->update(['id' => $config->getID(), 'audit_watch_state' => $this->originalState]);
    }

    public function testFirstCallPersistsTheCurrentCriticalKeys(): void
    {
        AuditWatchCron::cronAuditwatch(new CronTask());

        $state = json_decode((string) Config::getConfig()->fields['audit_watch_state'], true);

        $this->assertIsArray($state);
        $this->assertArrayHasKey('last_critical_keys', $state);
        $this->assertArrayHasKey('unseen_new_critical_count', $state);
    }

    public function testImmediateSecondCallNeverCountsTheSameCriticalFindingsAsNew(): void
    {
        AuditWatchCron::cronAuditwatch(new CronTask());
        $firstCount = json_decode((string) Config::getConfig()->fields['audit_watch_state'], true)['unseen_new_critical_count'];

        AuditWatchCron::cronAuditwatch(new CronTask());
        $secondCount = json_decode((string) Config::getConfig()->fields['audit_watch_state'], true)['unseen_new_critical_count'];

        $this->assertSame($firstCount, $secondCount);
    }

    public function testReturnsZeroOnceStateHasStabilized(): void
    {
        AuditWatchCron::cronAuditwatch(new CronTask());

        $result = AuditWatchCron::cronAuditwatch(new CronTask());

        $this->assertSame(0, $result);
    }
}
