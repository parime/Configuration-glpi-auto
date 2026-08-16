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

use Manufacturer;

/**
 * Turns on `manufacturers_enabled` into a real `Manufacturer` list (Configuration > Intitulés >
 * Général > "Fabricants") — GLPI ships none by default, yet every asset (`Computer`, `Monitor`,
 * `NetworkEquipment`...) has a `manufacturers_id` field pointing here. Unlike category/service
 * names elsewhere in this plugin, vendor-neutral phrasing doesn't apply — a manufacturer list's
 * entire purpose *is* naming real vendors, not describing a generic function.
 *
 * Broad, generic hardware/software vendor coverage (computers, network, printers, mobile,
 * software publishers) rather than a specific organization's actual supplier list — a starting
 * point the admin trims or extends, same philosophy as every other list in this plugin.
 *
 * Icons (optional, `manufacturer_icons_enabled`) are grouped *by product category* (computers,
 * network, printers...) rather than a distinct icon per brand — there's no established per-brand
 * emoji convention to draw from, and inventing one would look arbitrary. `Manufacturer extends
 * CommonDropdown` (confirmed in GLPI source), so the same `DropdownTranslation` icon-prepend
 * mechanism already used by `StateBuilder`/`CategoryBuilder` applies here too.
 */
class ManufacturerBuilder
{
    private const MANUFACTURERS = [
        ['name' => 'Dell', 'icon' => '💻'], ['name' => 'HP', 'icon' => '💻'], ['name' => 'Lenovo', 'icon' => '💻'],
        ['name' => 'Apple', 'icon' => '💻'], ['name' => 'Microsoft', 'icon' => '💻'], ['name' => 'ASUS', 'icon' => '💻'], ['name' => 'Acer', 'icon' => '💻'],
        ['name' => 'Cisco', 'icon' => '🌐'], ['name' => 'HPE Aruba', 'icon' => '🌐'], ['name' => 'Fortinet', 'icon' => '🌐'], ['name' => 'Ubiquiti', 'icon' => '🌐'], ['name' => 'Netgear', 'icon' => '🌐'],
        ['name' => 'Canon', 'icon' => '🖨️'], ['name' => 'Epson', 'icon' => '🖨️'], ['name' => 'Brother', 'icon' => '🖨️'], ['name' => 'Xerox', 'icon' => '🖨️'],
        ['name' => 'Samsung', 'icon' => '📱'], ['name' => 'LG', 'icon' => '📱'],
        ['name' => 'Synology', 'icon' => '💾'], ['name' => 'QNAP', 'icon' => '💾'], ['name' => 'NetApp', 'icon' => '💾'],
        ['name' => 'Logitech', 'icon' => '🎧'], ['name' => 'Jabra', 'icon' => '🎧'], ['name' => 'Poly', 'icon' => '🎧'],
        ['name' => 'APC', 'icon' => '🔌'], ['name' => 'Eaton', 'icon' => '🔌'],
        ['name' => 'Oracle', 'icon' => '☁️'], ['name' => 'VMware', 'icon' => '☁️'], ['name' => 'Red Hat', 'icon' => '☁️'],
    ];

    /**
     * @return int Number of manufacturers created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['manufacturers_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['manufacturer_icons_enabled']);
        $count = 0;
        foreach (self::MANUFACTURERS as $manufacturer) {
            $id = $this->getOrCreate($manufacturer['name']);
            if ($withIcons) {
                Translations::applyIcon(Manufacturer::class, $id, $manufacturer['name'], $manufacturer['icon']);
            }
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, icon: string}>
     */
    public static function getManufacturersPreview(): array
    {
        return self::MANUFACTURERS;
    }

    private function getOrCreate(string $name): int
    {
        $item = new Manufacturer();
        if ($item->getFromDBByCrit(['name' => $name])) {
            return (int) $item->getID();
        }

        return (int) $item->add(['name' => $name]);
    }
}
