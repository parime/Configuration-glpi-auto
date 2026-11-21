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

use Calendar;
use CalendarSegment;
use GlpiPlugin\Configurationglpiauto\Audit\BlueprintDrift;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\BlueprintDriftCheck;
use GlpiPlugin\Configurationglpiauto\CalendarBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use PHPUnit\Framework\TestCase;

/**
 * Issue #468 : calendrier modifié à la main après application du Blueprint. Les SLA, catégories et
 * statuts sont désactivés ici : l'instance de test est partagée, le SLM « SLA standard » n'est pas
 * touché.
 */
final class BlueprintDriftTest extends TestCase
{
    private const CALENDAR_NAME = 'PHPUnit: drift calendar';

    protected function tearDown(): void
    {
        $calendar = new Calendar();
        if ($calendar->getFromDBByCrit(['name' => self::CALENDAR_NAME])) {
            $calendar->delete(['id' => $calendar->getID()], true);
        }
    }

    private function buildConfig(): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'calendar_enabled'             => 1,
            'calendar_name'                => self::CALENDAR_NAME,
            'calendar_days'                => json_encode([1, 2]),
            'calendar_begin'               => '08:00',
            'calendar_end'                 => '18:00',
            'calendar_lunch_break_enabled' => 0,
            'sla_enabled'                  => 0,
            'category_enabled'             => 0,
            'state_enabled'                => 0,
        ]);

        return $config;
    }

    public function testMissingCalendarIsReportedAsRemoved(): void
    {
        $drift = BlueprintDrift::detect($this->buildConfig());

        $this->assertCount(1, $drift);
        $this->assertSame(BlueprintDrift::REMOVED, $drift[0]['change']);
        $this->assertSame(self::CALENDAR_NAME, $drift[0]['item']);
    }

    public function testHandEditedCalendarIsDetectedWithoutChangingAnything(): void
    {
        $config = $this->buildConfig();
        $calendarId = (int) (new CalendarBuilder())->build($config);
        $this->assertSame([], BlueprintDrift::detect($config));

        $segment = new CalendarSegment();
        $this->assertTrue($segment->getFromDBByCrit(['calendars_id' => $calendarId, 'day' => 1]));
        $segment->update(['id' => $segment->getID(), 'end' => '17:00:00']);
        $this->assertTrue($segment->getFromDBByCrit(['calendars_id' => $calendarId, 'day' => 2]));
        $segment->delete(['id' => $segment->getID()], true);
        (new CalendarSegment())->add(['calendars_id' => $calendarId, 'day' => 6, 'begin' => '09:00:00', 'end' => '12:00:00']);
        $before = countElementsInTable(CalendarSegment::getTable(), ['calendars_id' => $calendarId]);

        $changes = array_column(BlueprintDrift::detect($config), 'change');

        $this->assertSame([BlueprintDrift::MODIFIED, BlueprintDrift::REMOVED, BlueprintDrift::ADDED], $changes);
        $this->assertSame($before, countElementsInTable(CalendarSegment::getTable(), ['calendars_id' => $calendarId]));
    }

    public function testCheckIsNeverAutoFixed(): void
    {
        $check = new BlueprintDriftCheck();

        $this->assertSame('blueprint_drift', $check->getKey());
        $this->assertFalse($check->canFix());
    }
}
