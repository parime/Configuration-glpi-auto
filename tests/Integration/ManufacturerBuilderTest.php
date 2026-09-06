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

use DropdownTranslation;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ManufacturerBuilder;
use Manufacturer;
use PHPUnit\Framework\TestCase;

final class ManufacturerBuilderTest extends TestCase
{
    private const SAMPLE_NAMES = ['Dell', 'Cisco', 'Canon', 'Synology', 'Oracle'];

    private function buildConfig(bool $enabled, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'manufacturers_enabled' => $enabled ? 1 : 0,
            'manufacturer_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new ManufacturerBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresExactlyTheTwentyNineManufacturersExist(): void
    {
        $count = (new ManufacturerBuilder())->build($this->buildConfig(true));

        $this->assertSame(29, $count);
        foreach (self::SAMPLE_NAMES as $name) {
            $this->assertTrue((new Manufacturer())->getFromDBByCrit(['name' => $name]), "'$name' must exist.");
        }
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateManufacturers(): void
    {
        $builder = new ManufacturerBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(29, $second);

        global $DB;
        foreach (self::SAMPLE_NAMES as $name) {
            $count = $DB->request(['FROM' => Manufacturer::getTable(), 'WHERE' => ['name' => $name]])->count();
            $this->assertSame(1, $count, "Exactly one '$name' manufacturer must exist — no duplicate.");
        }
    }

    /**
     * Icons here are grouped by product category (💻/🌐/🖨️...), not per-brand — same
     * `Translations::applyIcon()` toggle regression guard as every other icon-capable builder.
     */
    public function testTogglingIconsOnThenOffUpdatesTheTranslation(): void
    {
        $builder = new ManufacturerBuilder();
        $builder->build($this->buildConfig(true, icons: true));

        $manufacturer = new Manufacturer();
        $manufacturer->getFromDBByCrit(['name' => 'Dell']);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => Manufacturer::class,
            'items_id' => $manufacturer->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]));
        $this->assertStringStartsWith('💻', $translation->fields['value']);

        $builder->build($this->buildConfig(true, icons: false));

        $translation = new DropdownTranslation();
        $translation->getFromDBByCrit([
            'itemtype' => Manufacturer::class,
            'items_id' => $manufacturer->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]);
        $this->assertSame('Dell', $translation->fields['value']);
    }
}
