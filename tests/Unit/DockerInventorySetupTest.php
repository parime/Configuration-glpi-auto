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

use GlpiPlugin\Configurationglpiauto\DockerInventorySetup;
use PHPUnit\Framework\TestCase;

/**
 * Issue #452 : saisie de l'étape « Inventaire Docker » (fonctions pures, sans GLPI ni réseau).
 * Adresses et comptes fictifs uniquement.
 */
final class DockerInventorySetupTest extends TestCase
{
    public function testInputKeepsOnlyTheFieldsOfItsType(): void
    {
        $input = DockerInventorySetup::buildInput('portainer', [
            'name'          => '  ',
            'url'           => ' https://portainer.exemple.test:9443 ',
            'api_key'       => ' ptr_jeton fictif ',
            'username'      => 'ignoré pour Portainer',
            'registry_host' => 'ignoré aussi',
            'allow_http'    => '1',
            'ticket_enabled' => '1',
        ], 3);

        $this->assertSame('portainer', $input['type']);
        $this->assertSame(3, $input['entities_id']);
        $this->assertSame('https://portainer.exemple.test:9443', $input['url']);
        $this->assertSame(' ptr_jeton fictif ', $input['api_key'], 'secret transmis tel quel');
        $this->assertSame(1, $input['allow_http']);
        $this->assertSame('Portainer - portainer.exemple.test', $input['name']);
        $this->assertArrayNotHasKey('username', $input);
        $this->assertArrayNotHasKey('registry_host', $input);
        $this->assertArrayNotHasKey('ticket_enabled', $input, 'rien hors des champs de l\'étape');
    }

    public function testHarborAndKubernetesFields(): void
    {
        $harbor = DockerInventorySetup::buildInput('harbor', [
            'name' => 'Harbor production',
            'url' => 'https://harbor.exemple.test',
            'registry_host' => ' registry.exemple.test:5000 ',
            'username' => 'robot$lecture',
            'api_key' => 'secret',
        ]);
        $this->assertSame('Harbor production', $harbor['name']);
        $this->assertSame('registry.exemple.test:5000', $harbor['registry_host']);
        $this->assertSame('robot$lecture', $harbor['username']);
        $this->assertSame(0, $harbor['allow_http'], 'HTTP refusé par défaut');

        $kubernetes = DockerInventorySetup::buildInput('kubernetes', [
            'url' => 'https://kubernetes.exemple.test:6443',
            'api_key' => 'jeton',
            'ca_certificate' => "-----BEGIN CERTIFICATE-----\nAAAA\n-----END CERTIFICATE-----\n",
            'allow_http' => '1',
        ]);
        $this->assertArrayNotHasKey('allow_http', $kubernetes, 'Kubernetes : HTTPS seulement');
        $this->assertStringEndsWith("-----END CERTIFICATE-----\n", $kubernetes['ca_certificate']);
    }

    public function testUnknownTypeKeepsNoField(): void
    {
        $input = DockerInventorySetup::buildInput('inconnu', ['url' => 'https://exemple.test', 'api_key' => 'x']);
        $this->assertArrayNotHasKey('url', $input);
        $this->assertArrayNotHasKey('api_key', $input);
    }

    public function testNonScalarValuesAreDropped(): void
    {
        $input = DockerInventorySetup::buildInput('wud', ['url' => ['https://wud.exemple.test'], 'username' => null]);
        $this->assertSame('', $input['url']);
        $this->assertSame('', $input['username']);
        $this->assertSame("What's Up Docker (WUD)", $input['name']);
    }

    public function testAWarningStaysAWarning(): void
    {
        $this->assertSame(DockerInventorySetup::LEVEL_OK, DockerInventorySetup::level(['ok' => true, 'warning' => false]));
        $this->assertSame(DockerInventorySetup::LEVEL_OK, DockerInventorySetup::level(['ok' => true]));
        $this->assertSame(DockerInventorySetup::LEVEL_WARNING, DockerInventorySetup::level(['ok' => true, 'warning' => true]));
        $this->assertSame(DockerInventorySetup::LEVEL_ERROR, DockerInventorySetup::level(['ok' => false, 'warning' => true]));
        $this->assertSame(DockerInventorySetup::LEVEL_ERROR, DockerInventorySetup::level([]));
    }
}
