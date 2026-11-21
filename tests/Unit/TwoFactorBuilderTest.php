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

namespace GlpiPlugin\Configurationglpiauto\Tests\Unit;

use GlpiPlugin\Configurationglpiauto\Blueprint\BlueprintSerializer;
use GlpiPlugin\Configurationglpiauto\TwoFactorBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Issue #464 : normalisation des profils retenus pour la double authentification (fonction pure,
 * sans GLPI). Noms de profils fictifs ou natifs uniquement.
 */
final class TwoFactorBuilderTest extends TestCase
{
    public function testProfileNamesAreTrimmedDeduplicatedAndEmptyValuesDropped(): void
    {
        $this->assertSame(
            ['Super-Admin', 'Technician'],
            TwoFactorBuilder::normalizeProfileNames([' Super-Admin ', '', 'Technician', 'Super-Admin', ['nested'], null])
        );
    }

    public function testStoredJsonIsDecodedAndInvalidJsonGivesAnEmptyList(): void
    {
        $this->assertSame(['Admin'], TwoFactorBuilder::normalizeProfileNames('["Admin"]'));
        $this->assertSame([], TwoFactorBuilder::normalizeProfileNames('not json'));
        $this->assertSame([], TwoFactorBuilder::normalizeProfileNames(null));
    }

    public function testRecommendedProfilesAreTheNativePrivilegedOnes(): void
    {
        $this->assertSame(['Super-Admin', 'Admin', 'Technician'], TwoFactorBuilder::RECOMMENDED_PROFILE_NAMES);
    }

    public function testBlueprintExportKeepsTheProfileNamesAsARealList(): void
    {
        $export = BlueprintSerializer::export(
            ['twofa_enforcement_enabled' => 1, 'twofa_profiles' => '["Admin","Technician"]'],
            'Fictitious profile',
            '0.0.0'
        );

        $this->assertSame(['Admin', 'Technician'], $export['config']['twofa_profiles']);
    }
}
