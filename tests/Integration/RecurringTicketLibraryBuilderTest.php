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
use GlpiPlugin\Configurationglpiauto\RecurringTicketLibraryBuilder;
use PHPUnit\Framework\TestCase;
use TicketTemplate;

final class RecurringTicketLibraryBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        global $DB;
        foreach (RecurringTicketLibraryBuilder::getLibraryPreview() as $template) {
            $item = new TicketTemplate();
            if ($item->getFromDBByCrit(['name' => $template['name']])) {
                $DB->delete('glpi_tickettemplatepredefinedfields', ['tickettemplates_id' => $item->getID()]);
                $item->delete(['id' => $item->getID()], true);
            }
        }
    }

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'recurring_ticket_library_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testDisabledReturnsZeroAndCreatesNothing(): void
    {
        $count = (new RecurringTicketLibraryBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testCreatesTemplatesWithPredefinedTitleAndContent(): void
    {
        (new RecurringTicketLibraryBuilder())->build($this->buildConfig(true));

        $template = new TicketTemplate();
        $this->assertTrue($template->getFromDBByCrit(['name' => 'Revue mensuelle des comptes utilisateurs inactifs']));

        global $DB;
        $rows = iterator_to_array($DB->request([
            'FROM' => 'glpi_tickettemplatepredefinedfields',
            'WHERE' => ['tickettemplates_id' => $template->getID()],
        ]));
        $byNum = [];
        foreach ($rows as $row) {
            $byNum[(int) $row['num']] = $row['value'];
        }
        $this->assertSame('Revue mensuelle des comptes utilisateurs inactifs', $byNum[1] ?? null);
        $this->assertStringContainsString('comptes utilisateurs', $byNum[21] ?? '');
    }

    /**
     * The whole point of #149's careful scoping (see this class's own docblock): content only,
     * never a live `TicketRecurrent` schedule guessed on the admin's behalf.
     */
    public function testNeverActivatesTicketRecurrent(): void
    {
        (new RecurringTicketLibraryBuilder())->build($this->buildConfig(true));

        global $DB;
        $count = $DB->request(['COUNT' => 'c', 'FROM' => 'glpi_ticketrecurrents'])->current()['c'];
        $this->assertSame(0, $count);
    }

    public function testBuildIsIdempotent(): void
    {
        $builder = new RecurringTicketLibraryBuilder();
        $config = $this->buildConfig(true);

        $first = $builder->build($config);
        $second = $builder->build($config);

        $this->assertSame($first, $second);

        global $DB;
        $count = $DB->request(['COUNT' => 'c', 'FROM' => 'glpi_tickettemplates', 'WHERE' => ['name' => 'Revue mensuelle des comptes utilisateurs inactifs']])->current()['c'];
        $this->assertSame(1, $count);
    }
}
