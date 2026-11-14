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
use GlpiPlugin\Configurationglpiauto\Import\CsvColumns;
use GlpiPlugin\Configurationglpiauto\Import\CsvImportProfile;
use GlpiPlugin\Configurationglpiauto\Import\CsvTicketImporter;
use GlpiPlugin\Configurationglpiauto\Import\TicketMapping;

// Issue #430 : import générique de tickets depuis un fichier CSV (autre outil d'assistance,
// tableur). Mêmes droits que l'import Jira : modification de la configuration du plugin ET droit
// natif de créer des tickets.
Session::checkRight(Config::$rightname, UPDATE);
Session::checkRight(Ticket::$rightname, CREATE);

$url = FrontUrl::get('csvimport.php');
$maxSize = 20 * 1024 * 1024;

/** Fichier téléversé sans erreur, de taille raisonnable (null sinon). */
$uploaded = static function (string $field, int $max): ?string {
    $file = $_FILES[$field] ?? null;
    $ok = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        && is_uploaded_file((string) $file['tmp_name']) && (int) $file['size'] <= $max;

    return $ok ? (string) $file['tmp_name'] : null;
};

if (isset($_POST['cancel'])) {
    // Import par lots en cours : on l'arrête, les tickets déjà importés et le journal restent.
    if (CsvTicketImporter::job() !== null) {
        CsvTicketImporter::abandonJob();
        Html::redirect($url . '?step=result');
    }
    CsvTicketImporter::forgetPending();
    Html::redirect($url);
}

