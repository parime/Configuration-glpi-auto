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

use Calendar;
use CalendarSegment;
use GlpiPlugin\Configurationglpiauto\CalendarBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use PHPUnit\Framework\TestCase;

/**
 * Runs against a real GLPI instance (see tests/integration-bootstrap.php) — CalendarBuilder writes
 * real Calendar/CalendarSegment rows and its own core validation (overlap rejection) only fires
 * against the real DB, so this can't be a pure-PHP unit test.
 *
 * testRebuildWithChangedHoursDoesNotThrowOnOverlap() is a regression test for the real bug reported
 * by the user 2026-08-14 (see CalendarBuilder's class docblock and CHANGELOG [0.61.1]): resubmitting
 * the wizard with different hours for a day that already had a calendar segment used to try to
 * insert an overlapping segment, which GLPI's own CalendarSegment::prepareInput() rejects
 * ("Impossible d'ajouter une plage chevauchant une plage existante") — silently, since the old code
 * never checked add()'s return value.
 */
final class CalendarBuilderTest extends TestCase
{
    private const CALENDAR_NAME = 'PHPUnit — Horaires test';

    protected function tearDown(): void
    {
        $calendar = new Calendar();
        if ($calendar->getFromDBByCrit(['name' => self::CALENDAR_NAME])) {
            $calendar->delete(['id' => $calendar->getID()], true);
        }
    }

    private function buildConfig(array $overrides = []): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'calendar_name' => self::CALENDAR_NAME,
        ], $overrides);

        return $config;
    }

    public function testBuildCreatesOneSegmentPerSelectedDayWithSharedHours(): void
    {
        $config = $this->buildConfig([
            'calendar_days' => json_encode([1, 2, 3, 4, 5]),
            'calendar_begin' => '08:00',
            'calendar_end' => '18:00',
        ]);

        $calendarId = (new CalendarBuilder())->build($config);

        $this->assertNotNull($calendarId);
        $segments = (new CalendarSegment())->find(['calendars_id' => $calendarId]);
        $this->assertCount(5, $segments);
        foreach ($segments as $segment) {
            $this->assertSame('08:00:00', $segment['begin']);
            $this->assertSame('18:00:00', $segment['end']);
        }
    }

    public function testBuildIsIdempotentOnUnchangedSubmission(): void
    {
        $config = $this->buildConfig([
            'calendar_days' => json_encode([1, 2, 3]),
            'calendar_begin' => '09:00',
            'calendar_end' => '17:00',
        ]);
        $builder = new CalendarBuilder();

        $firstId = $builder->build($config);
        $secondId = $builder->build($config);

        $this->assertSame($firstId, $secondId);
        $this->assertCount(3, (new CalendarSegment())->find(['calendars_id' => $firstId]));
    }

    /**
     * Regression test for the 2026-08-14 bug: rebuilding with a *different* end time for a day that
     * already has a segment must not throw and must fully replace that day's schedule, not merge
     * with (and overlap) the old one.
     */
    public function testRebuildWithChangedHoursDoesNotThrowOnOverlap(): void
    {
        $builder = new CalendarBuilder();

        // Friday (day 5) 08:00-18:00 first...
        $config = $this->buildConfig([
            'calendar_days' => json_encode([5]),
            'calendar_begin' => '08:00',
            'calendar_end' => '18:00',
        ]);
        $calendarId = $builder->build($config);

        // ...then the admin goes back and shortens Friday to 09:00-12:00 — this exact scenario
        // (a new segment overlapping the old one's bounds on the same day) is what used to throw.
        $config = $this->buildConfig([
            'calendar_days' => json_encode([5]),
            'calendar_begin' => '09:00',
            'calendar_end' => '12:00',
        ]);
        $rebuiltId = $builder->build($config);

        $this->assertSame($calendarId, $rebuiltId);
        $segments = (new CalendarSegment())->find(['calendars_id' => $calendarId, 'day' => 5]);
        $this->assertCount(1, $segments);
        $segment = reset($segments);
        $this->assertSame('09:00:00', $segment['begin']);
        $this->assertSame('12:00:00', $segment['end']);
    }

    public function testLunchBreakSplitsDayIntoTwoSegments(): void
    {
        $config = $this->buildConfig([
            'calendar_days' => json_encode([1]),
            'calendar_begin' => '08:00',
            'calendar_end' => '18:00',
            'calendar_lunch_break_enabled' => 1,
            'calendar_lunch_begin' => '12:00',
            'calendar_lunch_end' => '13:00',
        ]);

        $calendarId = (new CalendarBuilder())->build($config);

        $segments = (new CalendarSegment())->find(['calendars_id' => $calendarId, 'day' => 1]);
        $this->assertCount(2, $segments);
        $segments = array_values($segments);
        usort($segments, static fn (array $a, array $b) => $a['begin'] <=> $b['begin']);
        $this->assertSame(['08:00:00', '12:00:00'], [$segments[0]['begin'], $segments[0]['end']]);
        $this->assertSame(['13:00:00', '18:00:00'], [$segments[1]['begin'], $segments[1]['end']]);
    }

    public function testDayHoursOverrideAppliesOnlyToThatDay(): void
    {
        $config = $this->buildConfig([
            'calendar_days' => json_encode([1, 5]),
            'calendar_begin' => '08:00',
            'calendar_end' => '18:00',
            'calendar_day_hours' => json_encode([5 => ['begin' => '09:00', 'end' => '12:00']]),
        ]);

        $calendarId = (new CalendarBuilder())->build($config);

        $monday = (new CalendarSegment())->find(['calendars_id' => $calendarId, 'day' => 1]);
        $friday = (new CalendarSegment())->find(['calendars_id' => $calendarId, 'day' => 5]);
        $this->assertSame(['08:00:00', '18:00:00'], [reset($monday)['begin'], reset($monday)['end']]);
        $this->assertSame(['09:00:00', '12:00:00'], [reset($friday)['begin'], reset($friday)['end']]);
    }

    public function testDisabledCalendarReturnsNullAndCreatesNothing(): void
    {
        $config = $this->buildConfig(['calendar_enabled' => 0]);

        $result = (new CalendarBuilder())->build($config);

        $this->assertNull($result);
        $this->assertFalse((new Calendar())->getFromDBByCrit(['name' => self::CALENDAR_NAME]));
    }
}
