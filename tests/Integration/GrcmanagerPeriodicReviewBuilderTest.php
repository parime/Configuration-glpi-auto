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
use GlpiPlugin\Configurationglpiauto\GrcmanagerPeriodicReviewBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Issue #339 : revues périodiques de GRC Manager activées par l'assistant. La CI n'installe pas
 * GRC Manager : seul le cas « plugin absent » y est vérifié ; sur une instance où il est actif,
 * l'activation est vérifiée dans une transaction annulée (aucun ticket récurrent ne reste).
 */
final class GrcmanagerPeriodicReviewBuilderTest extends TestCase
{
    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'grcmanager_periodic_reviews_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testOptInByDefault(): void
    {
        $this->assertSame(0, (int) Config::getDefaults()['grcmanager_periodic_reviews_enabled']);
        $this->assertSame(0, (new GrcmanagerPeriodicReviewBuilder())->build($this->buildConfig(false)));
    }

    public function testActivatesOnlyInactiveReviewsWhenGrcmanagerIsAvailable(): void
    {
        global $DB;

        if (!GrcmanagerPeriodicReviewBuilder::isAvailable()) {
            $this->assertSame(0, (new GrcmanagerPeriodicReviewBuilder())->build($this->buildConfig(true)));

            return;
        }

        $savedProfile = $_SESSION['glpiactiveprofile'] ?? null;
        $DB->beginTransaction();
        try {
            // Droits nécessaires à l'activation (plugin, tickets récurrents, gabarits).
            $_SESSION['glpiactiveprofile'] = array_merge($_SESSION['glpiactiveprofile'] ?? [], [
                'plugin_grcmanager' => ALLSTANDARDRIGHT,
                'ticketrecurrent'   => ALLSTANDARDRIGHT,
                'tickettemplate'    => ALLSTANDARDRIGHT,
            ]);
            $_SESSION['glpiactiveentities'] ??= [0];
            $DB->update('glpi_plugin_grcmanager_periodicreviews', ['is_active' => 0], [1]);
            $DB->update('glpi_plugin_grcmanager_periodicreviews', ['is_active' => 1], ['review_key' => 'soa']);
            $total = countElementsInTable('glpi_plugin_grcmanager_periodicreviews');

            $activated = (new GrcmanagerPeriodicReviewBuilder())->build($this->buildConfig(true));

            $this->assertSame($total - 1, $activated, 'La revue déjà active n\'est pas comptée ni modifiée.');
            $this->assertSame(
                $total,
                countElementsInTable('glpi_plugin_grcmanager_periodicreviews', ['is_active' => 1])
            );
        } finally {
            $DB->rollBack();
            $_SESSION['glpiactiveprofile'] = $savedProfile;
        }
    }
}
