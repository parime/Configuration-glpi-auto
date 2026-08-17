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

use Glpi\Asset\AssetDefinition;
use Glpi\Asset\Capacity\AllowedInGlobalSearchCapacity;
use Glpi\Asset\Capacity\HasCertificatesCapacity;
use Glpi\Asset\Capacity\HasContractsCapacity;
use Glpi\Asset\Capacity\HasDatabaseInstanceCapacity;
use Glpi\Asset\Capacity\HasDevicesCapacity;
use Glpi\Asset\Capacity\HasDocumentsCapacity;
use Glpi\Asset\Capacity\HasHistoryCapacity;
use Glpi\Asset\Capacity\HasInfocomCapacity;
use Glpi\Asset\Capacity\HasLinksCapacity;
use Glpi\Asset\Capacity\HasNetworkPortCapacity;
use Glpi\Asset\Capacity\HasNotepadCapacity;
use Glpi\Asset\Capacity\HasOperatingSystemCapacity;
use Glpi\Asset\Capacity\HasRemoteManagementCapacity;
use Glpi\Asset\Capacity\HasSoftwaresCapacity;
use Glpi\Asset\Capacity\HasVirtualMachineCapacity;
use Glpi\Asset\Capacity\IsInventoriableCapacity;
use Glpi\Asset\Capacity\IsRackableCapacity;
use Glpi\Asset\CustomFieldDefinition;
use Glpi\Asset\CustomFieldType\StringType;

/**
 * Second custom asset type from the same "actifs personnalisés par branche de catégorie" idea as
 * `VehicleAssetBuilder` (see that class's docblock for the full API research — same mechanics,
 * not re-derived here). Generated the moment the admin selects the "IT & SI" category branch
 * (`CategoryBuilder`'s `it` key) — no separate toggle, same trigger pattern as Vehicule.
 *
 * Deliberately distinct from the native `Computer` asset type (the original idea's own framing:
 * "un type Serveur distinct de l'actif natif Computer") — a workstation/laptop has no meaningful
 * use for rack position, RAID, or hypervisor fields, so bolting them onto every `Computer` would
 * just be noise for the vast majority of a fleet. Capacities are broader than Vehicule's, closer to
 * what `Computer` itself has (`IsInventoriableCapacity`, `HasNetworkPortCapacity`,
 * `HasOperatingSystemCapacity`, `HasSoftwaresCapacity`, `HasDevicesCapacity`) plus what's
 * specifically server-relevant: `IsRackableCapacity` (goes in a datacenter rack),
 * `HasVirtualMachineCapacity` (may host VMs), `HasRemoteManagementCapacity` (iLO/iDRAC/IPMI),
 * `HasCertificatesCapacity` (server-side TLS certs), `HasDatabaseInstanceCapacity` (may run a
 * database GLPI's own inventory agent can discover). Not `IsReservableCapacity` — a server isn't
 * booked out like a pool car or meeting room.
 *
 * Custom fields kept to free text, same reasoning as Vehicule's first pass: "position en baie" has
 * no fixed value set (varies per rack/datacenter), "RAID"/"hyperviseur" could become dropdowns like
 * `type_carburant` did if that's wanted later, but nothing here requires it yet.
 */
class ServerAssetBuilder
{
    private const SYSTEM_NAME = 'Serveur';
    private const LABEL = 'Serveur';

    private const CAPACITIES = [
        HasInfocomCapacity::class,
        HasContractsCapacity::class,
        HasDocumentsCapacity::class,
        HasHistoryCapacity::class,
        HasNotepadCapacity::class,
        HasLinksCapacity::class,
        AllowedInGlobalSearchCapacity::class,
        IsInventoriableCapacity::class,
        HasNetworkPortCapacity::class,
        HasOperatingSystemCapacity::class,
        HasSoftwaresCapacity::class,
        HasVirtualMachineCapacity::class,
        HasDevicesCapacity::class,
        IsRackableCapacity::class,
        HasRemoteManagementCapacity::class,
        HasCertificatesCapacity::class,
        HasDatabaseInstanceCapacity::class,
    ];

    private const FIELDS = [
        ['system_name' => 'position_baie', 'label' => 'Position en baie', 'type' => StringType::class],
        ['system_name' => 'configuration_raid', 'label' => 'Configuration RAID', 'type' => StringType::class],
        ['system_name' => 'hyperviseur', 'label' => 'Hyperviseur', 'type' => StringType::class],
    ];

    // Seeds the *native* "type" dropdown every custom asset definition automatically gets (same
    // mechanism as VehicleAssetBuilder's own TYPES — see that class's seedTypes() docblock).
    // Server *form factor*, not brand/model (Manufacturer/AssetModel's job).
    private const TYPES = [
        'Serveur rack',
        'Serveur tour',
        'Lame (blade)',
        'Serveur virtuel',
        'NAS',
        'SAN',
    ];

    private const DEFAULT_RIGHTS_PROFILES = ['Super-Admin', 'Admin'];

    public function build(Config $config): int
    {
        if (!in_array('it', $config->getCategoryBranches(), true)) {
            return 0;
        }

        $definition = new AssetDefinition();
        $isNew = !$definition->getFromDBByCrit(['system_name' => self::SYSTEM_NAME]);

        if ($isNew) {
            $capacities = array_map(static fn (string $class): array => ['name' => $class], self::CAPACITIES);

            $definitionId = (int) $definition->add([
                'system_name' => self::SYSTEM_NAME,
                'label' => self::LABEL,
                'is_active' => 1,
                'capacities' => $capacities,
                'profiles' => $this->getDefaultProfileRights(),
                'comment' => 'Type d\'actif cree automatiquement par Configuration GLPI Auto, distinct de '
                    . 'l\'actif natif Ordinateur pour les champs propres aux serveurs.',
            ]);
            $definition->getFromDB($definitionId);

            foreach (self::FIELDS as $field) {
                (new CustomFieldDefinition())->add([
                    'assets_assetdefinitions_id' => $definitionId,
                    'system_name' => $field['system_name'],
                    'label' => $field['label'],
                    'type' => $field['type'],
                ]);
            }
        }

        $this->seedTypes($definition);

        return $isNew ? 1 : 0;
    }

    /**
     * Same mechanism as `VehicleAssetBuilder::seedTypes()` — see that class's docblock.
     */
    private function seedTypes(AssetDefinition $definition): void
    {
        $itemtype = $definition->getAssetTypeClassName();
        $definitionId = (int) $definition->getID();

        foreach (self::TYPES as $name) {
            $item = new $itemtype();
            $crit = ['name' => $name, 'assets_assetdefinitions_id' => $definitionId];
            if (!$item->getFromDBByCrit($crit)) {
                $item->add($crit);
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private function getDefaultProfileRights(): array
    {
        global $DB;

        $rights = [];
        $it = $DB->request(['FROM' => 'glpi_profiles', 'WHERE' => ['name' => self::DEFAULT_RIGHTS_PROFILES]]);
        foreach ($it as $row) {
            $rights[(int) $row['id']] = ALLSTANDARDRIGHT;
        }

        return $rights;
    }
}
