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
use GlpiPlugin\Configurationglpiauto\Blueprint\FieldGroupLabel;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;

// Les pages front sont incluses depuis une méthode du routeur GLPI : $CFG_GLPI n'y est
// visible qu'après une déclaration globale explicite.
global $CFG_GLPI;

$item = new ConfigurationProfile();

if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    $newID = $item->add($_POST);
    // Comme les formulaires natifs : retour sur le profil créé plutôt que sur un formulaire vide.
    if ($newID && $_SESSION['glpibackcreated']) {
        Html::redirect($item->getLinkURL());
    }
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
        Session::addMessageAfterRedirect(__('Ce profil ne contient aucun Blueprint exploitable.', 'configurationglpiauto'), false, ERROR);
        Html::back();
    }
    $decoded = json_decode((string) $item->fields['snapshot'], true);
    if (!is_array($decoded)) {
        Session::addMessageAfterRedirect(__('Ce profil ne contient aucun Blueprint exploitable.', 'configurationglpiauto'), false, ERROR);
        Html::back();
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
        Html::header(__('Exporter ce Blueprint', 'configurationglpiauto'), \GlpiPlugin\Configurationglpiauto\FrontUrl::get('profile.form.php'), 'config', ConfigurationProfile::class);
        $groups = [];
        foreach (array_keys($decoded['config'] ?? []) as $field) {
            $groups[FieldGroupLabel::for($field)][] = $field;
        }
        ksort($groups);
        $formUrl = \GlpiPlugin\Configurationglpiauto\FrontUrl::get('profile.form.php');
        TemplateRenderer::getInstance()->display('@configurationglpiauto/profile_export.html.twig', [
            'action'         => $formUrl,
            'profile_id'     => (int) $_GET['id'],
            'groups'         => array_map(
                static fn (string $label, array $fields): array => ['label' => $label, 'fields' => $fields],
                array_keys($groups),
                array_values($groups)
            ),
            'export_all_url' => $formUrl . '?id=' . (int) $_GET['id'] . '&export_snapshot=1&all=1',
        ]);
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
    Html::header(ConfigurationProfile::getTypeName(1), \GlpiPlugin\Configurationglpiauto\FrontUrl::get('profile.form.php'), 'config', ConfigurationProfile::class);
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $item->getFromDB($id);
        $formUrl = \GlpiPlugin\Configurationglpiauto\FrontUrl::get('profile.form.php');
        TemplateRenderer::getInstance()->display('@configurationglpiauto/profile_form_actions.html.twig', [
            'action'       => $formUrl,
            'profile_id'   => $id,
            'has_snapshot' => !empty($item->fields['snapshot']),
            'export_url'   => $formUrl . '?id=' . $id . '&export_snapshot=1',
        ]);
    }
    $item->showForm($id);
    Html::footer();
}
