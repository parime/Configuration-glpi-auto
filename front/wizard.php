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

use GlpiPlugin\Configurationglpiauto\AssetTypeBuilder;
use GlpiPlugin\Configurationglpiauto\BrandingBuilder;
use GlpiPlugin\Configurationglpiauto\BuildingAssetBuilder;
use GlpiPlugin\Configurationglpiauto\CalendarBuilder;
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\CertificateTypeBuilder;
use GlpiPlugin\Configurationglpiauto\ChangeProblemTemplateBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\CountryHolidayBuilder;
use GlpiPlugin\Configurationglpiauto\DocumentManagementBuilder;
use GlpiPlugin\Configurationglpiauto\EntityAddressBuilder;
use GlpiPlugin\Configurationglpiauto\EntityBuilder;
use GlpiPlugin\Configurationglpiauto\FieldUnicityBuilder;
use GlpiPlugin\Configurationglpiauto\FireSafetyAssetBuilder;
use GlpiPlugin\Configurationglpiauto\FollowupLibraryBuilder;
use GlpiPlugin\Configurationglpiauto\GeneralSettingsBuilder;
use GlpiPlugin\Configurationglpiauto\HelpdeskFormBuilder;
use GlpiPlugin\Configurationglpiauto\KnowbaseCategoryBuilder;
use GlpiPlugin\Configurationglpiauto\LineOperatorBuilder;
use GlpiPlugin\Configurationglpiauto\LocationBuilder;
use GlpiPlugin\Configurationglpiauto\ManufacturerBuilder;
use GlpiPlugin\Configurationglpiauto\ManufacturerDictionaryBuilder;
use GlpiPlugin\Configurationglpiauto\MarketplaceBuilder;
use GlpiPlugin\Configurationglpiauto\NotificationBrandingBuilder;
use GlpiPlugin\Configurationglpiauto\PaletteBuilder;
use GlpiPlugin\Configurationglpiauto\PhysicalSecurityAssetBuilder;
use GlpiPlugin\Configurationglpiauto\PlanningEventBuilder;
use GlpiPlugin\Configurationglpiauto\ProjectTaskTemplateBuilder;
use GlpiPlugin\Configurationglpiauto\ProjectTaxonomyBuilder;
use GlpiPlugin\Configurationglpiauto\ProjectTemplateBuilder;
use GlpiPlugin\Configurationglpiauto\RecurringTicketLibraryBuilder;
use GlpiPlugin\Configurationglpiauto\RequestTypeTranslationBuilder;
use GlpiPlugin\Configurationglpiauto\RSSFeedBuilder;
use GlpiPlugin\Configurationglpiauto\RuleRightBuilder;
use GlpiPlugin\Configurationglpiauto\SatisfactionSurveyBuilder;
use GlpiPlugin\Configurationglpiauto\ServerAssetBuilder;
use GlpiPlugin\Configurationglpiauto\ServiceCatalogBuilder;
use GlpiPlugin\Configurationglpiauto\SlaBuilder;
use GlpiPlugin\Configurationglpiauto\SoftwareLicenseTypeBuilder;
use GlpiPlugin\Configurationglpiauto\SolutionLibraryBuilder;
use GlpiPlugin\Configurationglpiauto\StateBuilder;
use GlpiPlugin\Configurationglpiauto\SupportTierBuilder;
use GlpiPlugin\Configurationglpiauto\TagBuilder;
use GlpiPlugin\Configurationglpiauto\TaskCategoryBuilder;
use GlpiPlugin\Configurationglpiauto\TaskTemplateBuilder;
use GlpiPlugin\Configurationglpiauto\TicketTemplateBuilder;
use GlpiPlugin\Configurationglpiauto\UserCategoryBuilder;
use GlpiPlugin\Configurationglpiauto\ValidationRoutingBuilder;
use GlpiPlugin\Configurationglpiauto\ValidationTemplateBuilder;
use GlpiPlugin\Configurationglpiauto\VehicleAssetBuilder;
use GlpiPlugin\Configurationglpiauto\VipBuilder;
use GlpiPlugin\Configurationglpiauto\WaitReasonBuilder;

/**
 * Validates an uploaded entity logo and returns it as a `data:` URI, or null if missing/invalid.
 * `getimagesize()` reads the actual image header rather than trusting the browser-supplied MIME
 * type. SVG is deliberately not in the allow-list — it can carry an embedded `<script>`, and while
 * that wouldn't execute rendered as a CSS `background-image`, excluding it removes the question
 * entirely rather than relying on that distinction being reliable across browsers.
 *
 * @param array{error?: int, size?: int, tmp_name?: string} $file One entry of $_FILES.
 */
function buildEntityLogoDataUri(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    // 1 MB cap: this ends up base64-encoded (~33% larger) inside custom_css_code, a plain text
    // field — keeps the entity row (and every page load that renders this CSS) reasonably sized.
    if (($file['size'] ?? 0) > 1_000_000 || ($file['tmp_name'] ?? '') === '') {
        return null;
    }

    $info = @getimagesize($file['tmp_name']);
    $allowedMimes = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
    if ($info === false || !in_array($info['mime'], $allowedMimes, true)) {
        return null;
    }

    $data = file_get_contents($file['tmp_name']);
    if ($data === false) {
        return null;
    }

    return 'data:' . $info['mime'] . ';base64,' . base64_encode($data);
}

/**
 * Validates a GPS coordinate/altitude typed or auto-filled on the Lieux step (`ajax/geocode.php`
 * normally fills latitude/longitude from Nominatim, but the fields stay plain editable text
 * inputs, so a hand-typed or pasted value is just as possible; altitude is always manual, no
 * geocoding service returns elevation) — same "trust nothing free-text" reasoning already applied
 * to `branding_primary_color`/`native_palette`/`location_geocoding_endpoint`. No digit-count cap
 * before the decimal point: latitude/longitude never exceed 3 digits by definition, but altitude
 * routinely does (a 2000m mountain site is a 4-digit value) — the `$max` range check is what
 * actually bounds each field, not the string shape. Returns '' (silently dropped by the caller)
 * rather than throwing: same "never block finishing the wizard over an optional field" posture as
 * everywhere else in this file.
 */
