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
use GlpiPlugin\Configurationglpiauto\SolutionLibraryBuilder;
use PHPUnit\Framework\TestCase;
use SolutionTemplate;
use SolutionType;

/**
 * Runs against a real GLPI instance. Focused on the 11th "Demande incomplète" template added to the
 * "Informationnel" category (forum GLPI officiel, topic 294630) — the other 10 pre-existing templates
 * already have implicit coverage via every prior sprint's own manual verification (see CHANGELOG.md),
 * not re-tested here.
 */
final class SolutionLibraryBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        $template = new SolutionTemplate();
        if ($template->getFromDBByCrit(['name' => 'Demande incomplète'])) {
            $template->delete(['id' => $template->getID()], true);
        }
    }

    public function testIncompleteRequestTemplateIsCreatedUnderInformationnelType(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'solution_library_enabled' => 1,
            'solution_type_icons_enabled' => 0,
            'solution_template_icons_enabled' => 0,
        ]);

        (new SolutionLibraryBuilder())->build($config);

        $type = new SolutionType();
        $this->assertTrue($type->getFromDBByCrit(['name' => 'Informationnel']));

        $template = new SolutionTemplate();
        $this->assertTrue($template->getFromDBByCrit(['name' => 'Demande incomplète']));
        $this->assertSame((int) $type->getID(), (int) $template->fields['solutiontypes_id']);
        $this->assertStringContainsString('informations', $template->fields['content']);
    }

    public function testBuildIsIdempotentForTheNewTemplate(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'solution_library_enabled' => 1,
            'solution_type_icons_enabled' => 0,
            'solution_template_icons_enabled' => 0,
        ]);
        $builder = new SolutionLibraryBuilder();

        $builder->build($config);
        $builder->build($config);

        $template = new SolutionTemplate();
        $matches = $template->find(['name' => 'Demande incomplète']);
        $this->assertCount(1, $matches);
    }
}
