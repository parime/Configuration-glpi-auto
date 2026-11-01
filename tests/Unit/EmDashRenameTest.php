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

use GlpiPlugin\Configurationglpiauto\Install\EmDashRename;
use PHPUnit\Framework\TestCase;

/**
 * Issue #390 : correspondance entre les noms d'origine (tiret cadratin) et les noms actuels.
 */
final class EmDashRenameTest extends TestCase
{
    public function testFixedNamesInEveryLanguage(): void
    {
        $this->assertSame('Onboarding : Arrivée collaborateur', EmDashRename::rename("Onboarding \u{2014} Arrivée collaborateur"));
        $this->assertSame('Onboarding: New employee', EmDashRename::rename("Onboarding \u{2014} New employee"));
        $this->assertSame(
            'Notification personnalisée : Nouveau ticket',
            EmDashRename::rename("Notification personnalisée \u{2014} Nouveau ticket"),
        );
    }

    public function testVariablePartsAreKept(): void
    {
        $this->assertSame('Horaires - Agence de Lyon', EmDashRename::rename("Horaires \u{2014} Agence de Lyon"));
        $this->assertSame(
            'SLA standard : entité #12 - Très haute',
            EmDashRename::rename("SLA standard \u{2014} entité #12 \u{2014} Très haute"),
        );
        // Préfixe suivi d'un libellé ajouté par le code.
        $this->assertSame('Numéro de série unique - Ordinateurs', EmDashRename::rename("Numéro de série unique \u{2014} Ordinateurs"));
    }

    public function testNamesNotGivenByThePluginAreLeftAlone(): void
    {
        $this->assertNull(EmDashRename::rename("Mon gabarit \u{2014} personnel"));
        $this->assertNull(EmDashRename::rename('Onboarding : Arrivée collaborateur'));
    }
}
