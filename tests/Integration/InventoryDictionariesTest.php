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
use GlpiPlugin\Configurationglpiauto\OperatingSystemDictionaryBuilder;
use GlpiPlugin\Configurationglpiauto\PrinterDictionaryBuilder;
use GlpiPlugin\Configurationglpiauto\SoftwareDictionaryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuleDictionnaryOperatingSystemArchitectureCollection;
use RuleDictionnaryOperatingSystemCollection;
use RuleDictionnaryPrinterCollection;
use RuleDictionnarySoftwareCollection;

/**
 * Issue #315 : revue des dictionnaires — systèmes d'exploitation, architectures, imprimantes
 * virtuelles, compléments logiciels.
 */
final class InventoryDictionariesTest extends TestCase
{
    protected function setUp(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'os_dictionary_enabled' => 1,
            'virtual_printers_ignored' => 1,
            'software_dictionary_enabled' => 1,
        ]);
        (new OperatingSystemDictionaryBuilder())->build($config);
        (new PrinterDictionaryBuilder())->build($config);
        (new SoftwareDictionaryBuilder())->build($config);
    }

    /**
     * @param class-string $collectionClass
     */
    private static function normalize(string $collectionClass, string $name): string
    {
        // Même entrée que l'inventaire (Glpi\Inventory\Asset\OperatingSystem / Software).
        $result = (new $collectionClass())->processAllRules([
            'name' => $name, 'os_name' => $name, 'manufacturer' => 0, 'old_version' => null, 'entities_id' => 0,
        ]);

        return (string) ($result['name'] ?? $name);
    }

    public function testDisabledOptionsCreateNothing(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['os_dictionary_enabled' => 0, 'virtual_printers_ignored' => 0]);

        $this->assertSame(0, (new OperatingSystemDictionaryBuilder())->build($config));
        $this->assertSame(0, (new PrinterDictionaryBuilder())->build($config));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function operatingSystems(): iterable
    {
        yield 'Windows 11 Pro en français' => ['Microsoft Windows 11 Professionnel', 'Microsoft Windows 11 Pro'];
        yield 'Windows 11 Pro en anglais' => ['Microsoft Windows 11 Pro', 'Microsoft Windows 11 Pro'];
        yield 'Windows 10 Pro N' => ['Microsoft Windows 10 Professionnel N', 'Microsoft Windows 10 Pro N'];
        yield 'Pro for Workstations reste distinct' => ['Microsoft Windows 11 Professionnel pour les Stations de travail', 'Microsoft Windows 11 Pro for Workstations'];
        yield 'Pro Education reste distinct' => ['Microsoft Windows 11 Professionnel Éducation', 'Microsoft Windows 11 Pro Education'];
        yield 'Famille' => ['Microsoft Windows 11 Famille', 'Microsoft Windows 11 Home'];
        yield 'Famille unilingue' => ['Microsoft Windows 10 Famille unilingue', 'Microsoft Windows 10 Home Single Language'];
        yield 'Entreprise' => ['Microsoft Windows 10 Entreprise', 'Microsoft Windows 10 Enterprise'];
        yield 'Entreprise LTSC' => ['Microsoft Windows 10 Entreprise LTSC 2021', 'Microsoft Windows 10 Enterprise LTSC 2021'];
        yield 'Éducation' => ['Microsoft Windows 11 Éducation', 'Microsoft Windows 11 Education'];
        yield 'Windows Server inchangé' => ['Microsoft Windows Server 2022 Standard', 'Microsoft Windows Server 2022 Standard'];
        yield 'Ubuntu, version mineure retirée' => ['Ubuntu 22.04.4 LTS', 'Ubuntu 22.04 LTS'];
        yield 'Ubuntu sans LTS' => ['Ubuntu 23.10', 'Ubuntu 23.10'];
        yield 'RHEL' => ['Red Hat Enterprise Linux 9.3 (Plow)', 'Red Hat Enterprise Linux 9'];
        yield 'Rocky' => ['Rocky Linux 9.4 (Blue Onyx)', 'Rocky Linux 9'];
        yield 'macOS' => ['macOS 14.4.1', 'macOS 14'];
        yield 'Debian inchangé' => ['Debian GNU/Linux 12 (bookworm)', 'Debian GNU/Linux 12 (bookworm)'];
    }

    #[DataProvider('operatingSystems')]
    public function testOperatingSystemNamesAreNormalized(string $reported, string $expected): void
    {
        $this->assertSame($expected, self::normalize(RuleDictionnaryOperatingSystemCollection::class, $reported));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function architectures(): iterable
    {
        yield 'Windows en français' => ['64 bits', 'x86_64'];
        yield 'Windows en anglais' => ['64-bit', 'x86_64'];
        yield 'Linux' => ['x86_64', 'x86_64'];
        yield 'Debian' => ['amd64', 'x86_64'];
        yield '32 bits' => ['32 bits', 'x86'];
        yield 'i686' => ['i686', 'x86'];
        yield 'ARM' => ['aarch64', 'ARM64'];
        yield 'ARM Windows' => ['ARM64-based PC', 'ARM64'];
    }

    #[DataProvider('architectures')]
    public function testArchitecturesAreNormalized(string $reported, string $expected): void
    {
        $this->assertSame($expected, self::normalize(RuleDictionnaryOperatingSystemArchitectureCollection::class, $reported));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function printers(): iterable
    {
        yield 'PDF de Windows' => ['Microsoft Print to PDF', true];
        yield 'XPS' => ['Microsoft XPS Document Writer', true];
        yield 'OneNote' => ['OneNote (Desktop)', true];
        yield 'OneNote 2016' => ['Send To OneNote 2016', true];
        yield 'Fax' => ['Fax', true];
        yield 'PDFCreator' => ['PDFCreator', true];
        yield 'Redirigée (Bureau à distance)' => ['HP LaserJet M404 (redirection 3)', true];
        yield 'Redirigée en anglais' => ['Canon iR-ADV C5535 (redirected 2)', true];
        yield 'Vraie imprimante' => ['HP LaserJet Pro M404dn', false];
        yield 'Vraie imprimante réseau' => ['Bureau RDC - Canon iR-ADV C5535', false];
    }

    #[DataProvider('printers')]
    public function testVirtualPrintersAreIgnoredAndRealOnesKept(string $name, bool $ignored): void
    {
        $result = (new RuleDictionnaryPrinterCollection())->processAllRules(['name' => $name]);

        $this->assertSame($ignored, (string) ($result['_ignore_import'] ?? '') === '1');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function software(): iterable
    {
        yield 'Office perpétuel français' => ['Microsoft Office Professionnel Plus 2019 - fr-fr', 'Microsoft Office Professional Plus 2019'];
        yield 'Office perpétuel anglais' => ['Microsoft Office Professional Plus 2021 - en-us', 'Microsoft Office Professional Plus 2021'];
        yield 'Office Famille et Petite Entreprise' => ['Microsoft Office Famille et Petite Entreprise 2019 - fr-fr', 'Microsoft Office Home and Business 2019'];
        yield 'Visio' => ['Microsoft Visio Professionnel 2021 - fr-fr', 'Microsoft Visio Professional 2021'];
        yield '.NET Runtime' => ['Microsoft .NET Runtime - 8.0.4 (x64)', 'Microsoft .NET Runtime 8 (x64)'];
        yield 'Desktop Runtime x86 distinct' => ['Microsoft Windows Desktop Runtime - 6.0.29 (x86)', 'Microsoft Windows Desktop Runtime 6 (x86)'];
        yield 'ASP.NET Core' => ['Microsoft ASP.NET Core 8.0.4 - Shared Framework (x64)', 'Microsoft ASP.NET Core 8 Shared Framework (x64)'];
        yield 'Kaspersky' => ['Kaspersky Endpoint Security 12.3 for Windows', 'Kaspersky Endpoint Security'];
        yield 'Dell Command Update' => ['Dell Command | Update for Windows Universal', 'Dell Command | Update'];
        yield 'Skype Entreprise distinct de Skype' => ['Skype Entreprise 2016', 'Skype for Business'];
        yield 'Skype' => ['Skype version 8.110', 'Skype'];
    }

    #[DataProvider('software')]
    public function testMoreSoftwareIsNormalized(string $reported, string $expected): void
    {
        $this->assertSame($expected, self::normalize(RuleDictionnarySoftwareCollection::class, $reported));
    }
}
