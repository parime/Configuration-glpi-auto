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
use GlpiPlugin\Configurationglpiauto\GrcmanagerRiskCatalogBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Issue #339 : registre des risques de GRC Manager amorcé par l'assistant avec les risques types de
 * son catalogue. La CI n'installe pas GRC Manager : seul le cas « plugin absent » y est vérifié ;
 * sur une instance où il est actif, l'import est vérifié dans une transaction annulée (aucun risque
 * ne reste).
 */
final class GrcmanagerRiskCatalogBuilderTest extends TestCase
{
    private const SENSITIVE_RIGHT = 'plugin_grcmanager_sensitive';

    private function buildConfig(bool $infrastructure, bool $ai): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'grcmanager_risk_catalog_enabled' => $infrastructure ? 1 : 0,
            'grcmanager_ai_risks_enabled'     => $ai ? 1 : 0,
        ]);

        return $config;
    }

    public function testOptInByDefault(): void
    {
        $this->assertSame(0, (int) Config::getDefaults()['grcmanager_risk_catalog_enabled']);
        $this->assertSame(0, (int) Config::getDefaults()['grcmanager_ai_risks_enabled']);
        $this->assertSame(0, (new GrcmanagerRiskCatalogBuilder())->build($this->buildConfig(false, false)));
    }

    /**
     * Infrastructure seule, puis IA en plus ; relancé, rien n'est recréé ; sans le droit de création
     * du registre (sensible), rien n'est importé.
     */
    public function testImportsRequestedGroupsOnceAndOnlyWithTheCreateRight(): void
    {
        global $DB;

        if (!GrcmanagerRiskCatalogBuilder::isAvailable()) {
            $this->assertSame(0, (new GrcmanagerRiskCatalogBuilder())->build($this->buildConfig(true, true)));

            return;
        }

        $savedProfile = $_SESSION['glpiactiveprofile'] ?? null;
        $DB->beginTransaction();
        try {
            $_SESSION['glpiactiveentities'] ??= [0];
            $DB->delete('glpi_plugin_grcmanager_riskcatalogimports', [1]);
            $catalog = 'GlpiPlugin\\Grcmanager\\Services\\Risk\\InfrastructureRiskCatalog';
            $aiKeys = array_filter($catalog::keys(), static fn (string $k): bool => $catalog::group($k) === $catalog::GROUP_AI);
            $infraCount = count($catalog::keys()) - count($aiKeys);

            // Sans le droit de création du registre des risques : rien.
            $_SESSION['glpiactiveprofile'][self::SENSITIVE_RIGHT] = READ;
            $this->assertSame(0, (new GrcmanagerRiskCatalogBuilder())->build($this->buildConfig(true, true)));

            $_SESSION['glpiactiveprofile'][self::SENSITIVE_RIGHT] = ALLSTANDARDRIGHT;
            $this->assertSame($infraCount, (new GrcmanagerRiskCatalogBuilder())->build($this->buildConfig(true, false)));
            $this->assertSame(
                count($aiKeys),
                (new GrcmanagerRiskCatalogBuilder())->build($this->buildConfig(true, true)),
                'Seuls les risques d\'IA restaient à importer.'
            );
            $this->assertSame(0, (new GrcmanagerRiskCatalogBuilder())->build($this->buildConfig(true, true)), 'Relancé : aucun doublon.');
        } finally {
            $DB->rollBack();
            $_SESSION['glpiactiveprofile'] = $savedProfile;
        }
    }
}
