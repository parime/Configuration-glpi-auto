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
use GlpiPlugin\Configurationglpiauto\Blueprint\FieldGroupLabel;
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
    // Import/Export avancé (issue #117), volet "conflict resolution" : n'applique plus directement
    // ici — redirige vers l'écran de diff+application sélective déjà construit pour la restauration
    // d'historique (front/history_restore.php, #114), généralisé pour accepter aussi un
    // ConfigurationProfile. Jamais un écrasement complet sans revue, même principe que le choix
    // d'un profil à l'étape 1 de l'assistant existant.
    $item->check($_POST['id'], READ);
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/configurationglpiauto/front/history_restore.php'
        . '?itemtype=' . ConfigurationProfile::class . '&id=' . (int) $_POST['id']);
} elseif (isset($_GET['export_snapshot'])) {
    $item->check($_GET['id'], READ);
    $item->getFromDB($_GET['id']);
    if (empty($item->fields['snapshot'])) {
        Html::displayErrorAndDie(__('Ce profil ne contient aucun Blueprint exploitable.', 'configurationglpiauto'));
    }
    $decoded = json_decode((string) $item->fields['snapshot'], true);
    if (!is_array($decoded)) {
        Html::displayErrorAndDie(__('Ce profil ne contient aucun Blueprint exploitable.', 'configurationglpiauto'));
    }

    // Import/Export avancé (issue #117), volet "export sélectif par catégorie" : sans sélection
    // explicite, affiche un écran de choix plutôt que de télécharger tout de suite — "Tout
    // exporter" reproduit exactement le comportement précédent (fichier complet).
    if (isset($_GET['fields'])) {
        $onlyFields = array_map('strval', (array) $_GET['fields']);
        $filtered = BlueprintSerializer::filterConfig($decoded, $onlyFields);
        $content = json_encode($filtered, JSON_PRETTY_PRINT);
    } elseif (isset($_GET['all'])) {
        $content = $item->fields['snapshot'];
    } else {
        Html::header(__('Exporter ce Blueprint', 'configurationglpiauto'), $_SERVER['PHP_SELF'], 'config', ConfigurationProfile::class);
        echo "<div class='card'><div class='card-body'>";
        echo "<p class='text-muted'>" . __('Choisissez les réglages à inclure dans le fichier exporté, ou exportez l\'intégralité de la configuration.', 'configurationglpiauto') . "</p>";
        echo "<form method='get' action='" . htmlspecialchars($_SERVER['PHP_SELF']) . "'>";
        echo Html::hidden('id', ['value' => $_GET['id']]);
        echo Html::hidden('export_snapshot', ['value' => 1]);
        $groups = [];
        foreach (array_keys($decoded['config'] ?? []) as $field) {
            $groups[FieldGroupLabel::for($field)][] = $field;
        }
        ksort($groups);
        foreach ($groups as $groupLabel => $groupFields) {
            echo "<h3 class='mt-3'>" . htmlspecialchars($groupLabel) . "</h3>";
            foreach ($groupFields as $field) {
                echo "<div class='form-check'>";
                echo "<input class='form-check-input' type='checkbox' name='fields[]' value='" . htmlspecialchars($field) . "' id='field_" . htmlspecialchars($field) . "' checked>";
                echo "<label class='form-check-label' for='field_" . htmlspecialchars($field) . "'><code>" . htmlspecialchars($field) . "</code></label>";
                echo "</div>";
            }
        }
        echo "<div class='d-flex gap-2 mt-3'>";
        echo "<button type='submit' class='btn btn-primary'><i class='ti ti-download'></i> " . __('Exporter la sélection', 'configurationglpiauto') . "</button>";
        $exportAllUrl = $_SERVER['PHP_SELF'] . '?id=' . (int) $_GET['id'] . '&export_snapshot=1&all=1';
        echo "<a class='btn btn-outline-primary' href='" . htmlspecialchars($exportAllUrl) . "'><i class='ti ti-download'></i> " . __('Exporter l\'intégralité', 'configurationglpiauto') . "</a>";
        echo "</div>";
        echo "</form></div></div>";
        Html::footer();
        exit;
    }

    $filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', $item->fields['name']) . '-blueprint.json';
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    echo $content;
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
