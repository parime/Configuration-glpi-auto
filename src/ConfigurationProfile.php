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
 * A predefined configuration profile (PME, ETI, MSP, ISO 27001...) offered as the wizard's first
 * step. Picking one pre-fills sensible starting defaults for the later steps — see
 * getSuggestedDefaults() — the admin can still change anything afterward.
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

    // Fait pointer le menu principal du plugin directement vers l'assistant plutot que vers la
    // liste CRUD generique de front/profile.php (celle-ci reste accessible par URL directe, mais
    // n'apporte rien comme point d'entree par defaut). getItemTypeSearchURL() du coeur construit
    // "$dir/front/" . strtolower($itemtype) . ".php" — meme convention reprise ici a la main.
    public static function getSearchURL($full = true)
    {
        global $CFG_GLPI;

        $dir = $full ? $CFG_GLPI['root_doc'] : '';

        return $dir . '/plugins/configurationglpiauto/front/wizard.php';
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

    /**
     * Suggested starting point for the wizard's later steps once this profile is picked in
     * step 1 — entity mode, and reasonable calendar/SLA defaults for that kind of organization.
     * Deliberately never touches entity_tree itself (no realistic way to guess real client/site
     * names) — only the mode, so the admin still builds their own tree in step 2, just starting
     * from a sensible mode instead of always mono. The admin can still change any of this in the
     * later steps; picking a profile only pre-fills, it doesn't lock anything in. 'custom'
     * intentionally returns no suggestions at all.
     *
     * ITIL and ISO 27001 are not org sizes, they're practice frameworks any organization can
     * follow regardless of size — a small company can be ISO 27001 certified, a large one might
     * follow no formal framework at all. So they're not a separate profile choice here: a
     * calendar-scoped SLA *is* the ITIL/ISO27001 baseline, and every non-minimal profile gets one
     * by default rather than only the "advanced" ones. What actually varies per profile is the
     * organization's calendar and whether it has astreinte (on-call coverage outside opening
     * hours, see sla_astreinte) — MSP defaults to astreinte on because round-the-clock
     * contractual coverage is characteristic of that business model, not of being "bigger".
     *
     * @return array<string, mixed>
     */
    public static function getSuggestedDefaults(string $type): array
    {
        $goodPracticeBaseline = [
            'calendar_enabled' => true, 'calendar_days' => [1, 2, 3, 4, 5], 'calendar_begin' => '08:00', 'calendar_end' => '18:00',
            'sla_enabled' => true, 'sla_tto_hours' => 4, 'sla_ttr_hours' => 48, 'sla_astreinte' => false,
        ];

        return match ($type) {
            'minimal' => [
                'entity_mode' => Config::MODE_MONO,
            ],
            'sme' => ['entity_mode' => Config::MODE_MULTI_SAME_COMPANY] + $goodPracticeBaseline,
            'eti' => ['entity_mode' => Config::MODE_MULTI_SAME_COMPANY] + $goodPracticeBaseline,
            'enterprise' => ['entity_mode' => Config::MODE_MULTI_SAME_COMPANY] + $goodPracticeBaseline,
            'msp' => ['entity_mode' => Config::MODE_MULTI_MSP] + $goodPracticeBaseline + [
                'sla_tto_hours' => 1, 'sla_ttr_hours' => 8, 'sla_astreinte' => true,
            ],
            default => [],
        };
    }
}
