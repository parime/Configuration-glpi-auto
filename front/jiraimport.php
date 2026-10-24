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
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\FrontUrl;
use GlpiPlugin\Configurationglpiauto\Jira\JiraImporter;
use GlpiPlugin\Configurationglpiauto\Jira\JiraMapping;

// Issue #345 : import de tickets Jira (export CSV). Droit de modification de la configuration du
// plugin ET droit natif de créer des tickets.
Session::checkRight(Config::$rightname, UPDATE);
Session::checkRight(Ticket::$rightname, CREATE);

$url = FrontUrl::get('jiraimport.php');

if (isset($_POST['cancel'])) {
    JiraImporter::forgetPending();
    Html::redirect($url);
}

if (isset($_POST['upload'])) {
    $file = $_FILES['jira_csv'] ?? null;
    $entitiesId = (int) ($_POST['entities_id'] ?? 0);
    $ok = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        && is_uploaded_file((string) $file['tmp_name']) && (int) $file['size'] <= 20 * 1024 * 1024;
    if (!$ok || !Session::haveAccessToEntity($entitiesId)) {
        Session::addMessageAfterRedirect(
            htmlescape(__('Choisissez un export CSV de Jira (20 Mo au plus) et une entité à laquelle vous avez accès.', 'configurationglpiauto')),
            false,
            ERROR
        );
        Html::redirect($url);
    }
    $content = (string) file_get_contents((string) $file['tmp_name']);
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
    }
    JiraImporter::storePending($content, $entitiesId);
    Html::redirect($url . '?step=preview');
}

$result = null;
if (isset($_POST['import'])) {
    $parsed = JiraImporter::loadPending();
    $entitiesId = JiraImporter::pendingEntity();
    if ($parsed === null || !Session::haveAccessToEntity($entitiesId)) {
        Session::addMessageAfterRedirect(htmlescape(__('Fichier d\'import expiré : recommencez.', 'configurationglpiauto')), false, ERROR);
        Html::redirect($url);
    }
    $result = (new JiraImporter())->import(
        $parsed['issues'],
        (array) ($_POST['status_map'] ?? []),
        (array) ($_POST['type_map'] ?? []),
        $entitiesId
    );
    JiraImporter::forgetPending();
}

Html::header(__('Importer depuis Jira', 'configurationglpiauto'), $url, 'config', ConfigurationProfile::class);

$statusLabels = [
    JiraMapping::STATUS_NEW          => __('Nouveau', 'configurationglpiauto'),
    JiraMapping::STATUS_ASSIGNED     => __('En cours (attribué)', 'configurationglpiauto'),
    JiraMapping::STATUS_WAITING      => __('En attente', 'configurationglpiauto'),
    JiraMapping::STATUS_SOLVED       => __('Résolu', 'configurationglpiauto'),
    JiraMapping::STATUS_CLOSED       => __('Clos', 'configurationglpiauto'),
    JiraMapping::STATUS_NOT_RETAINED => __('Clos — « Demande non retenue »', 'configurationglpiauto'),
];
$typeLabels = [
    JiraMapping::TYPE_INCIDENT => __('Incident', 'configurationglpiauto'),
    JiraMapping::TYPE_REQUEST  => __('Demande', 'configurationglpiauto'),
];
$token = htmlescape(Session::getNewCSRFToken());

echo '<div class="card"><div class="card-body">';
echo '<h3 class="card-title"><i class="ti ti-transfer-in me-1"></i>' . htmlescape(__('Importer depuis Jira', 'configurationglpiauto')) . '</h3>';

if ($result !== null) {
    echo '<div class="alert alert-success">' . htmlescape(sprintf(
        __('Import terminé : %1$d ticket(s) créé(s), %2$d mis à jour.', 'configurationglpiauto'),
        $result['created'],
        $result['updated']
    )) . '</div>';
    foreach ($result['errors'] as $error) {
        echo '<div class="alert alert-danger py-2">' . htmlescape($error) . '</div>';
    }
    echo '<details><summary>' . htmlescape(__('Journal de l\'import', 'configurationglpiauto')) . '</summary><pre class="small">'
        . htmlescape(implode("\n", $result['log'])) . '</pre></details>';
    echo '<a class="btn btn-outline-primary mt-3" href="' . htmlescape(Ticket::getSearchURL()) . '">'
        . htmlescape(__('Voir les tickets', 'configurationglpiauto')) . '</a>';
    echo '</div></div>';
    Html::footer();
    return;
}

