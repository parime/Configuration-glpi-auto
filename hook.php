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

use GlpiPlugin\Configurationglpiauto\Install\Installer;

function plugin_configurationglpiauto_install(): bool
{
    $migration = new Migration(PLUGIN_CONFIGURATIONGLPIAUTO_VERSION);

    return (new Installer())->install($migration);
}

function plugin_configurationglpiauto_uninstall(): bool
{
    $migration = new Migration(PLUGIN_CONFIGURATIONGLPIAUTO_VERSION);

    return (new Installer())->uninstall($migration);
}
