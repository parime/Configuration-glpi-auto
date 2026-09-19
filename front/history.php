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

use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigHistory;

Session::checkRight(Config::$rightname, READ);

if (isset($_POST['create_checkpoint'])) {
    Session::checkRight(Config::$rightname, UPDATE);

    $label = trim((string) ($_POST['label'] ?? ''));
    if ($label === '') {
        Session::addMessageAfterRedirect(__('Veuillez indiquer un nom pour ce point de sauvegarde.', 'configurationglpiauto'), false, ERROR);
        Html::back();
    }

    ConfigHistory::captureManual(Config::getConfig(), $label);
    Session::addMessageAfterRedirect(__('Point de sauvegarde créé.', 'configurationglpiauto'));
    Html::back();
} elseif (isset($_POST['purge'])) {
    Session::checkRight(Config::$rightname, UPDATE);

    $item = new ConfigHistory();
    $item->getFromDB((int) $_POST['id']);
    if ((int) $item->fields['is_manual'] !== 1) {
        Session::addMessageAfterRedirect(__('Seuls les points de sauvegarde manuels peuvent être supprimés.', 'configurationglpiauto'), false, ERROR);
        Html::back();
    }

    $item->delete($_POST, true);
    Html::back();
}

Html::header(ConfigHistory::getTypeName(2), $_SERVER['PHP_SELF'], 'config', ConfigHistory::class);

echo "<form method='post' action='" . htmlspecialchars($_SERVER['PHP_SELF']) . "' class='card mb-3'>";
echo "<div class='card-body d-flex gap-2 align-items-end'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo "<div>";
echo "<label class='form-label'>" . __('Label du point de sauvegarde', 'configurationglpiauto') . "</label>";
echo "<input type='text' name='label' class='form-control' required>";
echo "</div>";
echo "<button type='submit' name='create_checkpoint' value='1' class='btn btn-outline-primary'>";
echo "<i class='ti ti-device-floppy'></i> " . __('Créer un point de sauvegarde', 'configurationglpiauto');
echo "</button>";
echo "</div>";
echo "</form>";

Search::show(ConfigHistory::class);

Html::footer();