$parsed = ($_GET['step'] ?? '') === 'preview' ? JiraImporter::loadPending() : null;
if ($parsed === null) {
    // Étape 1 : fichier et entité.
    echo '<p>' . htmlescape(__(
        'Dans Jira, exportez les tickets en CSV (« Toutes les colonnes »). L\'import se fait en deux temps : un aperçu à blanc, sans rien écrire, où vous vérifiez les correspondances de statuts et de types, puis l\'import. Les personnes sont reconnues par adresse e-mail ou par nom ; une personne inconnue est signalée, jamais créée. Réimporter le même fichier met à jour les tickets au lieu de les recréer. Aucune notification n\'est envoyée.',
        'configurationglpiauto'
    )) . '</p>';
    echo '<form method="post" enctype="multipart/form-data" action="' . htmlescape($url) . '" class="row g-3 align-items-end">';
    echo '<input type="hidden" name="_glpi_csrf_token" value="' . $token . '">';
    echo '<div class="col-md-5"><label class="form-label" for="jira_csv">' . htmlescape(__('Export CSV de Jira', 'configurationglpiauto'))
        . '</label><input type="file" id="jira_csv" name="jira_csv" accept=".csv,text/csv" class="form-control" required></div>';
    echo '<div class="col-md-4"><label class="form-label">' . htmlescape(__('Entité des tickets', 'configurationglpiauto')) . '</label>';
    Entity::dropdown(['name' => 'entities_id', 'value' => Session::getActiveEntity(), 'entity' => $_SESSION['glpiactiveentities']]);
    echo '</div><div class="col-md-3"><button type="submit" name="upload" value="1" class="btn btn-primary">'
        . '<i class="ti ti-eye me-1"></i>' . htmlescape(__('Aperçu (rien n\'est écrit)', 'configurationglpiauto'))
        . '</button></div></form>';
    echo '</div></div>';
    Html::footer();
    return;
}

// Étape 2 : aperçu à blanc et correspondances.
$preview = (new JiraImporter())->preview($parsed['issues']);
echo '<div class="alert alert-info">' . htmlescape(sprintf(
    __('%1$d ticket(s) lus, dont %2$d déjà importé(s) (mis à jour plutôt que recréés). Entité : %3$s.', 'configurationglpiauto'),
    $preview['total'],
    $preview['already_imported'],
    Dropdown::getDropdownName('glpi_entities', JiraImporter::pendingEntity())
)) . '</div>';
$errorLabels = [
    'missing_columns'        => __('En-tête sans colonnes « Clé » et « Résumé » : ce n\'est pas un export Jira.', 'configurationglpiauto'),
    'empty'                  => __('Fichier vide.', 'configurationglpiauto'),
    'missing_key_or_summary' => __('Ligne sans clé ou sans résumé : ignorée.', 'configurationglpiauto'),
];
foreach ($parsed['errors'] as $error) {
    echo '<div class="alert alert-warning py-2">' . htmlescape(sprintf(
        __('Ligne %1$d : %2$s', 'configurationglpiauto'),
        $error['line'],
        $errorLabels[$error['message']] ?? $error['message']
    )) . '</div>';
}

echo '<form method="post" action="' . htmlescape($url) . '">';
echo '<input type="hidden" name="_glpi_csrf_token" value="' . $token . '">';
echo '<div class="row g-4">';
echo '<div class="col-lg-6"><h4>' . htmlescape(__('Statuts Jira', 'configurationglpiauto')) . '</h4><table class="table table-sm align-middle">';
foreach ($preview['statuses'] as $jiraStatus => $default) {
    echo '<tr><td>' . htmlescape($jiraStatus) . ' <span class="badge bg-secondary-lt">' . (int) $preview['summary']['statuses'][$jiraStatus]
        . '</span></td><td><select name="status_map[' . htmlescape($jiraStatus) . ']" class="form-select form-select-sm">';
    foreach ($statusLabels as $value => $label) {
        echo '<option value="' . htmlescape((string) $value) . '"' . ((string) $value === (string) $default ? ' selected' : '') . '>'
            . htmlescape($label) . '</option>';
    }
    echo '</select></td></tr>';
}
echo '</table></div>';
echo '<div class="col-lg-6"><h4>' . htmlescape(__('Types de ticket Jira', 'configurationglpiauto')) . '</h4><table class="table table-sm align-middle">';
foreach ($preview['types'] as $jiraType => $default) {
    echo '<tr><td>' . htmlescape($jiraType) . ' <span class="badge bg-secondary-lt">' . (int) $preview['summary']['types'][$jiraType]
        . '</span></td><td><select name="type_map[' . htmlescape($jiraType) . ']" class="form-select form-select-sm">';
    foreach ($typeLabels as $value => $label) {
        echo '<option value="' . $value . '"' . ($value === $default ? ' selected' : '') . '>' . htmlescape($label) . '</option>';
    }
    echo '</select></td></tr>';
}
echo '</table></div></div>';

echo '<h4 class="mt-3">' . htmlescape(__('Personnes', 'configurationglpiauto')) . '</h4>';
echo '<p>' . htmlescape(sprintf(
    __('%1$d reconnue(s) dans GLPI, %2$d inconnue(s).', 'configurationglpiauto'),
    count($preview['people_found']),
    count($preview['people_missing'])
)) . '</p>';
if ($preview['people_missing'] !== []) {
    echo '<div class="alert alert-warning py-2">' . htmlescape(__(
        'Inconnues (aucun compte créé ; le ticket sera importé sans elles, leurs commentaires gardent leur nom) :',
        'configurationglpiauto'
    )) . ' ' . htmlescape(implode(', ', $preview['people_missing'])) . '</div>';
}
echo '<div class="d-flex gap-2 mt-3"><button type="submit" name="import" value="1" class="btn btn-primary">'
    . '<i class="ti ti-transfer-in me-1"></i>' . htmlescape(sprintf(__('Importer %d ticket(s)', 'configurationglpiauto'), $preview['total']))
    . '</button><button type="submit" name="cancel" value="1" class="btn btn-outline-secondary" formnovalidate>'
    . htmlescape(__('Annuler', 'configurationglpiauto')) . '</button></div>';
echo '</form></div></div>';

Html::footer();
