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
use GlpiPlugin\Configurationglpiauto\Audit\AuditHistory;
use GlpiPlugin\Configurationglpiauto\Audit\AuditService;
use PHPUnit\Framework\TestCase;

/**
 * Issue #466 : note de durcissement sur l'instance réelle (sans hypothèse sur son état partagé),
 * photo du jour unique dans l'historique et comparaison avec une photo fictive de la veille.
 */
final class HardeningScoreTest extends TestCase
{
    /** @var list<int> */
    private array $createdIds = [];

    /** @var list<int> */
    private array $existingIds = [];

    protected function setUp(): void
    {
        global $DB;

        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => AuditHistory::TABLE]) as $row) {
            $this->existingIds[] = (int) $row['id'];
        }
    }

    protected function tearDown(): void
    {
        global $DB;

        $ids = array_values(array_diff($this->createdIds, $this->existingIds));
        if ($ids !== []) {
            $DB->delete(AuditHistory::TABLE, ['id' => $ids]);
        }
    }

    public function testEvaluateCoversEveryRegisteredControl(): void
    {
        $evaluation = AuditControlCatalog::evaluate();

        $this->assertCount(count(AuditService::getRegisteredChecks()), $evaluation['statuses']);
        foreach ($evaluation['statuses'] as $key => $status) {
            $this->assertContains($status, ['ok', 'warning', 'critical', 'na'], (string) $key);
            $this->assertNotSame((string) $key, AuditControlCatalog::label((string) $key), 'label missing for ' . $key);
        }
        if ($evaluation['score'] !== null) {
            $this->assertGreaterThanOrEqual(0, $evaluation['score']);
            $this->assertLessThanOrEqual(100, $evaluation['score']);
        }
    }

    public function testSecurityControlsWeighMoreThanDataQuality(): void
    {
        $this->assertSame(AuditControlCatalog::WEIGHT_SECURITY, AuditControlCatalog::weight('privileged_accounts_without_2fa'));
        $this->assertSame(AuditControlCatalog::WEIGHT_DATA, AuditControlCatalog::weight('uncategorized_software'));
        $this->assertSame(
            ['ghost_assets', 'warranty_expiring'],
            AuditControlCatalog::notApplicable(['audit_ghost_days' => 0, 'audit_warranty_days' => 0])
        );
    }

    public function testRecordKeepsOneSnapshotPerDayAndCompares(): void
    {
        global $DB;

        $todayId = AuditHistory::record(70, ['weak_password_policy' => 'critical'], 'page');
        $this->createdIds[] = $todayId;
        $this->assertGreaterThan(0, $todayId);
        $this->assertSame($todayId, AuditHistory::record(90, ['weak_password_policy' => 'ok'], 'page'));

        // Photo fictive de la veille, insérée directement pour comparer deux jours.
        $DB->insert(AuditHistory::TABLE, [
            'date_creation' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'users_id'      => 0,
            'source'        => 'report',
            'score'         => 40,
            'statuses'      => json_encode(['weak_password_policy' => 'critical', 'mail_collectors' => 'ok']),
        ]);
        $yesterdayId = (int) $DB->insertId();
        $this->createdIds[] = $yesterdayId;

        $runs = AuditHistory::list(50);
        $this->assertSame($todayId, $runs[0]['id']);
        $this->assertSame(90, $runs[0]['score']);

        $comparison = AuditHistory::comparison($runs, $todayId, $yesterdayId);
        $this->assertNotNull($comparison);
        $this->assertSame(50, $comparison['delta']);
        $this->assertSame([AuditControlCatalog::label('weak_password_policy')], $comparison['groups']['improved']);
        $this->assertSame([AuditControlCatalog::label('mail_collectors')], $comparison['groups']['removed']);
        $this->assertNull(AuditHistory::comparison($runs, $todayId, $todayId));
    }
}
