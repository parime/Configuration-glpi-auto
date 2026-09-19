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
use GlpiPlugin\Configurationglpiauto\ConfigHistory;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;

Session::checkRight(Config::$rightname, READ);

// Import/Export avancé (issue #117), volet "conflict resolution" : cet écran sert désormais aussi
// bien à restaurer une entrée d'historique (ConfigHistory, #114) qu'à appliquer un Blueprint
// (ConfigurationProfile, #113) — les deux sont structurellement le même Blueprint stocké dans une
// colonne `snapshot`, la même revue diff+sélective a donc du sens pour les deux plutôt que de
// garder un écrasement complet sans revue pour l'un des deux chemins. `ConfigHistory` par défaut :
// rétrocompatible avec les liens existants générés avant #117 (sans paramètre `itemtype`).
// Une branche explicite plutôt que `new $itemtype()` : seules deux classes sont jamais légitimes
// ici, autant l'exprimer directement plutôt que d'instancier une classe à partir d'une chaîne
// dérivée de la requête (Semgrep signale à raison ce motif comme une instanciation d'objet
// potentiellement contaminée, même quand — comme ici — une liste blanche stricte la précède déjà).
$requestedItemtype = $_GET['itemtype'] ?? $_POST['itemtype'] ?? ConfigHistory::class;
$itemtype = $requestedItemtype === ConfigurationProfile::class ? ConfigurationProfile::class : ConfigHistory::class;

$item = $itemtype === ConfigurationProfile::class ? new ConfigurationProfile() : new ConfigHistory();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$item->check($id, READ);

$decoded = json_decode((string) $item->fields['snapshot'], true);
if (!is_array($decoded)) {
    Session::addMessageAfterRedirect(__('Ce Blueprint n\'est pas exploitable.', 'configurationglpiauto'), false, ERROR);
    Html::back();
}

if (isset($_POST['restore_all']) || isset($_POST['restore_selected'])) {
    Session::checkRight(Config::$rightname, UPDATE);

    $onlyFields = isset($_POST['restore_selected'])
        ? array_map('strval', (array) ($_POST['fields'] ?? []))
        : null;

    $config = Config::getConfig();
    // La restauration est elle-même un changement réel de Config : elle laisse donc elle aussi une
    // trace automatique, permettant d'annuler la restauration comme n'importe quel autre changement.
    ConfigHistory::captureAutomatic($config);
    $fields = BlueprintSerializer::import($decoded, Config::getDefaults(), $onlyFields);
    $config->update($fields + ['id' => $config->getID()]);

    Session::addMessageAfterRedirect(__('Restauration appliquée. Vérifiez chaque étape avant de valider.', 'configurationglpiauto'));
    Html::redirect(ConfigurationProfile::getSearchURL());
}

Html::header(__('Restaurer une configuration', 'configurationglpiauto'), $_SERVER['PHP_SELF'], 'config', $itemtype);

$fullyDefaulted = BlueprintSerializer::import($decoded, Config::getDefaults());
$diff = BlueprintSerializer::diff(Config::getConfig()->fields, $fullyDefaulted);

$groups = [];
foreach ($diff as $field => $values) {
    $groups[FieldGroupLabel::for($field)][$field] = $values;
}
ksort($groups);

// Import/Export avancé (issue #117), volet "validation automatique" : Config::prepareInputForAdd()
// (pur, sans effet de bord — voir ConfigTest) corrige déjà silencieusement les valeurs devenues
// invalides (branche de catégorie disparue, statut hors liste blanche, profil LDAP non détenu par
// l'utilisateur qui importe...) sans jamais le dire. Réutilise diff() tel quel plutôt que d'écrire
// une nouvelle logique de comparaison : $fullyDefaulted est déjà "ce qui serait appliqué",
// $sanitized "ce qui serait réellement écrit une fois passé par le même sanitizeur que Config::
// update() applique de toute façon" — jamais bloquant, purement informatif.
$sanitized = (new Config())->prepareInputForAdd($fullyDefaulted);
$autoCorrections = BlueprintSerializer::diff($fullyDefaulted, $sanitized);

\Glpi\Application\View\TemplateRenderer::getInstance()->display('@configurationglpiauto/history_restore.html.twig', [
    'item'             => $item,
    'itemtype'         => $itemtype,
    'groups'           => $groups,
    'auto_corrections' => $autoCorrections,
    'csrf_token'       => Session::getNewCSRFToken(),
]);

Html::footer();
