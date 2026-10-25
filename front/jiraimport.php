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
$errorLabels = [
    'missing_columns'        => __('En-tête sans colonnes « Clé » et « Résumé » : ce n\'est pas un export Jira.', 'configurationglpiauto'),
    'empty'                  => __('Fichier vide.', 'configurationglpiauto'),
    'missing_key_or_summary' => __('Ligne sans clé ou sans résumé : ignorée.', 'configurationglpiauto'),
];
$data = [
    'url'           => $url,
    'status_labels' => $statusLabels,
    'type_labels'   => $typeLabels,
];
if ($result !== null) {
    $data += ['step' => 'result', 'result' => $result, 'tickets_url' => Ticket::getSearchURL()];
} else {
    $parsed = ($_GET['step'] ?? '') === 'preview' ? JiraImporter::loadPending() : null;
    if ($parsed === null) {
        // Étape 1 : fichier et entité.
        $data += ['step' => 'upload', 'entity_dropdown' => Entity::dropdown([
            'name'    => 'entities_id',
            'value'   => Session::getActiveEntity(),
            'entity'  => $_SESSION['glpiactiveentities'],
            'display' => false,
        ])];
    } else {
        // Étape 2 : aperçu à blanc et correspondances.
        $data += [
            'step'         => 'preview',
            'preview'      => (new JiraImporter())->preview($parsed['issues']),
            'entity_name'  => Dropdown::getDropdownName('glpi_entities', JiraImporter::pendingEntity()),
            'parse_errors' => array_map(
                static fn (array $error): array => $error + ['label' => $errorLabels[$error['message']] ?? $error['message']],
                $parsed['errors']
            ),
        ];
    }
}
TemplateRenderer::getInstance()->display('@configurationglpiauto/jira_import.html.twig', $data);

Html::footer();
