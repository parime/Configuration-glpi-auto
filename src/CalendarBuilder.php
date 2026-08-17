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

namespace GlpiPlugin\Configurationglpiauto;

use Calendar;
use CalendarSegment;
use Entity;

/**
 * Turns a Config's calendar settings (enabled/name/days/hours) into a real GLPI Calendar, then
 * assigns it to entities. Idempotent: reuses an existing calendar of the same name, and skips a
 * segment that's already there.
 *
 * One CalendarSegment per selected weekday by default (uniform hours), with two ways to diverge —
 * `glpi_calendarsegments` has no core limitation here, it already supports any number of segments
 * per day, this plugin's own `CalendarBuilder` just used to only ever create one: (1) per-day hour
 * overrides (`Config::getCalendarDayHours()`, e.g. "vendredi 9h-12h seulement" — only the days that
 * actually differ from the shared begin/end need an entry, everything else keeps using it), and
 * (2) an optional lunch break (`calendar_lunch_break_enabled`) that splits a day into two segments
 * (morning + afternoon) whenever the lunch window actually falls strictly inside that day's hours —
 * a day whose hours don't span the lunch window (e.g. a 9h-12h Friday with a 12h-13h lunch) keeps a
 * single segment rather than producing a zero-length or inverted one.
 *
 * Each day's existing segments are cleared right before that day's new one(s) are written
 * (`clearDaySegments()`) — a real bug found live (2026-08-14): the previous "skip if the exact
 * same segment already exists" check only protects against an *identical* resubmission. Once hours
 * can change between wizard runs (a different Friday end time, lunch break toggled on/off...), a
 * new segment can *overlap* an old one with different bounds on the same day, and
 * `CalendarSegment`'s own core validation (`Impossible d'ajouter une plage chevauchant une plage
 * existante`) rejects the insert outright — silently, since this class never checked `add()`'s
 * return value. Clearing the day first makes each resubmission fully replace that day's schedule
 * rather than merge with whatever was there before, which is also the behavior an admin actually
 * expects when they go back and change the hours.
 *
 * Public holidays are deliberately *not* handled here anymore — an earlier version seeded a
 * hardcoded 8-holiday French list behind its own `calendar_holidays_enabled` checkbox, regardless
 * of which country the calendar's own entity was actually in. Replaced by `CountryHolidayBuilder`,
 * driven by the country typed on that entity's own Location (or France by default when none is
 * typed) — see that class's docblock for the full reasoning. `front/wizard.php` calls it
 * separately, after this class has assigned every calendar to its entities (it needs
 * `assignMap()`'s result, `$calendarMap`, to resolve which calendar a given country belongs to).
 */
class CalendarBuilder
{
    /**
     * @return int|null The calendar's ID, or null if calendars aren't enabled in $config.
     */
    public function build(Config $config): ?int
    {
        if (empty($config->fields['calendar_enabled'])) {
            return null;
        }

        $name = trim((string) ($config->fields['calendar_name'] ?? '')) ?: __('Horaires standard', 'configurationglpiauto');
        $days = $config->getCalendarDays();
        $begin = (string) ($config->fields['calendar_begin'] ?? '08:00');
        $end = (string) ($config->fields['calendar_end'] ?? '18:00');
        $dayHours = $config->getCalendarDayHours();
        $lunchBreak = !empty($config->fields['calendar_lunch_break_enabled']);
        $lunchBegin = (string) ($config->fields['calendar_lunch_begin'] ?? '12:00');
        $lunchEnd = (string) ($config->fields['calendar_lunch_end'] ?? '13:00');

        return $this->buildCalendar($name, $days, $begin, $end, $dayHours, $lunchBreak, $lunchBegin, $lunchEnd);
    }

    /**
     * Same as build(), but for one MSP client's own calendar override (see
     * Config::sanitizeTree()'s per-client `settings.calendar`) instead of the plugin-wide shared
     * settings — named after the client so it doesn't collide with the shared calendar or another
     * client's.
     *
     * @param array{enabled: bool, days: int[], begin: string, end: string, dayHours?: array<int, array{begin: string, end: string}>, lunchBreakEnabled?: bool, lunchBegin?: string, lunchEnd?: string} $calendar
     */
    public function buildFromOverride(string $clientName, array $calendar): ?int
    {
        if (empty($calendar['enabled'])) {
            return null;
        }

        $name = sprintf(__('Horaires — %s', 'configurationglpiauto'), $clientName);

        return $this->buildCalendar(
            $name,
            $calendar['days'],
            $calendar['begin'],
            $calendar['end'],
            $calendar['dayHours'] ?? [],
            !empty($calendar['lunchBreakEnabled']),
            $calendar['lunchBegin'] ?? '12:00',
            $calendar['lunchEnd'] ?? '13:00',
        );
    }

