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

use GlpiPlugin\Configurationglpiauto\ComputerTypeDictionaryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ManufacturerBuilder;
use GlpiPlugin\Configurationglpiauto\ManufacturerDictionaryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rule;
use RuleAction;
use RuleCriteria;
use RuleDictionnaryComputerTypeCollection;
use RuleDictionnaryManufacturer;
use RuleDictionnaryManufacturerCollection;

final class ManufacturerDictionaryBuilderTest extends TestCase
{
    private const RULE_NAME = 'Fabricant — normalisation « HP »';

    private function buildConfig(bool $enabled, bool $assetTypes = true): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'manufacturer_dictionary_enabled' => $enabled ? 1 : 0,
            'asset_types_enabled' => $assetTypes ? 1 : 0,
        ]);

        return $config;
    }

    private static function normalize(string $collectionClass, string $name): string
    {
        $result = (new $collectionClass())->processAllRules(['name' => $name], [], []);

        return (string) ($result['name'] ?? $name);
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new ManufacturerDictionaryBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testEveryManufacturerCreatedByTheWizardHasARule(): void
    {
        $covered = ManufacturerDictionaryBuilder::getCanonicalNames();
        foreach (ManufacturerBuilder::getManufacturersPreview() as $manufacturer) {
            $this->assertContains($manufacturer['name'], $covered, 'Fabricant de l\'assistant sans règle de dictionnaire.');
        }
    }

    public function testBuildCreatesOneRulePerBrandWithExactVariantsARegexAndAnAssignAction(): void
    {
        $count = (new ManufacturerDictionaryBuilder())->build($this->buildConfig(true));

        $this->assertSame(count(ManufacturerDictionaryBuilder::getCanonicalNames()), $count);
        $this->assertGreaterThan(60, $count);

        $rule = new Rule();
        $this->assertTrue($rule->getFromDBByCrit(['name' => self::RULE_NAME]));
        $this->assertSame(RuleDictionnaryManufacturer::class, $rule->fields['sub_type']);
        $this->assertSame(Rule::OR_MATCHING, $rule->fields['match']);
        $this->assertSame(1, (int) $rule->fields['is_active']);

        $conditions = [];
        foreach ((new RuleCriteria())->find(['rules_id' => $rule->getID(), 'criteria' => 'name']) as $row) {
            $conditions[(int) $row['condition']][] = $row['pattern'];
        }
        $this->assertContains('Hewlett-Packard', $conditions[Rule::PATTERN_IS]);
        $this->assertCount(1, $conditions[Rule::REGEX_MATCH]);

        $action = new RuleAction();
        $this->assertTrue($action->getFromDBByCrit(['rules_id' => $rule->getID(), 'field' => 'name']));
        $this->assertSame('assign', $action->fields['action_type']);
        $this->assertSame('HP', $action->fields['value']);
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateCriteria(): void
    {
        global $DB;
        $builder = new ManufacturerDictionaryBuilder();
        $builder->build($this->buildConfig(true));
        $rule = new Rule();
        $rule->getFromDBByCrit(['name' => self::RULE_NAME]);
        $criteria = $DB->request(['FROM' => RuleCriteria::getTable(), 'WHERE' => ['rules_id' => $rule->getID()]])->count();

        $builder->build($this->buildConfig(true));

        $this->assertSame(1, $DB->request(['FROM' => Rule::getTable(), 'WHERE' => ['name' => self::RULE_NAME]])->count());
        $this->assertSame(
            $criteria,
            $DB->request(['FROM' => RuleCriteria::getTable(), 'WHERE' => ['rules_id' => $rule->getID()]])->count(),
            'A second run must not duplicate any criteria row.'
        );
    }

    public function testAnUpgradedRuleGetsTheNewRegexAddedOnce(): void
    {
        global $DB;
        (new ManufacturerDictionaryBuilder())->build($this->buildConfig(true));
        $rule = new Rule();
        $rule->getFromDBByCrit(['name' => self::RULE_NAME]);
        // Règle créée par une version précédente : pas encore d'expression régulière.
        $DB->delete(RuleCriteria::getTable(), ['rules_id' => $rule->getID(), 'condition' => Rule::REGEX_MATCH]);

        (new ManufacturerDictionaryBuilder())->build($this->buildConfig(true));

        $this->assertSame(1, $DB->request([
            'FROM' => RuleCriteria::getTable(),
            'WHERE' => ['rules_id' => $rule->getID(), 'condition' => Rule::REGEX_MATCH],
        ])->count());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function realManufacturerNames(): iterable
    {
        yield 'HP, imprimante réseau' => ['Hewlett-Packard Development Company, L.P.', 'HP'];
        yield 'HP, inconnu de la liste' => ['HP Inc. (Palo Alto)', 'HP'];
        yield 'HPE n\'est pas HP' => ['HPE', 'HPE Aruba'];
        yield 'HP Enterprise' => ['Hewlett Packard Enterprise Company', 'HPE Aruba'];
        yield 'HP Poly' => ['HP Poly', 'Poly'];
        yield 'Lenovo, variante inconnue' => ['Lenovo (Beijing) Limited', 'Lenovo'];
        yield 'Lenovo en majuscules' => ['LENOVO', 'Lenovo'];
        yield 'LG, nom EDID' => ['Goldstar Company Ltd', 'LG'];
        yield 'ASUS, nom EDID' => ['Ancor Communications Inc', 'ASUS'];
        yield 'Dell' => ['Dell Inc.', 'Dell'];
        yield 'Intel' => ['Intel(R) Corporation', 'Intel'];
        yield 'VirtualBox' => ['innotek GmbH', 'Oracle'];
        yield 'Konica Minolta' => ['KONICA MINOLTA business solutions', 'Konica Minolta'];
        yield 'nom proche mais autre marque' => ['Applied Signal', 'Applied Signal'];
        yield 'lettres contenues sans être la marque' => ['Delta Electronics', 'Delta Electronics'];
    }

    #[DataProvider('realManufacturerNames')]
    public function testRealInventoryNamesAreNormalized(string $reported, string $expected): void
    {
        (new ManufacturerDictionaryBuilder())->build($this->buildConfig(true));

        $this->assertSame($expected, self::normalize(RuleDictionnaryManufacturerCollection::class, $reported));
    }

    public function testComputerTypeDictionaryNeedsTheWizardAssetTypes(): void
    {
        $this->assertSame(0, (new ComputerTypeDictionaryBuilder())->build($this->buildConfig(true, false)));
        $this->assertSame(0, (new ComputerTypeDictionaryBuilder())->build($this->buildConfig(false, true)));
    }

    /**
     * @return iterable<int, array{string, string}>
     */
    public static function chassisTypes(): iterable
    {
        yield ['Notebook', 'Ordinateur portable'];
        yield ['Laptop', 'Ordinateur portable'];
        yield ['Mini Tower', 'Ordinateur de bureau'];
        yield ['Space-Saving', 'Ordinateur de bureau'];
        yield ['Rack Mount Chassis', 'Serveur'];
        yield ['Tablet', 'Tablette'];
        yield ['Mini PC', 'Mini PC'];
        yield ['Other', 'Other'];
    }

    #[DataProvider('chassisTypes')]
    public function testChassisTypesReportedByTheAgentMapToTheWizardTypes(string $chassis, string $expected): void
    {
        $this->assertSame(5, (new ComputerTypeDictionaryBuilder())->build($this->buildConfig(true)));

        $this->assertSame($expected, self::normalize(RuleDictionnaryComputerTypeCollection::class, $chassis));
    }
}