function sanitizeCoordinate(string $value, float $max): string
{
    $value = trim($value);
    if ($value === '' || !preg_match('/^-?\d+(\.\d+)?$/', $value) || abs((float) $value) > $max) {
        return '';
    }

    return $value;
}

/**
 * Collects every `location_<field>_<path>` field the "Lieux" step's JS may have submitted, for
 * *any* node of the entity tree (not just the top-level ones) — `<path>` is the node's own
 * root-to-node child-index chain joined by `-` (e.g. `"1-0"`), the same encoding
 * `LocationBuilder::buildNode()` computes while walking `Config::getEntityTree()`, so a path here
 * always lines up with the exact node it was typed against. Scans `$_POST` directly rather than
 * looping over a known list of paths: unlike `entity_color_N`/`entity_logo_N` (always exactly one
 * entry per top-level node), nothing on this side knows in advance which paths exist deeper in the
 * tree — only the wizard's own JS, which only ever renders inputs for nodes an admin has actually
 * expanded.
 *
 * @return array<string, array<string, string>> Path => non-empty fields only (a node with nothing
 *         filled in has no entry at all, matching `LocationBuilder`'s "no data, no Location" rule).
 */
function collectLocationDataFromPost(): array
{
    $textFields = ['address', 'postcode', 'town', 'state', 'country', 'building', 'room', 'code', 'alias', 'comment'];
    $coordinateFields = ['latitude' => 90.0, 'longitude' => 180.0, 'altitude' => 10_000.0];

    $byPath = [];
    foreach ($_POST as $key => $value) {
        foreach ($textFields as $field) {
            $prefix = 'location_' . $field . '_';
            if (str_starts_with($key, $prefix)) {
                $sanitized = trim((string) $value);
                if ($sanitized !== '') {
                    $byPath[substr($key, strlen($prefix))][$field] = $sanitized;
                }
                continue 2;
            }
        }
        foreach ($coordinateFields as $field => $max) {
            $prefix = 'location_' . $field . '_';
            if (str_starts_with($key, $prefix)) {
                $sanitized = sanitizeCoordinate((string) $value, $max);
                if ($sanitized !== '') {
                    $byPath[substr($key, strlen($prefix))][$field] = $sanitized;
                }
            }
        }
    }

    return $byPath;
}

/**
 * Collects every `entity_comms_<field>_<path>` field — `Entity`'s own `phonenumber`/`fax`/
 * `website`/`email`, fields `Location` has no equivalent of, so they can't be folded into
 * `collectLocationDataFromPost()`'s reused address data. Same path-based scan, only rendered by
 * the wizard's JS when `entity_native_address_enabled` is checked.
 *
 * @return array<string, array{phonenumber?: string, fax?: string, website?: string, email?: string}>
 */
function collectEntityCommsFromPost(): array
{
    $byPath = [];
    foreach (['phonenumber', 'fax', 'website', 'email'] as $field) {
        $prefix = 'entity_comms_' . $field . '_';
        foreach ($_POST as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $sanitized = trim((string) $value);
                if ($sanitized !== '') {
                    $byPath[substr($key, strlen($prefix))][$field] = $sanitized;
                }
            }
        }
    }

    return $byPath;
}

/**
 * Collects every `location_children_<path>` hidden field the "Lieux" step's JS may have submitted
 * — one JSON blob per entity path, holding whatever purely manual sub-locations (building/floor/
 * room, no entity of their own) the admin freely added/removed via that path's own tree editor.
 * Unlike `collectLocationDataFromPost()` (fixed, statically-known field set per path), this tree's
 * *shape* is entirely admin-driven, so it's serialized as JSON client-side rather than a a growing
 * set of flat named inputs — same reasoning `_entity_structure_fields.html.twig` already uses for
 * `entity_tree_json`.
 *
 * Recursively re-sanitizes every node server-side rather than trusting the submitted JSON shape:
 * unknown keys dropped, coordinates range-checked via `sanitizeCoordinate()`, a node with no name
 * kept (its `name` becomes '', `LocationBuilder` itself skips nameless nodes) so a malformed/
 * tampered blob never crashes the wizard, only silently produces fewer locations.
 *
 * @return array<string, array<int, array{name: string, fields: array<string, string>, children: array}>>
 */
function collectLocationChildrenFromPost(): array
{
    $textFields = ['address', 'postcode', 'town', 'state', 'country', 'building', 'room', 'code', 'alias', 'comment'];
    $coordinateFields = ['latitude' => 90.0, 'longitude' => 180.0, 'altitude' => 10_000.0];

    $sanitizeNode = function (array $node) use (&$sanitizeNode, $textFields, $coordinateFields): array {
        $fields = is_array($node['fields'] ?? null) ? $node['fields'] : [];
        $sanitizedFields = [];
        foreach ($textFields as $field) {
            $value = trim((string) ($fields[$field] ?? ''));
            if ($value !== '') {
                $sanitizedFields[$field] = $value;
            }
        }
        foreach ($coordinateFields as $field => $max) {
            $value = sanitizeCoordinate((string) ($fields[$field] ?? ''), $max);
            if ($value !== '') {
                $sanitizedFields[$field] = $value;
            }
        }

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];

        return [
            'name' => trim((string) ($node['name'] ?? '')),
            'fields' => $sanitizedFields,
            'children' => array_values(array_map($sanitizeNode, array_filter($children, 'is_array'))),
        ];
    };

    $byPath = [];
    foreach ($_POST as $key => $value) {
        $prefix = 'location_children_';
        if (!str_starts_with($key, $prefix) || !is_string($value)) {
            continue;
        }
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            continue;
        }
        $byPath[substr($key, strlen($prefix))] = array_values(array_map($sanitizeNode, array_filter($decoded, 'is_array')));
    }

    return $byPath;
}

