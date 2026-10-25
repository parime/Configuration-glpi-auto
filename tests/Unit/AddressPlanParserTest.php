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

use GlpiPlugin\Configurationglpiauto\AddressPlan\AddressPlanParser;
use PHPUnit\Framework\TestCase;

/**
 * Issue #343 : lecture d'un plan d'adressage fictif (adresses de documentation, RFC 5737 et 3849).
 */
final class AddressPlanParserTest extends TestCase
{
    public function testPastedSpreadsheetWithFrenchHeaders(): void
    {
        $text = "Site\tVLAN\tNom\tRéseau\tPasserelle\tRôle\n"
            . "Siège\t10\tPostes\t192.0.2.0/25\t192.0.2.1\tworkstations\n"
            . "Siège\t20\t\t192.0.2.128/25\t\tservers\n"
            . "Agence\t30\tVoix\t2001:db8:30::/64\t2001:db8:30::1\tvoice\n";
        $rows = AddressPlanParser::parse($text)['rows'];

        $this->assertCount(3, $rows);
        $this->assertSame([[], [], []], array_column($rows, 'errors'));
        $this->assertSame('192.0.2.0/25', $rows[0]['network']);
        $this->assertSame('VLAN 20', $rows[1]['name'], 'Nom par défaut.');
        $this->assertSame('2001:db8:30::/64', $rows[2]['network']);
        $this->assertSame(4, $rows[2]['line']);
    }

    public function testErrorsAreReportedLineByLine(): void
    {
        $text = "site;vlan;nom;reseau;passerelle\n"
            . "Siège;5000;Trop grand;198.51.100.0/24;\n"
            . "Siège;11;Pas aligné;198.51.100.7/24;\n"
            . "Siège;12;Illisible;198.51.100.0/33;\n"
            . "Siège;13;Passerelle;203.0.113.0/26;203.0.113.200\n"
            . ";14;Sans site;203.0.113.64/26;\n"
            . "Siège;15;Chevauche;203.0.113.0/24;\n";
        $rows = AddressPlanParser::parse($text)['rows'];

        $this->assertSame(['vlan_range'], $rows[0]['errors']);
        $this->assertSame(['cidr_not_network:198.51.100.0/24'], $rows[1]['errors']);
        $this->assertSame(['cidr_invalid'], $rows[2]['errors']);
        $this->assertSame(['gateway_outside'], $rows[3]['errors']);
        $this->assertSame(['site_missing'], $rows[4]['errors']);
        $this->assertSame(['overlap:5', 'overlap:6'], $rows[5]['errors'], 'Les deux /26 sont dans ce /24.');
    }

    public function testPositionalColumnsWithoutHeader(): void
    {
        $rows = AddressPlanParser::parse("Siège,40,Imprimantes,192.0.2.64/26,192.0.2.65")['rows'];

        $this->assertSame(40, $rows[0]['vlan']);
        $this->assertSame('Imprimantes', $rows[0]['name']);
        $this->assertSame([], $rows[0]['errors']);
    }

    public function testCidrHelpers(): void
    {
        $this->assertTrue(AddressPlanParser::contains('192.0.2.0/24', '192.0.2.254'));
        $this->assertFalse(AddressPlanParser::contains('192.0.2.0/24', '198.51.100.1'));
        $this->assertFalse(AddressPlanParser::contains('192.0.2.0/24', '2001:db8::1'));
        $this->assertTrue(AddressPlanParser::overlaps('10.0.0.0/8', '10.20.30.0/24'));
        $this->assertFalse(AddressPlanParser::overlaps('10.0.0.0/24', '10.0.1.0/24'));
        $this->assertFalse(AddressPlanParser::overlaps('10.0.0.0/8', '2001:db8::/32'));
    }

    public function testSiteTemplate(): void
    {
        $this->assertNull(AddressPlanParser::template('Siège', '10.20.0.0/24'), 'Un /16 est attendu.');
        $this->assertNull(AddressPlanParser::template('Siège', '10.20.5.0/16'), 'Adresse de réseau attendue.');

        $text = (string) AddressPlanParser::template('Siège', '10.20.0.0/16', ['guests' => 'Invités']);
        $rows = AddressPlanParser::parse($text)['rows'];
        $this->assertCount(count(AddressPlanParser::TEMPLATE_ROLES), $rows);
        $this->assertSame([[]], array_values(array_unique(array_column($rows, 'errors'), SORT_REGULAR)));
        $guests = $rows[5];
        $this->assertSame([60, 'Invités', '10.20.60.0/24', '10.20.60.1'], [
            $guests['vlan'], $guests['name'], $guests['network'], $guests['gateway'],
        ]);
    }
}
