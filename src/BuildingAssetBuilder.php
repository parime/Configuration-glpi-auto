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
use Glpi\Asset\Capacity\HasContractsCapacity;
use Glpi\Asset\Capacity\HasDocumentsCapacity;
use Glpi\Asset\Capacity\HasHistoryCapacity;
use Glpi\Asset\Capacity\HasInfocomCapacity;
use Glpi\Asset\Capacity\HasLinksCapacity;
use Glpi\Asset\Capacity\HasNotepadCapacity;
use Glpi\Asset\Capacity\IsReservableCapacity;
use Glpi\Asset\CustomFieldDefinition;
use Glpi\Asset\CustomFieldType\NumberType;
use Glpi\Asset\CustomFieldType\StringType;

/**
 * Third custom asset type from the same idea as `VehicleAssetBuilder`/`ServerAssetBuilder` (see
 * `VehicleAssetBuilder`'s docblock for the full API research). Generated the moment the admin
 * selects the "Bâtiment & Moyens Généraux" category branch (`CategoryBuilder`'s `batiment` key) —
 * same no-separate-toggle trigger pattern.
 *
 * Named "Local" (matching the original idea's own "Local"/"Salle" framing) rather than "Bâtiment"
 * — deliberately complements, not duplicates, GLPI's native `Location` (`glpi_locations`): a
 * `Location` answers *where* an asset physically sits (a hierarchical building/floor/room tree,
 * already built by `LocationBuilder` elsewhere in this plugin) and carries no capacities of its
 * own — it can't have a contract, a purchase value, an attached floor plan, or be booked. A "Local"
 * *asset* tracks a specific room/office as a real managed item with its own financial info
 * (rent/lease), contracts (cleaning, maintenance, insurance), documents (floor plans, permits,
 * diagnostics), and reservability (meeting rooms) — none of which the `Location` object supports.
 *
 * Capacities scoped to what a room/office genuinely needs — no hardware-inventory capacities at
 * all (unlike Vehicule/Serveur, a room has no network ports, OS, or devices). `IsReservableCapacity`
 * is the one capacity that matters most here — a meeting room is the textbook reservable resource.
 *
 * Custom fields: surface and capacity are numeric (`NumberType`, confirmed real in
 * `src/Glpi/Asset/CustomFieldType/NumberType.php`), room type stays free text (Bureau, Salle de
 * réunion, Entrepôt, Local technique... — a small enough, self-evident set that a dropdown would
 * add ceremony without real benefit, unlike `type_carburant`'s fixed physical-world vocabulary).
 */
class BuildingAssetBuilder
{
    private const SYSTEM_NAME = 'Local';
    private const LABEL = 'Local';

    private const CAPACITIES = [
        HasInfocomCapacity::class,
        HasContractsCapacity::class,
        HasDocumentsCapacity::class,
        HasHistoryCapacity::class,
        HasNotepadCapacity::class,
        HasLinksCapacity::class,
        AllowedInGlobalSearchCapacity::class,
        IsReservableCapacity::class,
    ];

    private const FIELDS = [
        ['system_name' => 'surface_m2', 'label' => 'Surface (m²)', 'type' => NumberType::class],
        ['system_name' => 'capacite_personnes', 'label' => 'Capacité (personnes)', 'type' => NumberType::class],
        ['system_name' => 'type_local', 'label' => 'Type de local', 'type' => StringType::class],
    ];

    private const DEFAULT_RIGHTS_PROFILES = ['Super-Admin', 'Admin'];

    public function build(Config $config): int
    {
        if (!in_array('batiment', $config->getCategoryBranches(), true)) {
            return 0;
        }

        $definition = new AssetDefinition();
        if ($definition->getFromDBByCrit(['system_name' => self::SYSTEM_NAME])) {
            return 0;
        }

        $capacities = array_map(static fn (string $class): array => ['name' => $class], self::CAPACITIES);

        $definitionId = (int) $definition->add([
            'system_name' => self::SYSTEM_NAME,
            'label' => self::LABEL,
            'is_active' => 1,
            'capacities' => $capacities,
            'profiles' => $this->getDefaultProfileRights(),
            'comment' => 'Type d\'actif cree automatiquement par Configuration GLPI Auto pour suivre '
                . 'un local (bureau, salle...) comme un actif a part entiere : contrats, documents, reservation.',
        ]);

        foreach (self::FIELDS as $field) {
            (new CustomFieldDefinition())->add([
                'assets_assetdefinitions_id' => $definitionId,
                'system_name' => $field['system_name'],
                'label' => $field['label'],
                'type' => $field['type'],
            ]);
        }

        return 1;
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
