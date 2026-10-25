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

    // Seul le format Blueprint est conservé : sans ce filtre, n'importe quelle clé du fichier
    // (y compris plusieurs Mo de données sans rapport) était stockée telle quelle dans le profil.
    $decoded['config'] = array_intersect_key($decoded['config'], Config::getDefaults());
    $decoded = array_intersect_key(
        $decoded,
        array_flip(['format_version', 'plugin_version', 'exported_at', 'profile_name', 'config'])
    );
    $profile = new ConfigurationProfile();
    $profile->add([
        'name'     => $name,
        'type'     => 'custom',
        'snapshot' => json_encode($decoded, JSON_PRETTY_PRINT),
    ]);

    Session::addMessageAfterRedirect(__('Blueprint importé comme nouveau profil.', 'configurationglpiauto'));
    if ($profile->fields['name'] !== $name) {
        Session::addMessageAfterRedirect(htmlescape(sprintf(
            __('Un profil s\'appelait déjà « %1$s » : le nouveau profil s\'appelle « %2$s ».', 'configurationglpiauto'),
            $name,
            $profile->fields['name']
        )), false, WARNING);
    }
    Html::redirect(ConfigurationProfile::getFormURL() . '?id=' . $profile->getID());
}

Html::header(__('Importer un Blueprint', 'configurationglpiauto'), \GlpiPlugin\Configurationglpiauto\FrontUrl::get('blueprint_import.php'), 'config', ConfigurationProfile::class);

TemplateRenderer::getInstance()->display('@configurationglpiauto/blueprint_import.html.twig', [
    'action' => \GlpiPlugin\Configurationglpiauto\FrontUrl::get('blueprint_import.php'),
]);

Html::footer();
