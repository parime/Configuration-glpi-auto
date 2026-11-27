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
use RuleAction;
use RuleTicket;
use SLA;
use SLM;
use State;
use Toolbox;

/**
 * Issue #468 : dérive par rapport au Blueprint appliqué. La configuration enregistrée du plugin est
 * le dernier Blueprint déployé (assistant, Blueprint importé ou sauvegarde restaurée) ; cette classe
 * compare ce qu'elle fait créer (SLA et OLA du SLM partagé, calendrier partagé, branches de
 * catégories, statuts, règles d'affectation des SLA) à ce qui existe réellement dans GLPI. La
 * détection ne modifie rien : la réapplication passe par l'écran de déploiement, avec son
 * récapitulatif et sa confirmation ; « Adopter » reporte un délai modifié à la main dans la
 * configuration, sur demande explicite (adopt()).
 *
 * Chaque écart : key (famille surveillée, voir OBJECTS), object (libellé), item, change, current,
 * expected, link (fiche GLPI ou null), reapply (le déploiement le corrige) et adopt (jeton pour
 * adopt(), ou null).
 */
final class BlueprintDrift
{
    public const ADDED = 'added';

    public const MODIFIED = 'modified';

    public const REMOVED = 'removed';

    /** Familles surveillées, réglables dans l'onglet Audit de la configuration du plugin. */
    public const OBJECTS = ['sla', 'calendar', 'categories', 'states', 'rules'];

    /**
     * @return array<string, string>
     */
    public static function objectLabels(): array
    {
        return [
            'sla'        => self::slaObjectLabel(),
            'calendar'   => __('Calendrier', 'configurationglpiauto'),
            'categories' => __('Catégories de tickets', 'configurationglpiauto'),
            'states'     => __('Statuts des éléments', 'configurationglpiauto'),
            'rules'      => __('Règles d\'affectation des SLA', 'configurationglpiauto'),
        ];
    }

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
     * Écarts des familles surveillées (réglage « audit_drift_objects »).
     *
     * @return list<array<string, mixed>>
     */
    public static function detect(Config $config): array
    {
        $watched = $config->getDriftObjects();
        $detectors = [
            'sla'        => static fn (): array => self::slaDrift($config),
            'calendar'   => static fn (): array => self::calendarDrift($config),
            'categories' => static fn (): array => self::categoryDrift($config),
            'states'     => static fn (): array => self::stateDrift($config),
            'rules'      => static fn (): array => self::ruleDrift($config),
        ];
        $items = [];
        foreach ($detectors as $key => $detector) {
            if (in_array($key, $watched, true)) {
                $items = array_merge($items, $detector());
            }
        }

        return $items;
    }

    /**
     * Délai GLPI converti en heures entières, ou null s'il ne tombe pas juste (minutes). Pur PHP.
     */
    public static function delayInHours(int $number, string $unit): ?int
    {
        return match ($unit) {
            'hour'   => $number,
            'day'    => $number * 24,
            'minute' => $number % 60 === 0 ? intdiv($number, 60) : null,
            default  => null,
        };
    }