// Journal CSV du dernier import (ou de l'import en cours).
if (isset($_GET['download_log'])) {
    $log = CsvTicketImporter::logFile();
    if ($log === '') {
        Html::redirect($url);
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="csv-import-journal-' . date('Ymd-His') . '.csv"');
    header('Content-Length: ' . (string) filesize($log));
    readfile($log);
    exit;
}

// Modèles de correspondance : export JSON, suppression, import JSON.
if (isset($_POST['export_profile'])) {
    $json = CsvImportProfile::export((int) $_POST['export_profile']);
    if ($json === null) {
        Html::redirect($url);
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="modele-import-csv-' . (int) $_POST['export_profile'] . '.json"');
    echo $json;
    exit;
}
if (isset($_POST['delete_profile'])) {
    CsvImportProfile::delete((int) $_POST['delete_profile']);
    Session::addMessageAfterRedirect(htmlescape(__('Modèle supprimé.', 'configurationglpiauto')));
    Html::redirect($url);
}
if (isset($_POST['import_profile'])) {
    $tmp = $uploaded('profile_json', 1024 * 1024);
    $profile = $tmp !== null ? CsvImportProfile::parseExport((string) file_get_contents($tmp)) : null;
    if ($profile === null) {
        Session::addMessageAfterRedirect(
            htmlescape(__('Ce fichier n\'est pas un modèle d\'import exporté par cette page.', 'configurationglpiauto')),
            false,
            ERROR
        );
    } else {
        CsvImportProfile::save($profile['name'], $profile['settings']);
        Session::addMessageAfterRedirect(htmlescape(sprintf(__('Modèle « %s » importé.', 'configurationglpiauto'), $profile['name'])));
    }
    Html::redirect($url);
}

// Étape 1 : fichier des tickets, entité, outil d'origine, fichiers facultatifs.
if (isset($_POST['upload'])) {
    $tmp = $uploaded('tickets_csv', $maxSize);
    $entitiesId = (int) ($_POST['entities_id'] ?? 0);
    if ($tmp === null || !Session::haveAccessToEntity($entitiesId)) {
        Session::addMessageAfterRedirect(
            htmlescape(__('Choisissez un fichier CSV (20 Mo au plus) et une entité à laquelle vous avez accès.', 'configurationglpiauto')),
            false,
            ERROR
        );
        Html::redirect($url);
    }
    $profile = CsvImportProfile::get((int) ($_POST['profile_id'] ?? 0));
    $sourceName = trim((string) ($_POST['source_name'] ?? ''));
    if ($sourceName === '' && $profile !== null) {
        $sourceName = $profile['settings']['source_name'];
    }
    CsvTicketImporter::storePending((string) file_get_contents($tmp), $entitiesId, [
        'source_name' => mb_substr($sourceName !== '' ? $sourceName : CsvTicketImporter::DEFAULT_SOURCE_NAME, 0, 100),
        'profile_id'  => $profile['id'] ?? 0,
    ]);
    $comments = $uploaded('comments_csv', $maxSize);
    if ($comments !== null) {
        CsvTicketImporter::storeComments((string) file_get_contents($comments));
    }
    $zip = $uploaded('attachments_zip', CsvTicketImporter::MAX_ATTACHMENTS_BYTES);
    if ($zip !== null) {
        $stored = CsvTicketImporter::storeAttachments($zip);
        if ($stored['skipped'] > 0) {
            Session::addMessageAfterRedirect(htmlescape(sprintf(
                __('%d fichier(s) de l\'archive écarté(s) : hors d\'un dossier portant l\'identifiant du ticket, vides ou trop volumineux (10 Mo par fichier, 200 Mo en tout).', 'configurationglpiauto'),
                $stored['skipped']
            )), false, WARNING);
        }
    }
    if (CsvTicketImporter::pendingTable() === null) {
        CsvTicketImporter::forgetPending();
        Session::addMessageAfterRedirect(htmlescape(__('Fichier vide ou illisible.', 'configurationglpiauto')), false, ERROR);
        Html::redirect($url);
    }
    Html::redirect($url . '?step=columns');
}

// Étape 2 : correspondance des colonnes (et modèle réutilisable).
if (isset($_POST['save_columns'])) {
    $table = CsvTicketImporter::pendingTable();
    if ($table === null) {
        Session::addMessageAfterRedirect(htmlescape(__('Fichier d\'import expiré : recommencez.', 'configurationglpiauto')), false, ERROR);
        Html::redirect($url);
    }
    $mapping = CsvColumns::normalize((array) ($_POST['column'] ?? []), count($table['header']));
    $separator = (string) ($_POST['separator'] ?? 'none');
    $settings = [
        'mapping'          => $mapping,
        'separator'        => in_array($separator, CsvColumns::SEPARATORS, true) ? $separator : 'none',
        'separator_custom' => mb_substr((string) ($_POST['separator_custom'] ?? ''), 0, 20),
    ];
    CsvTicketImporter::updatePending($settings);
    if (CsvColumns::missingRequired($mapping) !== []) {
        Session::addMessageAfterRedirect(
            htmlescape(__('Choisissez au moins la colonne de l\'identifiant d\'origine et celle du titre.', 'configurationglpiauto')),
            false,
            ERROR
        );
        Html::redirect($url . '?step=columns');
    }
    $profileName = trim((string) ($_POST['profile_name'] ?? ''));
    if (!empty($_POST['save_profile']) && $profileName !== '') {
        CsvImportProfile::save($profileName, [
            'source_name' => (string) (CsvTicketImporter::pending()['source_name'] ?? ''),
            'columns'     => CsvColumns::toLabels($mapping, $table['header']),
        ] + $settings);
        Session::addMessageAfterRedirect(htmlescape(sprintf(__('Modèle « %s » enregistré.', 'configurationglpiauto'), $profileName)));
    }
    Html::redirect($url . '?step=preview');
}

// Étape 3 : import par lots avec les correspondances de valeurs choisies dans l'aperçu.
if (isset($_POST['import'])) {
    $parsed = CsvTicketImporter::loadPending();
    $entitiesId = CsvTicketImporter::pendingEntity();
    if ($parsed === null || !Session::haveAccessToEntity($entitiesId)) {
        Session::addMessageAfterRedirect(htmlescape(__('Fichier d\'import expiré : recommencez.', 'configurationglpiauto')), false, ERROR);
        Html::redirect($url);
    }
    $values = static fn (string $field): array => array_values(array_unique(array_map(
        static fn (array $issue): string => (string) ($issue[$field] ?? ''),
        $parsed['issues']
    )));
    CsvTicketImporter::startJob([
        'status_map'       => CsvTicketImporter::valueMap($values('status'), (array) ($_POST['status_map'] ?? [])),
        'type_map'         => CsvTicketImporter::valueMap($values('type'), (array) ($_POST['type_map'] ?? [])),
        'import_labels'    => !empty($_POST['import_labels']),
        'project_map'      => [],
        'qualify_security' => !empty($_POST['qualify_security']),
        'value_maps'       => [
            'priority' => array_map('intval', CsvTicketImporter::valueMap($values('priority'), (array) ($_POST['priority_map'] ?? []))),
            'category' => CsvTicketImporter::categoryMap($values('category'), (array) ($_POST['category_map'] ?? []), $entitiesId),
        ],
    ], count($parsed['issues']));
    Html::redirect($url . '?step=run');
}

if (isset($_POST['continue_import'])) {
    if (CsvTicketImporter::job() === null) {
        Html::redirect($url);
    }
    if (!Session::haveAccessToEntity(CsvTicketImporter::pendingEntity())) {
        CsvTicketImporter::abandonJob();
        Html::redirect($url . '?step=result');
    }
    $done = (new CsvTicketImporter())->runNextBatch();
    Html::redirect($url . ($done ? '?step=result' : '?step=run'));
}

Html::header(__('Importer des tickets (CSV)', 'configurationglpiauto'), $url, 'config', ConfigurationProfile::class, 'csvimport');

$fieldLabels = [
    'key'            => __('Identifiant d\'origine', 'configurationglpiauto'),
    'summary'        => __('Titre', 'configurationglpiauto'),
    'description'    => __('Description', 'configurationglpiauto'),
    'status'         => __('Statut', 'configurationglpiauto'),
    'type'           => __('Type', 'configurationglpiauto'),
    'priority'       => __('Priorité', 'configurationglpiauto'),
    'category'       => __('Catégorie', 'configurationglpiauto'),
    'reporter'       => __('Demandeur (nom)', 'configurationglpiauto'),
    'reporter_email' => __('Demandeur (adresse e-mail)', 'configurationglpiauto'),
    'assignee'       => __('Technicien (nom)', 'configurationglpiauto'),
    'assignee_email' => __('Technicien (adresse e-mail)', 'configurationglpiauto'),
    'group'          => __('Groupe de techniciens', 'configurationglpiauto'),
    'created'        => __('Date d\'ouverture', 'configurationglpiauto'),
    'resolved'       => __('Date de résolution', 'configurationglpiauto'),
    'comments'       => __('Commentaires', 'configurationglpiauto'),
    'labels'         => __('Étiquettes', 'configurationglpiauto'),
];
$statusLabels = [
    TicketMapping::STATUS_NEW          => __('Nouveau', 'configurationglpiauto'),
    TicketMapping::STATUS_ASSIGNED     => __('En cours (attribué)', 'configurationglpiauto'),
    TicketMapping::STATUS_WAITING      => __('En attente', 'configurationglpiauto'),
    TicketMapping::STATUS_SOLVED       => __('Résolu', 'configurationglpiauto'),
    TicketMapping::STATUS_CLOSED       => __('Clos', 'configurationglpiauto'),
    TicketMapping::STATUS_NOT_RETAINED => __('Clos: « Demande non retenue »', 'configurationglpiauto'),
];
$typeLabels = [
    TicketMapping::TYPE_INCIDENT => __('Incident', 'configurationglpiauto'),
    TicketMapping::TYPE_REQUEST  => __('Demande', 'configurationglpiauto'),
];
$priorityLabels = [];
foreach ([5, 4, 3, 2, 1] as $priority) {
    $priorityLabels[$priority] = CommonITILObject::getPriorityName($priority);
}
$errorLabels = [
    'missing_columns'        => __('Colonnes de l\'identifiant d\'origine et du titre non choisies.', 'configurationglpiauto'),
    'empty'                  => __('Fichier vide.', 'configurationglpiauto'),
    'missing_key_or_summary' => __('Ligne sans identifiant ou sans titre : ignorée.', 'configurationglpiauto'),
    'duplicate_key'          => __('Identifiant déjà présent plus haut dans le fichier : ligne ignorée.', 'configurationglpiauto'),
];
$data = [
    'url'             => $url,
    'status_labels'   => $statusLabels,
    'type_labels'     => $typeLabels,
    'priority_labels' => $priorityLabels,
];
$step = (string) ($_GET['step'] ?? '');
$job = CsvTicketImporter::job();
$last = CsvTicketImporter::last();
$pending = CsvTicketImporter::pending();
$table = in_array($step, ['columns', 'preview'], true) ? CsvTicketImporter::pendingTable() : null;
if ($job !== null) {
    // Import en cours, y compris après une interruption : la page reprend où il s'est arrêté.
    $data += [
        'step'       => 'run',
        'job'        => [
            'done'     => (int) $job['cursor'],
            'total'    => (int) $job['total'],
            'percent'  => (int) $job['total'] > 0 ? (int) floor(100 * (int) $job['cursor'] / (int) $job['total']) : 100,
            'result'   => $job['result'],
            'resumed'  => $step !== 'run',
        ],
        'batch_size' => CsvTicketImporter::BATCH_SIZE,
        'log_url'    => $url . '?download_log=1',
    ];
} elseif ($step === 'result' && $last !== null) {
    $data += [
        'step'        => 'result',
        'result'      => $last['result'],
        'total'       => (int) $last['total'],
        'log'         => CsvTicketImporter::logLines(),
        'log_url'     => $url . '?download_log=1',
        'tickets_url' => Ticket::getSearchURL(),
    ];
} elseif ($step === 'columns' && $table !== null) {
    // Étape 2 : correspondance des colonnes, proposée d'après les en-têtes ou le modèle choisi.
    $mapping = isset($pending['mapping']) ? array_map('intval', (array) $pending['mapping']) : null;
    if ($mapping === null) {
        $profile = CsvImportProfile::get((int) ($pending['profile_id'] ?? 0));
        $mapping = $profile !== null
            ? CsvColumns::fromProfile($table['header'], $profile['settings']['columns']) + CsvColumns::suggest($table['header'])
            : CsvColumns::suggest($table['header']);
        $pending += $profile !== null ? [
            'separator'        => $profile['settings']['separator'],
            'separator_custom' => $profile['settings']['separator_custom'],
        ] : [];
    }
    $samples = CsvColumns::samples($table['header'], $table['rows']);
    $columns = [];
    foreach ($table['header'] as $position => $label) {
        $columns[] = [
            'position' => $position,
            'label'    => $label !== '' ? $label : sprintf(__('Colonne %d', 'configurationglpiauto'), $position + 1),
            'sample'   => $samples[$position] ?? '',
        ];
    }
    $fields = [];
    foreach ($fieldLabels as $field => $label) {
        $fields[] = [
            'name'     => $field,
            'label'    => $label,
            'required' => in_array($field, CsvColumns::REQUIRED, true),
            'selected' => $mapping[$field] ?? -1,
        ];
    }
    $comments = CsvTicketImporter::pendingComments();
    $profile = CsvImportProfile::get((int) ($pending['profile_id'] ?? 0));
    $data += [
        'step'              => 'columns',
        'columns'           => $columns,
        'fields'            => $fields,
        'rows'              => array_map(static fn (array $row): array => $row['values'], array_slice($table['rows'], 0, 3)),
        'delimiter'         => match ($table['delimiter']) {
            ';'     => __('point-virgule', 'configurationglpiauto'),
            "\t"    => __('tabulation', 'configurationglpiauto'),
            '|'     => __('barre verticale', 'configurationglpiauto'),
            default => __('virgule', 'configurationglpiauto'),
        },
        'source_name'       => (string) ($pending['source_name'] ?? ''),
        'separator'         => (string) ($pending['separator'] ?? 'none'),
        'separator_custom'  => (string) ($pending['separator_custom'] ?? ''),
        'has_comments_file' => (string) ($pending['comments_file'] ?? '') !== '',
        'comments'          => $comments,
        'attachments_count' => array_sum(array_map('count', (array) ($pending['attachments_index'] ?? []))),
        'profile_name'      => $profile['name'] ?? (string) ($pending['source_name'] ?? ''),
        'profile_loaded'    => $profile['name'] ?? '',
    ];
} elseif ($step === 'preview' && ($parsed = CsvTicketImporter::loadPending()) !== null && isset($pending['mapping'])) {
    // Étape 3 : aperçu à blanc et correspondances des valeurs.
    $entitiesId = CsvTicketImporter::pendingEntity();
    $importer = new CsvTicketImporter();
    $preview = $importer->preview($parsed['issues']);
    $values = $importer->previewValues($parsed['issues'], $entitiesId);
    $maps = static function (array $defaults, array $counts): array {
        $rows = [];
        foreach ($defaults as $value => $default) {
            // « - » : cellule vide (TicketMapping::summarize()), à qui l'on peut aussi donner une valeur.
            $rows[] = [
                'value'   => (string) $value,
                'empty'   => $value === '-',
                'key'     => TicketMapping::projectKey($value === '-' ? '' : (string) $value),
                'count'   => (int) ($counts[$value] ?? 0),
                'default' => $default,
            ];
        }

        return $rows;
    };
    $categories = [];
    foreach ($values['categories'] as $value => $category) {
        $key = TicketMapping::projectKey((string) $value);
        $categories[] = [
            'value'    => (string) $value,
            'count'    => $category['count'],
            'dropdown' => ITILCategory::dropdown([
                'name'    => 'category_map[' . $key . ']',
                'value'   => $category['default'],
                'entity'  => $entitiesId,
                'display' => false,
                'rand'    => mt_rand(),
            ]),
        ];
    }
    $data += [
        'step'          => 'preview',
        'preview'       => $preview,
        'values'        => $values,
        'statuses'      => $maps($preview['statuses'], $preview['summary']['statuses']),
        'types'         => $maps($preview['types'], $preview['summary']['types']),
        'priorities'    => $maps(
            array_map(static fn (array $p): int => $p['default'], $values['priorities']),
            array_map(static fn (array $p): int => $p['count'], $values['priorities'])
        ),
        'categories'    => $categories,
        'mapped'        => array_keys(array_map('intval', (array) $pending['mapping'])),
        'source_name'   => $importer->sourceName(),
        'entity_name'   => Dropdown::getDropdownName('glpi_entities', $entitiesId),
        'parse_errors'  => array_map(
            static fn (array $error): array => $error + ['label' => $errorLabels[$error['message']] ?? $error['message']],
            $parsed['errors']
        ),
    ];
} else {
    // Étape 1 : fichiers, entité, outil d'origine ; modèles enregistrés.
    $profiles = array_map(static fn (array $profile): array => [
        'id'          => $profile['id'],
        'name'        => $profile['name'],
        'source_name' => $profile['settings']['source_name'],
        'columns'     => count($profile['settings']['columns']),
    ], CsvImportProfile::all());
    $data += [
        'step'            => 'upload',
        'entity_dropdown' => Entity::dropdown([
            'name'    => 'entities_id',
            'value'   => Session::getActiveEntity(),
            'entity'  => $_SESSION['glpiactiveentities'],
            'display' => false,
        ]),
        'profiles'        => $profiles,
        'zip_available'   => class_exists(ZipArchive::class),
        'jira_url'        => FrontUrl::get('jiraimport.php'),
    ];
}
TemplateRenderer::getInstance()->display('@configurationglpiauto/csv_import.html.twig', $data);

Html::footer();