    /**
     * Assigns the same calendar to each given entity — sub-entities inherit it automatically
     * (GLPI's default Entity::CONFIG_PARENT strategy), so only the top of each branch needs this.
     *
     * @param int[] $entityIds
     */
    public function assignToEntities(int $calendarId, array $entityIds): void
    {
        foreach ($entityIds as $entityId) {
            $this->assignOne($entityId, $calendarId);
        }
    }

    /**
     * Per-client variant of assignToEntities(): a different calendar per entity instead of one
     * calendar for all of them.
     *
     * @param array<int, int> $entityIdToCalendarId
     */
    public function assignMap(array $entityIdToCalendarId): void
    {
        foreach ($entityIdToCalendarId as $entityId => $calendarId) {
            $this->assignOne($entityId, $calendarId);
        }
    }

    private function assignOne(int $entityId, int $calendarId): void
    {
        (new Entity())->update([
            'id' => $entityId,
            'calendars_strategy' => $calendarId,
            'calendars_id' => $calendarId,
        ]);
    }

    /**
     * @param int[] $days
     * @param array<int, array{begin: string, end: string}> $dayHours Per-day overrides of
     *        $begin/$end (e.g. "vendredi 9h-12h seulement") — days missing here just use the
     *        shared $begin/$end.
     */
    private function buildCalendar(
        string $name,
        array $days,
        string $begin,
        string $end,
        array $dayHours = [],
        bool $lunchBreak = false,
        string $lunchBegin = '12:00',
        string $lunchEnd = '13:00',
    ): int {
        // `entities_id`/`is_recursive` (confirmed via `DESCRIBE glpi_calendars`) were never set
        // here, silently defaulting to GLPI's own add() default (root entity, *not* recursive) —
        // real bug found live: every calendar this plugin ever created was invisible from any
        // sub-entity's own admin context (Configuration > Calendriers, scoped to the current
        // entity + recursive children only), even though the entity's own `calendars_id` still
        // correctly pointed to it (SLA/OLA/holiday behavior was never actually broken, only
        // browsing/managing the calendar from a site's own context). Root + recursive matches the
        // same scoping every other global dropdown this plugin creates already uses (Holiday,
        // ITILCategory...).
        $calendar = new Calendar();
        if (!$calendar->getFromDBByCrit(['name' => $name])) {
            $id = $calendar->add(['name' => $name, 'entities_id' => 0, 'is_recursive' => 1]);
            $calendar->getFromDB($id);
        }
        $calendarId = (int) $calendar->getID();

        $begin = $this->normalizeTime($begin);
        $end = $this->normalizeTime($end);
        $lunchBegin = $this->normalizeTime($lunchBegin);
        $lunchEnd = $this->normalizeTime($lunchEnd);

        foreach ($days as $day) {
            $dayBegin = isset($dayHours[$day]) ? $this->normalizeTime($dayHours[$day]['begin']) : $begin;
            $dayEnd = isset($dayHours[$day]) ? $this->normalizeTime($dayHours[$day]['end']) : $end;

            $this->clearDaySegments($calendarId, $day);

            if ($lunchBreak && $lunchBegin > $dayBegin && $lunchEnd < $dayEnd && $lunchBegin < $lunchEnd) {
                $this->addSegment($calendarId, $day, $dayBegin, $lunchBegin);
                $this->addSegment($calendarId, $day, $lunchEnd, $dayEnd);
            } else {
                $this->addSegment($calendarId, $day, $dayBegin, $dayEnd);
            }
        }

        return $calendarId;
    }

    /**
     * Removes every existing segment for this (calendar, day) — called right before writing that
     * day's segment(s) for the current submission, so a changed schedule always fully replaces the
     * old one instead of risking an overlap `CalendarSegment` would reject (see class docblock).
     */
    private function clearDaySegments(int $calendarId, int $day): void
    {
        global $DB;

        $DB->delete('glpi_calendarsegments', ['calendars_id' => $calendarId, 'day' => $day]);
    }

    private function addSegment(int $calendarId, int $day, string $begin, string $end): void
    {
        $segment = new CalendarSegment();
        if (!$segment->getFromDBByCrit(['calendars_id' => $calendarId, 'day' => $day, 'begin' => $begin, 'end' => $end])) {
            $segment->add([
                'calendars_id' => $calendarId,
                'day' => $day,
                'begin' => $begin,
                'end' => $end,
            ]);
        }
    }

    private function normalizeTime(string $time): string
    {
        return preg_match('/^\d{2}:\d{2}$/', $time) ? $time . ':00' : $time;
    }
}
