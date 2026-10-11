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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration\Audit;

use Computer;
use ComputerModel;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\DuplicateDropdownsCheck;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\DuplicateMerger;
use GlpiPlugin\Configurationglpiauto\ManufacturerDictionaryBuilder;
use Manufacturer;
use PHPUnit\Framework\TestCase;

/**
 * Issue #299 : doublons déjà en base, détectés par l'audit et fusionnés en un clic.
 */
final class DuplicateDropdownsCheckTest extends TestCase
{
    private string $brand;

    protected function setUp(): void
    {
        $this->brand = 'Phpunitdup' . mt_rand(100000, 999999);
        // Doublons d'avant le blocage (#298) : on les crée option désactivée.
        $this->setUnicity(false);
    }

    protected function tearDown(): void
    {
        $this->setUnicity(true);
    }

    private function setUnicity(bool $enabled): void
    {
        $config = Config::getConfig();
        $config->update(['id' => $config->getID(), 'dropdown_unicity_enabled' => $enabled ? 1 : 0]);
    }

    private function computer(string $fk, int $id): int
    {
        return (int) (new Computer())->add(['name' => 'PHPUNIT-DUP-' . mt_rand(), 'entities_id' => 0, $fk => $id]);
    }

    public function testBrandKeyIgnoresCaseAccentsPunctuationAndLegalMentions(): void
    {
        $this->assertSame(DuplicateMerger::brandKey('Acer'), DuplicateMerger::brandKey('ACER, Inc.'));
        $this->assertSame(DuplicateMerger::brandKey('Société Générale'), DuplicateMerger::brandKey('SOCIETE GENERALE SA'));
        $this->assertNotSame(DuplicateMerger::brandKey('HP'), DuplicateMerger::brandKey('HPE'));
    }

    public function testDuplicateManufacturersAreReportedThenMergedWithTheirReferences(): void
    {
        $keep = (int) (new Manufacturer())->add(['name' => $this->brand]);
        $twin = (int) (new Manufacturer())->add(['name' => strtoupper($this->brand) . ', Inc.']);
        $pc = $this->computer('manufacturers_id', $twin);

        $findings = (new DuplicateDropdownsCheck())->analyze();
        $this->assertCount(1, $findings);
        $this->assertTrue($findings[0]->fixable);
        $grouped = [];
        foreach (DuplicateMerger::findGroups() as $group) {
            foreach ($group['duplicates'] as $duplicate) {
                $grouped[$duplicate['id']] = $group['keep']['id'];
            }
        }
        $this->assertSame($keep, $grouped[$twin] ?? null, 'Le doublon est rattaché à la plus ancienne entrée.');

        (new DuplicateDropdownsCheck())->fix();

        $computer = new Computer();
        $computer->getFromDB($pc);
        $this->assertSame($keep, (int) $computer->fields['manufacturers_id'], 'Le matériel suit l\'entrée gardée.');
        $this->assertFalse((new Manufacturer())->getFromDB($twin), 'Le doublon est supprimé.');
        $this->assertTrue((new Manufacturer())->getFromDB($keep));
        $this->assertSame([], (new DuplicateDropdownsCheck())->analyze());
    }

    public function testAVariantCreatedBeforeTheDictionaryIsMergedIntoTheBrand(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['manufacturer_dictionary_enabled' => 1]);
        (new ManufacturerDictionaryBuilder())->build($config);
        $hp = new Manufacturer();
        $hpId = $hp->getFromDBByCrit(['name' => 'HP']) ? (int) $hp->getID() : (int) $hp->add(['name' => 'HP']);
        $variant = (int) (new Manufacturer())->add(['name' => 'Hewlett-Packard Development Company, L.P.']);
        $pc = $this->computer('manufacturers_id', $variant);

        DuplicateMerger::mergeAll();

        $computer = new Computer();
        $computer->getFromDB($pc);
        $this->assertSame($hpId, (int) $computer->fields['manufacturers_id']);
        $this->assertFalse((new Manufacturer())->getFromDB($variant));
    }

    public function testDuplicateModelsAreMergedButCloseNamesAreKept(): void
    {
        $keep = (int) (new ComputerModel())->add(['name' => $this->brand . ' X1']);
        $twin = (int) (new ComputerModel())->add(['name' => strtolower($this->brand) . ' x1']);
        $other = (int) (new ComputerModel())->add(['name' => $this->brand . ' X1 Carbon']);
        $pc = $this->computer('computermodels_id', $twin);

        DuplicateMerger::mergeAll();

        $computer = new Computer();
        $computer->getFromDB($pc);
        $this->assertSame($keep, (int) $computer->fields['computermodels_id']);
        $this->assertFalse((new ComputerModel())->getFromDB($twin));
        $this->assertTrue((new ComputerModel())->getFromDB($other), 'Un autre modèle n\'est pas un doublon.');
    }
}
