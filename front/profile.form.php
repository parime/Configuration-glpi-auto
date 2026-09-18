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

use GlpiPlugin\Configurationglpiauto\Blueprint\BlueprintSerializer;
use GlpiPlugin\Configurationglpiauto\Config;
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
} elseif (isset($_POST['save_snapshot'])) {
    // Capture l'état RÉEL actuel de Config (le singleton que front/wizard.php lit/écrit) dans le
    // Blueprint de ce profil — jamais l'inverse (voir apply_snapshot), donc jamais de risque
    // d'écraser silencieusement la configuration réelle depuis cet écran.
    $item->check($_POST['id'], UPDATE);
    $config = Config::getConfig();
    $snapshot = BlueprintSerializer::export($config->fields, $item->fields['name'], PLUGIN_CONFIGURATIONGLPIAUTO_VERSION);
    $item->update(['id' => $_POST['id'], 'snapshot' => json_encode($snapshot, JSON_PRETTY_PRINT)]);
    Session::addMessageAfterRedirect(__('Configuration actuelle enregistrée comme Blueprint.', 'configurationglpiauto'));
    Html::back();
} elseif (isset($_POST['apply_snapshot'])) {
    // Ne réécrit QUE Config (préremplissage) puis redirige vers l'assistant pour revue humaine —
    // jamais une exécution directe des Builders depuis cet écran, même principe que le choix d'un
    // profil à l'étape 1 de l'assistant existant.
    $item->check($_POST['id'], READ);
    $item->getFromDB($_POST['id']);
    $decoded = json_decode((string) $item->fields['snapshot'], true);
    if (!is_array($decoded)) {
        Session::addMessageAfterRedirect(__('Ce profil ne contient aucun Blueprint exploitable.', 'configurationglpiauto'), false, ERROR);
        Html::back();
    }
    $config = Config::getConfig();
    $fields = BlueprintSerializer::import($decoded, Config::getDefaults());
    $config->update($fields + ['id' => $config->getID()]);
    Session::addMessageAfterRedirect(__('Blueprint appliqué. Vérifiez chaque étape avant de valider.', 'configurationglpiauto'));
    Html::redirect(ConfigurationProfile::getSearchURL());
} elseif (isset($_GET['export_snapshot'])) {
    $item->check($_GET['id'], READ);
    $item->getFromDB($_GET['id']);
    if (empty($item->fields['snapshot'])) {
        Html::displayErrorAndDie(__('Ce profil ne contient aucun Blueprint exploitable.', 'configurationglpiauto'));
    }
    $filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', $item->fields['name']) . '-blueprint.json';
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($item->fields['snapshot']));
    echo $item->fields['snapshot'];
    exit;
} else {
    Session::checkRight(ConfigurationProfile::$rightname, READ);
    Html::header(ConfigurationProfile::getTypeName(1), $_SERVER['PHP_SELF'], 'config', ConfigurationProfile::class);
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $item->getFromDB($id);
        echo "<div class='mb-3 d-flex gap-2'>";
        echo "<form method='post' action='" . htmlspecialchars($_SERVER['PHP_SELF']) . "' class='d-inline'>";
        echo Html::hidden('id', ['value' => $id]);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<button type='submit' name='save_snapshot' value='1' class='btn btn-outline-primary'>";
        echo "<i class='ti ti-device-floppy'></i> " . __('Enregistrer la configuration actuelle comme Blueprint', 'configurationglpiauto');
        echo "</button>";
        echo "</form>";
        if (!empty($item->fields['snapshot'])) {
            echo "<form method='post' action='" . htmlspecialchars($_SERVER['PHP_SELF']) . "' class='d-inline'>";
            echo Html::hidden('id', ['value' => $id]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
            echo "<button type='submit' name='apply_snapshot' value='1' class='btn btn-outline-primary'>";
            echo "<i class='ti ti-wand'></i> " . __('Appliquer ce Blueprint (via l\'assistant)', 'configurationglpiauto');
            echo "</button>";
            echo "</form>";
            $exportUrl = $_SERVER['PHP_SELF'] . '?id=' . $id . '&export_snapshot=1';
            echo "<a class='btn btn-outline-primary' href='" . htmlspecialchars($exportUrl) . "'>";
            echo "<i class='ti ti-download'></i> " . __('Exporter ce Blueprint (JSON)', 'configurationglpiauto');
            echo "</a>";
        }
        echo "</div>";
        if (!empty($item->fields['snapshot'])) {
            echo "<div class='alert alert-warning'>";
            echo __('Ce fichier peut contenir des informations propres à votre organisation (adresses d\'entités...) : vérifiez son contenu avant de le partager.', 'configurationglpiauto');
            echo "</div>";
        }
    }
    $item->showForm($id);
    Html::footer();
}
