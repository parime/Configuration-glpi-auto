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

use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\StateBuilder;
use PHPUnit\Framework\TestCase;

/**
 * `Config` is a real singleton row (id=1) this instance's actual live plugin configuration lives
 * in — every other test in this suite deliberately avoids ever calling the real `add()`/`update()`
 * on it, and so does this one: `prepareInputForAdd()`/`prepareInputForUpdate()` are pure
 * array-in/array-out sanitization (confirmed by reading `prepareInput()` — no `$this->fields`
 * access, no DB call), so calling them directly on a fresh `new Config()` exercises the exact same
 * logic the real form submission does, without ever touching the live row. The plain getters
 * (`getSlaTiers()`, `getEntityTree()`...) are tested the same way every other builder test already
 * reads a `Config` — by setting `->fields` directly.
 */
final class ConfigTest extends TestCase
{
    public function testGetModesReturnsTheThreeEntityModes(): void
    {
        $this->assertSame([Config::MODE_MONO, Config::MODE_MULTI_SAME_COMPANY, Config::MODE_MULTI_MSP], array_keys(Config::getModes()));
    }

    public function testGetConfigReturnsTheExistingSingletonRowWithoutDuplicatingIt(): void
    {
        $config = Config::getConfig();

        $this->assertSame(1, (int) $config->getID());

        global $DB;
        $this->assertSame(1, $DB->request(['FROM' => Config::getTable()])->count(), 'A singleton table must never have more than one row.');
    }

    public function testGetDefaultsProducesOnlyWhitelistedValuesForEveryValidatedField(): void
    {
        $defaults = Config::getDefaults();

        $this->assertSame(Config::MODE_MONO, $defaults['entity_mode']);
        $this->assertContains($defaults['ldap_rights_profile'], Config::NATIVE_PROFILE_NAMES);
        $this->assertStringContainsString('{ENTITY}', $defaults['ldap_rights_group_template']);
        $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $defaults['branding_primary_color']);

        $branches = json_decode($defaults['category_branches'], true);
        $this->assertSame(Config::CATEGORY_BRANCH_KEYS, $branches, 'A fresh install suggests every branch — the admin trims afterward.');

        $stateNames = json_decode($defaults['state_names'], true);
        $this->assertSame(StateBuilder::getStateNames(), $stateNames);

