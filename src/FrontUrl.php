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
 * Adresse d'une page front/ du plugin.
 *
 * À utiliser à la place de `$_SERVER['PHP_SELF']` : derrière le point d'entrée unique de GLPI 11
 * et 12 (`public/index.php`), PHP_SELF vaut « /index.php ». Une redirection vers PHP_SELF menait
 * donc à l'accueil de GLPI, et un formulaire posté vers PHP_SELF n'arrivait jamais à sa page.
 */
final class FrontUrl
{
    public static function get(string $file): string
    {
        global $CFG_GLPI;

        return rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/configurationglpiauto/front/' . ltrim($file, '/');
    }
}
