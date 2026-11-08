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
use GlpiPlugin\Configurationglpiauto\Jira\JiraApiClient;
use GlpiPlugin\Configurationglpiauto\Jira\JiraApiMapper;
use GlpiPlugin\Configurationglpiauto\Jira\JiraConnection;
use GlpiPlugin\Configurationglpiauto\Jira\JiraImporter;
use GlpiPlugin\Configurationglpiauto\Jira\JiraMapping;

// Issue #345 : import de tickets Jira (export CSV). Droit de modification de la configuration du
// plugin ET droit natif de créer des tickets.
Session::checkRight(Config::$rightname, UPDATE);
Session::checkRight(Ticket::$rightname, CREATE);

$url = FrontUrl::get('jiraimport.php');

if (isset($_POST['cancel'])) {
    // Import par lots en cours : on l'arrête, les tickets déjà importés et le journal restent.
    if (JiraImporter::job() !== null) {
        JiraImporter::abandonJob();
        Html::redirect($url . '?step=result');
    }
    JiraImporter::forgetPending();
    Html::redirect($url);
}

// Issue #345 : journal CSV du dernier import (ou de l'import en cours).
if (isset($_GET['download_log'])) {
    $log = JiraImporter::logFile();
    if ($log === '') {
        Html::redirect($url);
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="jira-import-journal-' . date('Ymd-His') . '.csv"');
    header('Content-Length: ' . (string) filesize($log));
    readfile($log);
    exit;
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

// Issue #345, phase 2 : lecture par l'API REST de Jira. Le jeton sert à cette lecture puis est
// oublié (jamais enregistré) ; HTTPS exigé sauf case cochée explicitement.
if (isset($_POST['fetch_api'])) {
    $entitiesId = (int) ($_POST['entities_id'] ?? 0);
    $flavour = ($_POST['jira_flavour'] ?? '') === JiraApiMapper::FLAVOUR_CLOUD
        ? JiraApiMapper::FLAVOUR_CLOUD : JiraApiMapper::FLAVOUR_DATACENTER;
    $baseUrl = JiraApiMapper::normalizeBaseUrl((string) ($_POST['jira_url'] ?? ''), !empty($_POST['allow_http']));
    $user = trim((string) ($_POST['jira_user'] ?? ''));
    $token = (string) ($_POST['jira_token'] ?? '');
    $jql = trim((string) ($_POST['jira_jql'] ?? ''));
    $missing = $baseUrl === null || $token === '' || $jql === ''
        || ($flavour === JiraApiMapper::FLAVOUR_CLOUD && $user === '');
    if ($missing || !Session::haveAccessToEntity($entitiesId)) {
        Session::addMessageAfterRedirect(
            htmlescape(__('Renseignez l\'adresse de Jira (HTTPS), le jeton, la requête JQL (et l\'adresse e-mail pour Jira Cloud), et une entité à laquelle vous avez accès.', 'configurationglpiauto')),
            false,
            ERROR
        );
        Html::redirect($url);
    }
    $client = new JiraApiClient();
    $fetched = $client->fetch($baseUrl, $flavour, $user, $token, $jql);
    // Pièces jointes téléchargées maintenant : le jeton est oublié juste après.
    $attachmentsDir = '';
    if ($fetched['issues'] !== []) {
        $attachmentsDir = JiraImporter::newAttachmentsDir();
        $client->downloadAttachments($fetched['issues'], $baseUrl, $flavour, $user, $token, $attachmentsDir);
    }
    unset($token, $_POST['jira_token']);
    if ($fetched['issues'] === [] && $fetched['errors'] !== []) {
        $apiErrors = [
            'api_unauthorized' => __('Jira refuse l\'accès : vérifiez l\'adresse e-mail et le jeton, et les droits du compte sur le projet.', 'configurationglpiauto'),
            'api_not_found'    => __('Adresse introuvable : vérifiez l\'adresse de Jira et son type (Cloud ou Data Center).', 'configurationglpiauto'),
            'api_bad_query'    => __('Jira refuse la requête JQL : vérifiez-la dans Jira.', 'configurationglpiauto'),
            'api_unexpected'   => __('Réponse inattendue : ce n\'est pas l\'API de Jira.', 'configurationglpiauto'),
            'api_unreachable'  => __('Jira injoignable depuis le serveur GLPI (réseau, proxy ou certificat).', 'configurationglpiauto'),
            'api_private_network' => sprintf(
                __('GLPI refuse par défaut les appels vers un réseau privé. Pour un Jira interne, un administrateur ajoute %s à la constante GLPI_SERVERSIDE_URL_ALLOWED_PRIVATE_NETWORKS_CONTEXTS (fichier config/local_define.php).', 'configurationglpiauto'),
                JiraConnection::class
            ),
        ];
        $code = (string) $fetched['errors'][0]['message'];
        Session::addMessageAfterRedirect(htmlescape($apiErrors[$code] ?? $code), false, ERROR);
        Html::redirect($url);
    }
    if ($fetched['truncated']) {
        Session::addMessageAfterRedirect(htmlescape(sprintf(
            __('Plus de %d tickets : seuls les premiers sont importés, affinez la requête JQL pour le reste.', 'configurationglpiauto'),
            JiraApiClient::MAX_ISSUES
        )), false, WARNING);
    }
    JiraImporter::storePendingIssues($fetched['issues'], $fetched['errors'], $entitiesId, $attachmentsDir);
    Html::redirect($url . '?step=preview');
}

// Issue #345 : import par lots avec reprise. Les correspondances choisies sont gardées en session,
// chaque requête traite un lot puis la page suivante enchaîne (ou l'utilisateur reprend plus tard).
if (isset($_POST['import'])) {
    $parsed = JiraImporter::loadPending();
    $entitiesId = JiraImporter::pendingEntity();
    if ($parsed === null || !Session::haveAccessToEntity($entitiesId)) {
        Session::addMessageAfterRedirect(htmlescape(__('Fichier d\'import expiré : recommencez.', 'configurationglpiauto')), false, ERROR);
        Html::redirect($url);
    }
    JiraImporter::startJob([
        'status_map'       => (array) ($_POST['status_map'] ?? []),
        'type_map'         => (array) ($_POST['type_map'] ?? []),
        'import_labels'    => !empty($_POST['import_labels']),
        'project_map'      => JiraImporter::projectMap(
            $parsed['issues'],
            (array) ($_POST['project_entity'] ?? []),
            (array) ($_POST['project_category'] ?? []),
            $entitiesId
        ),
        'qualify_security' => !empty($_POST['qualify_security']),
    ], count($parsed['issues']));
    Html::redirect($url . '?step=run');
}

if (isset($_POST['continue_import'])) {
    if (JiraImporter::job() === null) {
        Html::redirect($url);
    }
    if (!Session::haveAccessToEntity(JiraImporter::pendingEntity())) {
        JiraImporter::abandonJob();
        Html::redirect($url . '?step=result');
    }
    $done = (new JiraImporter())->runNextBatch();
    Html::redirect($url . ($done ? '?step=result' : '?step=run'));
}

Html::header(__('Importer depuis Jira', 'configurationglpiauto'), $url, 'config', ConfigurationProfile::class, 'jiraimport');

$statusLabels = [
    JiraMapping::STATUS_NEW          => __('Nouveau', 'configurationglpiauto'),
    JiraMapping::STATUS_ASSIGNED     => __('En cours (attribué)', 'configurationglpiauto'),
    JiraMapping::STATUS_WAITING      => __('En attente', 'configurationglpiauto'),
    JiraMapping::STATUS_SOLVED       => __('Résolu', 'configurationglpiauto'),
    JiraMapping::STATUS_CLOSED       => __('Clos', 'configurationglpiauto'),
    JiraMapping::STATUS_NOT_RETAINED => __('Clos: « Demande non retenue »', 'configurationglpiauto'),
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
$job = JiraImporter::job();
$last = JiraImporter::last();
if ($job !== null) {
    // Import en cours, y compris après une interruption : la page reprend où il s'est arrêté.
    $data += [
        'step'       => 'run',
        'job'        => [
            'done'     => (int) $job['cursor'],
            'total'    => (int) $job['total'],
            'percent'  => (int) $job['total'] > 0 ? (int) floor(100 * (int) $job['cursor'] / (int) $job['total']) : 100,
            'result'   => $job['result'],
            'resumed'  => ($_GET['step'] ?? '') !== 'run',
        ],
        'batch_size' => JiraImporter::BATCH_SIZE,
        'log_url'    => $url . '?download_log=1',
    ];
} elseif (($_GET['step'] ?? '') === 'result' && $last !== null) {
    $data += [
        'step'        => 'result',
        'result'      => $last['result'],
        'total'       => (int) $last['total'],
        'log'         => JiraImporter::logLines(),
        'log_url'     => $url . '?download_log=1',
        'tickets_url' => Ticket::getSearchURL(),
    ];
} else {
    $parsed = ($_GET['step'] ?? '') === 'preview' ? JiraImporter::loadPending() : null;
    if ($parsed === null) {
        // Étape 1 : fichier et entité.
        $data += ['step' => 'upload', 'entity_dropdown' => Entity::dropdown([
            'name'    => 'entities_id',
            'value'   => Session::getActiveEntity(),
            'entity'  => $_SESSION['glpiactiveentities'],
            'display' => false,
        ]), 'api_entity_dropdown' => Entity::dropdown([
            'name'    => 'entities_id',
            'value'   => Session::getActiveEntity(),
            'entity'  => $_SESSION['glpiactiveentities'],
            'display' => false,
            'rand'    => mt_rand(),
        ])];
    } else {
        // Étape 2 : aperçu à blanc et correspondances.
        $preview = (new JiraImporter())->preview($parsed['issues']);
        // Issue #345 : entité et catégorie par projet Jira (par défaut : entité de l'import).
        $projects = [];
        foreach ($preview['summary']['projects'] ?? [] as $project => $count) {
            $key = JiraMapping::projectKey((string) $project);
            $projects[] = [
                'name'     => (string) $project,
                'count'    => (int) $count,
                'entity'   => Entity::dropdown([
                    'name'    => 'project_entity[' . $key . ']',
                    'value'   => JiraImporter::pendingEntity(),
                    'entity'  => $_SESSION['glpiactiveentities'],
                    'display' => false,
                    'rand'    => mt_rand(),
                ]),
                'category' => ITILCategory::dropdown([
                    'name'    => 'project_category[' . $key . ']',
                    'value'   => 0,
                    'entity'  => JiraImporter::pendingEntity(),
                    'display' => false,
                    'rand'    => mt_rand(),
                ]),
            ];
        }
        $data += [
            'step'         => 'preview',
            'preview'      => $preview,
            'projects'     => $projects,
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
