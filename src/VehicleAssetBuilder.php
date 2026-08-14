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
use Glpi\Asset\CustomFieldType\DropdownType;
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
 * incomplete without (mise en circulation, expiration assurance). "Immatriculation" is marked
 * `required` (a real, GLPI-native per-field option — `Glpi\Asset\CustomFieldType\AbstractType::
 * getOptions()` exposes a `BooleanOption('required', 'Mandatory')` on every field type, the exact
 * checkbox an admin would tick by hand on the "Champs" tab; not a client-side-only hint). "Type de
 * carburant" is a real dropdown (`FuelType`, this plugin's own `CommonDropdown` — GLPI has no
 * native fuel-type concept to reuse, unlike every other dropdown this plugin populates) seeded
 * with common fuel types, extended on every resubmit the same way `ManufacturerDictionaryBuilder`
 * extends its own list, independently of whether the Vehicule definition itself already exists.
 * No true database-level uniqueness on immatriculation, deliberately: GLPI's `FieldUnicity`
 * mechanism (`FieldUnicityBuilder` elsewhere in this plugin) only matches on real database columns
 * (confirmed in `FieldUnicity::dropdownFields()` — it lists `$DB->listFields($table)`), and this
 * field lives inside the shared `glpi_assets_assets.custom_fields` JSON blob, invisible to it. The
 * native `serial` column would have been eligible instead, but the user explicitly chose to keep
 * the clearer "Immatriculation" label over gaining server-side uniqueness.
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
        [
            'system_name' => 'immatriculation',
            'label' => 'Immatriculation',
            'type' => StringType::class,
            'field_options' => ['required' => true],
        ],
        [
            'system_name' => 'type_carburant',
            'label' => 'Type de carburant',
            'type' => DropdownType::class,
            'itemtype' => FuelType::class,
        ],
        ['system_name' => 'date_mise_circulation', 'label' => 'Date de mise en circulation', 'type' => DateType::class],
        ['system_name' => 'date_controle_technique', 'label' => 'Date du prochain contrôle technique', 'type' => DateType::class],
        ['system_name' => 'date_expiration_assurance', 'label' => "Date d'expiration de l'assurance", 'type' => DateType::class],
    ];

    private const FUEL_TYPES = [
        'Essence',
        'Diesel',
        'Électrique',
        'Hybride rechargeable',
        'Hybride',
        'GPL',
        'Hydrogène',
    ];

    private const DEFAULT_RIGHTS_PROFILES = ['Super-Admin', 'Admin'];

    public function build(Config $config): int
    {
        if (!in_array('flotte', $config->getCategoryBranches(), true)) {
            return 0;
        }

        // Independent of whether the definition below already exists — an upgrade that adds new
        // fuel types to the const list should still benefit an admin who ran the wizard before,
        // same reasoning as ManufacturerDictionaryBuilder::addMissingCriteria().
        $this->seedFuelTypes();

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
            $input = [
                'assets_assetdefinitions_id' => $definitionId,
                'system_name' => $field['system_name'],
                'label' => $field['label'],
                'type' => $field['type'],
            ];
            if (isset($field['itemtype'])) {
                $input['itemtype'] = $field['itemtype'];
            }
            if (isset($field['field_options'])) {
                $input['field_options'] = $field['field_options'];
            }
            (new CustomFieldDefinition())->add($input);
        }

        return 1;
    }

    private function seedFuelTypes(): void
    {
        foreach (self::FUEL_TYPES as $name) {
            $fuelType = new FuelType();
            if (!$fuelType->getFromDBByCrit(['name' => $name])) {
                $fuelType->add(['name' => $name]);
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
