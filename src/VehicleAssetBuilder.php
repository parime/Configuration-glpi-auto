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
use Glpi\Asset\CustomFieldType\DateType;
use Glpi\Asset\CustomFieldType\StringType;

/**
 * Generates a real GLPI 11 custom asset type ("Véhicule") the moment the admin selects the
 * "Flotte Automobile & Mobilité" category branch (`CategoryBuilder`'s `flotte` key) — no separate
 * toggle needed, that checkbox already is the trigger, exactly the idea scoped with the user
 * earlier ("la branche Flotte Automobile activée créerait un type d'actif Véhicule").
 *
 * Confirmed real and native since GLPI 11 (`Glpi\Asset\AssetDefinition extends AbstractDefinition`,
 * migrated from the old external "Generic Object" plugin) by creating one by hand through the real
 * admin UI (`front/asset/assetdefinition.form.php`) on the test instance and reading back exactly
 * what landed in `glpi_assets_assetdefinitions`/`glpi_assets_customfielddefinitions`, rather than
 * guessing the API shape from source alone:
 * - `capacities` must be `[{name: FQCN}, ...]` — each entry becomes a `Glpi\Asset\Capacity` object
 *   internally (`AssetDefinition::prepareInput()`), not a bare array of class-name strings.
 * - `profiles` decodes to a flat `{profile_id: rights_int}` map (confirmed in
 *   `AbstractDefinition::getDecodedProfilesField()`) — controls who can see/manage actual Vehicule
 *   *items*, entirely separate from the `config` right needed to edit the definition itself.
 * - `CustomFieldDefinition::type` stores the field type class's FQCN directly (confirmed in
 *   `CustomFieldDefinition::getFieldType()`), `system_name` must match `^[a-z0-9_]+$` and stay
 *   unique per definition.
 *
 * Capacities kept deliberately narrow — the ones a vehicle genuinely needs (financial/warranty
 * info, contracts for insurance/maintenance, attached documents, history, notes, external links,
 * global search, reservable for a pool car) — not the hardware-inventory ones every other asset
 * type in GLPI gets (network ports, OS, software, virtualization...), which make no sense for a
 * car. Only Super-Admin/Admin get rights by default — same reasoning as VipBuilder's native Group
 * default: a sensible, safe starting point, not a guess at which of an organisation's own
 * technician profiles should manage a vehicle fleet.
 *
 * Custom fields cover exactly what the original idea named (immatriculation, type de carburant,
 * date de contrôle technique) plus the two other pieces of information a real vehicle record is
 * incomplete without (mise en circulation, expiration assurance) — all free-text/date, no invented
 * dropdown list (e.g. for fuel types) requiring its own native table this plugin would have to
 * maintain.
 */
class VehicleAssetBuilder
{
    private const SYSTEM_NAME = 'Vehicule';
    private const LABEL = 'Véhicule';

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
        ['system_name' => 'immatriculation', 'label' => 'Immatriculation', 'type' => StringType::class],
        ['system_name' => 'type_carburant', 'label' => 'Type de carburant', 'type' => StringType::class],
        ['system_name' => 'date_mise_circulation', 'label' => 'Date de mise en circulation', 'type' => DateType::class],
        ['system_name' => 'date_controle_technique', 'label' => 'Date du prochain contrôle technique', 'type' => DateType::class],
        ['system_name' => 'date_expiration_assurance', 'label' => "Date d'expiration de l'assurance", 'type' => DateType::class],
    ];

    private const DEFAULT_RIGHTS_PROFILES = ['Super-Admin', 'Admin'];

    public function build(Config $config): int
    {
        if (!in_array('flotte', $config->getCategoryBranches(), true)) {
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
            'comment' => 'Type d\'actif cree automatiquement par Configuration GLPI Auto pour la gestion de flotte automobile.',
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