/**
 * Read-only environment checks specific to what *this wizard* is about to do — GLPI's own
 * installer already validated its own PHP/MySQL version requirements before this plugin could
 * even run, so re-checking those would be pointless duplication. Scoped instead to the two things
 * this plugin's own file-writing features need and that genuinely vary by hosting environment
 * (confirmed the hard way this session: a permissions slip on `GLPI_CACHE_DIR` after an
 * out-of-band `rm -rf` produced a site-wide 500, not a wizard-specific failure). Informational
 * only — never blocks continuing, matches every other "starting point, not a decision" toggle in
 * this wizard.
 *
 * @return array<int, array{label: string, ok: bool, hint: string}>
 */
function checkEnvironmentPrerequisites(): array
{
    return [
        [
            'label' => __('Dossier des palettes personnalisées inscriptible', 'configurationglpiauto'),
            'ok' => is_dir(GLPI_THEMES_DIR) && is_writable(GLPI_THEMES_DIR),
            'hint' => sprintf(__('nécessaire à la palette personnalisée (étape 17) — vérifiez les droits sur %s', 'configurationglpiauto'), GLPI_THEMES_DIR),
        ],
        [
            'label' => __('Dossier de cache GLPI inscriptible', 'configurationglpiauto'),
            'ok' => is_dir(GLPI_CACHE_DIR) && is_writable(GLPI_CACHE_DIR),
            'hint' => sprintf(__('nécessaire au bon fonctionnement général de GLPI — vérifiez les droits sur %s', 'configurationglpiauto'), GLPI_CACHE_DIR),
        ],
        [
            'label' => __('Extension GD ou Imagick disponible', 'configurationglpiauto'),
            'ok' => extension_loaded('gd') || extension_loaded('imagick'),
            'hint' => __('utile pour valider les logos uploadés (étape 17) — ni l\'une ni l\'autre n\'est chargée', 'configurationglpiauto'),
        ],
    ];
}

Session::checkRight(Config::$rightname, READ);

