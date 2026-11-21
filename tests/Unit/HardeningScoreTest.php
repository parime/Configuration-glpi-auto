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
use GlpiPlugin\Configurationglpiauto\Audit\HardeningScore;
use PHPUnit\Framework\TestCase;

/**
 * Issue #466 : note de durcissement pondérée et comparaison entre deux audits (calcul pur).
 */
final class HardeningScoreTest extends TestCase
{
    private static function finding(string $key, string $severity): AuditFinding
    {
        return new AuditFinding($key, 'Domain', $severity, 'Title', 'Description', 'Recommendation');
    }

    public function testStatusesKeepTheMostSevereFindingAndIgnoreInformation(): void
    {
        $statuses = HardeningScore::statuses(
            ['a', 'b', 'c', 'd'],
            [
                self::finding('a', AuditFinding::SEVERITY_WARNING),
                self::finding('a', AuditFinding::SEVERITY_CRITICAL),
                self::finding('b', AuditFinding::SEVERITY_INFO),
                self::finding('c', AuditFinding::SEVERITY_WARNING),
                self::finding('d', AuditFinding::SEVERITY_CRITICAL),
            ],
            ['d']
        );

        $this->assertSame(['a' => 'critical', 'b' => 'ok', 'c' => 'warning', 'd' => 'na'], $statuses);
    }

    public function testScoreIsWeightedAndExcludesNotApplicableControls(): void
    {
        // Possible : 3 + 1 + 2 = 6 ; obtenu : 3 (ok) + 0.5 (alerte) + 0 (échec) = 3.5, soit 58 %.
        $statuses = ['sec' => 'ok', 'data' => 'warning', 'ops' => 'critical', 'off' => 'na'];
        $weights  = ['sec' => 3, 'data' => 1, 'ops' => 2, 'off' => 3];

        $this->assertSame(58, HardeningScore::score($statuses, $weights));
        $this->assertSame(100, HardeningScore::score(['a' => 'ok'], []));
        $this->assertSame(0, HardeningScore::score(['a' => 'critical'], []));
        $this->assertNull(HardeningScore::score(['a' => 'na'], []));
        $this->assertNull(HardeningScore::score([], []));
    }

    public function testScoreByDomainIsSortedFromTheWeakest(): void
    {
        $domains = HardeningScore::byDomain(
            ['a' => 'ok', 'b' => 'critical', 'c' => 'warning', 'd' => 'na'],
            [],
            ['a' => 'Assets', 'b' => 'Accounts', 'c' => 'Assets', 'd' => 'Network']
        );

        $this->assertSame([
            ['domain' => 'Accounts', 'score' => 0, 'controls' => 1],
            ['domain' => 'Assets', 'score' => 75, 'controls' => 2],
        ], $domains);
    }

    public function testLevelColours(): void
    {
        $this->assertSame('success', HardeningScore::level(80));
        $this->assertSame('warning', HardeningScore::level(79));
        $this->assertSame('warning', HardeningScore::level(50));
        $this->assertSame('danger', HardeningScore::level(49));
        $this->assertSame('secondary', HardeningScore::level(null));
    }

    public function testCompareListsImprovedWorsenedNewAndRemovedControls(): void
    {
        $before = ['a' => 'critical', 'b' => 'ok', 'c' => 'warning', 'd' => 'na', 'e' => 'ok', 'gone' => 'ok'];
        $after  = ['a' => 'warning', 'b' => 'critical', 'c' => 'warning', 'd' => 'ok', 'e' => 'na', 'fresh' => 'critical'];

        $this->assertSame([
            'improved' => ['a'],
            'worsened' => ['b'],
            'new'      => ['d', 'fresh'],
            'removed'  => ['e', 'gone'],
        ], HardeningScore::compare($before, $after));
    }
}
