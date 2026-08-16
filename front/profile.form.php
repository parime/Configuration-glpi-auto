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

$item = new ConfigurationProfile();

if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    $item->add($_POST);
    Html::back();
} elseif (isset($_POST['update'])) {
    $item->check($_POST['id'], UPDATE);
    $item->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $item->check($_POST['id'], PURGE);
    $item->delete($_POST, true);
    Html::redirect(ConfigurationProfile::getSearchURL());
} else {
    Session::checkRight(ConfigurationProfile::$rightname, READ);
    Html::header(ConfigurationProfile::getTypeName(1), $_SERVER['PHP_SELF'], 'admin', ConfigurationProfile::class);
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $item->getFromDB($id);
    }
    $item->showForm($id);
    Html::footer();
}
