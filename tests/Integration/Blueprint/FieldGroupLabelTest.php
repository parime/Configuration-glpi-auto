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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration\Blueprint;

use GlpiPlugin\Configurationglpiauto\Blueprint\FieldGroupLabel;
use PHPUnit\Framework\TestCase;

/**
 * Contrairement à `BlueprintSerializer`, cette classe appelle `__()` (traduction GLPI) : elle a
 * donc besoin d'une vraie instance GLPI bootée pour être exercée, d'où sa place dans `tests/
 * Integration/` plutôt que `tests/Unit/` (confirmé en pratique : `__()` est indéfini sous le
 * bootstrap PHP pur de `phpunit.xml.dist`).
 */
final class FieldGroupLabelTest extends TestCase
{
    private string $previousLanguage = '';

    /** Libellés comparés en anglais quelle que soit la langue de l'instance (issue #501). */
    protected function setUp(): void
    {
        parent::setUp();
        $this->previousLanguage = (string) ($_SESSION['glpilanguage'] ?? '');
        $_SESSION['glpilanguage'] = 'en_GB';
        \Session::loadLanguage('en_GB');
    }

    protected function tearDown(): void
    {
        $_SESSION['glpilanguage'] = $this->previousLanguage;
        \Session::loadLanguage($this->previousLanguage);
        parent::tearDown();
    }

    public function testKnownPrefixReturnsItsTranslatedLabel(): void
    {
        // Anglais forcé par setUp() : le libellé traduit est comparé au texte de en_GB.po, ce qui
        // vérifie aussi que le domaine de traduction est le bon.
        $this->assertSame('SLA', FieldGroupLabel::for('sla_tiers'));
        $this->assertSame('Entities', FieldGroupLabel::for('entity_mode'));
    }

    public function testUnknownPrefixFallsBackToTheCapitalizedPrefixItself(): void
    {
        // Dégradation propre pour un futur champ Config dont le préfixe n'a pas encore de libellé
        // traduit : jamais une exception, jamais une chaîne vide.
        $this->assertSame('Zorglub', FieldGroupLabel::for('zorglub_enabled'));
    }

    public function testFieldWithNoUnderscoreUsesTheWholeFieldAsThePrefix(): void
    {
        $this->assertSame('Foo', FieldGroupLabel::for('foo'));
    }

    public function testSamePrefixAlwaysReturnsTheSameLabelRegardlessOfTheRestOfTheFieldName(): void
    {
        $this->assertSame(FieldGroupLabel::for('sla_tiers'), FieldGroupLabel::for('sla_astreinte'));
    }
}
