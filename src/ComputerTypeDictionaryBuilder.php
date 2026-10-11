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

use RuleDictionnaryComputerType;

/**
 * Issue #297 : dictionnaire des types d'ordinateur (Administration > Dictionnaires > Types
 * d'ordinateurs).
 *
 * `AssetTypeBuilder` crée des types en français (« Ordinateur portable »...), alors que l'agent
 * d'inventaire remonte le type de châssis SMBIOS en anglais (« Notebook », « Laptop », « Mini
 * Tower »...) : sans dictionnaire, GLPI crée un type de plus pour chacun, à côté des nôtres. La
 * liste SMBIOS est une norme fermée (DMTF DSP0134, « System Enclosure or Chassis Types »), d'où
 * des correspondances exactes, sans expression régulière.
 *
 * Laissés tels quels car sans équivalent fiable : Other, Unknown, Docking Station, Main System
 * Chassis, châssis d'extension, IoT Gateway.
 */
class ComputerTypeDictionaryBuilder
{
    /**
     * Nom créé par `AssetTypeBuilder` => types de châssis SMBIOS.
     *
     * @var array<string, list<string>>
     */
    private const RULES = [
        'Ordinateur de bureau' => ['Desktop', 'Low Profile Desktop', 'Pizza Box', 'Mini Tower', 'Tower', 'All in One', 'All-in-One', 'Space-saving', 'Lunch Box', 'Sealed-case PC'],
        'Ordinateur portable' => ['Portable', 'Laptop', 'Notebook', 'Sub Notebook', 'Convertible', 'Detachable'],
        'Tablette' => ['Tablet', 'Hand Held'],
        'Serveur' => ['Main Server Chassis', 'Rack Mount Chassis', 'Multi-system chassis', 'Blade', 'Blade Enclosure'],
        'Mini PC' => ['Mini PC', 'Stick PC', 'Embedded PC'],
    ];

    /**
     * Les noms cibles n'existent que si l'assistant crée les types de matériel : sans eux, la
     * règle créerait des types en français dans une base qui ne les utilise pas.
     *
     * @return int Number of dictionary rules created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['manufacturer_dictionary_enabled']) || empty($config->fields['asset_types_enabled'])) {
            return 0;
        }

        foreach (self::RULES as $canonical => $chassisTypes) {
            DictionaryRules::ensure(
                RuleDictionnaryComputerType::class,
                sprintf('Type d\'ordinateur — normalisation « %s »', $canonical),
                $canonical,
                $chassisTypes
            );
        }

        return count(self::RULES);
    }
}
