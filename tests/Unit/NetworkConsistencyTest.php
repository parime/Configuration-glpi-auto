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

use GlpiPlugin\Configurationglpiauto\AddressPlan\NetworkConsistency;
use PHPUnit\Framework\TestCase;

/**
 * Issue #344 : règles de cohérence du réseau, sur des adresses de documentation (RFC 5737, 3849).
 */
final class NetworkConsistencyTest extends TestCase
{
    public function testIgnorableAddressesAndPrefix(): void
    {
        $this->assertTrue(NetworkConsistency::isIgnorable('127.0.0.1'));
        $this->assertTrue(NetworkConsistency::isIgnorable('169.254.10.2'));
        $this->assertTrue(NetworkConsistency::isIgnorable('fe80::1'));
        $this->assertTrue(NetworkConsistency::isIgnorable('pas une adresse'));
        $this->assertFalse(NetworkConsistency::isIgnorable('192.0.2.10'));
        $this->assertSame(24, NetworkConsistency::prefixOf('255.255.255.0'));
        $this->assertSame(26, NetworkConsistency::prefixOf('255.255.255.192'));
        $this->assertSame(64, NetworkConsistency::prefixOf('ffff:ffff:ffff:ffff::'));
        $this->assertNull(NetworkConsistency::prefixOf('255.0.255.0'), 'Masque non contigu.');
    }

    public function testDuplicateAddresses(): void
    {
        $duplicates = NetworkConsistency::duplicateAddresses([
            ['address' => '192.0.2.10', 'owner' => 'Ordinateur PC-A'],
            ['address' => '192.0.2.10', 'owner' => 'Ordinateur PC-B'],
            ['address' => '192.0.2.11', 'owner' => 'Ordinateur PC-A'],
            ['address' => '192.0.2.11', 'owner' => 'Ordinateur PC-A'],
            ['address' => '127.0.0.1', 'owner' => 'Ordinateur PC-A'],
            ['address' => '127.0.0.1', 'owner' => 'Ordinateur PC-B'],
        ]);

        $this->assertSame(['192.0.2.10' => ['Ordinateur PC-A', 'Ordinateur PC-B']], $duplicates);
    }

    public function testOverlapsIgnoreDeclaredParents(): void
    {
        $pairs = NetworkConsistency::overlappingNetworks([
            ['id' => 1, 'cidr' => '198.51.100.0/24', 'ancestors' => []],
            ['id' => 2, 'cidr' => '198.51.100.0/26', 'ancestors' => [1]],
            ['id' => 3, 'cidr' => '198.51.100.0/24', 'ancestors' => []],
            ['id' => 4, 'cidr' => '203.0.113.0/24', 'ancestors' => []],
        ]);

        $this->assertSame([[1, 3], [2, 3]], $pairs, 'Le /26 rattaché au /24 n\'est pas un chevauchement.');
    }

    public function testOutsideAddresses(): void
    {
        $outside = NetworkConsistency::outsideAddresses(
            ['192.0.2.10', '198.51.100.7', '2001:db8::5', '127.0.0.1', '198.51.100.7'],
            ['192.0.2.0/24']
        );

        $this->assertSame(['198.51.100.7'], $outside, 'IPv6 non contrôlée : aucun réseau IPv6 déclaré.');
    }
}
