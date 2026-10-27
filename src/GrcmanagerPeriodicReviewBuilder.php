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

/**
 * Issue #339 : quand le plugin jumeau GRC Manager est actif, active ses revues périodiques du SMSI
 * (droits d'accès, comptes à privilèges, matrice des flux, registre des risques, tests de
 * restauration…) à leur fréquence par défaut, attribuées au groupe RSSI de GRC Manager.
 *
 * Activer une revue crée un gabarit et un ticket récurrent natifs de GLPI : c'est la logique propre
 * de GRC Manager (PluginGrcmanagerPeriodicReview::activate(), droits et attribution compris), donc
 * appelée telle quelle, et seulement si le plugin est actif et ses classes chargées. Option
 * facultative (décochée par défaut) : elle crée des tickets récurrents, comme les autres options
 * qui changent le flux des tickets.
 *
 * Non destructif : une revue déjà active (fréquence ou attribution réglées à la main) n'est jamais
 * modifiée.
 */
class GrcmanagerPeriodicReviewBuilder
{
    private const REVIEW_CLASS = 'PluginGrcmanagerPeriodicReview';
    private const CATALOG_CLASS = 'GlpiPlugin\\Grcmanager\\Services\\Review\\PeriodicReviewCatalog';

    /**
     * @return int nombre de revues activées
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['grcmanager_periodic_reviews_enabled']) || !self::isAvailable()) {
            return 0;
        }
        $reviewClass = self::REVIEW_CLASS;
        $catalogClass = self::CATALOG_CLASS;
        if (!$reviewClass::canManage()) {
            return 0;
        }
        $groupsId = self::cisoGroupId();
        $activated = 0;
        foreach ($catalogClass::keys() as $key) {
            $review = $reviewClass::getByKey($key);
            if ($review === null || $review->isActive()) {
                continue;
            }
            if ($review->activate($catalogClass::defaultMonths($key), 0, $groupsId)) {
                $activated++;
            }
        }

        return $activated;
    }

    public static function isAvailable(): bool
    {
        return GrcmanagerCisoGroupBuilder::isGrcmanagerActive()
            && class_exists(self::REVIEW_CLASS)
            && class_exists(self::CATALOG_CLASS);
    }

    private static function cisoGroupId(): int
    {
        $values = \Config::getConfigurationValues(
            GrcmanagerCisoGroupBuilder::CONFIG_CONTEXT,
            [GrcmanagerCisoGroupBuilder::CONFIG_KEY]
        );

        return (int) ($values[GrcmanagerCisoGroupBuilder::CONFIG_KEY] ?? 0);
    }
}
