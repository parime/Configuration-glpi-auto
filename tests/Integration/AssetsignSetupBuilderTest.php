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

use GlpiPlugin\Configurationglpiauto\AssetsignSetupBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use PHPUnit\Framework\TestCase;

/**
 * Issue #339 : entreprise et lien de la charte renseignés dans assetsign. La CI n'installe pas
 * assetsign : seul le calcul (plan()) et le cas « plugin absent » y sont vérifiés ; sur une
 * instance où il est actif, l'écriture est vérifiée dans une transaction annulée.
 */
final class AssetsignSetupBuilderTest extends TestCase
{
    public function testPlanOnlyFillsEmptySettingsWithValidValues(): void
    {
        $this->assertSame(
            ['company_name' => 'ACME', 'charter_url' => 'https://intranet.example.org/charte.pdf'],
            AssetsignSetupBuilder::plan(['company_name' => '', 'charter_url' => null], ' ACME ', 'https://intranet.example.org/charte.pdf')
        );
        // Réglages déjà renseignés dans assetsign : conservés.
        $this->assertSame([], AssetsignSetupBuilder::plan(
            ['company_name' => 'Déjà là', 'charter_url' => 'https://deja.example.org'],
            'ACME',
            'https://intranet.example.org/charte.pdf'
        ));
        // Lien invalide : ignoré, comme dans la configuration d'assetsign.
        $this->assertSame(['company_name' => 'ACME'], AssetsignSetupBuilder::plan([], 'ACME', 'javascript:alert(1)'));
    }

    public function testInvalidCharterUrlIsNeverStoredInTheWizardConfig(): void
    {
        $config = new Config();
        $input = $config->prepareInputForAdd(['assetsign_charter_url' => 'javascript:alert(1)', 'assetsign_company_name' => '  ACME  ']);
        $this->assertSame('', $input['assetsign_charter_url']);
        $this->assertSame('ACME', $input['assetsign_company_name']);
    }

    public function testFillsTheRootAssetsignConfigWhenThePluginIsActive(): void
    {
        global $DB;

        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'assetsign_setup_enabled' => 1,
            'assetsign_company_name'  => 'ACME',
            'assetsign_charter_url'   => 'https://intranet.example.org/charte.pdf',
        ]);
        if (!AssetsignSetupBuilder::isAvailable()) {
            $this->assertSame(0, (new AssetsignSetupBuilder())->build($config));

            return;
        }

        $DB->beginTransaction();
        try {
            $DB->update(AssetsignSetupBuilder::TABLE, ['company_name' => '', 'charter_url' => null], ['entities_id' => 0]);
            $this->assertSame(2, (new AssetsignSetupBuilder())->build($config));
            $row = $DB->request(['FROM' => AssetsignSetupBuilder::TABLE, 'WHERE' => ['entities_id' => 0]])->current();
            $this->assertSame('ACME', $row['company_name']);
            $this->assertSame('https://intranet.example.org/charte.pdf', $row['charter_url']);
            // Deuxième passage : rien n'est réécrit.
            $this->assertSame(0, (new AssetsignSetupBuilder())->build($config));
        } finally {
            $DB->rollBack();
        }
    }
}
