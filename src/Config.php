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

use CommonDBTM;

/**
 * Plugin-wide settings screen (Configuration > Plugins > wrench icon), a single row (id=1). For
 * now this only holds the entity-structure choice that will drive the future entity-creation
 * wizard (ROADMAP "Assistant de création des entités") — it does not create any Entity yet, it
 * only records the shape the wizard will build later and lets the admin preview it live.
 */
class Config extends CommonDBTM
{
    public static $rightname = Profile::RIGHT_CONFIG;

    public const MODE_MONO = 'mono';

    public const MODE_MULTI_SAME_COMPANY = 'multi_same_company';

    public const MODE_MULTI_MSP = 'multi_msp';

    public const MAX_LEVELS = 5;

    // GLPI's own priority scale (CommonITILObject::getPriorityName()) — 6=Majeure down to
    // 1=Très basse, computed per ticket from the instance-wide urgency×impact matrix. Highest
    // first since that's also natural display order.
    public const PRIORITY_LEVELS = [6, 5, 4, 3, 2, 1];

    private const SINGLETON_ID = 1;

    // Starting point for a fresh sla_tiers table, editable by the admin afterward — same
    // philosophy as the plugin's other defaults (e.g. the old flat 4h/48h).
    private const DEFAULT_SLA_TIERS = [
        '6' => ['tto_hours' => 1, 'ttr_hours' => 4],
        '5' => ['tto_hours' => 2, 'ttr_hours' => 8],
        '4' => ['tto_hours' => 4, 'ttr_hours' => 24],
        '3' => ['tto_hours' => 8, 'ttr_hours' => 48],
        '2' => ['tto_hours' => 24, 'ttr_hours' => 72],
        '1' => ['tto_hours' => 48, 'ttr_hours' => 120],
    ];

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_configurationglpiauto_configs';
    }

    public static function getTypeName($nb = 0): string
    {
        return __('Configuration', 'configurationglpiauto');
    }

    public static function getModes(): array
    {
        return [
            self::MODE_MONO => __('Mono-entité — une seule structure, pas de sous-entités', 'configurationglpiauto'),
            self::MODE_MULTI_SAME_COMPANY => __('Multi-entité — plusieurs sites ou services d\'une même entreprise', 'configurationglpiauto'),
            self::MODE_MULTI_MSP => __('Multi-entité — infogérance de plusieurs entreprises clientes (MSP)', 'configurationglpiauto'),
        ];
    }

    /**
     * Always returns the single settings row, creating it with defaults if it doesn't exist yet
     * (belt and braces alongside Installer seeding it — keeps this safe to call even if the
     * plugin was ever activated before this feature existed).
     */
    public static function getConfig(): self
    {
        $config = new self();
        if (!$config->getFromDB(self::SINGLETON_ID)) {
            $config->add(self::getDefaults() + ['id' => self::SINGLETON_ID]);
            $config->getFromDB(self::SINGLETON_ID);
        }

        return $config;
    }

    public static function getDefaults(): array
    {
        return [
            'entity_mode' => self::MODE_MONO,
            'entity_tree' => json_encode([]),
            'configurationprofiles_id' => 0,
            'calendar_enabled' => 0,
            'calendar_name' => __('Horaires standard', 'configurationglpiauto'),
            'calendar_days' => json_encode([1, 2, 3, 4, 5]),
            'calendar_begin' => '08:00',
            'calendar_end' => '18:00',
            'branding_enabled' => 0,
            'branding_primary_color' => '#206bc4',
            'sla_enabled' => 0,
            'sla_tiers' => json_encode(self::DEFAULT_SLA_TIERS),
            'sla_astreinte' => 0,
        ];
    }

    /**
     * Weekday numbers this calendar covers, PHP date('w') convention (0=Sunday..6=Saturday) —
     * matches GLPI core's own Toolbox::getDaysOfWeekArray()/CalendarSegment.day.
     */
    public function getCalendarDays(): array
    {
        $days = json_decode((string) ($this->fields['calendar_days'] ?? '[]'), true);

        return is_array($days) ? array_map('intval', $days) : [];
    }

    /**
     * The shared/default SLA table: one `['tto_hours' => int, 'ttr_hours' => int]` pair per
     * priority level (string keys matching PRIORITY_LEVELS) — a site/client with no override of
     * its own (see sanitizeClientSettings()) uses this. Missing/invalid levels fall back to
     * DEFAULT_SLA_TIERS rather than leaving a gap.
     *
     * @return array<string, array{tto_hours: int, ttr_hours: int}>
     */
    public function getSlaTiers(): array
    {
        $tiers = json_decode((string) ($this->fields['sla_tiers'] ?? '[]'), true);

        return $this->sanitizeSlaTiers(is_array($tiers) ? $tiers : []);
    }

    /**
     * The built-in starting-point tier table — exposed so ConfigurationProfile::getSuggestedDefaults()
     * can reuse it instead of duplicating the same 6 numbers a second time.
     *
     * @return array<string, array{tto_hours: int, ttr_hours: int}>
     */
    public static function getDefaultSlaTiers(): array
    {
        return self::DEFAULT_SLA_TIERS;
    }

    /**
     * The entity tree the admin has built, root-relative: an array of nodes, each
     * `['name' => string, 'children' => Node[]]` — arbitrary shape, every node can have a
     * different number of children at a different depth (e.g. "test1" has 2 children, one of
     * which has 3 children of its own, while "test2" has none). Empty means "nothing built yet".
     */
    public function getEntityTree(): array
    {
        $tree = json_decode((string) ($this->fields['entity_tree'] ?? '[]'), true);

        return is_array($tree) ? $tree : [];
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInput($input);
    }

    public function prepareInputForAdd($input)
    {
        return $this->prepareInput($input);
    }

    private function prepareInput(array $input): array
    {
        if (isset($input['entity_mode']) && !array_key_exists($input['entity_mode'], self::getModes())) {
            $input['entity_mode'] = self::MODE_MONO;
        }

        if (isset($input['configurationprofiles_id'])) {
            $input['configurationprofiles_id'] = (int) $input['configurationprofiles_id'];
        }

        if (isset($input['entity_tree_json'])) {
            $tree = json_decode((string) $input['entity_tree_json'], true);
            $input['entity_tree'] = json_encode(is_array($tree) ? $this->sanitizeTree($tree) : []);
            unset($input['entity_tree_json']);
        }

        if (isset($input['calendar_enabled'])) {
            $input['calendar_enabled'] = !empty($input['calendar_enabled']) ? 1 : 0;
        }

        if (isset($input['calendar_day'])) {
            $input['calendar_days'] = json_encode(array_values(array_map('intval', (array) $input['calendar_day'])));
            unset($input['calendar_day']);
        }

        if (isset($input['branding_enabled'])) {
            $input['branding_enabled'] = !empty($input['branding_enabled']) ? 1 : 0;
        }

        if (isset($input['branding_primary_color']) && !preg_match('/^#[0-9a-fA-F]{6}$/', $input['branding_primary_color'])) {
            $input['branding_primary_color'] = '#206bc4';
        }

        if (isset($input['sla_enabled'])) {
            $input['sla_enabled'] = !empty($input['sla_enabled']) ? 1 : 0;
        }

        if (isset($input['sla_tiers']) && is_array($input['sla_tiers'])) {
            $input['sla_tiers'] = json_encode($this->sanitizeSlaTiers($input['sla_tiers']));
        }

        if (isset($input['sla_astreinte'])) {
            $input['sla_astreinte'] = !empty($input['sla_astreinte']) ? 1 : 0;
        }

        return $input;
    }

    /**
     * Recursively trims node names, drops nodes left with an empty name, and caps depth at
     * MAX_LEVELS (guards against a malicious/malformed deeply-nested payload, not a realistic
     * admin use case). Top-level nodes only (depth 1 — the MSP "client" nodes a calendar/SLA
     * actually gets assigned to) may also carry an optional per-client `settings` override
     * (calendar and/or SLA); dropped silently at any deeper depth since sub-entities always
     * inherit from their parent.
     */
    private function sanitizeTree(array $nodes, int $depth = 1): array
    {
        if ($depth > self::MAX_LEVELS) {
            return [];
        }

        $clean = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $name = trim((string) ($node['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];

            $cleanNode = [
                'name' => $name,
                'children' => $this->sanitizeTree($children, $depth + 1),
            ];

            if ($depth === 1 && is_array($node['settings'] ?? null)) {
                $settings = $this->sanitizeClientSettings($node['settings']);
                if ($settings !== []) {
                    $cleanNode['settings'] = $settings;
                }
            }

            $clean[] = $cleanNode;
        }

        return $clean;
    }

    /**
     * @return array{calendar?: array, sla?: array} Only the sub-keys that were actually present
     *         and valid — a client with no override at all in $settings ends up with an empty
     *         array here, which sanitizeTree() then drops entirely rather than storing `{}`.
     */
    private function sanitizeClientSettings(array $settings): array
    {
        $clean = [];

        if (is_array($settings['calendar'] ?? null)) {
            $clean['calendar'] = $this->sanitizeCalendarSettings($settings['calendar']);
        }

        if (is_array($settings['sla'] ?? null)) {
            $clean['sla'] = $this->sanitizeSlaSettings($settings['sla']);
        }

        return $clean;
    }

    private function sanitizeCalendarSettings(array $calendar): array
    {
        return [
            'enabled' => !empty($calendar['enabled']),
            'days' => is_array($calendar['days'] ?? null)
                ? array_values(array_map('intval', $calendar['days']))
                : [1, 2, 3, 4, 5],
            'begin' => $this->sanitizeTimeString((string) ($calendar['begin'] ?? '08:00')),
            'end' => $this->sanitizeTimeString((string) ($calendar['end'] ?? '18:00')),
        ];
    }

    /**
     * @return array{enabled: bool, astreinte: bool, tiers: array<string, array{tto_hours: int, ttr_hours: int}>}
     */
    private function sanitizeSlaSettings(array $sla): array
    {
        return [
            'enabled' => !empty($sla['enabled']),
            'astreinte' => !empty($sla['astreinte']),
            'tiers' => $this->sanitizeSlaTiers(is_array($sla['tiers'] ?? null) ? $sla['tiers'] : []),
        ];
    }

    /**
     * Fills in every level from PRIORITY_LEVELS, falling back to DEFAULT_SLA_TIERS for any level
     * missing or malformed in $tiers rather than leaving a gap a ticket could fall through.
     *
     * @return array<string, array{tto_hours: int, ttr_hours: int}>
     */
    private function sanitizeSlaTiers(array $tiers): array
    {
        $clean = [];
        foreach (self::PRIORITY_LEVELS as $level) {
            $key = (string) $level;
            $tier = is_array($tiers[$key] ?? null) ? $tiers[$key] : [];

            $clean[$key] = [
                'tto_hours' => max(1, (int) ($tier['tto_hours'] ?? self::DEFAULT_SLA_TIERS[$key]['tto_hours'])),
                'ttr_hours' => max(1, (int) ($tier['ttr_hours'] ?? self::DEFAULT_SLA_TIERS[$key]['ttr_hours'])),
            ];
        }

        return $clean;
    }

    private function sanitizeTimeString(string $time): string
    {
        return preg_match('/^\d{2}:\d{2}$/', $time) ? $time : '08:00';
    }
}
