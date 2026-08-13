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

    // Stable keys for the 11 top-level category branches (CategoryBuilder::CATEGORIES) — used to
    // validate category_branches against a whitelist, same role PRIORITY_LEVELS plays for tiers.
    public const CATEGORY_BRANCH_KEYS = [
        'it', 'batiment', 'flotte', 'rh', 'achats', 'securite',
        'services_generaux', 'administratif', 'communication', 'qualite', 'maintenance',
    ];

    // The 8 profiles GLPI 11 ships out of the box (confirmed on a fresh install) — used to
    // validate ldap_rights_profile against a whitelist rather than trusting free text that could
    // reference a profile that doesn't exist (RuleRightBuilder would then have nothing to assign).
    public const NATIVE_PROFILE_NAMES = [
        'Super-Admin', 'Admin', 'Supervisor', 'Technician', 'Hotliner', 'Observer', 'Self-Service', 'Read-Only',
    ];

    // The 18 palette keys GLPI 11 ships out of the box (Glpi\UI\ThemeManager::getCoreThemes()) —
    // same whitelist role as NATIVE_PROFILE_NAMES above, kept here rather than re-querying
    // ThemeManager on every prepareInput() call for a fixed, rarely-changing list.
    public const NATIVE_PALETTE_KEYS = [
        'aerialgreen', 'auror', 'auror_dark', 'automn', 'classic', 'clockworkorange', 'dark',
        'darker', 'flood', 'greenflat', 'hipster', 'icecream', 'lightblue', 'midnight',
        'premiumred', 'purplehaze', 'teclib', 'vintage',
    ];

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

    // OLA = internal commitment that has to land *before* the matching SLA deadline for the SLA
    // to actually be met (e.g. tier 1 support triage before the customer-facing clock runs out),
    // so tighter than DEFAULT_SLA_TIERS at every level — starting point, admin-editable.
    private const DEFAULT_OLA_TIERS = [
        '6' => ['tto_hours' => 1, 'ttr_hours' => 2],
        '5' => ['tto_hours' => 1, 'ttr_hours' => 4],
        '4' => ['tto_hours' => 2, 'ttr_hours' => 8],
        '3' => ['tto_hours' => 4, 'ttr_hours' => 24],
        '2' => ['tto_hours' => 8, 'ttr_hours' => 48],
        '1' => ['tto_hours' => 24, 'ttr_hours' => 72],
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
            'calendar_holidays_enabled' => 0,
            'branding_enabled' => 0,
            'branding_primary_color' => '#206bc4',
            'custom_palette_enabled' => 0,
            'native_palette' => '',
            'branding_per_client_enabled' => 0,
            'sla_enabled' => 0,
            'sla_tiers' => json_encode(self::DEFAULT_SLA_TIERS),
            'sla_astreinte' => 0,
            'sla_escalation_enabled' => 0,
            'sla_escalation_threshold_percent' => 75,
            'escalation_enabled' => 0,
            'escalation_includes_n0' => 0,
            'escalation_auto_n1_n2' => 1,
            'escalation_auto_n2_n3' => 1,
            'support_tier_icons_enabled' => 0,
            'ola_enabled' => 0,
            'ola_tiers' => json_encode(self::DEFAULT_OLA_TIERS),
            'category_enabled' => 0,
            'category_branches' => json_encode(self::CATEGORY_BRANCH_KEYS),
            'category_icons_enabled' => 0,
            'state_enabled' => 0,
            'state_icons_enabled' => 0,
            // JSON_UNESCAPED_UNICODE: several state names carry accents (Attribué, Obsolète...),
            // and this default value also gets embedded literally in a SQL DEFAULT clause on
            // upgrade (Installer.php's addField() call) — a `\uXXXX` escape sequence there is
            // fragile (confirmed: MySQL's own backslash-escaping in a DEFAULT literal ate the
            // backslash, corrupting "Attribué" into "Attribuu00e9"). Storing the raw UTF-8
            // character instead sidesteps the whole class of problem.
            'state_names' => json_encode(StateBuilder::getStateNames(), JSON_UNESCAPED_UNICODE),
            'general_ui_enabled' => 0,
            'notifications_enabled' => 0,
            'financial_info_enabled' => 0,
            'project_task_states_enabled' => 0,
            'satisfaction_survey_enabled' => 0,
            'committee_validation_enabled' => 0,
            'ticket_template_enabled' => 0,
            'ticket_template_icons_enabled' => 0,
            'helpdesk_form_hide_fields' => 0,
            'service_catalog_enabled' => 0,
            'wait_reasons_enabled' => 0,
            'ldap_rights_enabled' => 0,
            'ldap_rights_group_template' => 'GLPI_{ENTITY}',
            // Native "Admin" — deliberately not "Super-Admin" (fifth completeness audit, Sprint 35):
            // the single most severe GLPI pain point found in web research is orgs defaulting too
            // many people to Super-Admin out of convenience, since it's the only native profile
            // with enough day-to-day capability. Confirmed by diffing glpi_profilerights: Admin
            // already lacks `profile` write access (can't edit profile definitions, so can't grant
            // itself more), `rule_ldap`/`rule_import` (can't rewrite the very sync rules this
            // toggle creates), and `config` (no general instance configuration) — the exact
            // self-elevation vectors, without inventing a new bespoke rights bitmask.
            'ldap_rights_profile' => 'Admin',
            'ldap_function_rights' => '[]',
            'task_categories_enabled' => 0,
            'task_templates_enabled' => 0,
            'task_template_icons_enabled' => 0,
            'solution_library_enabled' => 0,
            'solution_type_icons_enabled' => 0,
            'solution_template_icons_enabled' => 0,
            'followup_library_enabled' => 0,
            'followup_library_icons_enabled' => 0,
            'validation_templates_enabled' => 0,
            'validation_template_icons_enabled' => 0,
            'change_problem_templates_enabled' => 0,
            'change_problem_template_icons_enabled' => 0,
            'locations_enabled' => 0,
            'manufacturers_enabled' => 0,
            'manufacturer_icons_enabled' => 0,
            'manufacturer_dictionary_enabled' => 0,
            'kb_categories_enabled' => 0,
            'document_management_enabled' => 0,
            'document_management_icons_enabled' => 0,
            'planning_events_enabled' => 0,
            'planning_events_icons_enabled' => 0,
            'project_taxonomy_enabled' => 0,
            'project_taxonomy_icons_enabled' => 0,
            'project_task_templates_enabled' => 0,
            'project_task_template_icons_enabled' => 0,
            'entity_logos_enabled' => 0,
            'wait_reason_icons_enabled' => 0,
            'notification_branding_enabled' => 0,
            'location_geocoding_enabled' => 0,
            'location_geocoding_endpoint' => 'https://nominatim.openstreetmap.org',
            'project_templates_enabled' => 0,
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

        return $this->sanitizeSlaTiers(is_array($tiers) ? $tiers : [], self::DEFAULT_SLA_TIERS);
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
     * Same as getSlaTiers(), for the OLA (internal commitment) table — see class docs on
     * sanitizeSlaSettings() for why OLA lives alongside SLA rather than as its own concept.
     *
     * @return array<string, array{tto_hours: int, ttr_hours: int}>
     */
    public function getOlaTiers(): array
    {
        $tiers = json_decode((string) ($this->fields['ola_tiers'] ?? '[]'), true);

        return $this->sanitizeSlaTiers(is_array($tiers) ? $tiers : [], self::DEFAULT_OLA_TIERS);
    }

    /**
     * @return array<string, array{tto_hours: int, ttr_hours: int}>
     */
    public static function getDefaultOlaTiers(): array
    {
        return self::DEFAULT_OLA_TIERS;
    }

    /**
     * Which of CategoryBuilder's 11 top-level branches the admin wants created — a client with an
     * empty/malformed value gets none rather than a guess, but a fresh install defaults to all of
     * them (see getDefaults()) since that's a more useful starting point than an empty wizard step.
     *
     * @return string[]
     */
    public function getCategoryBranches(): array
    {
        $branches = json_decode((string) ($this->fields['category_branches'] ?? '[]'), true);

        return is_array($branches) ? array_values(array_intersect(self::CATEGORY_BRANCH_KEYS, $branches)) : [];
    }

    /**
     * Which of `StateBuilder`'s 14 states the admin wants created — same "whitelist-intersect,
     * empty rather than a guess" reasoning as `getCategoryBranches()`.
     *
     * @return string[]
     */
    public function getStateNames(): array
    {
        $names = json_decode((string) ($this->fields['state_names'] ?? '[]'), true);

        return is_array($names) ? array_values(array_intersect(StateBuilder::getStateNames(), $names)) : [];
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

    /**
     * @return array<int, array{group: string, profile: string}>
     */
    public function getLdapFunctionRights(): array
    {
        $rights = json_decode((string) ($this->fields['ldap_function_rights'] ?? '[]'), true);

        return is_array($rights) ? $rights : [];
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

        if (isset($input['ldap_function_rights'])) {
            $input['ldap_function_rights'] = json_encode($this->sanitizeLdapFunctionRights($input['ldap_function_rights']));
        }

        if (isset($input['calendar_enabled'])) {
            $input['calendar_enabled'] = !empty($input['calendar_enabled']) ? 1 : 0;
        }

        if (isset($input['calendar_holidays_enabled'])) {
            $input['calendar_holidays_enabled'] = !empty($input['calendar_holidays_enabled']) ? 1 : 0;
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
            $input['sla_tiers'] = json_encode($this->sanitizeSlaTiers($input['sla_tiers'], self::DEFAULT_SLA_TIERS));
        }

        if (isset($input['sla_astreinte'])) {
            $input['sla_astreinte'] = !empty($input['sla_astreinte']) ? 1 : 0;
        }

        if (isset($input['sla_escalation_enabled'])) {
            $input['sla_escalation_enabled'] = !empty($input['sla_escalation_enabled']) ? 1 : 0;
        }

        if (isset($input['sla_escalation_threshold_percent'])) {
            // Below 50%: escalating before half the delay has even elapsed is noise, not an
            // early warning. Above 95%: leaves no meaningful time to act before breach.
            $input['sla_escalation_threshold_percent'] = max(50, min(95, (int) $input['sla_escalation_threshold_percent']));
        }

        if (isset($input['ola_enabled'])) {
            $input['ola_enabled'] = !empty($input['ola_enabled']) ? 1 : 0;
        }

        if (isset($input['ola_tiers']) && is_array($input['ola_tiers'])) {
            $input['ola_tiers'] = json_encode($this->sanitizeSlaTiers($input['ola_tiers'], self::DEFAULT_OLA_TIERS));
        }

        if (isset($input['category_enabled'])) {
            $input['category_enabled'] = !empty($input['category_enabled']) ? 1 : 0;
        }

        if (isset($input['category_branches'])) {
            // Two different shapes reach here: a real array from the wizard form's
            // `category_branches[]` checkboxes, or the JSON-encoded string getDefaults() uses for
            // the fresh-install seed row (add()'s own prepareInputForAdd() call). `(array) $string`
            // would wrap the whole JSON string as a single bogus element instead of decoding it —
            // confirmed as a real bug (a fresh install silently got zero branches selected instead
            // of all 11) via the empty-DB reset done this session, which finally surfaced it.
            $branches = is_string($input['category_branches'])
                ? (json_decode($input['category_branches'], true) ?? [])
                : $input['category_branches'];
            $input['category_branches'] = json_encode(array_values(array_intersect(
                self::CATEGORY_BRANCH_KEYS,
                is_array($branches) ? $branches : []
            )));
        }

        if (isset($input['category_icons_enabled'])) {
            $input['category_icons_enabled'] = !empty($input['category_icons_enabled']) ? 1 : 0;
        }

        if (isset($input['state_enabled'])) {
            $input['state_enabled'] = !empty($input['state_enabled']) ? 1 : 0;
        }

        if (isset($input['state_names'])) {
            // Same two shapes as category_branches (real array from the form vs. the JSON string
            // getDefaults() seeds a fresh row with) — decode explicitly rather than `(array)`
            // casting a string, which would wrap it as one bogus element instead of decoding it
            // (the exact bug fixed in category_branches this same session).
            $names = is_string($input['state_names']) ? (json_decode($input['state_names'], true) ?? []) : $input['state_names'];
            $input['state_names'] = json_encode(array_values(array_intersect(
                StateBuilder::getStateNames(),
                is_array($names) ? $names : []
            )), JSON_UNESCAPED_UNICODE);
        }

        if (isset($input['state_icons_enabled'])) {
            $input['state_icons_enabled'] = !empty($input['state_icons_enabled']) ? 1 : 0;
        }

        foreach (['general_ui_enabled', 'notifications_enabled', 'financial_info_enabled', 'project_task_states_enabled', 'satisfaction_survey_enabled', 'committee_validation_enabled'] as $field) {
            if (isset($input[$field])) {
                $input[$field] = !empty($input[$field]) ? 1 : 0;
            }
        }

        if (isset($input['ticket_template_enabled'])) {
            $input['ticket_template_enabled'] = !empty($input['ticket_template_enabled']) ? 1 : 0;
        }

        if (isset($input['helpdesk_form_hide_fields'])) {
            $input['helpdesk_form_hide_fields'] = !empty($input['helpdesk_form_hide_fields']) ? 1 : 0;
        }

        if (isset($input['service_catalog_enabled'])) {
            $input['service_catalog_enabled'] = !empty($input['service_catalog_enabled']) ? 1 : 0;
        }

        if (isset($input['wait_reasons_enabled'])) {
            $input['wait_reasons_enabled'] = !empty($input['wait_reasons_enabled']) ? 1 : 0;
        }

        if (isset($input['ldap_rights_enabled'])) {
            $input['ldap_rights_enabled'] = !empty($input['ldap_rights_enabled']) ? 1 : 0;
        }

        if (isset($input['ldap_rights_group_template'])) {
            $template = trim((string) $input['ldap_rights_group_template']);
            $input['ldap_rights_group_template'] = str_contains($template, '{ENTITY}') ? $template : 'GLPI_{ENTITY}';
        }

        if (isset($input['ldap_rights_profile']) && !in_array($input['ldap_rights_profile'], self::NATIVE_PROFILE_NAMES, true)) {
            $input['ldap_rights_profile'] = 'Admin';
        }

        if (isset($input['location_geocoding_endpoint'])) {
            // Only ever an admin-typed URL, never request data reaching ajax/geocode.php — but
            // validated here too (not just there) since this is the one place that actually
            // writes it to storage. https:// only: the proxy forwards this over the network,
            // an http:// (or any other scheme, including file:// gadgets) endpoint has no
            // legitimate use here.
            $endpoint = rtrim(trim((string) $input['location_geocoding_endpoint']), '/');
            $input['location_geocoding_endpoint'] = preg_match('#^https://[^\s]+$#', $endpoint)
                ? $endpoint
                : 'https://nominatim.openstreetmap.org';
        }

        // Empty string (no native palette chosen) is always valid — only a non-empty value has to
        // match a real GLPI palette key, same "trust nothing free-text" reasoning as
        // ldap_rights_profile above.
        if (isset($input['native_palette']) && $input['native_palette'] !== '' && !in_array($input['native_palette'], self::NATIVE_PALETTE_KEYS, true)) {
            $input['native_palette'] = '';
        }

        foreach (['task_categories_enabled', 'task_templates_enabled', 'solution_library_enabled', 'solution_type_icons_enabled', 'followup_library_enabled', 'validation_templates_enabled', 'change_problem_templates_enabled', 'locations_enabled', 'manufacturers_enabled', 'manufacturer_icons_enabled', 'kb_categories_enabled', 'project_taxonomy_enabled', 'project_taxonomy_icons_enabled', 'project_task_templates_enabled', 'entity_logos_enabled', 'wait_reason_icons_enabled', 'escalation_enabled', 'escalation_includes_n0', 'escalation_auto_n1_n2', 'escalation_auto_n2_n3', 'support_tier_icons_enabled', 'ticket_template_icons_enabled', 'task_template_icons_enabled', 'solution_template_icons_enabled', 'followup_library_icons_enabled', 'validation_template_icons_enabled', 'change_problem_template_icons_enabled', 'project_task_template_icons_enabled', 'custom_palette_enabled', 'document_management_enabled', 'document_management_icons_enabled', 'planning_events_enabled', 'planning_events_icons_enabled', 'branding_per_client_enabled', 'notification_branding_enabled', 'manufacturer_dictionary_enabled', 'location_geocoding_enabled', 'project_templates_enabled'] as $field) {
            if (isset($input[$field])) {
                $input[$field] = !empty($input[$field]) ? 1 : 0;
            }
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
     * Drops any row with an empty group name or a profile outside NATIVE_PROFILE_NAMES — same
     * "trust nothing free-text" reasoning as ldap_rights_profile/native_palette, applied per-row
     * since this is a user-supplied list rather than a single field.
     *
     * @param mixed $rights Raw $_POST['ldap_function_rights'] shape: array<int, array{group?: string, profile?: string}>
     * @return array<int, array{group: string, profile: string}>
     */
    private function sanitizeLdapFunctionRights($rights): array
    {
        if (!is_array($rights)) {
            return [];
        }

        $clean = [];
        foreach ($rights as $row) {
            if (!is_array($row)) {
                continue;
            }
            $group = trim((string) ($row['group'] ?? ''));
            $profile = (string) ($row['profile'] ?? '');
            if ($group === '' || !in_array($profile, self::NATIVE_PROFILE_NAMES, true)) {
                continue;
            }
            $clean[] = ['group' => $group, 'profile' => $profile];
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

        if (is_array($settings['escalation'] ?? null)) {
            $clean['escalation'] = $this->sanitizeEscalationSettings($settings['escalation']);
        }

        return $clean;
    }

    /**
     * @return array{enabled: bool, includes_n0: bool, auto_n1_n2: bool, auto_n2_n3: bool}
     */
    private function sanitizeEscalationSettings(array $escalation): array
    {
        return [
            'enabled' => !empty($escalation['enabled']),
            'includes_n0' => !empty($escalation['includes_n0']),
            'auto_n1_n2' => !empty($escalation['auto_n1_n2']),
            'auto_n2_n3' => !empty($escalation['auto_n2_n3']),
        ];
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
     * OLA (internal commitment) is kept as sibling keys here rather than its own top-level
     * `settings.ola` — it only ever exists attached to the same client's SLA (same SLM
     * container in SlaBuilder), so nesting it separately would just be two objects that always
     * have to agree on which client they belong to.
     *
     * @return array{enabled: bool, astreinte: bool, tiers: array<string, array{tto_hours: int, ttr_hours: int}>, ola_enabled: bool, ola_tiers: array<string, array{tto_hours: int, ttr_hours: int}>}
     */
    private function sanitizeSlaSettings(array $sla): array
    {
        return [
            'enabled' => !empty($sla['enabled']),
            'astreinte' => !empty($sla['astreinte']),
            'tiers' => $this->sanitizeSlaTiers(is_array($sla['tiers'] ?? null) ? $sla['tiers'] : [], self::DEFAULT_SLA_TIERS),
            'ola_enabled' => !empty($sla['ola_enabled']),
            'ola_tiers' => $this->sanitizeSlaTiers(is_array($sla['ola_tiers'] ?? null) ? $sla['ola_tiers'] : [], self::DEFAULT_OLA_TIERS),
        ];
    }

    /**
     * Fills in every level from PRIORITY_LEVELS, falling back to $defaults for any level missing
     * or malformed in $tiers rather than leaving a gap a ticket could fall through. Shared by SLA
     * and OLA tables — same shape, different starting-point numbers ($defaults).
     *
     * @param array<string, array{tto_hours: int, ttr_hours: int}> $defaults
     * @return array<string, array{tto_hours: int, ttr_hours: int}>
     */
    private function sanitizeSlaTiers(array $tiers, array $defaults): array
    {
        $clean = [];
        foreach (self::PRIORITY_LEVELS as $level) {
            $key = (string) $level;
            $tier = is_array($tiers[$key] ?? null) ? $tiers[$key] : [];

            $clean[$key] = [
                'tto_hours' => max(1, (int) ($tier['tto_hours'] ?? $defaults[$key]['tto_hours'])),
                'ttr_hours' => max(1, (int) ($tier['ttr_hours'] ?? $defaults[$key]['ttr_hours'])),
            ];
        }

        return $clean;
    }

    private function sanitizeTimeString(string $time): string
    {
        return preg_match('/^\d{2}:\d{2}$/', $time) ? $time : '08:00';
    }
}
