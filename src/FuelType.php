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

namespace GlpiPlugin\Configurationglpiauto;

use CommonDropdown;

/**
 * A plain, flat dropdown (no `CommonTreeDropdown` nesting needed) — GLPI has no native "fuel type"
 * concept anywhere, unlike every other dropdown this plugin populates (`Manufacturer`, `State`,
 * `ITILCategory`...), so this is the first one this plugin owns and creates the table for, rather
 * than just seeding rows into an existing native table. Exists solely to be the `itemtype` target
 * of `VehicleAssetBuilder`'s "Type de carburant" `DropdownType` custom field — a
 * `Glpi\Asset\CustomFieldType\DropdownType` accepts any real `CommonDBTM`-backed itemtype with a
 * table and a name field, confirmed by reading `DropdownType::getFormInput()`, which just calls
 * GLPI's generic `Dropdown::show($itemtype, ...)`.
 *
 * `$rightname = Profile::RIGHT_CONFIG` (this plugin's own dedicated right, same as `Config`/
 * `ConfigurationProfile`) rather than registering a brand new right — a small, rarely-edited
 * reference dropdown doesn't need its own permission bit. Deliberately NOT the native GLPI
 * `'config'` right: core's `\Config::getRights()` unsets CREATE/DELETE/PURGE on it (a per-entity
 * singleton has no concept of creating or deleting rows), which silently made "Ajouter"/"Purger"
 * on this dropdown's own screen 403 for everyone, super-admin included — the exact bug
 * Profile.php's own docblock explains this dedicated right was created to avoid.
 */
class FuelType extends CommonDropdown
{
    public static $rightname = Profile::RIGHT_CONFIG;

    public static function getTypeName($nb = 0)
    {
        return _n('Type de carburant', 'Types de carburant', $nb, 'configurationglpiauto');
    }

    public static function getIcon()
    {
        return 'ti ti-gas-station';
    }
}
