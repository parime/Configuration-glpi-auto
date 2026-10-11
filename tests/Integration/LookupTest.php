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

use GlpiPlugin\Configurationglpiauto\Lookup;
use Manufacturer;
use PHPUnit\Framework\TestCase;

/**
 * Doublons de noms (autorisés par GLPI, et comparaison MySQL insensible à la casse) : l'assistant ne
 * doit plus planter (TooManyResultsException de getFromDBByCrit()) mais retenir la plus ancienne ligne.
 */
final class LookupTest extends TestCase
{
    /** @var list<int> */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $id) {
            (new Manufacturer())->delete(['id' => $id], true);
        }
    }

    public function testTheOldestOfSeveralDuplicatesIsLoaded(): void
    {
        $name = 'PHPUnit Lookup ' . mt_rand();
        $this->created[] = $first = (int) (new Manufacturer())->add(['name' => $name]);
        $this->created[] = (int) (new Manufacturer())->add(['name' => mb_strtoupper($name)]);

        $item = new Manufacturer();
        $this->assertTrue(Lookup::loadFirst($item, ['name' => $name]));
        $this->assertSame($first, $item->getID());
    }

    public function testNothingFoundReturnsFalse(): void
    {
        $this->assertFalse(Lookup::loadFirst(new Manufacturer(), ['name' => 'PHPUnit absent ' . mt_rand()]));
    }
}
