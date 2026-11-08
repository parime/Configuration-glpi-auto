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

namespace GlpiPlugin\Configurationglpiauto\Jira;

/**
 * Issue #345, phase 2 : contexte des appels sortants vers Jira. GLPI 12 refuse par défaut les
 * appels vers un réseau privé ; un administrateur qui héberge Jira Data Center en interne ajoute
 * cette classe à la constante GLPI_SERVERSIDE_URL_ALLOWED_PRIVATE_NETWORKS_CONTEXTS
 * (config/local_define.php), sans ouvrir les réseaux privés aux autres appels de GLPI.
 */
class JiraConnection extends \CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return 'Jira';
    }
}
