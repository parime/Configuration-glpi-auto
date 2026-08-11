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
 */
class ManufacturerBuilder
{
    private const MANUFACTURERS = [
        'Dell', 'HP', 'Lenovo', 'Apple', 'Microsoft', 'ASUS', 'Acer',
        'Cisco', 'HPE Aruba', 'Fortinet', 'Ubiquiti', 'Netgear',
        'Canon', 'Epson', 'Brother', 'Xerox',
        'Samsung', 'LG',
        'Synology', 'QNAP', 'NetApp',
        'Logitech', 'Jabra', 'Poly',
        'APC', 'Eaton',
        'Oracle', 'VMware', 'Red Hat',
    ];

    /**
     * @return int Number of manufacturers created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['manufacturers_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::MANUFACTURERS as $name) {
            $this->getOrCreate($name);
            $count++;
        }

        return $count;
    }

    /**
     * @return string[]
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
