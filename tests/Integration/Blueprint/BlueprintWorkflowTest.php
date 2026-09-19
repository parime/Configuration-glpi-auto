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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration\Blueprint;

use GlpiPlugin\Configurationglpiauto\Blueprint\BlueprintSerializer;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use PHPUnit\Framework\TestCase;

/**
 * `Config` is the real singleton row this instance's actual live plugin configuration lives in —
 * same discipline as `ConfigTest`: this suite reads the real row (`Config::getConfig()->fields`)
 * to prove the serializer round-trips real data, but never calls `add()`/`update()` on it, so
 * running this suite never leaves the shared instance's own configuration changed.
 * `ConfigurationProfile` rows, on the other hand, are throwaway fixtures purged in `tearDown()`,
 * same convention as `ConfigurationProfileTest::testCanBeAddedToAndReadBackFromItsOwnRealTable`.
 */
final class BlueprintWorkflowTest extends TestCase
{
    private int $profileId = 0;

    protected function tearDown(): void
    {
        if ($this->profileId > 0) {
            (new ConfigurationProfile())->delete(['id' => $this->profileId], true);
        }
    }

    public function testTheSnapshotColumnRoundTripsOnARealConfigurationProfileRow(): void
    {
        $snapshot = json_encode(['format_version' => 1, 'config' => ['entity_mode' => 'mono']]);

        $profile = new ConfigurationProfile();
        $this->profileId = (int) $profile->add(['name' => 'CGA-Blueprint-Test', 'type' => 'custom', 'snapshot' => $snapshot]);

        $this->assertGreaterThan(0, $this->profileId);

        $reloaded = new ConfigurationProfile();
        $this->assertTrue($reloaded->getFromDB($this->profileId));
        $this->assertSame($snapshot, $reloaded->fields['snapshot']);
    }

    public function testANewlyCreatedProfileHasNoSnapshotUntilOneIsSavedIntoIt(): void
    {
        $profile = new ConfigurationProfile();
        $this->profileId = (int) $profile->add(['name' => 'CGA-Blueprint-Test-Empty', 'type' => 'custom']);

        $reloaded = new ConfigurationProfile();
        $reloaded->getFromDB($this->profileId);
        $this->assertEmpty($reloaded->fields['snapshot']);
    }

    /**
     * Exercises the exact export path `save_snapshot` uses (`front/profile.form.php`) against the
     * REAL live `Config` row — read-only, never written back here — and confirms the resulting
     * Blueprint, once re-imported, produces fields that `Config::prepareInputForAdd()` (pure
     * sanitizer, no DB access — same technique `ConfigTest` already uses to exercise it safely)
     * accepts without rejecting or corrupting them.
     */
    public function testExportOfTheRealLiveConfigProducesAReimportableBlueprint(): void
    {
        $liveFields = Config::getConfig()->fields;

        $exported = BlueprintSerializer::export($liveFields, 'Instantané live', PLUGIN_CONFIGURATIONGLPIAUTO_VERSION);

        $this->assertSame(1, $exported['format_version']);
        $this->assertArrayNotHasKey('id', $exported['config']);
        $this->assertArrayNotHasKey('configurationprofiles_id', $exported['config']);

        // The ~8 JSON-encoded Config columns must come back as real nested JSON in the exported
        // file, not a JSON string nested inside the JSON string.
        $this->assertIsArray($exported['config']['state_names']);
        $this->assertIsArray($exported['config']['entity_tree']);

        $imported = BlueprintSerializer::import($exported, Config::getDefaults());

        $this->assertSame($liveFields['entity_mode'], $imported['entity_mode']);
        // Re-encoded JSON columns must decode back to the exact same structure the live row had,
        // even though the byte-for-byte string may differ (escaping, key order).
        $this->assertSame(
            json_decode($liveFields['state_names'], true),
            json_decode($imported['state_names'], true)
        );

        // prepareInputForAdd() never touches $this->fields/the DB (confirmed in ConfigTest's own
        // docblock) — safe to run against the live-derived values without writing anything back.
        $sanitized = (new Config())->prepareInputForAdd($imported);
        $this->assertSame($imported['entity_mode'], $sanitized['entity_mode']);
    }

    /**
     * A Blueprint saved on an OLDER version of this plugin (before some `Config` field existed)
     * must not leave that field null/missing once imported — it falls back to today's real default,
     * matching `BlueprintSerializer::import()`'s own contract already covered in the pure unit test,
     * exercised here once more against the real, full-sized `Config::getDefaults()` (~135 keys)
     * rather than a small hand-written fixture.
     */
    public function testImportingAnOlderBlueprintFillsEveryFieldItNeverKnewAboutFromRealDefaults(): void
    {
        $oldBlueprint = [
            'format_version' => BlueprintSerializer::FORMAT_VERSION,
            'config' => ['entity_mode' => Config::MODE_MULTI_MSP],
        ];

        $imported = BlueprintSerializer::import($oldBlueprint, Config::getDefaults());

        $this->assertSame(Config::MODE_MULTI_MSP, $imported['entity_mode']);
        $this->assertSame(Config::getDefaults()['branding_primary_color'], $imported['branding_primary_color']);
        $this->assertSame(Config::getDefaults()['calendar_begin'], $imported['calendar_begin']);
    }

    /**
     * Export sélectif par catégorie (issue #117), exercé contre le vrai `Config` en direct plutôt
     * qu'un petit jeu de données à la main : exporter, filtrer à un seul champ, réimporter — seul
     * ce champ doit provenir du fichier filtré, tout le reste doit retomber sur les vraies valeurs
     * par défaut (jamais sur la valeur réelle non retenue dans le fichier).
     */
    public function testSelectivelyExportedBlueprintOnlyCarriesTheChosenFieldOnReimport(): void
    {
        $liveFields = Config::getConfig()->fields;
        $exported = BlueprintSerializer::export($liveFields, 'Export sélectif', PLUGIN_CONFIGURATIONGLPIAUTO_VERSION);

        $filtered = BlueprintSerializer::filterConfig($exported, ['entity_mode']);
        $this->assertSame(['entity_mode' => $liveFields['entity_mode']], $filtered['config']);

        $imported = BlueprintSerializer::import($filtered, Config::getDefaults());

        $this->assertSame($liveFields['entity_mode'], $imported['entity_mode']);
        $this->assertSame(Config::getDefaults()['branding_primary_color'], $imported['branding_primary_color'], 'A field left out of the selective export must fall back to the real default, never the live value it was never given.');
    }
}