    /**
     * Adopte un délai modifié à la main : le palier correspondant de la configuration prend la valeur
     * actuelle de GLPI, qui devient la référence. Jeton « sla|ola:tto|ttr:priorité:heures » produit
     * par detect() ; tout autre jeton est refusé.
     */
    public static function adopt(Config $config, string $token): bool
    {
        if (!preg_match('/^(sla|ola):(tto|ttr):(\d):(\d{1,6})$/', $token, $m)) {
            return false;
        }
        $priority = (int) $m[3];
        $hours = (int) $m[4];
        if (!in_array($priority, Config::PRIORITY_LEVELS, true) || $hours < 1) {
            return false;
        }
        $field = $m[1] === 'ola' ? 'ola_tiers' : 'sla_tiers';
        $tiers = $m[1] === 'ola' ? $config->getOlaTiers() : $config->getSlaTiers();
        $tiers[$priority][$m[2] . '_hours'] = $hours;

        return $config->update(['id' => $config->getID(), $field => $tiers]);
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
     * @return list<array<string, mixed>>
     */
    private static function slaDrift(Config $config): array
    {
        if (empty($config->fields['sla_enabled'])) {
            return [];
        }
        $object = self::slaObjectLabel();
        $slm = new SLM();
        if (!Lookup::loadFirst($slm, ['name' => SlaBuilder::SLM_NAME])) {
            return [self::item('sla', $object, SlaBuilder::SLM_NAME, self::REMOVED, '', SlaBuilder::SLM_NAME)];
        }

        $expected = SlaBuilder::expectedAgreements($config);
        $expectedNames = [];
        foreach ($expected as [$class, , $name]) {
            $expectedNames[$class][] = $name;
        }
        $allExpectedNames = array_merge($expectedNames[SLA::class] ?? [], $expectedNames[OLA::class] ?? []);

        $items = [];
        // Calendrier du SLM : seul écart de l'aperçu qui ne porte pas sur un délai.
        foreach ((new SlaBuilder())->previewChanges($config, CalendarBuilder::findShared($config)) as $change) {
            if (!in_array($change['item'], $allExpectedNames, true)) {
                $items[] = self::item('sla', $object, $change['item'], self::MODIFIED, $change['current'], $change['expected'], SLM::getFormURLWithID($slm->getID()));
            }
        }

        foreach ($expected as [$class, $type, $name, $hours, $priority]) {
            $agreement = new $class();
            if (!Lookup::loadFirst($agreement, ['slms_id' => $slm->getID(), 'type' => $type, 'name' => $name])) {
                $items[] = self::item('sla', $object, $name, self::REMOVED, '', SlaBuilder::delayLabel($hours, 'hour'));
                continue;
            }
            if (SlaBuilder::hasDelay($agreement->fields, $hours)) {
                continue;
            }
            $number = (int) $agreement->fields['number_time'];
            $unit = (string) $agreement->fields['definition_time'];
            $currentHours = self::delayInHours($number, $unit);
            $items[] = self::item(
                'sla',
                $object,
                $name,
                self::MODIFIED,
                SlaBuilder::delayLabel($number, $unit),
                SlaBuilder::delayLabel($hours, 'hour'),
                $class::getFormURLWithID($agreement->getID()),
                true,
                $currentHours !== null && $currentHours >= 1
                    ? sprintf('%s:%s:%d:%d', $class === OLA::class ? 'ola' : 'sla', $type === SLM::TTO ? 'tto' : 'ttr', $priority, $currentHours)
                    : null
            );
        }
        foreach ([SLA::class, OLA::class] as $class) {
            foreach ((new $class())->find(['slms_id' => $slm->getID()], ['name']) as $row) {
                if (!in_array((string) $row['name'], $expectedNames[$class] ?? [], true)) {
                    $items[] = self::item(
                        'sla',
                        $object,
                        (string) $row['name'],
                        self::ADDED,
                        SlaBuilder::delayLabel((int) $row['number_time'], (string) $row['definition_time']),
                        '',
                        $class::getFormURLWithID((int) $row['id'])
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
     * @return list<array<string, mixed>>
     */
    private static function calendarDrift(Config $config): array
    {
        if (empty($config->fields['calendar_enabled'])) {
            return [];
        }
        $object = self::objectLabels()['calendar'];
        $name = CalendarBuilder::sharedCalendarName($config);
        $calendar = new Calendar();
        if (!Lookup::loadFirst($calendar, ['name' => $name])) {
            return [self::item('calendar', $object, $name, self::REMOVED, '', $name)];
        }

        $actual = [];
        foreach ((new CalendarSegment())->find(['calendars_id' => $calendar->getID()]) as $row) {
            $actual[] = ['day' => (int) $row['day'], 'begin' => (string) $row['begin'], 'end' => (string) $row['end']];
        }
        $dayNames = Toolbox::getDaysOfWeekArray();
        $items = [];
        foreach (self::diffSegments(CalendarBuilder::expectedSharedSegments($config), $actual) as $change) {
            $items[] = self::item(
                'calendar',
                $object,
                sprintf('%s - %s', $name, $dayNames[$change['day']] ?? (string) $change['day']),
                $change['change'],
                $change['current'],
                $change['expected'],
                Calendar::getFormURLWithID($calendar->getID())
            );
        }

        return $items;
    }

    /**
     * Branches de catégories choisies mais absentes (supprimées ou renommées à la main).
     *
     * @return list<array<string, mixed>>
     */
    private static function categoryDrift(Config $config): array
    {
        if (empty($config->fields['category_enabled'])) {
            return [];
        }
        $object = self::objectLabels()['categories'];
        $selected = $config->getCategoryBranches();
        $items = [];
        foreach (CategoryBuilder::getCategoriesPreview() as $branch) {
            if (!in_array($branch['key'], $selected, true)) {
                continue;
            }
            if (!Lookup::loadFirst(new ITILCategory(), ['name' => $branch['name'], 'itilcategories_id' => 0])) {
                $items[] = self::item('categories', $object, $branch['name'], self::REMOVED, '', $branch['name']);
            }
        }

        return $items;
    }

    /**
     * Statuts choisis mais absents.
     *
     * @return list<array<string, mixed>>
     */
    private static function stateDrift(Config $config): array
    {
        if (empty($config->fields['state_enabled'])) {
            return [];
        }
        $object = self::objectLabels()['states'];
        $items = [];
        foreach ($config->getStateNames() as $name) {
            if (!Lookup::loadFirst(new State(), ['name' => $name])) {
                $items[] = self::item('states', $object, $name, self::REMOVED, '', $name);
            }
        }

        return $items;
    }

    /**
     * Règles d'affectation des SLA créées par le plugin (« SLA standard : entité #… ») désactivées
     * ou pointant vers un SLA ou un OLA supprimé. Le déploiement ne retouche pas une règle existante :
     * l'écart se corrige depuis la fiche de la règle (reapply à faux).
     *
     * @return list<array<string, mixed>>
     */
    private static function ruleDrift(Config $config): array
    {
        if (empty($config->fields['sla_enabled'])) {
            return [];
        }
        $object = self::objectLabels()['rules'];
        $items = [];
        $rules = (new RuleTicket())->find(['sub_type' => RuleTicket::class, 'name' => ['LIKE', SlaBuilder::SLM_NAME . ' : %']], ['name']);
        foreach ($rules as $row) {
            $name = (string) $row['name'];
            $link = RuleTicket::getFormURLWithID((int) $row['id']);
            if (empty($row['is_active'])) {
                $items[] = self::item('rules', $object, $name, self::MODIFIED, __('Désactivée', 'configurationglpiauto'), __('Active', 'configurationglpiauto'), $link, false);
            }
            $actions = getAllDataFromTable(RuleAction::getTable(), ['rules_id' => (int) $row['id'], 'field' => ['slas_id_tto', 'slas_id_ttr', 'olas_id_tto', 'olas_id_ttr']]);
            foreach ($actions as $action) {
                $class = str_starts_with((string) $action['field'], 'olas') ? OLA::class : SLA::class;
                if (!(new $class())->getFromDB((int) $action['value'])) {
                    $items[] = self::item(
                        'rules',
                        $object,
                        $name,
                        self::MODIFIED,
                        sprintf(__('Niveau de service introuvable (#%d)', 'configurationglpiauto'), (int) $action['value']),
                        SlaBuilder::SLM_NAME,
                        $link,
                        false
                    );
                }
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(
        string $key,
        string $object,
        string $item,
        string $change,
        string $current,
        string $expected,
        ?string $link = null,
        ?bool $reapply = null,
        ?string $adopt = null
    ): array {
        return [
            'key'      => $key,
            'object'   => $object,
            'item'     => $item,
            'change'   => $change,
            'current'  => $current,
            'expected' => $expected,
            'link'     => $link,
            'reapply'  => $reapply ?? $change !== self::ADDED,
            'adopt'    => $adopt,
        ];
    }
}
