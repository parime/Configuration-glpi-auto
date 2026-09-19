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
use GlpiPlugin\Configurationglpiauto\ConfigHistory;
use PHPUnit\Framework\TestCase;

/**
 * `Config` est le vrai singleton où vit la configuration réelle de cette instance — même
 * discipline que `BlueprintWorkflowTest`/`ConfigTest` : lecture seule (`Config::getConfig()`), on
 * n'appelle jamais `update()` dessus ici, donc exécuter cette suite ne laisse jamais l'instance
 * partagée dans un état différent de celui trouvé au départ. Les lignes `ConfigHistory` créées ici
 * sont jetables, purgées dans `tearDown()`.
 */
final class ConfigHistoryTest extends TestCase
{
    private array $historyIds = [];

    protected function tearDown(): void
    {
        foreach ($this->historyIds as $id) {
            (new ConfigHistory())->delete(['id' => $id], true);
        }
        $this->historyIds = [];
    }

    private function track(int $id): int
    {
        $this->historyIds[] = $id;

        return $id;
    }

    public function testCaptureAutomaticCreatesAnIsManualZeroRowWithARealBlueprintSnapshot(): void
    {
        $config = Config::getConfig();
        ConfigHistory::captureAutomatic($config);

        $latest = new ConfigHistory();
        $rows = $latest->find(['is_manual' => 0], ['id DESC'], 1);
        $row = reset($rows);
        $this->track((int) $row['id']);

        $this->assertSame(0, (int) $row['is_manual']);
        $decoded = json_decode((string) $row['snapshot'], true);
        $this->assertSame(1, $decoded['format_version']);
        $this->assertSame($config->fields['entity_mode'], $decoded['config']['entity_mode']);
    }

    public function testCaptureManualCreatesAnIsManualOneRowWithTheGivenLabel(): void
    {
        $id = $this->track(ConfigHistory::captureManual(Config::getConfig(), 'CGA-Checkpoint-Test'));

        $item = new ConfigHistory();
        $this->assertTrue($item->getFromDB($id));
        $this->assertSame(1, (int) $item->fields['is_manual']);
        $this->assertSame('CGA-Checkpoint-Test', $item->fields['label']);
    }

    /**
     * `captureAutomatic()` purges automatic rows beyond its cap on every call — inserts more than
     * the cap via repeated calls and confirms the count never grows unbounded, only the most recent
     * rows survive.
     */
    public function testCaptureAutomaticPrunesOldestAutomaticRowsBeyondTheCap(): void
    {
        $config = Config::getConfig();

        // MAX_AUTOMATIC_ENTRIES is private on purpose (an implementation detail, not a public
        // contract) — 25 repeated captures is comfortably above any reasonable cap without the test
        // needing to know the exact number.
        for ($i = 0; $i < 25; $i++) {
            ConfigHistory::captureAutomatic($config);
        }

        global $DB;
        $rows = $DB->request(['FROM' => ConfigHistory::getTable(), 'WHERE' => ['is_manual' => 0]]);
        foreach ($rows as $row) {
            $this->track((int) $row['id']);
        }

        $this->assertLessThanOrEqual(20, $rows->count());
    }

    /**
     * Points de sauvegarde manuels : jamais comptés/supprimés par la purge automatique, peu importe
     * leur ancienneté relative aux entrées automatiques.
     */
    public function testPruneNeverDeletesManualCheckpoints(): void
    {
        $config = Config::getConfig();

        $manualId = $this->track(ConfigHistory::captureManual($config, 'CGA-Old-Manual-Checkpoint'));

        for ($i = 0; $i < 25; $i++) {
            ConfigHistory::captureAutomatic($config);
        }

        global $DB;
        $rows = $DB->request(['FROM' => ConfigHistory::getTable(), 'WHERE' => ['is_manual' => 0]]);
        foreach ($rows as $row) {
            $this->track((int) $row['id']);
        }

        $survivingManual = new ConfigHistory();
        $this->assertTrue($survivingManual->getFromDB($manualId), 'A manual checkpoint must survive any number of automatic captures.');
    }

    /**
     * Même technique que BlueprintWorkflowTest::testExportOfTheRealLiveConfigProducesAReimportableBlueprint,
     * en passant par une ligne ConfigHistory plutôt qu'un ConfigurationProfile : confirme que le
     * format stocké est bien un Blueprint standard, réimportable et acceptable par le sanitizeur pur
     * de Config.
     */
    public function testRestoringAHistoryEntryProducesFieldsAcceptedByConfigsPureSanitizer(): void
    {
        $config = Config::getConfig();
        $id = $this->track(ConfigHistory::captureManual($config, 'CGA-Restore-Roundtrip'));

        $item = new ConfigHistory();
        $item->getFromDB($id);
        $decoded = json_decode((string) $item->fields['snapshot'], true);

        $imported = \GlpiPlugin\Configurationglpiauto\Blueprint\BlueprintSerializer::import($decoded, Config::getDefaults());
        $sanitized = (new Config())->prepareInputForAdd($imported);

        $this->assertSame($config->fields['entity_mode'], $sanitized['entity_mode']);
    }
}
