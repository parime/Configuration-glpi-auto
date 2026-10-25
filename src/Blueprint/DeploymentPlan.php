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

namespace GlpiPlugin\Configurationglpiauto\Blueprint;

/**
 * Issue #338 : déploiement direct de la configuration chargée (Blueprint importé ou historique
 * restauré), sans repasser par les étapes de l'assistant. Le déploiement lui-même reste celui de
 * l'assistant (`front/wizard.php`, bloc `finish`), éprouvé en production : cette classe prépare
 * seulement les champs à lui transmettre et le récapitulatif affiché avant de confirmer.
 */
final class DeploymentPlan
{
    /** Champs techniques jamais transmis au déploiement. */
    private const EXCLUDED = ['id', 'date_mod', 'date_creation'];

    /**
     * Champs de la configuration, tels qu'enregistrés, à poster vers l'étape « Terminer ».
     * Une valeur vide (NULL) n'est pas transmise : le champ garde alors sa valeur enregistrée.
     *
     * @param array<string, mixed> $configFields
     * @return array<string, string>
     */
    public static function formFields(array $configFields): array
    {
        $fields = [];
        foreach ($configFields as $name => $value) {
            if (in_array($name, self::EXCLUDED, true) || $value === null || !is_scalar($value)) {
                continue;
            }
            $fields[(string) $name] = (string) $value;
        }
        ksort($fields);

        return $fields;
    }

    /**
     * Fonctionnalités activées (`*_enabled`), regroupées comme dans l'écran de restauration.
     *
     * @param array<string, mixed> $configFields
     * @return array<string, list<string>> Libellé du groupe => champs activés.
     */
    public static function enabledGroups(array $configFields): array
    {
        $groups = [];
        foreach ($configFields as $name => $value) {
            if (str_ends_with((string) $name, '_enabled') && (int) $value === 1) {
                $groups[FieldGroupLabel::for((string) $name)][] = (string) $name;
            }
        }
        ksort($groups);

        return $groups;
    }
}