        // PHP casts numeric-string array keys ('6', '5'...) to int automatically.
        $slaTiers = json_decode($defaults['sla_tiers'], true);
        $this->assertSame(Config::PRIORITY_LEVELS, array_keys($slaTiers));
    }

    // --- Plain getters (Config->fields set directly, same pattern every other builder test uses) ---

    private function configWithFields(array $overrides): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), $overrides);

        return $config;
    }

    public function testGetCalendarDaysCastsEveryValueToInt(): void
    {
        $config = $this->configWithFields(['calendar_days' => json_encode(['1', '3', 5])]);

        $this->assertSame([1, 3, 5], $config->getCalendarDays());
    }

    public function testGetCalendarDayHoursFallsBackToDefaultTimesForAMalformedRange(): void
    {
        $config = $this->configWithFields(['calendar_day_hours' => json_encode([5 => ['begin' => '09:00', 'end' => '12:00']])]);

        $this->assertSame(['begin' => '09:00', 'end' => '12:00'], $config->getCalendarDayHours()[5]);
    }

    public function testGetSlaTiersFillsInMissingLevelsFromTheDefaultTable(): void
    {
        // Only priority 6 provided — every other level must fall back to DEFAULT_SLA_TIERS.
        $config = $this->configWithFields(['sla_tiers' => json_encode(['6' => ['tto_hours' => 2, 'ttr_hours' => 6]])]);

        $tiers = $config->getSlaTiers();
        $this->assertSame(['tto_hours' => 2, 'ttr_hours' => 6], $tiers['6']);
        $this->assertSame(Config::getDefaultSlaTiers()['3'], $tiers['3']);
    }

    public function testGetOlaTiersFallsBackToTheOlaDefaultTableNotTheSlaOne(): void
    {
        $config = $this->configWithFields(['ola_tiers' => json_encode([])]);

        $this->assertSame(Config::getDefaultOlaTiers(), $config->getOlaTiers());
    }

    public function testGetCategoryBranchesIntersectsAgainstTheWhitelist(): void
    {
        $config = $this->configWithFields(['category_branches' => json_encode(['it', 'not-a-real-branch', 'rh'])]);

        $this->assertSame(['it', 'rh'], $config->getCategoryBranches());
    }

    public function testGetCategoryBranchesReturnsEmptyForMalformedJson(): void
    {
        $config = $this->configWithFields(['category_branches' => 'not json']);

        $this->assertSame([], $config->getCategoryBranches());
    }

    public function testGetStateNamesIntersectsAgainstStateBuilderWhitelist(): void
    {
        $config = $this->configWithFields(['state_names' => json_encode(['Externe', 'Pas un vrai statut'])]);

        $this->assertSame(['Externe'], $config->getStateNames());
    }

    public function testGetEntityTreeReturnsEmptyArrayWhenNothingBuiltYet(): void
    {
        $config = $this->configWithFields(['entity_tree' => json_encode([])]);

        $this->assertSame([], $config->getEntityTree());
    }

    public function testGetLdapFunctionRightsReturnsTheDecodedArray(): void
    {
        $config = $this->configWithFields(['ldap_function_rights' => json_encode([['group' => 'DSI', 'profile' => 'Admin']])]);

        $this->assertSame([['group' => 'DSI', 'profile' => 'Admin']], $config->getLdapFunctionRights());
    }

    // --- prepareInput() sanitization, exercised directly (no DB write — see class docblock) ---

    public function testPrepareInputResetsAnInvalidEntityModeToMono(): void
    {
        $input = (new Config())->prepareInputForAdd(['entity_mode' => 'not-a-real-mode']);

        $this->assertSame(Config::MODE_MONO, $input['entity_mode']);
    }

    public function testPrepareInputCastsConfigurationProfilesIdToInt(): void
    {
        $input = (new Config())->prepareInputForAdd(['configurationprofiles_id' => '42']);

        $this->assertSame(42, $input['configurationprofiles_id']);
    }

    public function testPrepareInputSanitizesEntityTreeJsonTrimmingNamesAndDroppingEmptyNodes(): void
    {
        $tree = [
            ['name' => '  Client A  ', 'children' => [
                ['name' => '', 'children' => []],
                ['name' => 'Site Nord', 'children' => []],
            ]],
        ];
        $input = (new Config())->prepareInputForAdd(['entity_tree_json' => json_encode($tree)]);

        $clean = json_decode($input['entity_tree'], true);
        $this->assertSame('Client A', $clean[0]['name'], 'Trimmed.');
        $this->assertCount(1, $clean[0]['children'], 'The empty-named child is dropped.');
        $this->assertArrayNotHasKey('entity_tree_json', $input, 'The raw POST key is never persisted.');
    }

    public function testPrepareInputCapsEntityTreeDepthAtMaxLevels(): void
    {
        $node = ['name' => 'Leaf', 'children' => []];
        for ($i = 0; $i < Config::MAX_LEVELS + 3; $i++) {
            $node = ['name' => "Level$i", 'children' => [$node]];
        }

        $input = (new Config())->prepareInputForAdd(['entity_tree_json' => json_encode([$node])]);
        $clean = json_decode($input['entity_tree'], true);

        $depth = 0;
        $cursor = $clean[0];
        while (!empty($cursor['children'])) {
            $depth++;
            $cursor = $cursor['children'][0];
        }
        $this->assertLessThanOrEqual(Config::MAX_LEVELS - 1, $depth, 'Depth beyond MAX_LEVELS must be truncated, not fatal.');
    }

    /**
     * Per-client `settings` (calendar/SLA/escalation overrides) only survive at depth 1 — a
     * sub-entity always inherits from its parent, so a deeper one carrying its own override would
     * be ambiguous and is dropped silently rather than kept.
     */
    public function testPrepareInputKeepsPerClientSettingsOnlyAtTopLevel(): void
    {
        $tree = [
            [
                'name' => 'Client A',
                'settings' => ['calendar' => ['enabled' => true, 'begin' => '09:00', 'end' => '17:00']],
                'children' => [
                    ['name' => 'Site Nord', 'settings' => ['calendar' => ['enabled' => true]], 'children' => []],
                ],
            ],
        ];
        $input = (new Config())->prepareInputForAdd(['entity_tree_json' => json_encode($tree)]);
        $clean = json_decode($input['entity_tree'], true);

        $this->assertSame('09:00', $clean[0]['settings']['calendar']['begin']);
        $this->assertArrayNotHasKey('settings', $clean[0]['children'][0], 'A depth-2 node never keeps its own settings override.');
    }

    /**
     * `settings.sla`/`settings.escalation` round-trip through their own sanitizers — tiers filled
     * in from defaults, booleans coerced.
     */
    public function testPrepareInputSanitizesPerClientSlaAndEscalationOverrides(): void
    {
        $tree = [[
            'name' => 'Client A',
            'settings' => [
                'sla' => ['enabled' => 1, 'astreinte' => 0, 'tiers' => ['6' => ['tto_hours' => 2, 'ttr_hours' => 3]], 'ola_enabled' => 1],
                'escalation' => ['enabled' => 1, 'auto_n1_n2' => 0],
            ],
            'children' => [],
        ]];
        $input = (new Config())->prepareInputForAdd(['entity_tree_json' => json_encode($tree)]);
        $clean = json_decode($input['entity_tree'], true);

        $sla = $clean[0]['settings']['sla'];
        $this->assertTrue($sla['enabled']);
        $this->assertFalse($sla['astreinte']);
        $this->assertSame(['tto_hours' => 2, 'ttr_hours' => 3], $sla['tiers']['6']);
        $this->assertSame(Config::getDefaultSlaTiers()['3'], $sla['tiers']['3'], 'Missing levels fall back to the default table.');

        $escalation = $clean[0]['settings']['escalation'];
        $this->assertTrue($escalation['enabled']);
        $this->assertFalse($escalation['auto_n1_n2']);
    }

    public function testPrepareInputSanitizesLdapFunctionRightsDroppingInvalidRows(): void
    {
        $rights = [
            ['group' => 'DSI', 'profile' => 'Admin'],
            ['group' => '', 'profile' => 'Admin'],
            ['group' => 'RH', 'profile' => 'Not-A-Real-Profile'],
        ];
        $input = (new Config())->prepareInputForAdd(['ldap_function_rights' => $rights]);

        $this->assertSame([['group' => 'DSI', 'profile' => 'Admin']], json_decode($input['ldap_function_rights'], true));
    }

    public function testPrepareInputCastsCalendarEnabledToInt(): void
    {
        $input = (new Config())->prepareInputForAdd(['calendar_enabled' => true]);
        $this->assertSame(1, $input['calendar_enabled']);

        $input = (new Config())->prepareInputForAdd(['calendar_enabled' => false]);
        $this->assertSame(0, $input['calendar_enabled']);
    }

    public function testPrepareInputConvertsCalendarDayToCalendarDaysJson(): void
    {
        $input = (new Config())->prepareInputForAdd(['calendar_day' => ['1', '3']]);

        $this->assertSame([1, 3], json_decode($input['calendar_days'], true));
        $this->assertArrayNotHasKey('calendar_day', $input);
    }

    public function testPrepareInputSanitizesCalendarDayHoursDroppingOutOfRangeDays(): void
    {
        $input = (new Config())->prepareInputForAdd(['calendar_day_hours' => [
            2 => ['begin' => '09:00', 'end' => '12:00'],
            9 => ['begin' => '09:00', 'end' => '12:00'],
        ]]);

        $clean = json_decode($input['calendar_day_hours'], true);
        $this->assertArrayHasKey(2, $clean);
        $this->assertArrayNotHasKey(9, $clean, 'Day 9 does not exist (0-6 range) and must be dropped.');
    }

    public function testPrepareInputFallsBackToDefaultTimeForAMalformedLunchTime(): void
    {
        $input = (new Config())->prepareInputForAdd(['calendar_lunch_begin' => 'not-a-time', 'calendar_lunch_end' => '13:30']);

        $this->assertSame('08:00', $input['calendar_lunch_begin']);
        $this->assertSame('13:30', $input['calendar_lunch_end']);
    }

    public function testPrepareInputRejectsAnInvalidBrandingColorHex(): void
    {
        $input = (new Config())->prepareInputForAdd(['branding_primary_color' => 'not-a-color']);
        $this->assertSame('#206bc4', $input['branding_primary_color']);

        $input = (new Config())->prepareInputForAdd(['branding_primary_color' => '#ABCDEF']);
        $this->assertSame('#ABCDEF', $input['branding_primary_color']);
    }

    public function testPrepareInputClampsSlaEscalationThresholdPercentToTheSafeRange(): void
    {
        $this->assertSame(50, (new Config())->prepareInputForAdd(['sla_escalation_threshold_percent' => 10])['sla_escalation_threshold_percent']);
        $this->assertSame(95, (new Config())->prepareInputForAdd(['sla_escalation_threshold_percent' => 200])['sla_escalation_threshold_percent']);
        $this->assertSame(80, (new Config())->prepareInputForAdd(['sla_escalation_threshold_percent' => 80])['sla_escalation_threshold_percent']);
    }

    /**
     * Regression guard (documented directly in the class): `category_branches` reaches
     * `prepareInput()` in two different shapes — a real array from the wizard form, or the
     * JSON-encoded string `getDefaults()` uses to seed a fresh row via `add()`'s own
     * `prepareInputForAdd()` call. `(array) $string` would wrap the whole string as one bogus
     * element instead of decoding it — a fresh install would silently get zero branches selected.
     */
    public function testPrepareInputAcceptsCategoryBranchesAsBothARealArrayAndAJsonString(): void
    {
        $fromArray = (new Config())->prepareInputForAdd(['category_branches' => ['it', 'rh', 'bogus']]);
        $this->assertSame(['it', 'rh'], json_decode($fromArray['category_branches'], true));

        $fromJsonString = (new Config())->prepareInputForAdd(['category_branches' => json_encode(['it', 'rh'])]);
        $this->assertSame(['it', 'rh'], json_decode($fromJsonString['category_branches'], true));
    }

    public function testPrepareInputAcceptsStateNamesAsBothARealArrayAndAJsonString(): void
    {
        $fromArray = (new Config())->prepareInputForAdd(['state_names' => ['Externe', 'Pas réel']]);
        $this->assertSame(['Externe'], json_decode($fromArray['state_names'], true));

        $fromJsonString = (new Config())->prepareInputForAdd(['state_names' => json_encode(['Externe'])]);
        $this->assertSame(['Externe'], json_decode($fromJsonString['state_names'], true));
    }

    public function testPrepareInputFallsBackToDefaultLdapGroupTemplateWhenPlaceholderIsMissing(): void
    {
        $input = (new Config())->prepareInputForAdd(['ldap_rights_group_template' => 'NoPlaceholderHere']);
        $this->assertSame('GLPI_{ENTITY}', $input['ldap_rights_group_template']);

        $input = (new Config())->prepareInputForAdd(['ldap_rights_group_template' => 'CUSTOM_{ENTITY}_GROUP']);
        $this->assertSame('CUSTOM_{ENTITY}_GROUP', $input['ldap_rights_group_template']);
    }

    public function testPrepareInputFallsBackToAdminForAnUnrecognizedLdapProfile(): void
    {
        $this->assertSame('Admin', (new Config())->prepareInputForAdd(['ldap_rights_profile' => 'Not-A-Real-Profile'])['ldap_rights_profile']);
        $this->assertSame('Supervisor', (new Config())->prepareInputForAdd(['ldap_rights_profile' => 'Supervisor'])['ldap_rights_profile']);
    }

    public function testPrepareInputRejectsANonHttpsGeocodingEndpoint(): void
    {
        $input = (new Config())->prepareInputForAdd(['location_geocoding_endpoint' => 'http://insecure.example.test']);
        $this->assertSame('https://nominatim.openstreetmap.org', $input['location_geocoding_endpoint']);
    }

    public function testPrepareInputTrimsTrailingSlashFromAValidGeocodingEndpoint(): void
    {
        $input = (new Config())->prepareInputForAdd(['location_geocoding_endpoint' => '  https://geocode.example.test/  ']);
        $this->assertSame('https://geocode.example.test', $input['location_geocoding_endpoint']);
    }

    public function testPrepareInputAllowsAnEmptyNativePaletteButRejectsAnUnknownOne(): void
    {
        $this->assertSame('', (new Config())->prepareInputForAdd(['native_palette' => ''])['native_palette']);
        $this->assertSame('', (new Config())->prepareInputForAdd(['native_palette' => 'not-a-real-palette'])['native_palette']);
        $this->assertSame('dark', (new Config())->prepareInputForAdd(['native_palette' => 'dark'])['native_palette']);
    }

    public function testPrepareInputCastsGenericBooleanTogglesAcrossBothForeachGroups(): void
    {
        // One representative field from each of the two boolean-cast foreach lists in prepareInput().
        foreach (['notifications_enabled', 'task_categories_enabled', 'fire_safety_assets_enabled'] as $field) {
            $this->assertSame(1, (new Config())->prepareInputForAdd([$field => '1'])[$field]);
            $this->assertSame(0, (new Config())->prepareInputForAdd([$field => ''])[$field]);
        }
    }

    public function testPrepareInputForUpdateAppliesTheExactSameSanitizationAsAdd(): void
    {
        $addResult = (new Config())->prepareInputForAdd(['entity_mode' => 'bogus']);
        $updateResult = (new Config())->prepareInputForUpdate(['entity_mode' => 'bogus']);

        $this->assertSame($addResult, $updateResult);
    }

    // --- getLatestGithubVersion(): cache-hit paths only — the live GitHub API call itself is left
    // out of this automated suite's scope, same reasoning already documented in RSSFeedBuilderTest
    // / CountryHolidayBuilderTest for a different third-party service this plugin depends on. ---

    private const GITHUB_VERSION_CACHE_KEY = 'plugin_configurationglpiauto_latest_github_version';

    protected function tearDown(): void
    {
        global $GLPI_CACHE;
        $GLPI_CACHE->delete(self::GITHUB_VERSION_CACHE_KEY);
    }

    public function testGetLatestGithubVersionReturnsTheCachedVersionWithoutHittingTheNetwork(): void
    {
        global $GLPI_CACHE;
        $GLPI_CACHE->set(self::GITHUB_VERSION_CACHE_KEY, '9.9.9', DAY_TIMESTAMP);

        $this->assertSame('9.9.9', Config::getLatestGithubVersion());
    }

    /**
     * An empty string is the deliberate "the last live call failed" cache sentinel (see the
     * method's own docblock) — cached as `''`, returned to the caller as `null`.
     */
    public function testGetLatestGithubVersionReturnsNullWhenTheCachedSentinelIsEmpty(): void
    {
        global $GLPI_CACHE;
        $GLPI_CACHE->set(self::GITHUB_VERSION_CACHE_KEY, '', DAY_TIMESTAMP);

        $this->assertNull(Config::getLatestGithubVersion());
    }
}