if (isset($_POST['finish'])) {
    Session::checkRight(Config::$rightname, UPDATE);

    $config = Config::getConfig();
    $config->update($_POST + ['id' => $config->getID()]);

    $created = (new EntityBuilder())->build($config);
    $entityIds = EntityBuilder::topEntityIds($created) ?: [0];

    // One pass over the top-level nodes (= MSP clients), pairing each EntityBuilder result with
    // its matching entity_tree node (same order/index) to check for a per-client calendar/SLA
    // override (Config::sanitizeTree()'s `settings.calendar`/`settings.sla`). No override on a
    // node → falls back to the plugin-wide shared calendar/SLA, built once and reused (lazy) —
    // same net effect as before this existed. Mono-entité/empty tree ($created empty) has no tree
    // node to pair with, so it always uses the shared path against the root entity, unchanged.
    $tree = $config->getEntityTree();
    $calendarBuilder = new CalendarBuilder();
    $slaBuilder = new SlaBuilder();

    // Collected here (rather than after this loop, where it used to live) so the calendar-building
    // loop below can already tell, per top-level path, whether a country was explicitly typed —
    // see the "Fork a country-specific calendar" block inside that loop.
    $locationDataByPath = !empty($config->fields['locations_enabled']) ? collectLocationDataFromPost() : [];

    // Built once, shared by every client unless overridden below — a client's own
    // settings.escalation only ever changes whether/how escalation applies, never the group
    // identities themselves (see SupportTierBuilder's docblock on that scope choice).
    $tierGroupIds = (new SupportTierBuilder())->build($config) ?: null;

    // Built eagerly (not lazily on first use) so every client that falls back to the shared
    // calendar/SLA gets the *same* pairing regardless of which order clients are processed in —
    // build() is idempotent and a cheap no-op when calendar_enabled/sla_enabled is off.
    $sharedCalendarId = $calendarBuilder->build($config);
    $sharedSlaIds = $slaBuilder->build(
        $config,
        $sharedCalendarId,
        $tierGroupIds,
        !empty($config->fields['escalation_auto_n1_n2']),
        !empty($config->fields['escalation_auto_n2_n3'])
    );

    $calendarMap = [];
    $slaMap = [];
    $entityIdToTierGroupIds = [];
    $perClientCount = 0;
    $countryCalendarForkCount = 0;

    foreach (($created ?: [['name' => '', 'entities_id' => 0]]) as $i => $result) {
        $calendarOverride = $tree[$i]['settings']['calendar'] ?? null;
        $slaOverride = $tree[$i]['settings']['sla'] ?? null;
        $escalationOverride = $tree[$i]['settings']['escalation'] ?? null;
        if ($calendarOverride !== null || $slaOverride !== null || $escalationOverride !== null) {
            $perClientCount++;
        }

        $calendarId = $calendarOverride !== null
            ? $calendarBuilder->buildFromOverride($result['name'], $calendarOverride)
            : $sharedCalendarId;

        // One calendar *per country* (not per site) whenever this top-level entity's own Location
        // has an explicitly typed country — real bug reported by the user: a shared calendar used
        // by every entity meant a German site's public holidays landed on the exact same calendar
        // object as the French sites', invisibly mixed in together, and re-checking the wrong
        // (GLPI-native "Default") calendar made it look like nothing had been created at all. Named
        // by country alone ("Horaires — <Pays>"), not "<site> — <Pays>": every site sharing the same
        // country reuses the *same* calendar object (buildFromOverride() is idempotent by name), on
        // explicit user request ("je veux un calendrier par pays") rather than one calendar per
        // site with the country just appended to its name. Whichever site is processed first for a
        // given country determines that shared calendar's hours — same "first submission wins,
        // idempotent on name" convention this class already uses for the plugin-wide shared
        // calendar, not a new inconsistency.
        $explicitCountry = trim((string) ($locationDataByPath[(string) $i]['country'] ?? ''));
        if ($calendarId !== null && $explicitCountry !== '') {
            $baseSettings = $calendarOverride ?? [
                'enabled' => true,
                'days' => $config->getCalendarDays(),
                'begin' => (string) ($config->fields['calendar_begin'] ?? '08:00'),
                'end' => (string) ($config->fields['calendar_end'] ?? '18:00'),
                'dayHours' => $config->getCalendarDayHours(),
                'lunchBreakEnabled' => !empty($config->fields['calendar_lunch_break_enabled']),
                'lunchBegin' => (string) ($config->fields['calendar_lunch_begin'] ?? '12:00'),
                'lunchEnd' => (string) ($config->fields['calendar_lunch_end'] ?? '13:00'),
            ];
            $countryCalendarId = $calendarBuilder->buildFromOverride($explicitCountry, $baseSettings);
            if ($countryCalendarId !== null) {
                $calendarId = $countryCalendarId;
                $countryCalendarForkCount++;
            }
        }

        if ($calendarId !== null) {
            $calendarMap[$result['entities_id']] = $calendarId;
        }

        // A client override can only ever narrow escalation (opt this client out, or change which
        // hops are automatic) — it never invents tier groups of its own, see SupportTierBuilder.
        $clientTierGroupIds = $escalationOverride !== null && empty($escalationOverride['enabled']) ? null : $tierGroupIds;
        $clientAutoN1N2 = $escalationOverride !== null ? !empty($escalationOverride['auto_n1_n2']) : !empty($config->fields['escalation_auto_n1_n2']);
        $clientAutoN2N3 = $escalationOverride !== null ? !empty($escalationOverride['auto_n2_n3']) : !empty($config->fields['escalation_auto_n2_n3']);
        $entityIdToTierGroupIds[$result['entities_id']] = $clientTierGroupIds;

        $slaIds = $slaOverride !== null
            ? $slaBuilder->buildFromOverride(
                $result['name'],
                $slaOverride,
                $calendarId,
                !empty($config->fields['sla_escalation_enabled']),
                (int) ($config->fields['sla_escalation_threshold_percent'] ?? 75),
                $clientTierGroupIds,
                $clientAutoN1N2,
                $clientAutoN2N3
            )
            : $sharedSlaIds;
        if ($slaIds !== null) {
            $slaMap[$result['entities_id']] = $slaIds;
        }
    }

    if ($calendarMap !== []) {
        $calendarBuilder->assignMap($calendarMap);
    }
    if ($slaMap !== []) {
        $slaBuilder->assignMap($slaMap, $tierGroupIds, $entityIdToTierGroupIds);
    }

    $olaBuilt = false;
    foreach ($slaMap as $slaIdsByPriority) {
        foreach ($slaIdsByPriority as $ids) {
            if ($ids['ola_tto'] !== null) {
                $olaBuilt = true;
                break 2;
            }
        }
    }

    $categoriesCreated = (new CategoryBuilder())->build($config);
    // Runs right after CategoryBuilder: triggered by the same "Flotte Automobile" branch checkbox,
    // no dedicated toggle of its own.
    $vehicleAssetCreated = (new VehicleAssetBuilder())->build($config);
    $serverAssetCreated = (new ServerAssetBuilder())->build($config);
    $buildingAssetCreated = (new BuildingAssetBuilder())->build($config);
    // Unlike the three builders above, each also needs its own dedicated toggle checked (not just
    // the branch) — see FireSafetyAssetBuilder/PhysicalSecurityAssetBuilder docblocks for why.
    $fireSafetyAssetCreated = (new FireSafetyAssetBuilder())->build($config);
    $physicalSecurityAssetCreated = (new PhysicalSecurityAssetBuilder())->build($config);
    $servicesCreated = (new ServiceCatalogBuilder())->build($config);
    $statesCreated = (new StateBuilder())->build($config);
    $waitReasonsCreated = (new WaitReasonBuilder())->build($config);
    $ruleRightBuilder = new RuleRightBuilder();
    $ldapRulesCreated = $ruleRightBuilder->build($config);
    $ldapFunctionRulesCreated = $ruleRightBuilder->buildFunctionRights($config->getLdapFunctionRights());
    $taskCategoriesCreated = (new TaskCategoryBuilder())->build($config);
    // Runs after TaskCategoryBuilder: resolves task categories by name lookup.
    $taskTemplatesCreated = (new TaskTemplateBuilder())->build($config);
    $solutionTemplatesCreated = (new SolutionLibraryBuilder())->build($config);
    $followupTemplatesCreated = (new FollowupLibraryBuilder())->build($config);
    $validationTemplatesCreated = (new ValidationTemplateBuilder())->build($config);
    // Runs after EntityBuilder: LocationBuilder resolves entities by name lookup to scope each
    // location it actually creates. $locationDataByPath itself was already collected further above
    // (needed earlier, by the calendar-building loop's per-country fork).
    $locationChildrenByPath = !empty($config->fields['locations_enabled']) ? collectLocationChildrenFromPost() : [];
    $locationsCreated = (new LocationBuilder())->build($config, $locationDataByPath, $locationChildrenByPath);
    // Scans only the top-level Location panels' own country field, not child locations' (bâtiment/
    // étage/salle very rarely differ from their parent's country). Keys preserved (not
    // array_column(), which would re-index 0..n and lose the path) — CountryHolidayBuilder needs
    // the path to resolve each country to the right calendar below.
    $countryByPath = array_filter(array_map(static fn (array $d) => $d['country'] ?? null, $locationDataByPath));
    // Every top-level client/site with no country typed for its own path (locations disabled
    // entirely, or the field just left blank) defaults to France — same "automatic per-country
    // closures" request that removed the old calendar_holidays_enabled checkbox (see
    // CountryHolidayBuilder's docblock): the common case (no address ever entered) should still
    // get French public holidays out of the box, not none at all just because Locations wasn't
    // used. A path that *did* type a real (even if unrecognized) country is left alone — an admin
    // who explicitly said "Germany" never gets silently defaulted back to France.
    $topLevelCount = $created !== [] ? count($created) : 1;
    for ($i = 0; $i < $topLevelCount; $i++) {
        $path = (string) $i;
        if (!isset($countryByPath[$path])) {
            $countryByPath[$path] = 'France';
        }
    }
    // Each path's calendar is its top-level ancestor's (a path is root-to-node indices joined by
    // "-", e.g. "1-0" — see LocationBuilder's own docblock); sub-entities inherit their calendar
    // from that ancestor (Entity::CONFIG_PARENT), so resolving down to it is enough regardless of
    // how deep the location itself is nested. $created is empty in mono-entité/no-tree mode, same
    // fallback to entity 0 as the calendar-building loop above.
    $calendarIdByPath = [];
    foreach (array_keys($countryByPath) as $path) {
        $topEntityId = $created[(int) explode('-', $path, 2)[0]]['entities_id'] ?? 0;
        if (isset($calendarMap[$topEntityId])) {
            $calendarIdByPath[$path] = $calendarMap[$topEntityId];
        }
    }
    $countryHolidaysCreated = (new CountryHolidayBuilder())->build($config, $countryByPath, $calendarIdByPath);
    $satisfactionSurveyCreated = (new SatisfactionSurveyBuilder())->build($config);
    $vipGroupCreated = (new VipBuilder())->build($config);
    $tagsCreated = (new TagBuilder())->build($config);
    $validationRoutingCreated = (new ValidationRoutingBuilder())->build($config);
    // Reuses $locationDataByPath (same physical address, no reason to type it twice) plus its own
    // phonenumber/fax/website/email fields, with no Location equivalent.
    $entityCommsByPath = !empty($config->fields['entity_native_address_enabled']) ? collectEntityCommsFromPost() : [];
    $entityAddressesApplied = (new EntityAddressBuilder())->build($config, $locationDataByPath, $entityCommsByPath);
    $userCategoriesCreated = (new UserCategoryBuilder())->build($config);
    $fieldUnicityRulesCreated = (new FieldUnicityBuilder())->build($config);
    $rssFeedsCreated = (new RSSFeedBuilder())->build($config);
    // Never stored in $config (our own table has no field-level encryption) — read straight from
    // POST, forwarded directly to GLPI core's own encrypted config store.
    $marketplaceRegistrationSaved = (new MarketplaceBuilder())->build((string) ($_POST['glpi_network_registration_key'] ?? ''));
    $manufacturersCreated = (new ManufacturerBuilder())->build($config);
    $manufacturerDictionaryCreated = (new ManufacturerDictionaryBuilder())->build($config);
    $lineOperatorsCreated = (new LineOperatorBuilder())->build($config);
    $assetTypesCreated = (new AssetTypeBuilder())->build($config);
    $softwareLicenseTypesCreated = (new SoftwareLicenseTypeBuilder())->build($config);
    $certificateTypesCreated = (new CertificateTypeBuilder())->build($config);
    $recurringTicketLibraryCreated = (new RecurringTicketLibraryBuilder())->build($config);
    $kbCategoriesCreated = (new KnowbaseCategoryBuilder())->build($config);
    $documentManagementCreated = (new DocumentManagementBuilder())->build($config);
    $planningEventsCreated = (new PlanningEventBuilder())->build($config);
    $projectTaxonomyCreated = (new ProjectTaxonomyBuilder())->build($config);
    // Runs after ProjectTaxonomyBuilder: resolves project task types by name lookup.
    $projectTaskTemplatesCreated = (new ProjectTaskTemplateBuilder())->build($config);
    // Runs after ProjectTaxonomyBuilder: resolves project/task types by name lookup.
    $projectTemplatesCreated = (new ProjectTemplateBuilder())->build($config);
    $requestTypeTranslationsCreated = (new RequestTypeTranslationBuilder())->build($config);

    // The login screen (no active session yet) falls back to the *root* entity's custom CSS
    // (confirmed in GLPI core: Glpi\Application\View\Extension\FrontEndAssetsExtension::customCss()
    // reads $_SESSION['glpiactive_entity'] when set, entity 0 otherwise) — so without this, the
    // color chosen here would never reach the login page in any multi-entity mode, since
    // $entityIds only lists the top-level *client* entities there, never entity 0 itself. Only
    // added outside MSP mode: an MSP's unauthenticated login page has no single "the" client color
    // to show, leaking one client's branding there would be wrong, not just incomplete.
    $colorEntityIds = $entityIds;
    if ($config->fields['entity_mode'] !== Config::MODE_MULTI_MSP && !in_array(0, $colorEntityIds, true)) {
        $colorEntityIds[] = 0;
    }

    $brandingBuilder = new BrandingBuilder();
    $perClientColorsCreated = 0;
    // Per-client color only makes sense once there's more than one top-level entity to actually
    // differentiate ($entityIds === [0] in mono-entité/empty-tree, same guard EntityLogos already
    // relies on implicitly through its own per-node panel loop). The root entity (login-page
    // fallback, added to $colorEntityIds above outside MSP mode) is deliberately never part of
    // $entityIds itself, so it keeps the *shared* color below regardless of this toggle — an
    // unauthenticated login page has no single "the" client color to show any more than it did
    // before this feature existed.
    if (!empty($config->fields['branding_per_client_enabled']) && $entityIds !== [0]) {
        $entityIdToColor = [];
        foreach ($entityIds as $i => $entityId) {
            $color = (string) ($_POST['entity_color_' . $i] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $entityIdToColor[$entityId] = $color;
            }
        }
        $perClientColorsCreated = $brandingBuilder->applyPerClientColors($entityIdToColor);

        $sharedOnlyIds = array_diff($colorEntityIds, $entityIds);
        $brandingApplied = $sharedOnlyIds !== [] && $brandingBuilder->apply($config, $sharedOnlyIds);
    } else {
        $brandingApplied = $brandingBuilder->apply($config, $colorEntityIds);
    }

    $logosCreated = 0;
    $entityIdToLogoDataUri = [];
    if (!empty($config->fields['entity_logos_enabled'])) {
        foreach ($entityIds as $i => $entityId) {
            $dataUri = buildEntityLogoDataUri((array) ($_FILES['entity_logo_' . $i] ?? []));
            if ($dataUri !== null) {
                $entityIdToLogoDataUri[$entityId] = $dataUri;
            }
        }
        $logosCreated = $brandingBuilder->applyLogos($entityIdToLogoDataUri);
    } else {
        // Active undo, same bug class as BrandingBuilder::apply()'s own fix: unchecking "Ajouter un
        // logo par entité" must remove a logo block a previous run already wrote, not just stop
        // re-writing it.
        $brandingBuilder->removeLogos($entityIds);
    }

    // Reuses whatever color/logo were already collected above for the UI — root entity's own logo
    // if one was uploaded (matches the login-page fallback logic just above), the shared primary
    // color (not a per-client one: GLPI notification templates aren't entity-scoped the way
    // custom_css_code is, one shared set of branded templates for the whole instance).
    $notificationBrandingCreated = (new NotificationBrandingBuilder())->apply(
        $config,
        (string) ($config->fields['branding_primary_color'] ?? '#206bc4'),
        $entityIdToLogoDataUri[0] ?? null,
    );

    $paletteApplied = (new PaletteBuilder())->apply($config);

    $generalSettingsApplied = (new GeneralSettingsBuilder())->apply($config);
    $ticketTemplatesApplied = (new TicketTemplateBuilder())->apply($config);
    $helpdeskFormApplied = (new HelpdeskFormBuilder())->apply($config);
    $changeProblemTemplatesApplied = (new ChangeProblemTemplateBuilder())->apply($config);

    $messages = [];
    $messages[] = empty($created)
        ? __('Aucune entité à créer (mode mono-entité, ou arborescence vide).', 'configurationglpiauto')
        : sprintf(__('Structure créée : %s.', 'configurationglpiauto'), EntityBuilder::describe($created));
    if ($calendarMap !== []) {
        $messages[] = __('Calendrier créé et assigné.', 'configurationglpiauto');
    }
    if ($countryCalendarForkCount > 0) {
        $messages[] = sprintf(
            __('%d site(s) rattaché(s) à un calendrier dédié à leur pays (partagé entre sites du même pays).', 'configurationglpiauto'),
            $countryCalendarForkCount
        );
    }
    if ($slaMap !== []) {
        $messages[] = __('SLA créés et assignés.', 'configurationglpiauto');
    }
    if ($olaBuilt) {
        $messages[] = __('OLA (engagements internes) créés et assignés.', 'configurationglpiauto');
    }
    if ($tierGroupIds !== null) {
        $messages[] = __('Groupes de support N1/N2/N3 créés, escalade automatique configurée.', 'configurationglpiauto');
    }
    if ($perClientCount > 0) {
        $messages[] = sprintf(__('%d client(s) avec des réglages personnalisés.', 'configurationglpiauto'), $perClientCount);
    }
    if ($categoriesCreated > 0) {
        $messages[] = sprintf(__('%d catégories de tickets créées.', 'configurationglpiauto'), $categoriesCreated);
    }
    if ($vehicleAssetCreated > 0) {
        $messages[] = __('Actif personnalisé "Véhicule" créé (branche Flotte Automobile).', 'configurationglpiauto');
    }
    if ($serverAssetCreated > 0) {
        $messages[] = __('Actif personnalisé "Serveur" créé (branche IT & SI).', 'configurationglpiauto');
    }
    if ($buildingAssetCreated > 0) {
        $messages[] = __('Actif personnalisé "Local" créé (branche Bâtiment).', 'configurationglpiauto');
    }
    if ($fireSafetyAssetCreated > 0) {
        $messages[] = __('Actif personnalisé "Sécurité incendie & premiers secours" créé (branche Bâtiment).', 'configurationglpiauto');
    }
    if ($physicalSecurityAssetCreated > 0) {
        $messages[] = __('Actif personnalisé "Sécurité physique" créé (branche Sécurité & Protection des Personnes).', 'configurationglpiauto');
    }
    if ($servicesCreated > 0) {
        $messages[] = sprintf(__('%d services créés dans le catalogue.', 'configurationglpiauto'), $servicesCreated);
    }
    if ($statesCreated !== []) {
        $messages[] = sprintf(__('%d statuts d\'éléments créés.', 'configurationglpiauto'), count($statesCreated));
    }
    if ($waitReasonsCreated > 0) {
        $messages[] = sprintf(__('%d raisons d\'attente créées.', 'configurationglpiauto'), $waitReasonsCreated);
    }
    if ($brandingApplied) {
        $messages[] = __('Personnalisation graphique appliquée.', 'configurationglpiauto');
    }
    if ($logosCreated > 0) {
        $messages[] = sprintf(__('%d logo(s) d\'entité appliqué(s).', 'configurationglpiauto'), $logosCreated);
    }
    $skippedLogoCount = count($brandingBuilder->getSkippedLogoEntityIds());
    if ($skippedLogoCount > 0) {
        $messages[] = sprintf(
            __('%d logo(s) trop volumineux pour être appliqué(s) — utilisez un fichier plus léger (idéalement sous 40 Ko) ou configurez-le manuellement depuis Configuration > Entités > onglet Général.', 'configurationglpiauto'),
            $skippedLogoCount,
        );
    }
    if ($perClientColorsCreated > 0) {
        $messages[] = sprintf(__('%d couleur(s) par client/site appliquée(s).', 'configurationglpiauto'), $perClientColorsCreated);
    }
    if ($notificationBrandingCreated > 0) {
        $messages[] = sprintf(__('%d modèle(s) de notification personnalisés.', 'configurationglpiauto'), $notificationBrandingCreated);
    }
    if ($paletteApplied) {
        $messages[] = !empty($config->fields['custom_palette_enabled'])
            ? __('Palette GLPI personnalisée créée et définie par défaut.', 'configurationglpiauto')
            : __('Palette GLPI native définie par défaut.', 'configurationglpiauto');
    }
    if ($generalSettingsApplied) {
        $messages[] = __('Réglages généraux GLPI appliqués.', 'configurationglpiauto');
    }
    if ($ticketTemplatesApplied) {
        $messages[] = __('Modèles de tickets créés et assignés aux profils.', 'configurationglpiauto');
    }
    if ($helpdeskFormApplied) {
        $messages[] = __('Champs masqués sur les formulaires de création en libre-service.', 'configurationglpiauto');
    }
    if ($ldapRulesCreated > 0) {
        $messages[] = sprintf(__('%d règle(s) de droits LDAP créées.', 'configurationglpiauto'), $ldapRulesCreated);
    }
    if ($ldapFunctionRulesCreated > 0) {
        $messages[] = sprintf(__('%d règle(s) de droits par fonction créées.', 'configurationglpiauto'), $ldapFunctionRulesCreated);
    }
    if ($taskCategoriesCreated > 0) {
        $messages[] = sprintf(__('%d catégories de tâches créées.', 'configurationglpiauto'), $taskCategoriesCreated);
    }
    if ($taskTemplatesCreated > 0) {
        $messages[] = sprintf(__('%d gabarits de tâche créés.', 'configurationglpiauto'), $taskTemplatesCreated);
    }
    if ($solutionTemplatesCreated > 0) {
        $messages[] = sprintf(__('%d gabarits de solution créés.', 'configurationglpiauto'), $solutionTemplatesCreated);
    }
    if ($followupTemplatesCreated > 0) {
        $messages[] = sprintf(__('%d gabarits de suivis créés.', 'configurationglpiauto'), $followupTemplatesCreated);
    }
    if ($validationTemplatesCreated > 0) {
        $messages[] = sprintf(__('%d gabarits de validation créés.', 'configurationglpiauto'), $validationTemplatesCreated);
    }
    if ($changeProblemTemplatesApplied) {
        $messages[] = __('Modèles de changement et de problème créés et assignés aux profils.', 'configurationglpiauto');
    }
    if ($locationsCreated > 0) {
        $messages[] = sprintf(__('%d lieux créés.', 'configurationglpiauto'), $locationsCreated);
    }
    if ($manufacturersCreated > 0) {
        $messages[] = sprintf(__('%d fabricants créés.', 'configurationglpiauto'), $manufacturersCreated);
    }
    if ($manufacturerDictionaryCreated > 0) {
        $messages[] = sprintf(__('%d règle(s) de dictionnaire fabricant créées.', 'configurationglpiauto'), $manufacturerDictionaryCreated);
    }
    if ($lineOperatorsCreated > 0) {
        $messages[] = sprintf(__('%d opérateurs téléphoniques créés.', 'configurationglpiauto'), $lineOperatorsCreated);
    }
    if ($assetTypesCreated > 0) {
        $messages[] = sprintf(__('%d types de matériel créés.', 'configurationglpiauto'), $assetTypesCreated);
    }
    if ($softwareLicenseTypesCreated > 0) {
        $messages[] = sprintf(__('%d types de licence logicielle créés.', 'configurationglpiauto'), $softwareLicenseTypesCreated);
    }
    if ($certificateTypesCreated > 0) {
        $messages[] = sprintf(__('%d types de certificat créés.', 'configurationglpiauto'), $certificateTypesCreated);
    }
    if ($recurringTicketLibraryCreated > 0) {
        $messages[] = sprintf(__('%d modèles de tickets récurrents créés.', 'configurationglpiauto'), $recurringTicketLibraryCreated);
    }
    if ($kbCategoriesCreated > 0) {
        $messages[] = sprintf(__('%d catégories de base de connaissances créées.', 'configurationglpiauto'), $kbCategoriesCreated);
    }
    if ($documentManagementCreated > 0) {
        $messages[] = sprintf(__('%d rubriques de documents/criticités créées.', 'configurationglpiauto'), $documentManagementCreated);
    }
    if ($planningEventsCreated > 0) {
        $messages[] = sprintf(__('%d catégories/gabarits d\'évènements de planning créés.', 'configurationglpiauto'), $planningEventsCreated);
    }
    if ($projectTaxonomyCreated > 0) {
        $messages[] = sprintf(__('%d types de projet/tâche de projet/statuts de projet créés.', 'configurationglpiauto'), $projectTaxonomyCreated);
    }
    if ($projectTaskTemplatesCreated > 0) {
        $messages[] = sprintf(__('%d gabarits de tâches de projets créés.', 'configurationglpiauto'), $projectTaskTemplatesCreated);
    }
    if ($projectTemplatesCreated > 0) {
        $messages[] = sprintf(__('%d modèle(s) de projet créés, structure de tâches incluse.', 'configurationglpiauto'), $projectTemplatesCreated);
    }
    if ($requestTypeTranslationsCreated > 0) {
        $messages[] = sprintf(__('%d source(s) de demande traduites.', 'configurationglpiauto'), $requestTypeTranslationsCreated);
    }
    if ($entityAddressesApplied > 0) {
        $messages[] = sprintf(__('%d fiche(s) d\'entité complétées avec leur adresse.', 'configurationglpiauto'), $entityAddressesApplied);
    }
    if ($countryHolidaysCreated > 0) {
        $messages[] = sprintf(__('%d jour(s) férié(s) créés et rattachés au(x) calendrier(s) concerné(s).', 'configurationglpiauto'), $countryHolidaysCreated);
    }
    if ($satisfactionSurveyCreated > 0) {
        $messages[] = __('Enquête de satisfaction créée (plugin More satisfaction).', 'configurationglpiauto');
    }
    if ($vipGroupCreated > 0) {
        $messages[] = __('Groupe "VIP" créé et activé (plugin VIP).', 'configurationglpiauto');
    }
    if ($tagsCreated > 0) {
        $messages[] = sprintf(__('%d tag(s) créé(s) (plugin Tag).', 'configurationglpiauto'), $tagsCreated);
    }
    if ($validationRoutingCreated > 0) {
        $messages[] = __('Règle de validation automatique (supérieur hiérarchique) créée.', 'configurationglpiauto');
    }
    if ($userCategoriesCreated > 0) {
        $messages[] = sprintf(__('%d catégories d\'utilisateur créées.', 'configurationglpiauto'), $userCategoriesCreated);
    }
    if ($fieldUnicityRulesCreated > 0) {
        $messages[] = sprintf(__('%d règle(s) d\'unicité de numéro de série créées.', 'configurationglpiauto'), $fieldUnicityRulesCreated);
    }
    if ($rssFeedsCreated > 0) {
        $messages[] = sprintf(__('%d flux RSS ajouté(s).', 'configurationglpiauto'), $rssFeedsCreated);
    }
    if ($marketplaceRegistrationSaved > 0) {
        $messages[] = __('Clé d\'enregistrement GLPI Network enregistrée.', 'configurationglpiauto');
    }
    Session::addMessageAfterRedirect(implode(' ', $messages));

    Html::redirect(ConfigurationProfile::getSearchURL());
}

