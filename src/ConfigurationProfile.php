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

use CommonDBTM;

/**
 * A predefined configuration profile (PME, ETI, MSP, ISO 27001...) that the wizard will be able
 * to deploy onto a GLPI instance. Sprint 1 only stores and lists the catalog of profiles; the
 * actual deployment engine (Sprint 2+) is not built yet.
 *
 * Deliberately NOT namespaced under an `Entity\` sub-namespace: GLPI's automatic table-name
 * derivation reads namespace segments after the plugin prefix as part of the class name, and
 * "Entity" collides with GLPI's own core Entity class, producing a bogus many-to-many relation
 * table name (`..._entities_configurationprofiles` instead of `..._profiles`) — confirmed by
 * reproducing it against a real GLPI 11 instance. getTable() is also overridden explicitly below
 * to remove the whole risk category, per the same lesson already documented on the sibling
 * glpi-vulnerability-manager plugin.
 */
class ConfigurationProfile extends CommonDBTM
{
    public static $rightname = Profile::RIGHT_PROFILE;

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_configurationglpiauto_profiles';
    }

    public static function getTypeName($nb = 0): string
    {
        return _n('Profil de configuration', 'Profils de configuration', $nb, 'configurationglpiauto');
    }

    public static function getIcon(): string
    {
        return 'fas fa-cogs';
    }

    public static function getTypes(): array
    {
        return [
            'minimal'    => __('Installation minimale', 'configurationglpiauto'),
            'sme'        => __('PME', 'configurationglpiauto'),
            'eti'        => __('ETI', 'configurationglpiauto'),
            'enterprise' => __('Grande entreprise', 'configurationglpiauto'),
            'msp'        => __('MSP', 'configurationglpiauto'),
            'iso27001'   => __('ISO 27001', 'configurationglpiauto'),
            'itil'       => __('ITIL', 'configurationglpiauto'),
            'custom'     => __('Personnalisé', 'configurationglpiauto'),
        ];
    }

    // rawSearchOptions() (pas getSearchOptions(), final dans CommonDBTM) : sinon la liste
    // s'affiche sans colonnes ni en-tetes — meme correctif que sur remise-glpi et
    // glpi-vulnerability-manager (piege documente sur les deux plugins jumeaux).
    public function rawSearchOptions(): array
    {
        return [
            ['id' => 'common', 'name' => self::getTypeName(1)],
            ['id' => 1, 'table' => self::getTable(), 'field' => 'name', 'name' => __('Nom'), 'datatype' => 'itemlink', 'itemtype' => self::class],
            ['id' => 2, 'table' => self::getTable(), 'field' => 'description', 'name' => __('Description'), 'datatype' => 'text'],
            ['id' => 3, 'table' => self::getTable(), 'field' => 'type', 'name' => __('Type', 'configurationglpiauto'), 'datatype' => 'specific'],
            ['id' => 4, 'table' => self::getTable(), 'field' => 'is_active', 'name' => __('Actif'), 'datatype' => 'bool'],
            ['id' => 16, 'table' => self::getTable(), 'field' => 'comment', 'name' => __('Commentaires'), 'datatype' => 'text'],
            ['id' => 19, 'table' => self::getTable(), 'field' => 'date_mod', 'name' => __('Dernière modification'), 'datatype' => 'datetime'],
        ];
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        if ($field === 'type') {
            $types = self::getTypes();
            return $types[$values[$field]] ?? $values[$field];
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public function prepareInputForAdd($input)
    {
        if (empty($input['type']) || !array_key_exists($input['type'], self::getTypes())) {
            $input['type'] = 'custom';
        }

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInputForAdd($input);
    }
}
