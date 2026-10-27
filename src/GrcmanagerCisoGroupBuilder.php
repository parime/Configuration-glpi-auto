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

use Group;

/**
 * Issue #339 : quand le plugin jumeau GRC Manager (glpi-iso27001-management, clé `grcmanager`)
 * est actif, crée le groupe natif « RSSI » et le déclare comme groupe RSSI de GRC Manager. Ce
 * groupe y lit les documents de tous les audits internes et les copies de quiz des formations ;
 * sans lui, ces documents ne sont lisibles que par l'équipe de chaque audit ou session.
 *
 * Même contrat que AssetsignTriggerBuilder : jamais de `use` d'une classe d'un plugin qui peut être
 * absent. Le réglage de GRC Manager est une valeur de configuration GLPI standard (contexte
 * `plugin:grcmanager`, clé `ciso_groups_id`), écrite par l'API Config du cœur.
 *
 * Idempotent et non destructif : un groupe RSSI déjà choisi dans GRC Manager n'est jamais remplacé.
 */
class GrcmanagerCisoGroupBuilder
{
    public const GROUP_NAME = 'RSSI';
    public const CONFIG_CONTEXT = 'plugin:grcmanager';
    public const CONFIG_KEY = 'ciso_groups_id';

    /**
     * @return int 1 si GRC Manager a reçu le groupe RSSI, 0 sinon (option décochée, plugin absent ou
     *             groupe déjà choisi)
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['grcmanager_ciso_group_enabled']) || !self::isGrcmanagerActive()) {
            return 0;
        }
        $current = \Config::getConfigurationValues(self::CONFIG_CONTEXT, [self::CONFIG_KEY]);
        if ((int) ($current[self::CONFIG_KEY] ?? 0) > 0) {
            return 0;
        }
        $groupId = $this->getOrCreateGroup();
        if ($groupId <= 0) {
            return 0;
        }
        \Config::setConfigurationValues(self::CONFIG_CONTEXT, [self::CONFIG_KEY => $groupId]);

        return 1;
    }

    public static function isGrcmanagerActive(): bool
    {
        return class_exists('Plugin') && \Plugin::isPluginActive('grcmanager');
    }

    private function getOrCreateGroup(): int
    {
        $group = new Group();
        if (Lookup::loadFirst($group, ['name' => self::GROUP_NAME, 'entities_id' => 0])) {
            return (int) $group->getID();
        }

        return (int) $group->add([
            'name'         => self::GROUP_NAME,
            'entities_id'  => 0,
            'is_recursive' => 1,
            'comment'      => 'Groupe cree automatiquement par Configuration GLPI Auto : responsables de la '
                . 'securite du systeme d\'information. Dans GRC Manager, ses membres lisent les documents '
                . 'de tous les audits internes et les copies de quiz des formations.',
        ]);
    }
}
