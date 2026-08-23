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

use CertificateType;
use GlpiPlugin\Configurationglpiauto\CertificateTypeBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use PHPUnit\Framework\TestCase;

final class CertificateTypeBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        global $DB;
        foreach (CertificateTypeBuilder::getTypesPreview() as $type) {
            $item = new CertificateType();
            if ($item->getFromDBByCrit(['name' => $type['name']])) {
                $DB->delete('glpi_dropdowntranslations', ['itemtype' => CertificateType::class, 'items_id' => $item->getID()]);
                $item->delete(['id' => $item->getID()], true);
            }
        }
    }

    private function buildConfig(bool $enabled, bool $withIcons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'certificate_types_enabled' => $enabled ? 1 : 0,
            'certificate_type_icons_enabled' => $withIcons ? 1 : 0,
        ]);

        return $config;
    }

    public function testDisabledReturnsZeroAndCreatesNothing(): void
    {
        $count = (new CertificateTypeBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testCreatesAllTypes(): void
    {
        $count = (new CertificateTypeBuilder())->build($this->buildConfig(true));

        $this->assertSame(count(CertificateTypeBuilder::getTypesPreview()), $count);

        $ssl = new CertificateType();
        $this->assertTrue($ssl->getFromDBByCrit(['name' => 'SSL/TLS (serveur)']));
    }

    public function testIconsToggleOffRemovesAPreviouslyAppliedIcon(): void
    {
        $builder = new CertificateTypeBuilder();
        $builder->build($this->buildConfig(true, true));

        $ssl = new CertificateType();
        $ssl->getFromDBByCrit(['name' => 'SSL/TLS (serveur)']);
        global $DB;
        $withIcon = $DB->request(['FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => CertificateType::class, 'items_id' => $ssl->getID(), 'language' => 'fr_FR', 'field' => 'name']])->current();
        $this->assertSame('🔒 SSL/TLS (serveur)', $withIcon['value']);

        $builder->build($this->buildConfig(true, false));

        $withoutIcon = $DB->request(['FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => CertificateType::class, 'items_id' => $ssl->getID(), 'language' => 'fr_FR', 'field' => 'name']])->current();
        $this->assertSame('SSL/TLS (serveur)', $withoutIcon['value']);
    }

    public function testBuildIsIdempotent(): void
    {
        $builder = new CertificateTypeBuilder();
        $config = $this->buildConfig(true);

        $first = $builder->build($config);
        $second = $builder->build($config);

        $this->assertSame($first, $second);

        global $DB;
        $count = $DB->request(['COUNT' => 'c', 'FROM' => 'glpi_certificatetypes', 'WHERE' => ['name' => 'SSL/TLS (serveur)']])->current()['c'];
        $this->assertSame(1, $count);
    }
}
