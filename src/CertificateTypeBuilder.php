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

use CertificateType;

/**
 * Turns on `certificate_types_enabled` into real `CertificateType` rows (Configuration > Intitulés
 * > Actifs > "Types de certificat") — GLPI ships none by default (confirmed empty on a fresh
 * install), even though `Certificate` itself, expiration tracking, and its own notification target
 * (`NotificationTargetCertificate`) are all native since GLPI 10 — same "native table, zero
 * defaults" gap this plugin already fills for `Manufacturer`/`SoftwareLicenseType`/asset "Types".
 *
 * Added in response to #151 ("étudier ce que recommandent ITIL et ISO 27001 en matière de gestion
 * d'actifs, pour identifier d'autres types d'actifs personnalisés pertinents"): read through both
 * frameworks' asset-management guidance before picking this specifically, rather than inventing a
 * new custom asset type speculatively. ISO 27001 Annex A explicitly calls out cryptographic
 * material (certificates, keys) as an asset category requiring its own inventory and expiry
 * tracking (A.8.24, "Use of cryptography") — GLPI already has the exact native mechanism for this
 * (`Certificate` + `NotificationTargetCertificate`'s expiration alerts), it's just never seeded with
 * any starting categories, unlike almost every other native "Types" table this plugin already
 * covers. No *new* custom asset type needed here (unlike `VehicleAssetBuilder`/
 * `FireSafetyAssetBuilder`): `Certificate` is already a first-class native asset, this only fills
 * its empty dropdown — unlike vehicles/fire-safety/physical-security equipment, which had no native
 * GLPI table to seed at all.
 *
 * `CertificateType extends CommonType` (confirmed in GLPI source) — a flat `CommonDropdown` like
 * `Manufacturer`, not a tree like `SoftwareLicenseType`, so this follows `ManufacturerBuilder`'s
 * shape. `entities_id`/`is_recursive` set explicitly (confirmed present via `DESCRIBE
 * glpi_certificatetypes`, unlike `Manufacturer` which GLPI defaults on its own) — same as
 * `SoftwareLicenseTypeBuilder`/`StateBuilder`.
 */
class CertificateTypeBuilder
{
    /**
     * @var array<int, array{name: string, icon: string}>
     */
    private const TYPES = [
        ['name' => 'SSL/TLS (serveur)', 'icon' => '🔒'],
        ['name' => 'Signature de code', 'icon' => '✍️'],
        ['name' => 'S/MIME (email)', 'icon' => '📧'],
        ['name' => 'Authentification client (VPN/mTLS)', 'icon' => '🔑'],
        ['name' => 'Autorité de certification (CA racine/intermédiaire)', 'icon' => '🏛️'],
        ['name' => 'Signature de document', 'icon' => '📄'],
    ];

    /**
     * @return int Number of certificate types created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['certificate_types_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['certificate_type_icons_enabled']);
        $count = 0;
        foreach (self::TYPES as $type) {
            $id = $this->getOrCreate($type['name']);
            // Always called (see StateBuilder for the reasoning) so unchecking icons after a prior
            // run actually strips them instead of leaving old rows stuck.
            Translations::applyIcon(CertificateType::class, $id, $type['name'], $withIcons ? $type['icon'] : '');
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, icon: string}>
     */
    public static function getTypesPreview(): array
    {
        return self::TYPES;
    }

    private function getOrCreate(string $name): int
    {
        $item = new CertificateType();
        if ($item->getFromDBByCrit(['name' => $name])) {
            return (int) $item->getID();
        }

        return (int) $item->add(['name' => $name, 'entities_id' => 0, 'is_recursive' => 1]);
    }
}
