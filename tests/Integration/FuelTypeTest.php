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

use GlpiPlugin\Configurationglpiauto\FuelType;
use GlpiPlugin\Configurationglpiauto\Profile;
use PHPUnit\Framework\TestCase;

/**
 * `FuelType` is a plain `CommonDropdown` this plugin owns end-to-end (table included) — unlike
 * every other dropdown this plugin populates, GLPI has no native concept for it at all. This suite
 * only confirms the real table/CRUD wiring actually works (`DropdownType::getFormInput()`'s
 * `Dropdown::show($itemtype, ...)` needs a real `CommonDBTM`-backed table with a `name` field) —
 * `VehicleAssetBuilder` (the class that actually seeds/consumes rows) has its own dedicated test.
 */
final class FuelTypeTest extends TestCase
{
    /**
     * Compared against the same `_n()` call rather than a hardcoded literal — CI's default session
     * language isn't necessarily French (confirmed: CI returned "Fuel type" for the same call), so
     * asserting a specific translated string would depend on which locale happens to be active.
     */
    public function testGetTypeNameUsesTheRealTranslationDomainForSingularAndPlural(): void
    {
        $this->assertSame(_n('Type de carburant', 'Types de carburant', 1, 'configurationglpiauto'), FuelType::getTypeName(1));
        $this->assertSame(_n('Type de carburant', 'Types de carburant', 2, 'configurationglpiauto'), FuelType::getTypeName(2));
        $this->assertNotSame(FuelType::getTypeName(1), FuelType::getTypeName(2), 'Singular and plural must actually differ.');
    }

    public function testGetIconReturnsAGasStationIcon(): void
    {
        $this->assertSame('ti ti-gas-station', FuelType::getIcon());
    }

    /**
     * Regression guard: `$rightname` used to be the native GLPI `'config'` right, which core's own
     * `\Config::getRights()` strips CREATE/DELETE/PURGE from (a per-entity singleton has no concept
     * of creating/deleting rows) — meaning "Ajouter"/"Purger" on this dropdown's own screen 403'd
     * for everyone, super-admin included. Must be this plugin's own dedicated right instead, the
     * exact fix `Profile.php`'s own docblock explains this right was created to make possible.
     */
    public function testRightnameIsThisPluginsOwnDedicatedRightNotTheNativeConfigRight(): void
    {
        $this->assertSame(Profile::RIGHT_CONFIG, FuelType::$rightname);
        $this->assertNotSame('config', FuelType::$rightname);
    }

    public function testASuperAdminCanCreateAndPurgeThroughTheRealCanCheck(): void
    {
        $item = new FuelType();
        $createInput = ['name' => 'Test — CREATE right'];
        $this->assertTrue($item->can(-1, CREATE, $createInput), 'CREATE must not be stripped, unlike the native config right.');

        $id = (int) $item->add(['name' => 'Test — CREATE right']);
        $this->assertGreaterThan(0, $id);
        $this->assertTrue($item->can($id, PURGE), 'PURGE must not be stripped, unlike the native config right.');

        $item->delete(['id' => $id], true);
    }

    public function testCanBeAddedToAndReadBackFromItsOwnRealTable(): void
    {
        $item = new FuelType();
        $id = $item->add(['name' => 'Test — Hydrogène']);

        $this->assertGreaterThan(0, $id);

        $reloaded = new FuelType();
        $this->assertTrue($reloaded->getFromDB($id));
        $this->assertSame('Test — Hydrogène', $reloaded->fields['name']);

        $reloaded->delete(['id' => $id], true);
    }
}
