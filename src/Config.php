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
 * Plugin-wide settings screen (Configuration > Plugins > wrench icon), a single row (id=1). For
 * now this only holds the entity-structure choice that will drive the future entity-creation
 * wizard (ROADMAP "Assistant de création des entités") — it does not create any Entity yet, it
 * only records the shape the wizard will build later and lets the admin preview it live.
 */
class Config extends CommonDBTM
{
    public static $rightname = Profile::RIGHT_CONFIG;

    public const MODE_MONO = 'mono';

    public const MODE_MULTI_SAME_COMPANY = 'multi_same_company';

    public const MODE_MULTI_MSP = 'multi_msp';

    public const MAX_LEVELS = 5;

    private const SINGLETON_ID = 1;

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_configurationglpiauto_configs';
    }

    public static function getTypeName($nb = 0): string
    {
        return __('Configuration', 'configurationglpiauto');
    }

    public static function getModes(): array
    {
        return [
            self::MODE_MONO => __('Mono-entité — une seule structure, pas de sous-entités', 'configurationglpiauto'),
            self::MODE_MULTI_SAME_COMPANY => __('Multi-entité — plusieurs sites ou services d\'une même entreprise', 'configurationglpiauto'),
            self::MODE_MULTI_MSP => __('Multi-entité — infogérance de plusieurs entreprises clientes (MSP)', 'configurationglpiauto'),
        ];
    }

    /**
     * Always returns the single settings row, creating it with defaults if it doesn't exist yet
     * (belt and braces alongside Installer seeding it — keeps this safe to call even if the
     * plugin was ever activated before this feature existed).
     */
    public static function getConfig(): self
    {
        $config = new self();
        if (!$config->getFromDB(self::SINGLETON_ID)) {
            $config->add(self::getDefaults() + ['id' => self::SINGLETON_ID]);
            $config->getFromDB(self::SINGLETON_ID);
        }

        return $config;
    }

    public static function getDefaults(): array
    {
        return [
            'entity_mode' => self::MODE_MONO,
            'entity_levels' => 1,
            'level_labels' => json_encode(['Site']),
        ];
    }

    public function getLevelLabels(): array
    {
        $labels = json_decode((string) ($this->fields['level_labels'] ?? '[]'), true);

        return is_array($labels) ? $labels : [];
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInput($input);
    }

    public function prepareInputForAdd($input)
    {
        return $this->prepareInput($input);
    }

    private function prepareInput(array $input): array
    {
        if (isset($input['entity_mode']) && !array_key_exists($input['entity_mode'], self::getModes())) {
            $input['entity_mode'] = self::MODE_MONO;
        }

        if (isset($input['entity_levels'])) {
            $input['entity_levels'] = max(1, min(self::MAX_LEVELS, (int) $input['entity_levels']));
        }

        if (isset($input['level_label'])) {
            $levels = isset($input['entity_levels']) ? (int) $input['entity_levels'] : count((array) $input['level_label']);
            $labels = [];
            for ($i = 0; $i < $levels; $i++) {
                $labels[] = trim((string) ($input['level_label'][$i] ?? '')) ?: sprintf(__('Niveau %d', 'configurationglpiauto'), $i + 1);
            }
            $input['level_labels'] = json_encode($labels);
            unset($input['level_label']);
        }

        return $input;
    }
}
