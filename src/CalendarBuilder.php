<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Parime
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
use Calendar_Holiday;
use CalendarSegment;
use Entity;
use Holiday;

/**
 * Turns a Config's calendar settings (enabled/name/days/hours) into a real GLPI Calendar with
 * one CalendarSegment per selected weekday, then assigns it to entities. Idempotent: reuses an
 * existing calendar of the same name, and skips a segment that's already there.
 *
 * Optionally also seeds the 8 fixed-date French public holidays (`calendar_holidays_enabled`) —
 * confirmed `glpi_holidays` ships empty on a fresh install, so without this SLA/OLA due dates keep
 * counting through a public holiday. Only the 8 *fixed*-date ones (1er janvier, 1er mai, 8 mai, 14
 * juillet, 15 août, 1er novembre, 11 novembre, 25 décembre) — deliberately not the 3 movable ones
 * tied to Easter (Lundi de Pâques, Ascension, Lundi de Pentecôte). `Holiday.is_perpetual` only
 * repeats a fixed month/day every year (`Calendar::isHoliday()` compares `m-d` when set); there's
 * no "recompute from Easter" mechanism, so a movable date would need to be recreated by hand every
 * year — out of scope for a one-time wizard step, so left out entirely rather than seeded once and
 * silently going stale.
 */
class CalendarBuilder
{
    // Arbitrary reference year — is_perpetual makes Calendar::isHoliday() compare month/day only,
    // never the year, so any year works here.
    private const HOLIDAY_REFERENCE_YEAR = 2026;

    private const FRENCH_HOLIDAYS = [
        ['name' => "Jour de l'An", 'month' => 1, 'day' => 1],
        ['name' => 'Fête du Travail', 'month' => 5, 'day' => 1],
        ['name' => 'Victoire 1945', 'month' => 5, 'day' => 8],
        ['name' => 'Fête nationale', 'month' => 7, 'day' => 14],
        ['name' => 'Assomption', 'month' => 8, 'day' => 15],
        ['name' => 'Toussaint', 'month' => 11, 'day' => 1],
        ['name' => 'Armistice 1918', 'month' => 11, 'day' => 11],
        ['name' => 'Noël', 'month' => 12, 'day' => 25],
    ];

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

        $calendarId = $this->buildCalendar($name, $days, $begin, $end);
        if (!empty($config->fields['calendar_holidays_enabled'])) {
            $this->attachFrenchHolidays($calendarId);
        }

        return $calendarId;
    }

    /**
     * Same as build(), but for one MSP client's own calendar override (see
     * Config::sanitizeTree()'s per-client `settings.calendar`) instead of the plugin-wide shared
     * settings — named after the client so it doesn't collide with the shared calendar or another
     * client's.
     *
     * @param array{enabled: bool, days: int[], begin: string, end: string} $calendar
     */
    public function buildFromOverride(string $clientName, array $calendar, bool $withHolidays = false): ?int
    {
        if (empty($calendar['enabled'])) {
            return null;
        }

        $name = sprintf(__('Horaires — %s', 'configurationglpiauto'), $clientName);

        $calendarId = $this->buildCalendar($name, $calendar['days'], $calendar['begin'], $calendar['end']);
        if ($withHolidays) {
            $this->attachFrenchHolidays($calendarId);
        }

        return $calendarId;
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
     */
    private function buildCalendar(string $name, array $days, string $begin, string $end): int
    {
        $calendar = new Calendar();
        if (!$calendar->getFromDBByCrit(['name' => $name])) {
            $id = $calendar->add(['name' => $name]);
            $calendar->getFromDB($id);
        }
        $calendarId = (int) $calendar->getID();

        $begin = $this->normalizeTime($begin);
        $end = $this->normalizeTime($end);

        foreach ($days as $day) {
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

        return $calendarId;
    }

    private function normalizeTime(string $time): string
    {
        return preg_match('/^\d{2}:\d{2}$/', $time) ? $time . ':00' : $time;
    }

    /**
     * Idempotent: reuses an existing Holiday of the same name (instance-wide, not per-calendar —
     * two calendars sharing the same public holiday shouldn't each get their own copy), and skips
     * a Calendar_Holiday link that's already there.
     */
    private function attachFrenchHolidays(int $calendarId): void
    {
        foreach (self::FRENCH_HOLIDAYS as $holiday) {
            $date = sprintf('%04d-%02d-%02d', self::HOLIDAY_REFERENCE_YEAR, $holiday['month'], $holiday['day']);

            $item = new Holiday();
            if (!$item->getFromDBByCrit(['name' => $holiday['name'], 'entities_id' => 0])) {
                $id = $item->add([
                    'name' => $holiday['name'],
                    'entities_id' => 0,
                    'is_recursive' => 1,
                    'begin_date' => $date,
                    'end_date' => $date,
                    'is_perpetual' => 1,
                ]);
                $item->getFromDB($id);
            }
            $holidayId = (int) $item->getID();

            $link = new Calendar_Holiday();
            if (!$link->getFromDBByCrit(['calendars_id' => $calendarId, 'holidays_id' => $holidayId])) {
                $link->add(['calendars_id' => $calendarId, 'holidays_id' => $holidayId]);
            }
        }
    }
}
