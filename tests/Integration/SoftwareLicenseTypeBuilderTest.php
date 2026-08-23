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
use GlpiPlugin\Configurationglpiauto\SoftwareLicenseTypeBuilder;
use PHPUnit\Framework\TestCase;
use SoftwareLicenseType;

final class SoftwareLicenseTypeBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (SoftwareLicenseTypeBuilder::getTypesPreview() as $type) {
            $item = new SoftwareLicenseType();
            if ($item->getFromDBByCrit(['name' => $type['name']])) {
                $item->delete(['id' => $item->getID()], true);
            }
        }
    }

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'software_license_types_enabled' => $enabled ? 1 : 0,
            'software_license_type_icons_enabled' => 0,
        ]);

        return $config;
    }

    public function testDisabledReturnsZeroAndCreatesNothing(): void
    {
        $count = (new SoftwareLicenseTypeBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    /**
     * #146: reviewed for gaps, added "Perpétuelle" and "Académique / Éducation".
     */
    public function testCreatesAllTypesIncludingThe146Additions(): void
    {
        (new SoftwareLicenseTypeBuilder())->build($this->buildConfig(true));

        $perpetual = new SoftwareLicenseType();
        $this->assertTrue($perpetual->getFromDBByCrit(['name' => 'Perpétuelle']));

        $academic = new SoftwareLicenseType();
        $this->assertTrue($academic->getFromDBByCrit(['name' => 'Académique / Éducation']));
    }

    /**
     * Same "re-sync on every run" behaviour as `ManufacturerDictionaryBuilder`/`VehicleAssetBuilder`
     * fuel types: an admin who ran the wizard before #146 added these two entries should still get
     * them on a later run, not just on a fresh install.
     */
    public function testRerunningCatchesUpAnInstallMissingNewerTypes(): void
    {
        $builder = new SoftwareLicenseTypeBuilder();
        $config = $this->buildConfig(true);
        $builder->build($config);

        $perpetual = new SoftwareLicenseType();
        $perpetual->getFromDBByCrit(['name' => 'Perpétuelle']);
        $perpetual->delete(['id' => $perpetual->getID()], true);

        $builder->build($config);

        $recreated = new SoftwareLicenseType();
        $this->assertTrue($recreated->getFromDBByCrit(['name' => 'Perpétuelle']));
    }

    public function testBuildIsIdempotent(): void
    {
        $builder = new SoftwareLicenseTypeBuilder();
        $config = $this->buildConfig(true);

        $first = $builder->build($config);
        $second = $builder->build($config);

        $this->assertSame($first, $second);

        global $DB;
        $count = $DB->request(['COUNT' => 'c', 'FROM' => 'glpi_softwarelicensetypes', 'WHERE' => ['name' => 'OEM']])->current()['c'];
        $this->assertSame(1, $count);
    }
}
