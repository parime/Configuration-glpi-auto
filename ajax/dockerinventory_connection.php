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
use GlpiPlugin\Configurationglpiauto\DockerInventorySetup;

/**
 * Issue #452 : boutons de l'étape « Inventaire Docker » de l'assistant.
 * - action=test : teste la saisie, rien n'est enregistré ;
 * - action=save : même test, puis création de la connexion s'il a réussi ;
 * - action=registries : registres maîtrisés de Harbor.
 *
 * Droits : modification de la configuration de ce plugin, droit natif « Configuration » (une
 * connexion donne accès à toute l'infrastructure de conteneurs, comme un collecteur de courriels),
 * puis droit d'Inventaire Docker pour créer une connexion (vérifié par DockerInventorySetup).
 * Les secrets reçus ne sont jamais renvoyés ni journalisés.
 */

Session::checkRight(Config::$rightname, UPDATE);
Session::checkRight('config', UPDATE);

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['level' => 'error', 'message' => 'POST only']);
    return;
}

if (!DockerInventorySetup::isAvailable()) {
    http_response_code(404);
    echo json_encode(['level' => 'error', 'message' => __('Le plugin Inventaire Docker n\'est pas actif.', 'configurationglpiauto')]);
    return;
}

if (!DockerInventorySetup::canCreate()) {
    http_response_code(403);
    echo json_encode(['level' => 'error', 'message' => __('Vous n\'avez pas le droit de créer une connexion dans Inventaire Docker.', 'configurationglpiauto')]);
    return;
}

$setup = new DockerInventorySetup();
$action = (string) ($_POST['action'] ?? '');
$type = (string) ($_POST['type'] ?? '');
$raw = [];
foreach (DockerInventorySetup::FIELDS[$type] ?? [] as $field) {
    if (isset($_POST[$field]) && is_string($_POST[$field])) {
        $raw[$field] = $_POST[$field];
    }
}

$result = match ($action) {
    'test' => $setup->test($type, $raw),
    'save' => $setup->save($type, $raw),
    'registries' => $setup->saveManagedRegistries((string) ($_POST['managed_registries'] ?? '')),
    default => ['level' => 'error', 'message' => __('Action inconnue.', 'configurationglpiauto')],
};

echo json_encode($result);
