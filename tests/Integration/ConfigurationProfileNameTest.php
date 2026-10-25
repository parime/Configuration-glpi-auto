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

use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use PHPUnit\Framework\TestCase;

/**
 * Deux profils du même nom étaient indiscernables à l'étape 1 de l'assistant : importer deux fois
 * le même Blueprint en créait autant de copies homonymes.
 */
final class ConfigurationProfileNameTest extends TestCase
{
    private const NAME = 'PHPUnit profil importé';

    /** @var list<int> */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $id) {
            (new ConfigurationProfile())->delete(['id' => $id], true);
        }
    }

    private function add(string $name): ConfigurationProfile
    {
        $profile = new ConfigurationProfile();
        $this->created[] = (int) $profile->add(['name' => $name, 'type' => 'custom', 'snapshot' => '{}']);

        return $profile;
    }

    public function testSecondProfileWithTheSameNameGetsANumber(): void
    {
        $this->assertSame(self::NAME, $this->add(self::NAME)->fields['name']);
        $this->assertSame(self::NAME . ' (2)', $this->add(self::NAME)->fields['name']);
        $this->assertSame(self::NAME . ' (3)', $this->add(self::NAME)->fields['name']);
    }

    public function testRenamingAProfileToItsOwnNameKeepsIt(): void
    {
        $profile = $this->add(self::NAME);
        $profile->update(['id' => $profile->getID(), 'name' => self::NAME]);
        $profile->getFromDB($profile->getID());
        $this->assertSame(self::NAME, $profile->fields['name']);

        $other = $this->add('PHPUnit autre profil');
        $other->update(['id' => $other->getID(), 'name' => self::NAME]);
        $other->getFromDB($other->getID());
        $this->assertSame(self::NAME . ' (2)', $other->fields['name']);
    }
}
