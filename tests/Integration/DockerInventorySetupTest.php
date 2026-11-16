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

use GlpiPlugin\Configurationglpiauto\DockerInventorySetup;
use PHPUnit\Framework\TestCase;

/**
 * Issue #452 : étape « Inventaire Docker » de l'assistant. La CI n'installe pas Inventaire Docker
 * à côté de ce plugin : seul le cas « plugin absent » y est vérifié ; sur une instance où il est
 * actif, la création, le chiffrement, le refus des doublons et le refus sans droit le sont aussi.
 * Le test de connexion est remplacé par un test fictif (aucun réseau). Les assertions portent sur
 * les niveaux et la base, jamais sur des textes traduits (CI en anglais, instance de dev en français).
 */
final class DockerInventorySetupTest extends TestCase
{
    private const URL = 'https://portainer-452.exemple.test:9443';
    private const OTHER_URL = 'https://portainer-452-echec.exemple.test:9443';
    private const SECRET = 'ptr_jeton_fictif_452';

    protected function tearDown(): void
    {
        if (!DockerInventorySetup::isAvailable()) {
            return;
        }
        $class = DockerInventorySetup::CONNECTION_CLASS;
        $connection = new $class();
        foreach ([self::URL, self::OTHER_URL] as $url) {
            foreach ($connection->find(['url' => $url]) as $row) {
                $connection->delete(['id' => $row['id']], true);
            }
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private static function okTester(array $result = ['ok' => true, 'warning' => true, 'message' => 'fictif']): \Closure
    {
        return static fn (\CommonDBTM $connection): array => $result;
    }

    private static function countFor(string $url): int
    {
        $class = DockerInventorySetup::CONNECTION_CLASS;

        return count((new $class())->find(['url' => $url]));
    }

    public function testStepIsHiddenWithoutThePlugin(): void
    {
        if (DockerInventorySetup::isAvailable()) {
            $view = DockerInventorySetup::viewData();
            $this->assertIsArray($view);
            $this->assertContains(DockerInventorySetup::TYPE_PORTAINER, $view['types']);
            $this->assertContains(DockerInventorySetup::TYPE_WUD, $view['types']);
            $this->assertContains(DockerInventorySetup::TYPE_HARBOR, $view['types']);

            return;
        }

        $this->assertNull(DockerInventorySetup::viewData(), 'étape masquée sans le plugin');
        $this->assertFalse(DockerInventorySetup::canCreate());
        $this->assertSame('', DockerInventorySetup::pluginPageUrl());
        $result = (new DockerInventorySetup(self::okTester()))->save('portainer', ['url' => self::URL, 'api_key' => self::SECRET]);
        $this->assertSame(DockerInventorySetup::LEVEL_ERROR, $result['level']);
        $this->assertArrayNotHasKey('saved', $result);
    }

    public function testStepTemplateIsRenderedOnlyWithItsData(): void
    {
        $renderer = \Glpi\Application\View\TemplateRenderer::getInstance();
        $html = $renderer->render('@configurationglpiauto/wizard_docker_inventory.html.twig', [
            'step_number' => 20,
            'docker_inventory' => [
                'can_create' => true,
                'types' => ['portainer', 'wud', 'harbor'],
                'existing' => [],
                'plugin_url' => '/plugins/dockerinventory/front/connection.php',
                'managed_registries' => 'registry.exemple.test:5000',
            ],
        ]);
        foreach (['portainer', 'wud', 'harbor'] as $type) {
            $this->assertStringContainsString('data-type="' . $type . '"', $html);
        }
        $this->assertStringNotContainsString('data-type="kubernetes"', $html);
        $this->assertStringContainsString('/plugins/dockerinventory/front/connection.php', $html);
        // Champs sans attribut name : jamais envoyés avec « Terminer ».
        $this->assertDoesNotMatchRegularExpression('/<(input|textarea)[^>]*\sname=/', $html);

        $refused = $renderer->render('@configurationglpiauto/wizard_docker_inventory.html.twig', [
            'step_number' => 20,
            'docker_inventory' => ['can_create' => false, 'types' => ['portainer'], 'existing' => [], 'plugin_url' => '', 'managed_registries' => ''],
        ]);
        $this->assertStringNotContainsString('cga-docker-source', $refused, 'sans droit : aucun formulaire');
    }

    public function testConnectionIsCreatedEncryptedAndNeverOverwritten(): void
    {
        if (!DockerInventorySetup::isAvailable()) {
            $this->assertNull(DockerInventorySetup::viewData());

            return;
        }
        $seen = [];
        $tester = static function (\CommonDBTM $connection) use (&$seen): array {
            $seen[] = (string) ($connection->fields['api_key'] ?? '');

            return ['ok' => true, 'warning' => true, 'message' => 'fictif'];
        };

        // Test seul : rien n'est enregistré, la clé testée est déjà chiffrée.
        $tested = (new DockerInventorySetup($tester))->test('portainer', ['url' => self::URL, 'api_key' => self::SECRET]);
        $this->assertSame(DockerInventorySetup::LEVEL_WARNING, $tested['level'], 'un avertissement reste un avertissement');
        $this->assertSame(0, self::countFor(self::URL));
        $this->assertNotSame(self::SECRET, $seen[0]);
        $this->assertSame(self::SECRET, (new \GLPIKey())->decrypt($seen[0]));

        $saved = (new DockerInventorySetup($tester))->save('portainer', [
            'name' => 'Portainer 452 (test)',
            'url' => self::URL . '/',
            'api_key' => self::SECRET,
        ]);
        $this->assertSame(DockerInventorySetup::LEVEL_WARNING, $saved['level']);
        $this->assertTrue($saved['saved'] ?? false);
        $this->assertStringNotContainsString(self::SECRET, (string) json_encode($saved), 'secret jamais renvoyé');

        $class = DockerInventorySetup::CONNECTION_CLASS;
        $connection = new $class();
        $this->assertTrue($connection->getFromDB((int) $saved['id']));
        $this->assertSame(self::URL, $connection->fields['url'], 'adresse normalisée par le plugin');
        $this->assertNotSame(self::SECRET, $connection->fields['api_key'], 'secret chiffré en base');
        $this->assertSame(self::SECRET, (new \GLPIKey())->decrypt($connection->fields['api_key']));
        $this->assertSame(self::SECRET, $connection->getDecryptedApiKey());

        // Même type et même adresse : refusé, la connexion existante reste telle quelle.
        $duplicate = (new DockerInventorySetup($tester))->save('portainer', ['url' => self::URL, 'api_key' => 'autre_jeton']);
        $this->assertSame(DockerInventorySetup::LEVEL_ERROR, $duplicate['level']);
        $this->assertArrayNotHasKey('saved', $duplicate);
        $this->assertSame(1, self::countFor(self::URL));
        $this->assertTrue($connection->getFromDB((int) $saved['id']));
        $this->assertSame(self::SECRET, $connection->getDecryptedApiKey());
    }

    public function testFailedTestSavesNothingAndLeavesNoSessionMessage(): void
    {
        if (!DockerInventorySetup::isAvailable()) {
            $this->assertFalse(DockerInventorySetup::canCreate());

            return;
        }
        $before = $_SESSION['MESSAGE_AFTER_REDIRECT'] ?? null;

        $failed = (new DockerInventorySetup(self::okTester(['ok' => false, 'message' => 'fictif'])))
            ->save('portainer', ['url' => self::OTHER_URL, 'api_key' => self::SECRET]);
        $this->assertSame(DockerInventorySetup::LEVEL_ERROR, $failed['level']);
        $this->assertSame(0, self::countFor(self::OTHER_URL));

        // Refus du plugin (HTTP sans l'autoriser, clé manquante) : rendu, pas laissé en session.
        $http = (new DockerInventorySetup(self::okTester()))->test('portainer', ['url' => 'http://portainer-452.exemple.test', 'api_key' => self::SECRET]);
        $this->assertSame(DockerInventorySetup::LEVEL_ERROR, $http['level']);
        $this->assertNotSame('', $http['message']);
        $noKey = (new DockerInventorySetup(self::okTester()))->save('portainer', ['url' => self::OTHER_URL]);
        $this->assertSame(DockerInventorySetup::LEVEL_ERROR, $noKey['level']);
        $this->assertSame(0, self::countFor(self::OTHER_URL));
        $this->assertSame($before, $_SESSION['MESSAGE_AFTER_REDIRECT'] ?? null);
    }

    public function testRefusedWithoutTheRight(): void
    {
        if (!DockerInventorySetup::isAvailable()) {
            $result = (new DockerInventorySetup(self::okTester()))->save('portainer', ['url' => self::URL, 'api_key' => self::SECRET]);
            $this->assertSame(DockerInventorySetup::LEVEL_ERROR, $result['level']);

            return;
        }
        $class = DockerInventorySetup::CONNECTION_CLASS;
        $right = $class::RIGHTNAME;
        $original = $_SESSION['glpiactiveprofile'][$right] ?? null;
        try {
            $_SESSION['glpiactiveprofile'][$right] = READ;
            $this->assertFalse(DockerInventorySetup::canCreate());
            $view = DockerInventorySetup::viewData();
            $this->assertIsArray($view);
            $this->assertFalse($view['can_create']);
            $this->assertSame([], $view['existing']);

            $setup = new DockerInventorySetup(self::okTester());
            $this->assertSame(DockerInventorySetup::LEVEL_ERROR, $setup->save('portainer', ['url' => self::URL, 'api_key' => self::SECRET])['level']);
            $this->assertSame(DockerInventorySetup::LEVEL_ERROR, $setup->test('portainer', ['url' => self::URL, 'api_key' => self::SECRET])['level']);
            $this->assertSame(DockerInventorySetup::LEVEL_ERROR, $setup->saveManagedRegistries('registry.exemple.test')['level']);
            $this->assertSame(0, self::countFor(self::URL));
        } finally {
            $_SESSION['glpiactiveprofile'][$right] = $original;
        }
    }
}
