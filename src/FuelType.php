<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Parime
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
 * `$rightname = 'config'` (the same native right this plugin's own `Config`/`ConfigurationProfile`
 * classes use) rather than registering a brand new right — a small, rarely-edited reference
 * dropdown doesn't need its own permission bit.
 */
class FuelType extends CommonDropdown
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return _n('Type de carburant', 'Types de carburant', $nb, 'configurationglpiauto');
    }

    public static function getIcon()
    {
        return 'ti ti-gas-station';
    }
}
