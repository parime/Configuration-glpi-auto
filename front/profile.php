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

use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;

Session::checkRight(ConfigurationProfile::$rightname, READ);

Html::header(ConfigurationProfile::getTypeName(2), $_SERVER['PHP_SELF'], 'admin', ConfigurationProfile::class);

// Search::show() alone generates no create link (same pitfall documented on remise-glpi).
global $CFG_GLPI;
echo "<div class='mb-3 d-flex gap-2'>";
echo "<a class='btn btn-outline-primary' href='" . htmlspecialchars($CFG_GLPI['root_doc'] . '/plugins/configurationglpiauto/front/wizard.php') . "'>";
echo "<i class='ti ti-wand'></i> " . __('Lancer l\'assistant de configuration', 'configurationglpiauto');
echo "</a>";
if (ConfigurationProfile::canCreate()) {
    echo "<a class='btn btn-primary' href='" . htmlspecialchars(ConfigurationProfile::getFormURL()) . "'>";
    echo "<i class='ti ti-plus'></i> " . __('Ajouter');
    echo "</a>";
}
echo "</div>";

Search::show(ConfigurationProfile::class);

Html::footer();
