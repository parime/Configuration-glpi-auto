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

namespace GlpiPlugin\Configurationglpiauto\Audit;

use Calendar;
use CalendarSegment;
use GlpiPlugin\Configurationglpiauto\CalendarBuilder;
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigHistory;
use GlpiPlugin\Configurationglpiauto\Lookup;
use GlpiPlugin\Configurationglpiauto\SlaBuilder;
use ITILCategory;
use OLA;
use SLA;
use SLM;
use State;
use Toolbox;

/**
 * Issue #468 : dérive par rapport au Blueprint appliqué. La configuration enregistrée du plugin est
 * le dernier Blueprint déployé (assistant, Blueprint importé ou sauvegarde restaurée) ; cette classe
 * compare ce qu'elle fait créer (SLA et OLA du SLM partagé, calendrier partagé, branches de
 * catégories, statuts) à ce qui existe réellement dans GLPI. Lecture seule : rien n'est modifié,
 * la réapplication passe par l'écran de déploiement, avec son récapitulatif et sa confirmation.
 */
final class BlueprintDrift
{
    public const ADDED = 'added';

    public const MODIFIED = 'modified';

    public const REMOVED = 'removed';

    /**
     * Vrai si un Blueprint a déjà été appliqué : chaque application (assistant, restauration)
     * laisse une sauvegarde automatique dans l'historique (un point manuel ne compte pas). Sans elle,
     * tout apparaîtrait « supprimé » sur une instance jamais configurée par le plugin.
     */
    public static function wasApplied(): bool
    {
        return countElementsInTable(ConfigHistory::getTable(), ['is_manual' => 0]) > 0;
    }

    /**
     * @return list<array{object: string, item: string, change: string, current: string, expected: string}>
     */
    public static function detect(Config $config): array
    {
        return array_merge(
            self::slaDrift($config),
            self::calendarDrift($config),
            self::categoryDrift($config),
            self::stateDrift($config),
        );
    }

    /**
     * Compare les plages horaires attendues et réelles, jour par jour. Pur PHP.
     *
     * @param list<array{day: int, begin: string, end: string}> $expected
     * @param list<array{day: int, begin: string, end: string}> $actual
     * @return list<array{day: int, change: string, current: string, expected: string}>
     */
    public static function diffSegments(array $expected, array $actual): array
    {
        $expectedDays = self::rangesByDay($expected);
        $actualDays = self::rangesByDay($actual);
        $allDays = array_unique(array_merge(array_keys($expectedDays), array_keys($actualDays)));
        sort($allDays);

        $changes = [];
        foreach ($allDays as $day) {
            $want = $expectedDays[$day] ?? [];
            $have = $actualDays[$day] ?? [];
            if ($want === $have) {
                continue;
            }
            $changes[] = [
                'day'      => $day,
                'change'   => $want === [] ? self::ADDED : ($have === [] ? self::REMOVED : self::MODIFIED),
                'current'  => implode(', ', $have),
                'expected' => implode(', ', $want),
            ];
        }

        return $changes;
    }

    /**
     * @param list<array{day: int, begin: string, end: string}> $segments
     * @return array<int, list<string>> Jour => plages « HH:MM-HH:MM » triées.
     */
    private static function rangesByDay(array $segments): array
    {
        $days = [];
        foreach ($segments as $segment) {
            $days[(int) $segment['day']][] = substr((string) $segment['begin'], 0, 5) . '-' . substr((string) $segment['end'], 0, 5);
        }
        foreach ($days as $day => $ranges) {
            sort($ranges);
            $days[$day] = $ranges;
        }

        return $days;
    }

