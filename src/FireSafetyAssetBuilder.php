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
use Glpi\Asset\CustomFieldDefinition;
use Glpi\Asset\CustomFieldType\DateType;

/**
 * ESM (Enterprise Service Management) custom asset type, same `AssetDefinition` + capacities
 * mechanism as `VehicleAssetBuilder`/`ServerAssetBuilder`/`BuildingAssetBuilder` (see
 * `VehicleAssetBuilder`'s docblock for the full API research — not re-derived here, re-confirmed
 * live against this same GLPI 11.0.8 test instance before writing this class: `AssetDefinition::
 * add()` with the `capacities`/`profiles` shape already used by the three sibling builders behaves
 * identically; a `translations` key on the definition itself was *not* added — GLPI's real
 * validation for that field (`AbstractDefinition::validateTranslationsArray()`) expects a
 * CLDR-plural-category shape (`{language: {plural_category: string}}`), a materially different and
 * untested mechanism the three sibling builders don't use either — kept out of scope here for the
 * same reason, see class docblock further down for what *is* translated).
 *
 * Prompted by a real GLPI official forum thread (https://forum.glpi-project.org/viewtopic.php?id=293900,
 * "ITSM → ESM", user Perreip asking whether GLPI can track non-IT regulated equipment — extincteurs,
 * ascenseurs — the same "actifs personnalisés" mechanism `VehicleAssetBuilder` et al. already prove
 * out). Fire safety equipment specifically: extincteurs, RIA (Robinet d'Incendie Armé), systèmes de
 * désenfumage, détection incendie, and — folded in on explicit request (2026-08-18) — défibrillateurs
 * automatisés externes (DAE/AED): distinct equipment family from fire suppression, but the exact same
 * "universal + regulatory periodic-verification date" shape (French ERP — Établissement Recevant du
 * Public — regulatory obligation for AED since 2020, same spirit as the extincteur/RIA verification
 * requirement already covered) that made fire-safety equipment a clean fit for this plugin's
 * generalist scope in the first place. Kept as one asset type rather than a second dedicated builder
 * for AED specifically: same "one broad vertical, sub-kinds via the native type dropdown" pattern
 * `VehicleAssetBuilder` already uses for Voiture/Poids lourd/Moto (`self::TYPES` below) — a single
 * shared field (periodic verification date) genuinely serves every sub-kind here, so a second
 * `AssetDefinition` would just be ceremony without a real capability difference.
 *
 * Triggered by a new, dedicated checkbox (`fire_safety_assets_enabled`) shown alongside the
 * "Bâtiment & Moyens Généraux" branch checkbox at the wizard's category-branches step — deliberately
 * NOT auto-triggered by the branch checkbox alone (unlike `BuildingAssetBuilder`'s "Local," which
 * *is* implied by picking that branch): not every organisation running the "Bâtiment" branch's
 * ticket categories actually wants to track fire-safety equipment as GLPI assets (many delegate this
 * to a dedicated safety-compliance tool), so this stays an explicit opt-in nested under that branch
 * rather than bundled into it. `build()` still requires the branch itself to be selected — no branch,
 * no fire-safety category context, so the checkbox has nothing to attach to either.
 *
 * Capacities deliberately exclude `IsReservableCapacity` (present on `VehicleAssetBuilder`/
 * `BuildingAssetBuilder`): a fire extinguisher or smoke detector is never booked out like a pool car
 * or a meeting room — reservability has no real meaning for this equipment family, so it's left off
 * rather than included "just in case" like the other two custom assets. Otherwise the same
 * compliance-tracking set as `VehicleAssetBuilder`: financial/warranty info, contracts (maintenance),
 * documents (inspection certificates), history, notes, global search.
 */
class FireSafetyAssetBuilder
{
    private const SYSTEM_NAME = 'SecuriteIncendieSecours';

    private const LABEL = 'Sécurité incendie & premiers secours';

    private const CAPACITIES = [
        HasInfocomCapacity::class,
        HasContractsCapacity::class,
        HasDocumentsCapacity::class,
        HasHistoryCapacity::class,
        HasNotepadCapacity::class,
        HasLinksCapacity::class,
        AllowedInGlobalSearchCapacity::class,
    ];

