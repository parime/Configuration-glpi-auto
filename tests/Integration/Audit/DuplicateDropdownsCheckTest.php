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
use Rule;
use RuleDictionnaryManufacturer;

/**
 * Issue #299 : doublons déjà en base, détectés par l'audit et fusionnés en un clic.
 *
 * Issue #496 : le test travaille sur l'instance partagée. Tout ce qu'il crée porte le préfixe
 * BRAND_PREFIX (intitulés) ou COMPUTER_PREFIX (matériels) et est purgé dans tearDown(), qui
 * s'exécute aussi quand une assertion échoue ; les règles de dictionnaire et le fabricant « HP »
 * ne sont supprimés que s'ils n'existaient pas avant le test, la variante « Hewlett-Packard… »
 * par son identifiant seulement (un vrai fabricant de ce nom reste intact). Les restes d'anciennes
 * exécutions sont purgés avant la classe (setUpBeforeClass) et après (tearDownAfterClass).
 */
final class DuplicateDropdownsCheckTest extends TestCase
{
    private const BRAND_PREFIX = 'Phpunitdup';
    private const COMPUTER_PREFIX = 'PHPUNIT-DUP-';
    private const HP_VARIANT = 'Hewlett-Packard Development Company, L.P.';

    private string $brand;

    private int $unicityBefore = 1;

    /** @var list<int> */
    private array $rulesBefore = [];

    private bool $hpExisted = true;

    /** Variante « Hewlett-Packard… » créée par le test (0 : aucune) ; supprimée par son id seulement. */
    private int $hpVariant = 0;

    public static function setUpBeforeClass(): void
    {
        self::purgeLeftovers();
    }

    public static function tearDownAfterClass(): void
    {
        self::purgeLeftovers();
    }

    protected function setUp(): void
    {
        $this->brand = self::BRAND_PREFIX . mt_rand(100000, 999999);
        $this->unicityBefore = (int) (Config::getConfig()->fields['dropdown_unicity_enabled'] ?? 1);
        $this->rulesBefore = self::dictionaryRuleIds();
        $this->hpExisted = (new Manufacturer())->getFromDBByCrit(['name' => 'HP']);
        // Doublons d'avant le blocage (#298) : on les crée option désactivée.
        $this->setUnicity(false);
    }

    protected function tearDown(): void
    {
        self::purgeLeftovers();
        foreach (array_diff(self::dictionaryRuleIds(), $this->rulesBefore) as $ruleId) {
            (new RuleDictionnaryManufacturer())->delete(['id' => $ruleId], true);
        }
        if ($this->hpVariant > 0 && (new Manufacturer())->getFromDB($this->hpVariant)) {
            (new Manufacturer())->delete(['id' => $this->hpVariant], true);
        }
        $hp = new Manufacturer();
        if (!$this->hpExisted && $hp->getFromDBByCrit(['name' => 'HP'])) {
            $hp->delete(['id' => $hp->getID()], true);
        }
        $this->setUnicity($this->unicityBefore === 1);

        $this->assertSame(0, self::leftoverCount(), 'Le test ne laisse aucun intitulé ni matériel de test.');
        $this->assertSame($this->rulesBefore, self::dictionaryRuleIds(), 'Les règles de dictionnaire sont celles d\'avant le test.');
    }

    /**
     * Supprime définitivement (corbeille comprise) les intitulés et matériels de test, y compris
     * les restes d'exécutions plus anciennes (issue #496 : modèles « Phpunitdup… X1 »).
     */
    private static function purgeLeftovers(): void
    {
        (new Computer())->deleteByCriteria(['name' => ['LIKE', self::COMPUTER_PREFIX . '%']], true);
        foreach ([ComputerModel::class, Manufacturer::class] as $class) {
            (new $class())->deleteByCriteria(['name' => ['LIKE', self::BRAND_PREFIX . '%']], true);
        }
    }

    private static function leftoverCount(): int
    {
        return countElementsInTable(Computer::getTable(), ['name' => ['LIKE', self::COMPUTER_PREFIX . '%']])
            + countElementsInTable(ComputerModel::getTable(), ['name' => ['LIKE', self::BRAND_PREFIX . '%']])
            + countElementsInTable(Manufacturer::getTable(), ['name' => ['LIKE', self::BRAND_PREFIX . '%']]);
    }

    /** @return list<int> */
    private static function dictionaryRuleIds(): array
    {
        $ids = [];
        foreach ((new Rule())->find(['sub_type' => RuleDictionnaryManufacturer::class], 'id') as $row) {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }

    private function setUnicity(bool $enabled): void
    {
        $config = Config::getConfig();
        $config->update(['id' => $config->getID(), 'dropdown_unicity_enabled' => $enabled ? 1 : 0]);
    }

    private function computer(string $fk, int $id): int
    {
        return (int) (new Computer())->add(['name' => self::COMPUTER_PREFIX . mt_rand(), 'entities_id' => 0, $fk => $id]);
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
        $variant = (int) (new Manufacturer())->add(['name' => self::HP_VARIANT]);
        $this->hpVariant = $variant;
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
