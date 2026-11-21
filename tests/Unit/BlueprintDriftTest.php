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

use GlpiPlugin\Configurationglpiauto\Audit\BlueprintDrift;
use GlpiPlugin\Configurationglpiauto\CalendarBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Issue #468 : comparaison des plages horaires attendues et réelles (pur PHP).
 */
final class BlueprintDriftTest extends TestCase
{
    public function testExpectedSegmentsSplitOnLunchBreakOnly(): void
    {
        $segments = CalendarBuilder::expectedSegments([1, 5], '08:00', '18:00', [5 => ['begin' => '09:00', 'end' => '12:00']], true);

        $this->assertSame([
            ['day' => 1, 'begin' => '08:00:00', 'end' => '12:00:00'],
            ['day' => 1, 'begin' => '13:00:00', 'end' => '18:00:00'],
            ['day' => 5, 'begin' => '09:00:00', 'end' => '12:00:00'],
        ], $segments);
    }

    public function testIdenticalSegmentsGiveNoDrift(): void
    {
        $expected = CalendarBuilder::expectedSegments([1, 2], '08:00', '18:00');
        $actual = array_reverse($expected);

        $this->assertSame([], BlueprintDrift::diffSegments($expected, $actual));
    }

    public function testAddedModifiedAndRemovedDays(): void
    {
        $expected = CalendarBuilder::expectedSegments([1, 2], '08:00', '18:00');
        $actual = [
            ['day' => 1, 'begin' => '08:00:00', 'end' => '17:00:00'],
            ['day' => 6, 'begin' => '09:00:00', 'end' => '12:00:00'],
        ];

        $this->assertSame([
            ['day' => 1, 'change' => BlueprintDrift::MODIFIED, 'current' => '08:00-17:00', 'expected' => '08:00-18:00'],
            ['day' => 2, 'change' => BlueprintDrift::REMOVED, 'current' => '', 'expected' => '08:00-18:00'],
            ['day' => 6, 'change' => BlueprintDrift::ADDED, 'current' => '09:00-12:00', 'expected' => ''],
        ], BlueprintDrift::diffSegments($expected, $actual));
    }
}
