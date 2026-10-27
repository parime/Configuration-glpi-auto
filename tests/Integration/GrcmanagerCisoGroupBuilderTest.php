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
use GlpiPlugin\Configurationglpiauto\GrcmanagerCisoGroupBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Issue #339 : groupe « RSSI » déclaré dans GRC Manager. La CI n'installe pas GRC Manager à côté
 * de ce plugin : seul le cas « plugin absent » y est vérifié ; sur une instance où il est actif,
 * le test vérifie aussi la déclaration, puis remet le réglage d'origine.
 */
final class GrcmanagerCisoGroupBuilderTest extends TestCase
{
    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'grcmanager_ciso_group_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    private function cisoGroupId(): int
    {
        $values = \Config::getConfigurationValues(
            GrcmanagerCisoGroupBuilder::CONFIG_CONTEXT,
            [GrcmanagerCisoGroupBuilder::CONFIG_KEY]
        );

        return (int) ($values[GrcmanagerCisoGroupBuilder::CONFIG_KEY] ?? 0);
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $this->assertSame(0, (new GrcmanagerCisoGroupBuilder())->build($this->buildConfig(false)));
    }

    public function testOnlyActsWhenGrcmanagerIsActiveAndNoCisoGroupIsChosen(): void
    {
        $before = $this->cisoGroupId();
        if (!GrcmanagerCisoGroupBuilder::isGrcmanagerActive()) {
            $this->assertSame(0, (new GrcmanagerCisoGroupBuilder())->build($this->buildConfig(true)));
            $this->assertSame($before, $this->cisoGroupId(), 'Rien n\'est écrit sans GRC Manager.');

            return;
        }

        try {
            \Config::setConfigurationValues(GrcmanagerCisoGroupBuilder::CONFIG_CONTEXT, [
                GrcmanagerCisoGroupBuilder::CONFIG_KEY => 0,
            ]);
            $this->assertSame(1, (new GrcmanagerCisoGroupBuilder())->build($this->buildConfig(true)));
            $group = new \Group();
            $this->assertTrue($group->getFromDB($this->cisoGroupId()));
            $this->assertSame(GrcmanagerCisoGroupBuilder::GROUP_NAME, $group->fields['name']);

            // Groupe déjà choisi : jamais remplacé.
            \Config::setConfigurationValues(GrcmanagerCisoGroupBuilder::CONFIG_CONTEXT, [
                GrcmanagerCisoGroupBuilder::CONFIG_KEY => 999999,
            ]);
            $this->assertSame(0, (new GrcmanagerCisoGroupBuilder())->build($this->buildConfig(true)));
            $this->assertSame(999999, $this->cisoGroupId());
        } finally {
            \Config::setConfigurationValues(GrcmanagerCisoGroupBuilder::CONFIG_CONTEXT, [
                GrcmanagerCisoGroupBuilder::CONFIG_KEY => $before,
            ]);
        }
    }
}