    /**
     * @return list<array{object: string, item: string, change: string, current: string, expected: string}>
     */
    private static function slaDrift(Config $config): array
    {
        if (empty($config->fields['sla_enabled'])) {
            return [];
        }
        $object = self::slaObjectLabel();
        $slm = new SLM();
        if (!Lookup::loadFirst($slm, ['name' => SlaBuilder::SLM_NAME])) {
            return [self::item($object, SlaBuilder::SLM_NAME, self::REMOVED, '', SlaBuilder::SLM_NAME)];
        }

        $items = [];
        foreach ((new SlaBuilder())->previewChanges($config, CalendarBuilder::findShared($config)) as $change) {
            $items[] = self::item($object, $change['item'], self::MODIFIED, $change['current'], $change['expected']);
        }

        $expectedNames = [];
        foreach (SlaBuilder::expectedAgreements($config) as [$class, $type, $name, $hours]) {
            $expectedNames[$class][] = $name;
            if (!Lookup::loadFirst(new $class(), ['slms_id' => $slm->getID(), 'type' => $type, 'name' => $name])) {
                $items[] = self::item($object, $name, self::REMOVED, '', SlaBuilder::delayLabel($hours, 'hour'));
            }
        }
        foreach ([SLA::class, OLA::class] as $class) {
            foreach ((new $class())->find(['slms_id' => $slm->getID()], ['name']) as $row) {
                if (!in_array((string) $row['name'], $expectedNames[$class] ?? [], true)) {
                    $items[] = self::item(
                        $object,
                        (string) $row['name'],
                        self::ADDED,
                        SlaBuilder::delayLabel((int) $row['number_time'], (string) $row['definition_time']),
                        ''
                    );
                }
            }
        }

        return $items;
    }

    public static function slaObjectLabel(): string
    {
        return __('Niveaux de service (SLA, OLA)', 'configurationglpiauto');
    }

    /**
     * @return list<array{object: string, item: string, change: string, current: string, expected: string}>
     */
    private static function calendarDrift(Config $config): array
    {
        if (empty($config->fields['calendar_enabled'])) {
            return [];
        }
        $object = __('Calendrier', 'configurationglpiauto');
        $name = CalendarBuilder::sharedCalendarName($config);
        $calendar = new Calendar();
        if (!Lookup::loadFirst($calendar, ['name' => $name])) {
            return [self::item($object, $name, self::REMOVED, '', $name)];
        }

        $actual = [];
        foreach ((new CalendarSegment())->find(['calendars_id' => $calendar->getID()]) as $row) {
            $actual[] = ['day' => (int) $row['day'], 'begin' => (string) $row['begin'], 'end' => (string) $row['end']];
        }
        $dayNames = Toolbox::getDaysOfWeekArray();
        $items = [];
        foreach (self::diffSegments(CalendarBuilder::expectedSharedSegments($config), $actual) as $change) {
            $items[] = self::item(
                $object,
                sprintf('%s - %s', $name, $dayNames[$change['day']] ?? (string) $change['day']),
                $change['change'],
                $change['current'],
                $change['expected']
            );
        }

        return $items;
    }

    /**
     * Branches de catégories choisies mais absentes (supprimées ou renommées à la main).
     *
     * @return list<array{object: string, item: string, change: string, current: string, expected: string}>
     */
    private static function categoryDrift(Config $config): array
    {
        if (empty($config->fields['category_enabled'])) {
            return [];
        }
        $object = __('Catégories de tickets', 'configurationglpiauto');
        $selected = $config->getCategoryBranches();
        $items = [];
        foreach (CategoryBuilder::getCategoriesPreview() as $branch) {
            if (!in_array($branch['key'], $selected, true)) {
                continue;
            }
            if (!Lookup::loadFirst(new ITILCategory(), ['name' => $branch['name'], 'itilcategories_id' => 0])) {
                $items[] = self::item($object, $branch['name'], self::REMOVED, '', $branch['name']);
            }
        }

        return $items;
    }

    /**
     * Statuts choisis mais absents.
     *
     * @return list<array{object: string, item: string, change: string, current: string, expected: string}>
     */
    private static function stateDrift(Config $config): array
    {
        if (empty($config->fields['state_enabled'])) {
            return [];
        }
        $object = __('Statuts des éléments', 'configurationglpiauto');
        $items = [];
        foreach ($config->getStateNames() as $name) {
            if (!Lookup::loadFirst(new State(), ['name' => $name])) {
                $items[] = self::item($object, $name, self::REMOVED, '', $name);
            }
        }

        return $items;
    }

    /**
     * @return array{object: string, item: string, change: string, current: string, expected: string}
     */
    private static function item(string $object, string $item, string $change, string $current, string $expected): array
    {
        return ['object' => $object, 'item' => $item, 'change' => $change, 'current' => $current, 'expected' => $expected];
    }
}
