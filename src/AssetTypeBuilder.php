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

use ComputerType;
use MonitorType;
use NetworkEquipmentType;
use PeripheralType;
use PhoneType;
use PrinterType;

/**
 * Turns on `asset_types_enabled` into real `*Type` rows (`glpi_computertypes`,
 * `glpi_monitortypes`, `glpi_networkequipmenttypes`, `glpi_peripheraltypes`, `glpi_phonetypes`,
 * `glpi_printertypes`) — all native GLPI, all confirmed empty on a fresh install
 * (`information_schema` row-count check against a real instance). Scoped to the same six hardware
 * asset types already covered by `ManufacturerBuilder`/`FieldUnicityBuilder` (the ones with a
 * meaningful, industry-standard "kind of device" categorization) rather than the full ~30-table
 * native "Types" section — the rest (Rack/PDU/Cluster/DatabaseInstance/DeviceSensor...) need their
 * own per-itemtype content research before seeding, deliberately left for a later, separately
 * scoped pass (see ROADMAP.md) rather than rushed through in one block.
 *
 * Deliberately excluded from this batch despite being in the same native "Types" section:
 * `AgentType` (auto-created by `Agent::handleAgent()` the moment any real inventory agent
 * connects — seeding it ourselves would be redundant, not filling a real gap) and
 * `Assets_AssetType` (tied to a specific `AssetDefinition` a plugin/admin has to create first, not
 * a standalone global dropdown).
 *
 * All six `*Type` classes extend `CommonDropdown` (`ComputerType`/`MonitorType`/`PeripheralType`/
 * `PhoneType`/`PrinterType` via `CommonType`, `NetworkEquipmentType` directly) with no
 * `$can_be_translated` override, so the inherited default (`true`) applies — same icon mechanism as
 * `ManufacturerBuilder`.
 */
class AssetTypeBuilder
{
    /**
     * @var array<string, array<int, array{name: string, icon: string}>>
     */
    private const TYPES = [
        ComputerType::class => [
            ['name' => 'Ordinateur de bureau', 'icon' => '🖥️'],
            ['name' => 'Ordinateur portable', 'icon' => '💻'],
            ['name' => 'Serveur', 'icon' => '🗄️'],
            ['name' => 'Mini PC', 'icon' => '📦'],
            ['name' => 'Tablette', 'icon' => '📱'],
        ],
        MonitorType::class => [
            ['name' => 'Écran LCD/LED', 'icon' => '🖥️'],
            ['name' => 'Écran tactile', 'icon' => '👆'],
            ['name' => 'Vidéoprojecteur', 'icon' => '📽️'],
        ],
        NetworkEquipmentType::class => [
            ['name' => 'Switch', 'icon' => '🔀'],
            ['name' => 'Routeur', 'icon' => '📡'],
            ['name' => 'Pare-feu', 'icon' => '🛡️'],
            ['name' => 'Point d\'accès Wi-Fi', 'icon' => '📶'],
            ['name' => 'Répartiteur de charge', 'icon' => '⚖️'],
        ],
        PeripheralType::class => [
            ['name' => 'Clavier', 'icon' => '⌨️'],
            ['name' => 'Souris', 'icon' => '🖱️'],
            ['name' => 'Webcam', 'icon' => '📷'],
            ['name' => 'Casque', 'icon' => '🎧'],
            ['name' => 'Station d\'accueil', 'icon' => '🔌'],
            ['name' => 'Scanner', 'icon' => '🖨️'],
            ['name' => 'Disque externe', 'icon' => '💾'],
        ],
        PhoneType::class => [
            ['name' => 'Smartphone', 'icon' => '📱'],
            ['name' => 'Téléphone fixe', 'icon' => '☎️'],
            ['name' => 'Téléphone DECT', 'icon' => '📞'],
            ['name' => 'Softphone', 'icon' => '💬'],
        ],
        PrinterType::class => [
            ['name' => 'Imprimante laser', 'icon' => '🖨️'],
            ['name' => 'Imprimante jet d\'encre', 'icon' => '🖨️'],
            ['name' => 'Multifonction', 'icon' => '📠'],
            ['name' => 'Imprimante d\'étiquettes', 'icon' => '🏷️'],
        ],
    ];

    /**
     * @return int Number of types created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['asset_types_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['asset_type_icons_enabled']);
        $count = 0;
        foreach (self::TYPES as $itemtype => $types) {
            foreach ($types as $type) {
                $id = $this->getOrCreate($itemtype, $type['name']);
                if ($withIcons) {
                    Translations::applyIcon($itemtype, $id, $type['name'], $type['icon']);
                }
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, array<int, array{name: string, icon: string}>>
     */
    public static function getTypesPreview(): array
    {
        return self::TYPES;
    }

    private function getOrCreate(string $itemtype, string $name): int
    {
        $item = new $itemtype();
        if ($item->getFromDBByCrit(['name' => $name])) {
            return (int) $item->getID();
        }

        return (int) $item->add(['name' => $name]);
    }
}
