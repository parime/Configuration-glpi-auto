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
use CalendarSegment;
use Entity;

/**
 * Turns a Config's calendar settings (enabled/name/days/hours) into a real GLPI Calendar with
 * one CalendarSegment per selected weekday, then assigns it to entities. Idempotent: reuses an
 * existing calendar of the same name, and skips a segment that's already there.
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

        return $this->buildCalendar($name, $days, $begin, $end);
    }

    /**
     * Same as build(), but for one MSP client's own calendar override (see
     * Config::sanitizeTree()'s per-client `settings.calendar`) instead of the plugin-wide shared
     * settings — named after the client so it doesn't collide with the shared calendar or another
     * client's.
     *
     * @param array{enabled: bool, days: int[], begin: string, end: string} $calendar
     */
    public function buildFromOverride(string $clientName, array $calendar): ?int
    {
        if (empty($calendar['enabled'])) {
            return null;
        }

        $name = sprintf(__('Horaires — %s', 'configurationglpiauto'), $clientName);

        return $this->buildCalendar($name, $calendar['days'], $calendar['begin'], $calendar['end']);
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
}
