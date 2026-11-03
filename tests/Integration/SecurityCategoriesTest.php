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

use Config as CoreConfig;
use DropdownTranslation;
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\SecurityCategories;
use ITILCategory;
use PHPUnit\Framework\TestCase;

/**
 * grcmanager #372 : identifiants des catégories « Sécurité SI », lus par grcmanager sans dépendre
 * du nom affiché, et renommage de « Indisponibilité / Malware / Ransomware ».
 */
final class SecurityCategoriesTest extends TestCase
{
    protected function tearDown(): void
    {
        SecurityCategories::uninstall();
    }

    private static function buildItBranch(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_enabled'  => 1,
            'category_branches' => json_encode(['it']),
        ]);
        (new CategoryBuilder())->build($config);
    }

    public function testBuilderRecordsTheBranchAndEachSubcategory(): void
    {
        SecurityCategories::uninstall();
        self::buildItBranch();

        $ids = SecurityCategories::ids();
        $branch = new ITILCategory();
        $this->assertTrue($branch->getFromDB($ids['security']));
        $this->assertSame('Sécurité SI', $branch->fields['name']);
        foreach (SecurityCategories::KEYS as $key => $name) {
            $category = new ITILCategory();
            $this->assertTrue($category->getFromDB($ids[$key]), $key);
            $this->assertSame($name, $category->fields['name'], $key);
            if ($key !== 'security') {
                $this->assertSame($ids['security'], (int) $category->fields['itilcategories_id'], $key);
            }
        }

        // Contrat lu par grcmanager : clés de configuration stables.
        $values = CoreConfig::getConfigurationValues('plugin:configurationglpiauto', ['security_malware_itilcategories_id']);
        $this->assertSame($ids['security_malware'], (int) $values['security_malware_itilcategories_id']);
    }

    public function testLegacyMalwareCategoryIsRenamedInPlaceThenRecorded(): void
    {
        self::buildItBranch();
        $ids = SecurityCategories::ids();
        $malwareId = $ids['security_malware'];
        // Installation antérieure : ancien nom, aucun identifiant enregistré.
        $category = new ITILCategory();
        $category->update(['id' => $malwareId, 'name' => 'Indisponibilité / Malware / Ransomware']);
        SecurityCategories::uninstall();

        SecurityCategories::renameLegacy();
        SecurityCategories::recordExisting();

        $category->getFromDB($malwareId);
        $this->assertSame('Malware / Ransomware', $category->fields['name'], 'Même catégorie, renommée.');
        $this->assertStringEndsWith('Sécurité SI > Malware / Ransomware', $category->fields['completename']);
        $this->assertSame($malwareId, SecurityCategories::ids()['security_malware']);
        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => 'ITILCategory', 'items_id' => $malwareId, 'language' => 'en_GB', 'field' => 'name',
        ]));
        $this->assertSame('Malware / Ransomware', $translation->fields['value']);
    }

    public function testLegacyAccountCategoryIsSplitIntoAccessAndDataBreach(): void
    {
        self::buildItBranch();
        $ids = SecurityCategories::ids();
        $accountId = $ids['security_account'];
        // Installation antérieure : ancien libellé, pas encore de « Fuite de données ».
        (new ITILCategory())->update(['id' => $accountId, 'name' => 'Compte compromis / Fuite de données']);
        (new ITILCategory())->delete(['id' => $ids['security_data_breach']], true);
        SecurityCategories::uninstall();

        SecurityCategories::renameLegacy();
        SecurityCategories::recordExisting();
        SecurityCategories::addMissing();

        $account = new ITILCategory();
        $account->getFromDB($accountId);
        $this->assertSame('Compte compromis / Accès non autorisé', $account->fields['name'], 'Renommée en place.');
        $after = SecurityCategories::ids();
        $this->assertSame($accountId, $after['security_account']);
        $breach = new ITILCategory();
        $this->assertTrue($breach->getFromDB($after['security_data_breach']), 'Créée sous la branche.');
        $this->assertSame('Fuite de données', $breach->fields['name']);
        $this->assertSame($ids['security'], (int) $breach->fields['itilcategories_id']);

        // Rejouée : ni doublon ni nouvel identifiant.
        SecurityCategories::addMissing();
        $this->assertSame($after['security_data_breach'], SecurityCategories::ids()['security_data_breach']);
        $this->assertSame(1, countElementsInTable('glpi_itilcategories', [
            'name' => 'Fuite de données', 'itilcategories_id' => $ids['security'],
        ]));
    }

    public function testNothingIsAddedWithoutTheSecurityBranch(): void
    {
        SecurityCategories::uninstall();
        $branch = new ITILCategory();
        if ($branch->getFromDBByCrit(['name' => 'Sécurité SI'])) {
            $this->markTestSkipped('Branche déjà installée dans cette base de test.');
        }
        SecurityCategories::addMissing();
        $this->assertSame(0, countElementsInTable('glpi_itilcategories', ['name' => 'Fuite de données']));
    }

    public function testCategoryCreatedByHandIsNeverRenamedNorReplaced(): void
    {
        self::buildItBranch();
        $ids = SecurityCategories::ids();

        // Homonyme hors de la branche : jamais renommé.
        $outside = new ITILCategory();
        $outsideId = (int) $outside->add(['name' => 'Indisponibilité / Malware / Ransomware', 'entities_id' => 0]);
        SecurityCategories::renameLegacy();
        $outside->getFromDB($outsideId);
        $this->assertSame('Indisponibilité / Malware / Ransomware', $outside->fields['name']);
        $outside->delete(['id' => $outsideId], true);

        // Homonyme créé ensuite : l'identifiant enregistré n'est pas remplacé.
        $homonym = new ITILCategory();
        $homonymId = (int) $homonym->add(['name' => 'Certificats SSL', 'itilcategories_id' => $ids['security'], 'entities_id' => 0]);
        SecurityCategories::recordExisting();
        $this->assertSame($ids['security_certificates'], SecurityCategories::ids()['security_certificates']);
        $homonym->delete(['id' => $homonymId], true);

        // Catégorie supprimée : 0 est renvoyé.
        SecurityCategories::record('security_phishing', 999999999);
        $this->assertSame(0, SecurityCategories::ids()['security_phishing']);
    }
}
