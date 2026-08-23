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
use Glpi\Asset\CustomFieldType\StringType;

/**
 * Second ESM (Enterprise Service Management) custom asset type from the same forum-sourced idea as
 * `FireSafetyAssetBuilder` (see that class's docblock for the shared `AssetDefinition` API research,
 * re-verified live against this same GLPI 11.0.8 test instance — not repeated here). Added on
 * explicit request (2026-08-18, owner's own words: "tout ce qui touche la sécurité physique du
 * bâtiment, et tout ce qui est lié, par exemple les caméras") — video surveillance, intrusion alarms,
 * access control.
 *
 * Explicitly grounded in ISO/IEC 27001:2022 Annex A.7 "Physical controls" (this plugin already
 * positions itself as ITIL4/ISO27001-aligned — see README/ROADMAP framing, `composer.json` keywords,
 * `configurationglpiauto.xml` tags) rather than invented equipment categories: each seeded `TYPES`
 * entry maps onto a specific, named A.7.x control, the same "cite the real standard/doc" bar this
 * plugin already applies elsewhere (e.g. `DocumentManagementBuilder`'s ISO 27001 Annex A.8.2
 * information-classification scale). This is also *why* these types were judged "universal" rather
 * than org-specific enough to build automatically — a documented, standard control clause, not one
 * admin's personal preference:
 * - Caméra de vidéosurveillance → A.7.4 Physical security monitoring
 * - Centrale d'alarme intrusion / Détecteur de mouvement → A.7.5 Protecting against physical and
 *   environmental threats
 * - Contrôle d'accès (lecteur de badge) / Serrure électronique → A.7.2 Physical entry
 * - Interphone / Vidéophone → A.7.1 Physical security perimeters / A.7.2 Physical entry
 *
 * Trigger deliberately NOT the "Bâtiment & Moyens Généraux" branch used by `BuildingAssetBuilder`/
 * `FireSafetyAssetBuilder`: this plugin's `CategoryBuilder` already ships a distinct, more
 * specifically on-topic branch — "Sécurité & Protection des Personnes" (`securite` key, icon 🔐),
 * whose own children are literally "Contrôle d'Accès & Badges" and "Vidéosurveillance & Alarmes"
 * (confirmed by reading `CategoryBuilder::CATEGORIES` directly rather than assuming) — the same
 * "pick the branch that's actually about this equipment" reasoning `VehicleAssetBuilder`/
 * `ServerAssetBuilder`/`BuildingAssetBuilder` each already apply to their own single trigger branch.
 * Same explicit opt-in checkbox pattern as `FireSafetyAssetBuilder` (`physical_security_assets_enabled`,
 * shown alongside that branch's checkbox) rather than an automatic branch-implies-asset trigger:
 * not every organisation selecting "Sécurité & Protection des Personnes" ticket categories wants
 * this equipment tracked as GLPI assets either — some have it under a dedicated physical-security
 * platform already.
 *
 * One asset type covering every sub-kind via the native "type" dropdown (`self::TYPES`), same
 * breadth-vs-granularity call already made for `VehicleAssetBuilder` (Voiture/Poids lourd/Moto... all
 * one "Véhicule" type) and `FireSafetyAssetBuilder` (Extincteur/RIA/DAE... all one type): every
 * sub-kind here genuinely shares the same two fields (coverage zone, last maintenance/check date) —
 * a camera and a badge reader are both "a security device with a location and a check date," so a
 * second/third `AssetDefinition` per sub-kind would just be ceremony, the same conclusion
 * `BuildingAssetBuilder`'s docblock reaches for why "Local" isn't split further either.
 *
 * Capacities: same non-reservable, non-hardware-inventory set as `FireSafetyAssetBuilder` (financial
 * info, contracts, documents, history, notes, global search) — deliberately no
 * `HasNetworkPortCapacity`/other IT-inventory capacities even though IP cameras/networked access
 * controllers exist: this asset tracks the equipment as a physical-security control for compliance
 * purposes (which ISO 27001 clause it satisfies, when it was last checked), not as a managed network
 * device — that's `ServerAssetBuilder`'s job if an organisation wants to inventory the network side
 * of the same hardware.
 */
class PhysicalSecurityAssetBuilder
{
    private const SYSTEM_NAME = 'SecuritePhysique';

    private const LABEL = 'Sécurité physique';

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
        ['system_name' => 'zone_couverte', 'label' => 'Zone couverte', 'type' => StringType::class],
        ['system_name' => 'date_derniere_maintenance', 'label' => 'Date de dernière maintenance / vérification', 'type' => DateType::class],
    ];

    // Seeds the *native* "type" dropdown every custom asset definition automatically gets (same
    // mechanism as VehicleAssetBuilder's own TYPES — see that class's seedTypes() docblock). Each
    // entry maps to a named ISO 27001 Annex A.7 control — see class docblock.
    private const TYPES = [
        'Caméra de vidéosurveillance',
        "Centrale d'alarme intrusion",
        'Détecteur de mouvement',
        "Contrôle d'accès (lecteur de badge)",
        'Serrure électronique',
        'Interphone / Vidéophone',
    ];

    private const TYPE_ICONS = [
        'Caméra de vidéosurveillance' => '📹',
        "Centrale d'alarme intrusion" => '🚨',
        'Détecteur de mouvement' => '🚶',
        "Contrôle d'accès (lecteur de badge)" => '🪪',
        'Serrure électronique' => '🔐',
        'Interphone / Vidéophone' => '📞',
    ];

    private const DEFAULT_RIGHTS_PROFILES = ['Super-Admin', 'Admin'];

    public function build(Config $config): int
    {
        if (!in_array('securite', $config->getCategoryBranches(), true) || empty($config->fields['physical_security_assets_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['physical_security_asset_icons_enabled']);

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
                    . 'equipements de securite physique du batiment (ISO 27001 Annexe A.7) : videosurveillance, '
                    . 'alarme intrusion, controle d\'acces.',
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
     * Same mechanism as `VehicleAssetBuilder::seedTypes()` — see that class's docblock, and
     * `FireSafetyAssetBuilder::seedTypes()` for why the icon is baked directly into `name` here
     * instead of via `Translations::applyIcon()`/`DropdownTranslation` (breaks GLPI's own `Search`
     * on the resulting asset list — root-caused live against `glpi_assets_assettypes` being a table
     * shared across every `AssetDefinition`, not a dedicated one).
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
