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
use GlpiPlugin\Configurationglpiauto\SoftwareDictionaryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rule;
use RuleDictionnarySoftware;
use RuleDictionnarySoftwareCollection;

/**
 * Issue #303 : une seule fiche par logiciel malgré les variantes de nom remontées par l'agent.
 */
final class SoftwareDictionaryBuilderTest extends TestCase
{
    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['software_dictionary_enabled' => $enabled ? 1 : 0]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $this->assertSame(0, (new SoftwareDictionaryBuilder())->build($this->buildConfig(false)));
    }

    public function testBuildIsIdempotent(): void
    {
        global $DB;
        $builder = new SoftwareDictionaryBuilder();
        $count = $builder->build($this->buildConfig(true));
        $builder->build($this->buildConfig(true));

        $this->assertSame(count(SoftwareDictionaryBuilder::getPreview()), $count);
        $this->assertSame($count, $DB->request([
            'FROM' => Rule::getTable(),
            'WHERE' => ['sub_type' => RuleDictionnarySoftware::class, 'name' => ['LIKE', 'Logiciel — normalisation %']],
        ])->count());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function inventoryNames(): iterable
    {
        yield 'Firefox, langue et architecture' => ['Mozilla Firefox (x64 fr)', 'Mozilla Firefox'];
        yield 'Firefox, version dans le nom' => ['Mozilla Firefox 131.0 (x64 en-US)', 'Mozilla Firefox'];
        yield 'Firefox ESR reste distinct' => ['Mozilla Firefox ESR (x64 fr)', 'Mozilla Firefox ESR'];
        yield 'Edge' => ['Microsoft Edge', 'Microsoft Edge'];
        yield 'WebView2 reste distinct' => ['Microsoft Edge WebView2 Runtime', 'Microsoft Edge WebView2 Runtime'];
        yield 'Edge Update n\'est pas Edge' => ['Microsoft Edge Update', 'Microsoft Edge Update'];
        yield 'Bureau à distance Chrome n\'est pas Chrome' => ['Google Chrome Remote Desktop Host', 'Google Chrome Remote Desktop Host'];
        yield 'Office' => ['Microsoft 365 Apps for enterprise - fr-fr', 'Microsoft 365 Apps'];
        yield 'Acrobat Reader' => ['Adobe Acrobat Reader DC - Français', 'Adobe Acrobat Reader'];
        yield 'Acrobat' => ['Adobe Acrobat (64-bit)', 'Adobe Acrobat'];
        yield '7-Zip' => ['7-Zip 23.01 (x64)', '7-Zip'];
        yield 'Notepad++' => ['Notepad++ (64-bit x64)', 'Notepad++'];
        yield 'Pack d\'aide LibreOffice reste à part' => ['LibreOffice 24.2.0.3 Help Pack (French)', 'LibreOffice 24.2.0.3 Help Pack (French)'];
        yield 'LibreOffice' => ['LibreOffice 24.2.0.3', 'LibreOffice'];
        yield 'Extension Outlook de Zoom reste à part' => ['Zoom Outlook Plugin', 'Zoom Outlook Plugin'];
        yield 'Opera GX reste à part' => ['Opera GX Stable', 'Opera GX Stable'];
        yield 'GitHub Desktop n\'est pas Git' => ['GitHub Desktop', 'GitHub Desktop'];
        yield 'Java garde la version majeure' => ['Java 8 Update 401 (64-bit)', 'Java 8'];
        yield 'Python garde la version mineure' => ['Python 3.12.1 (64-bit)', 'Python 3.12'];
        yield 'Visual C++ garde année et architecture' => ['Microsoft Visual C++ 2015-2022 Redistributable (x64) - 14.38.33130', 'Microsoft Visual C++ 2015-2022 Redistributable (x64)'];
        yield 'Visual C++ x86 reste distinct' => ['Microsoft Visual C++ 2015-2022 Redistributable (x86) - 14.38.33130', 'Microsoft Visual C++ 2015-2022 Redistributable (x86)'];
        yield 'logiciel inconnu inchangé' => ['Logiciel Maison Comptabilité 2.1', 'Logiciel Maison Comptabilité 2.1'];
    }

    #[DataProvider('inventoryNames')]
    public function testInventoryNamesAreNormalized(string $reported, string $expected): void
    {
        (new SoftwareDictionaryBuilder())->build($this->buildConfig(true));

        $result = (new RuleDictionnarySoftwareCollection())->processAllRules([
            'name' => $reported,
            'manufacturer' => 0,
            'old_version' => null,
            'entities_id' => 0,
            '_system_category' => null,
        ]);

        $this->assertSame($expected, (string) ($result['name'] ?? $reported));
    }
}
