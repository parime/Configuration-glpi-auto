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

Session::checkRight(ConfigurationProfile::$rightname, CREATE);

if (isset($_POST['import'])) {
    $error = null;

    if (empty($_FILES['blueprint_file']['tmp_name']) || !is_uploaded_file($_FILES['blueprint_file']['tmp_name'])) {
        $error = __('Veuillez choisir un fichier Blueprint (.json).', 'configurationglpiauto');
    } else {
        $content = file_get_contents($_FILES['blueprint_file']['tmp_name']);
        $decoded = json_decode((string) $content, true);

        if (!is_array($decoded)) {
            $error = __('Ce fichier n\'est pas un JSON valide.', 'configurationglpiauto');
        } else {
            try {
                // Validation seule ici (le résultat n'est pas utilisé) : confirme que le fichier
                // est un Blueprint exploitable avant de créer quoi que ce soit — jamais une ligne
                // créée à partir d'un fichier qui s'avérerait ensuite inutilisable.
                BlueprintSerializer::import($decoded, Config::getDefaults());
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
    }

    if ($error !== null) {
        Session::addMessageAfterRedirect($error, false, ERROR);
        Html::back();
    }

    $name = trim((string) ($_POST['name'] ?? '')) !== ''
        ? trim((string) $_POST['name'])
        : ($decoded['profile_name'] ?? __('Blueprint importé', 'configurationglpiauto'));

    $profile = new ConfigurationProfile();
    $profile->add([
        'name'     => $name,
        'type'     => 'custom',
        'snapshot' => json_encode($decoded, JSON_PRETTY_PRINT),
    ]);

    Session::addMessageAfterRedirect(__('Blueprint importé comme nouveau profil.', 'configurationglpiauto'));
    Html::redirect(ConfigurationProfile::getFormURL() . '?id=' . $profile->getID());
}

Html::header(__('Importer un Blueprint', 'configurationglpiauto'), $_SERVER['PHP_SELF'], 'config', ConfigurationProfile::class);

echo "<form method='post' action='" . htmlspecialchars($_SERVER['PHP_SELF']) . "' enctype='multipart/form-data' class='card'>";
echo "<div class='card-body'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo "<div class='mb-3'>";
echo "<label class='form-label'>" . __('Fichier Blueprint (.json)', 'configurationglpiauto') . "</label>";
echo "<input type='file' name='blueprint_file' accept='.json,application/json' class='form-control' required>";
echo "</div>";
echo "<div class='mb-3'>";
echo "<label class='form-label'>" . __('Nom du profil (facultatif, repris du fichier sinon)', 'configurationglpiauto') . "</label>";
echo "<input type='text' name='name' class='form-control'>";
echo "</div>";
echo "<div class='alert alert-warning'>";
echo __('N\'importez que des fichiers Blueprint dont vous connaissez la provenance : ce fichier reconfigurera votre instance une fois appliqué via l\'assistant.', 'configurationglpiauto');
echo "</div>";
echo "<button type='submit' name='import' value='1' class='btn btn-primary'>";
echo "<i class='ti ti-upload'></i> " . __('Importer');
echo "</button>";
echo "</div>";
echo "</form>";

Html::footer();
