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
 * ------------------------------------------------------------------------- * SPDX-License-Identifier: GPL-3.0-or-later

 */

use GlpiPlugin\Configurationglpiauto\Config;

/**
 * Issue #429 : bouton « Tester la connexion » de l'étape « Réception des tickets par e-mail ».
 * Teste un collecteur DÉJÀ enregistré dans GLPI avec la connexion de GLPI lui-même
 * (`MailCollector::connect()`, celle de la relève et de la liste des dossiers de l'écran natif) :
 * aucun message n'est relevé ni déplacé, seul le nombre de messages du dossier est lu. Jamais un
 * serveur ou un mot de passe fourni par la requête : seul l'identifiant du collecteur est lu.
 */

Session::checkRight(Config::$rightname, UPDATE);
Session::checkRight(MailCollector::$rightname, UPDATE);

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST only']);
    return;
}

$collector = new MailCollector();
if (!$collector->getFromDB((int) ($_POST['id'] ?? 0))) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => __('Collecteur introuvable.', 'configurationglpiauto')]);
    return;
}

if (empty($collector->fields['passwd'])) {
    echo json_encode(['ok' => false, 'message' => __('Aucun mot de passe enregistré pour ce collecteur : saisissez-le dans l\'assistant ou dans l\'écran du collecteur.', 'configurationglpiauto')]);
    return;
}

try {
    $collector->connect();
    $count = (int) $collector->getTotalMails();
    echo json_encode([
        'ok' => true,
        'message' => sprintf(__('Connexion réussie : %d message(s) dans le dossier relevé.', 'configurationglpiauto'), $count),
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'message' => sprintf(__('Échec de la connexion : %s', 'configurationglpiauto'), $e->getMessage()),
    ]);
}