Html::header(__('Assistant de configuration', 'configurationglpiauto'), $_SERVER['PHP_SELF'], 'config', ConfigurationProfile::class);

$config = Config::getConfig();
$profiles = (new ConfigurationProfile())->find(['is_active' => 1], ['sort_order ASC']);

$profileDefaults = [];
foreach ($profiles as $profile) {
    $profileDefaults[$profile['id']] = ConfigurationProfile::getSuggestedDefaults($profile['type']);
}

$priorityLabels = [];
foreach (Config::PRIORITY_LEVELS as $priority) {
    $priorityLabels[$priority] = CommonITILObject::getPriorityName($priority);
}

\Glpi\Application\View\TemplateRenderer::getInstance()->display('@configurationglpiauto/wizard.html.twig', [
    'prereq_checks'    => checkEnvironmentPrerequisites(),
    'config'           => $config->fields,
    'profiles'         => $profiles,
    'profile_defaults' => $profileDefaults,
    'modes'            => Config::getModes(),
    'max_levels'       => Config::MAX_LEVELS,
    'entity_tree'      => $config->getEntityTree(),
    'ldap_function_rights' => $config->getLdapFunctionRights(),
    'sla_tiers'        => $config->getSlaTiers(),
    'ola_tiers'        => $config->getOlaTiers(),
    'priority_levels'  => Config::PRIORITY_LEVELS,
    'priority_labels'  => $priorityLabels,
    'category_branches' => $config->getCategoryBranches(),
    'categories_preview' => CategoryBuilder::getCategoriesPreview(),
    'fire_safety_assets_preview' => FireSafetyAssetBuilder::getPreview(),
    'physical_security_assets_preview' => PhysicalSecurityAssetBuilder::getPreview(),
    'services_preview' => ServiceCatalogBuilder::getServicesPreview(),
    'states_preview'   => StateBuilder::getStatesPreview(),
    'state_names'      => $config->getStateNames(),
    'state_recommended_names' => StateBuilder::RECOMMENDED_NAMES,
    'wait_reasons_preview' => WaitReasonBuilder::getReasonsPreview(),
    'native_profile_names' => Config::NATIVE_PROFILE_NAMES,
    'native_palettes' => array_map(
        static fn (\Glpi\UI\Theme $theme) => ['key' => $theme->getKey(), 'name' => $theme->getName(), 'is_dark' => $theme->isDarkTheme()],
        \Glpi\UI\ThemeManager::getInstance()->getCoreThemes()
    ),
    'task_categories_preview' => TaskCategoryBuilder::getCategoriesPreview(),
    'task_templates_preview' => TaskTemplateBuilder::getLibraryPreview(),
    'solution_library_preview' => SolutionLibraryBuilder::getLibraryPreview(),
    'followup_library_preview' => FollowupLibraryBuilder::getLibraryPreview(),
    'validation_templates_preview' => ValidationTemplateBuilder::getLibraryPreview(),
    'manufacturers_preview' => ManufacturerBuilder::getManufacturersPreview(),
    'line_operators_preview' => LineOperatorBuilder::getOperatorsPreview(),
    'asset_types_preview' => AssetTypeBuilder::getTypesPreview(),
    'software_license_types_preview' => SoftwareLicenseTypeBuilder::getTypesPreview(),
    'certificate_types_preview' => CertificateTypeBuilder::getTypesPreview(),
    'recurring_ticket_library_preview' => RecurringTicketLibraryBuilder::getLibraryPreview(),
    'document_management_preview' => DocumentManagementBuilder::getPreview(),
    'planning_events_preview' => PlanningEventBuilder::getPreview(),
    'project_taxonomy_preview' => ProjectTaxonomyBuilder::getPreview(),
    'project_task_templates_preview' => ProjectTaskTemplateBuilder::getLibraryPreview(),
    'project_templates_preview' => ProjectTemplateBuilder::getPreview(),
    'request_type_translations_preview' => RequestTypeTranslationBuilder::getPreview(),
    'user_categories_preview' => UserCategoryBuilder::getCategoriesPreview(),
    'field_unicity_rules_preview' => FieldUnicityBuilder::getRulesPreview(),
    'rss_feeds_preview' => RSSFeedBuilder::getFeedsPreview(),
    'marketplace_recommended_plugins' => MarketplaceBuilder::getRecommendedPluginsPreview(),
    // Read straight from GLPI core's own encrypted store, matching the native "Enregistrement"
    // page's own behavior — never mirrored into this plugin's own config table (see MarketplaceBuilder).
    'glpi_network_registration_key' => \GLPINetwork::getRegistrationKey(),
    'satisfaction_plugin_active' => SatisfactionSurveyBuilder::isThirdPartyPluginActive(),
    'vip_plugin_active' => VipBuilder::isThirdPartyPluginActive(),
    'tag_plugin_active' => TagBuilder::isThirdPartyPluginActive(),
    'support_tiers_preview' => SupportTierBuilder::getTiersPreview(),
    'installed_version'     => PLUGIN_CONFIGURATIONGLPIAUTO_VERSION,
    'latest_github_version' => Config::getLatestGithubVersion(),
    'csrf_token'       => Session::getNewCSRFToken(),
]);

Html::footer();
