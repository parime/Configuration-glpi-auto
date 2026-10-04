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

use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\AuditReport;
use PHPUnit\Framework\TestCase;

final class AuditReportTest extends TestCase
{
    private static function finding(string $key, string $severity, string $title = 'T'): AuditFinding
    {
        return new AuditFinding($key, 'Domaine', $severity, $title, 'Description détaillée', 'Recommandation');
    }

    public function testChecksWithoutFindingsAreOkAndWorstSeverityWins(): void
    {
        $report = AuditReport::build(['a', 'b', 'c', 'd'], [
            self::finding('b', AuditFinding::SEVERITY_WARNING),
            self::finding('c', AuditFinding::SEVERITY_INFO),
            self::finding('c', AuditFinding::SEVERITY_CRITICAL),
            self::finding('c', AuditFinding::SEVERITY_WARNING),
        ]);

        $this->assertSame(['a' => 'ok', 'b' => 'warning', 'c' => 'critical', 'd' => 'ok'], $report['statuses']);
        $this->assertSame(['ok' => 2, 'warning' => 1, 'critical' => 1, 'total' => 4], $report['counts']);
    }

    public function testProblemsAreSortedCriticalFirstAndNeverCarryTheDescription(): void
    {
        $report = AuditReport::build(['a', 'b'], [
            self::finding('a', AuditFinding::SEVERITY_WARNING, 'Avertissement'),
            self::finding('b', AuditFinding::SEVERITY_CRITICAL, 'Critique'),
        ]);

        $this->assertSame('Critique', $report['problems'][0]['title']);
        $this->assertSame(['severity', 'domain', 'title', 'recommendation'], array_keys($report['problems'][0]));
        $this->assertStringNotContainsString('Description détaillée', json_encode($report, JSON_UNESCAPED_UNICODE));
    }

    public function testEvolution(): void
    {
        $this->assertSame(
            ['first_report' => true, 'worsened' => [], 'improved' => []],
            AuditReport::evolution(null, ['a' => 'ok'])
        );
        $this->assertSame(
            ['first_report' => false, 'worsened' => ['b', 'c'], 'improved' => ['a']],
            AuditReport::evolution(['a' => 'critical', 'b' => 'ok'], ['a' => 'warning', 'b' => 'critical', 'c' => 'warning'])
        );
    }

    public function testShouldSend(): void
    {
        $clean = AuditReport::build(['a'], []);
        $anomaly = AuditReport::build(['a'], [self::finding('a', AuditFinding::SEVERITY_WARNING)]);

        $this->assertTrue(AuditReport::shouldSend(false, $clean, ['a' => 'ok']), 'Option off: always send.');
        $this->assertTrue(AuditReport::shouldSend(true, $clean, null), 'First report is always sent.');
        $this->assertFalse(AuditReport::shouldSend(true, $clean, ['a' => 'ok']), 'Nothing changed, nothing wrong.');
        $this->assertTrue(AuditReport::shouldSend(true, $clean, ['a' => 'critical']), 'A fix is a change worth reporting.');
        $this->assertTrue(AuditReport::shouldSend(true, $anomaly, ['a' => 'warning']), 'An anomaly is always reported.');
    }
}
