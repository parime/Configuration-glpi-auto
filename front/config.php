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

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigSettings;

// Issue #495 : page de configuration du plugin (icône « Configurer » de Configuration > Plugins),
// réglages seulement, en onglets. L'usage (assistant, audit, historique...) est dans
// Configuration > Configuration GLPI Auto. Mêmes droits que les réglages déplacés de l'audit.
Session::checkRight(Config::$rightname, READ);
Session::checkRight('config', READ);

if (ConfigSettings::isSettingsPost($_POST)) {
    // Droits UPDATE vérifiés par handle() ; jeton CSRF vérifié par GLPI (csrf_compliant).
    Html::redirect(ConfigSettings::url(ConfigSettings::handle($_POST, $_FILES)));
}

$tab = ConfigSettings::tab((string) ($_GET['tab'] ?? ConfigSettings::TAB_GENERAL));

Html::header(__('Configuration GLPI Auto', 'configurationglpiauto'), ConfigSettings::url($tab), 'config', 'plugin');

TemplateRenderer::getInstance()->display('@configurationglpiauto/config.html.twig', ConfigSettings::view($tab));

Html::footer();
