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

use Planning;
use PlanningEventCategory;
use PlanningExternalEventTemplate;

/**
 * Turns on `planning_events_enabled` into real `PlanningEventCategory`/`PlanningExternalEventTemplate`
 * lists (Configuration > Intitulés > Assistance > "Catégories d'évènements" / "Gabarits
 * d'évènements externes") — GLPI ships neither by default, confirmed empty on a fresh 11.0.8
 * install. Low-priority, specific-usage intitulés (per the third audit) — built once the more
 * central Assistance gaps were closed.
 *
 * `PlanningEventCategory` has its own native `color` field (confirmed in `glpi_planningeventcategories`
 * — distinct from every other intitulé in this plugin) used to color-code events of that category
 * in GLPI's planning/calendar view, so the visual differentiation here goes through that dedicated
 * field rather than through `Translations::applyIcon()`'s `DropdownTranslation` mechanism used
 * elsewhere — icons are added too (list legibility outside the calendar), but `color` is what
 * actually renders in the planning grid itself.
 *
 * `PlanningExternalEventTemplate` deliberately leaves `rrule` (recurrence) and `before_time`
 * (reminder lead time) at their defaults rather than guessing a recurrence pattern — a reusable
 * *shell* (name, description, category, a plausible duration), same "starting point, not a
 * decision" philosophy as every other template library in this plugin; the admin sets the actual
 * recurrence when scheduling a real event from it. `background = 1` on "Astreinte" only: GLPI's
 * own meaning for that flag is "renders as a busy-background block rather than a discrete event"
 * (confirmed in `glpi_planningexternaleventtemplates`), which is exactly how on-call coverage is
 * conventionally shown on a shared calendar — the other templates are real discrete events.
 */
class PlanningEventBuilder
{
    private const CATEGORIES = [
        ['icon' => '📅', 'name' => 'Réunion', 'color' => '#0ca678'],
        ['icon' => '🎓', 'name' => 'Formation', 'color' => '#4263eb'],
        ['icon' => '🔧', 'name' => 'Maintenance planifiée', 'color' => '#7048e8'],
        ['icon' => '🏖️', 'name' => 'Congés / Absences', 'color' => '#f59f00'],
        ['icon' => '🚨', 'name' => 'Astreinte / Garde', 'color' => '#e03131'],
    ];

    private const TEMPLATES = [
        [
            'name' => 'Réunion d\'équipe',
            'category' => 'Réunion',
            'text' => 'Point d\'équipe régulier — avancement, blocages, priorités.',
            'duration' => 3600,
            'background' => 0,
        ],
        [
            'name' => 'Formation planifiée',
            'category' => 'Formation',
            'text' => 'Session de formation ou de sensibilisation.',
            'duration' => 14400,
            'background' => 0,
        ],
        [
            'name' => 'Astreinte',
            'category' => 'Astreinte / Garde',
            'text' => 'Couverture en dehors des horaires standards.',
            'duration' => 86400,
            'background' => 1,
        ],
    ];

    /**
     * @return int Number of event categories + templates created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['planning_events_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['planning_events_icons_enabled']);
        $count = 0;
        $categoryIds = [];

        foreach (self::CATEGORIES as $category) {
            $item = new PlanningEventCategory();
            $crit = ['name' => $category['name']];
            if (!$item->getFromDBByCrit($crit)) {
                $id = $item->add($crit + ['color' => $category['color']]);
                $item->getFromDB($id);
            }
            $categoryIds[$category['name']] = (int) $item->getID();
            if ($withIcons) {
                Translations::applyIcon(PlanningEventCategory::class, (int) $item->getID(), $category['name'], $category['icon']);
            }
            $count++;
        }

        foreach (self::TEMPLATES as $template) {
            $item = new PlanningExternalEventTemplate();
            $crit = ['name' => $template['name']];
            if (!$item->getFromDBByCrit($crit)) {
                $item->add($crit + [
                    'text' => $template['text'],
                    'duration' => $template['duration'],
                    'background' => $template['background'],
                    'state' => Planning::TODO,
                    'planningeventcategories_id' => $categoryIds[$template['category']] ?? 0,
                    'entities_id' => 0,
                ]);
            }
            $count++;
        }

        return $count;
    }

    /**
     * @return array{categories: array<int, array{icon: string, name: string, color: string}>, templates: array<int, array{name: string, category: string, text: string}>}
     */
    public static function getPreview(): array
    {
        return ['categories' => self::CATEGORIES, 'templates' => self::TEMPLATES];
    }
}