    private const FIELDS = [
        ['system_name' => 'date_verification_periodique', 'label' => 'Date de vérification périodique', 'type' => DateType::class],
    ];

    // Seeds the *native* "type" dropdown every custom asset definition automatically gets (same
    // mechanism as VehicleAssetBuilder's own TYPES — see that class's seedTypes() docblock).
    // "Défibrillateur automatisé externe (DAE)" folded in here rather than a second builder — see
    // class docblock.
    private const TYPES = [
        'Extincteur',
        "Robinet d'incendie armé (RIA)",
        'Système de désenfumage',
        'Détecteur de fumée / alarme incendie',
        'Éclairage de sécurité / issue de secours',
        'Défibrillateur automatisé externe (DAE)',
    ];

    private const TYPE_ICONS = [
        'Extincteur' => '🧯',
        "Robinet d'incendie armé (RIA)" => '🚰',
        'Système de désenfumage' => '💨',
        'Détecteur de fumée / alarme incendie' => '🚨',
        'Éclairage de sécurité / issue de secours' => '🚪',
        'Défibrillateur automatisé externe (DAE)' => '❤️',
    ];

    private const DEFAULT_RIGHTS_PROFILES = ['Super-Admin', 'Admin'];

    public function build(Config $config): int
    {
        if (!in_array('batiment', $config->getCategoryBranches(), true) || empty($config->fields['fire_safety_assets_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['fire_safety_asset_icons_enabled']);

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
                'comment' => 'Type d\'actif cree automatiquement par Configuration GLPI Auto pour le suivi des '
                    . 'equipements de securite incendie et de premiers secours (extincteurs, RIA, DAE...).',
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

        $this->seedTypes($definition, $withIcons);

        return $isNew ? 1 : 0;
    }

    /**
     * @return array{types: array<int, string>, fields: array<int, string>}
     */
    public static function getPreview(): array
    {
        return [
            'types' => self::TYPES,
            'fields' => array_column(self::FIELDS, 'label'),
        ];
    }

    /**
     * Same mechanism as `VehicleAssetBuilder::seedTypes()` — see that class's docblock. Icons are
     * baked directly into `name` rather than via `Translations::applyIcon()`/`DropdownTranslation`
     * (unlike ~20 other builders, e.g. `CategoryBuilder`): confirmed live that this dropdown's real
     * table (`glpi_assets_assettypes`) is *shared* across every `AssetDefinition` in the instance
     * (scoped by `assets_assetdefinitions_id`), not a dedicated table of its own like
     * `glpi_itilcategories` — a `DropdownTranslation` row on it made GLPI's own `Search`/
     * `SQLProvider` emit a `glpi_assets_assettypes_trans_name` column with no matching JOIN,
     * breaking with "Unknown column" on *every* view of the resulting asset list, not just this
     * dropdown — reproduced and root-caused this way rather than assumed. Looked up by definition +
     * either the bare or icon-prefixed name so toggling the icon option on/off later stays
     * idempotent instead of creating a duplicate row.
     */
    private function seedTypes(AssetDefinition $definition, bool $withIcons): void
    {
        $itemtype = $definition->getAssetTypeClassName();
        $definitionId = (int) $definition->getID();

        foreach (self::TYPES as $name) {
            $iconVariant = !empty(self::TYPE_ICONS[$name]) ? trim(self::TYPE_ICONS[$name] . ' ' . $name) : $name;
            $displayName = $withIcons ? $iconVariant : $name;

            // Matches either the bare or icon-prefixed name regardless of *this* run's own
            // withIcons value — otherwise toggling the option off after a prior run left an
            // icon-prefixed row behind would miss it here and create a bare-name duplicate instead
            // of updating it (reproduced live before adding this).
            $item = new $itemtype();
            $crit = ['assets_assetdefinitions_id' => $definitionId, 'name' => [$name, $iconVariant]];
            if (!$item->getFromDBByCrit($crit)) {
                $item->add(['assets_assetdefinitions_id' => $definitionId, 'name' => $displayName]);
            } elseif ($item->fields['name'] !== $displayName) {
                $item->update(['id' => $item->getID(), 'name' => $displayName]);
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
