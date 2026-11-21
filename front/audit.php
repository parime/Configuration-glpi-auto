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

use GlpiPlugin\Configurationglpiauto\Audit\AuditReportCron;
use GlpiPlugin\Configurationglpiauto\Audit\AuditService;
use GlpiPlugin\Configurationglpiauto\Audit\BlueprintDrift;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\EndOfLifeCheck;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeCache;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeClient;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeMapping;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;

// L'audit lit et corrige l'état général de toute l'instance (parc de toutes les entités, comptes par
// défaut, configuration cœur) : le droit propre au plugin, délégable à un profil non super-admin
// (voir Profile.php), ne suffit pas : le droit natif GLPI 'config' est exigé en plus, READ pour
// consulter, UPDATE pour corriger (fusions/purges de logiciels et d'intitulés, réglages cœur,
// tickets ouverts dans n'importe quelle entité) ou envoyer le rapport hors de GLPI par e-mail.
Session::checkRight(Config::$rightname, READ);
Session::checkRight('config', READ);

if (isset($_POST['fix'])) {
    Session::checkRight(Config::$rightname, UPDATE);
    Session::checkRight('config', UPDATE);

    $checkKey = (string) $_POST['fix'];

    try {
        AuditService::fix($checkKey);
        Session::addMessageAfterRedirect(__('Correction appliquée.', 'configurationglpiauto'));
    } catch (\LogicException $e) {
        Session::addMessageAfterRedirect($e->getMessage(), false, ERROR);
    }

    Html::redirect(\GlpiPlugin\Configurationglpiauto\FrontUrl::get('audit.php'));
}

// Issue #306 : délais des contrôles du parc (jours, 0 = contrôle désactivé).
if (isset($_POST['save_park'])) {
    Session::checkRight(Config::$rightname, UPDATE);
    Session::checkRight('config', UPDATE);

    $config = Config::getConfig();
    $config->update([
        'id'                  => $config->getID(),
        'audit_ghost_days'    => max(0, min(3650, (int) ($_POST['audit_ghost_days'] ?? 30))),
        'audit_warranty_days' => max(0, min(3650, (int) ($_POST['audit_warranty_days'] ?? 30))),
    ]);
    Session::addMessageAfterRedirect(__('Contrôles du parc enregistrés.', 'configurationglpiauto'));

    Html::redirect(\GlpiPlugin\Configurationglpiauto\FrontUrl::get('audit.php'));
}

// Issue #467 : fin de support (endoflife.date). Rien n'est téléchargé sans activation, et jamais en
// mode hors ligne : les dates viennent alors d'un fichier importé à la main.
if (isset($_POST['save_eol']) || isset($_POST['suggest_eol']) || isset($_POST['refresh_eol']) || isset($_POST['import_eol'])) {
    Session::checkRight(Config::$rightname, UPDATE);
    Session::checkRight('config', UPDATE);

    $config = Config::getConfig();
    if (isset($_POST['save_eol'])) {
        $config->update([
            'id'                => $config->getID(),
            'audit_eol_enabled' => !empty($_POST['audit_eol_enabled']) ? 1 : 0,
            'audit_eol_offline' => !empty($_POST['audit_eol_offline']) ? 1 : 0,
            'audit_eol_days'    => (int) ($_POST['audit_eol_days'] ?? 180),
            'audit_eol_mapping' => (string) ($_POST['audit_eol_mapping'] ?? ''),
        ]);
        Session::addMessageAfterRedirect(__('Réglages de fin de support enregistrés.', 'configurationglpiauto'));
    } elseif (isset($_POST['suggest_eol'])) {
        $config->update([
            'id'                => $config->getID(),
            'audit_eol_mapping' => EndOfLifeMapping::withSuggestions(EndOfLifeCheck::mappingText(), EndOfLifeCheck::osNames()),
        ]);
        Session::addMessageAfterRedirect(__('Correspondances proposées ajoutées : vérifiez-les avant de télécharger les dates.', 'configurationglpiauto'));
    } elseif (!EndOfLifeCheck::enabled()) {
        Session::addMessageAfterRedirect(__('Activez d\'abord le contrôle de fin de support.', 'configurationglpiauto'), false, ERROR);
    } elseif (isset($_POST['refresh_eol'])) {
        $products = EndOfLifeMapping::products(EndOfLifeCheck::mappingText());
        if (!empty($config->fields['audit_eol_offline'])) {
            Session::addMessageAfterRedirect(__('Mode hors ligne : aucun téléchargement. Importez un fichier endoflife.date à la place.', 'configurationglpiauto'), false, ERROR);
        } elseif ($products === []) {
            Session::addMessageAfterRedirect(__('Aucune correspondance : rien à télécharger.', 'configurationglpiauto'), false, ERROR);
        } else {
            $failed = EndOfLifeCache::refresh($products);
            if ($failed === []) {
                Session::addMessageAfterRedirect(sprintf(__('Dates téléchargées pour %d produit(s).', 'configurationglpiauto'), count($products)));
            } else {
                Session::addMessageAfterRedirect(
                    sprintf(__('Téléchargement impossible pour : %s (produit inconnu d\'endoflife.date ou site injoignable). Les dates déjà en cache sont gardées.', 'configurationglpiauto'), implode(', ', $failed)),
                    false,
                    WARNING
                );
            }
        }
    } else {
        $product = strtolower(trim((string) ($_POST['eol_product'] ?? '')));
        $file = $_FILES['eol_file'] ?? null;
        $json = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int) ($file['size'] ?? 0) <= 2 * 1024 * 1024
            ? (string) file_get_contents((string) $file['tmp_name'])
            : '';
        try {
            $count = EndOfLifeCache::import($product, $json);
            Session::addMessageAfterRedirect(sprintf(__('%1$d cycle(s) importé(s) pour %2$s.', 'configurationglpiauto'), $count, $product));
        } catch (\RuntimeException) {
            Session::addMessageAfterRedirect(__('Fichier refusé : choisissez le fichier JSON d\'un produit endoflife.date (2 Mo au plus) et indiquez son identifiant, par exemple « windows ».', 'configurationglpiauto'), false, ERROR);
        }
    }

    Html::redirect(\GlpiPlugin\Configurationglpiauto\FrontUrl::get('audit.php'));
}

// Issue #285 : réglages du rapport d'audit périodique par e-mail. La fréquence est celle de la
// tâche planifiée GLPI elle-même (désactivée / hebdomadaire / mensuelle), pour qu'elle reste
// cohérente avec Configuration > Actions automatiques.
$reportTask = new CronTask();
$reportTaskFound = $reportTask->getFromDBbyName(AuditReportCron::class, AuditReportCron::TASK_NAME);

if (isset($_POST['save_report']) || isset($_POST['send_report_now'])) {
    Session::checkRight(Config::$rightname, UPDATE);
    Session::checkRight('config', UPDATE);

    $config = Config::getConfig();
    $config->update([
        'id'                          => $config->getID(),
        'audit_report_only_on_change' => !empty($_POST['audit_report_only_on_change']) ? 1 : 0,
        'audit_report_emails'         => (string) ($_POST['audit_report_emails'] ?? ''),
    ]);

    $frequencies = ['week' => WEEK_TIMESTAMP, 'month' => MONTH_TIMESTAMP];
    $frequency = (string) ($_POST['audit_report_frequency'] ?? 'off');
    if ($reportTaskFound) {
        $reportTask->update([
            'id'        => $reportTask->getID(),
            'state'     => isset($frequencies[$frequency]) ? CronTask::STATE_WAITING : CronTask::STATE_DISABLE,
            'frequency' => $frequencies[$frequency] ?? $reportTask->fields['frequency'],
        ]);
    }

    if (isset($_POST['send_report_now'])) {
        global $CFG_GLPI;
        if (empty($CFG_GLPI['use_notifications']) || empty($CFG_GLPI['notifications_mailing'])) {
            Session::addMessageAfterRedirect(
                __('Les notifications par e-mail de GLPI sont désactivées : activez-les dans Configuration > Notifications.', 'configurationglpiauto'),
                false,
                ERROR
            );
        } elseif (AuditReportCron::run(true)) {
            Session::addMessageAfterRedirect(__('Rapport d\'audit mis en file d\'envoi.', 'configurationglpiauto'));
        } else {
            Session::addMessageAfterRedirect(
                __('Aucune notification active n\'a pu envoyer le rapport : vérifiez la notification et ses destinataires.', 'configurationglpiauto'),
                false,
                ERROR
            );
        }
    } else {
        Session::addMessageAfterRedirect(__('Réglages du rapport d\'audit enregistrés.', 'configurationglpiauto'));
    }

    Html::redirect(\GlpiPlugin\Configurationglpiauto\FrontUrl::get('audit.php'));
}

Html::header(__('Audit de configuration', 'configurationglpiauto'), \GlpiPlugin\Configurationglpiauto\FrontUrl::get('audit.php'), 'config', ConfigurationProfile::class, 'audit');

$findings = AuditService::runAll();

$eolCache = [];
foreach (EndOfLifeCache::all() as $product => $entry) {
    $eolCache[] = [
        'product' => $product,
        'cycles'  => count($entry['cycles']),
        'date'    => Html::convDateTime($entry['date']),
        'url'     => EndOfLifeClient::productPage($product),
    ];
}

$severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];
usort($findings, static fn ($a, $b) => $severityOrder[$a->severity] <=> $severityOrder[$b->severity]);

// Analyse continue (issue #131) : le compteur accumulé par AuditWatchCron est affiché une fois puis
// remis à zéro ici (vu = acquitté) : jamais `last_critical_keys`, uniquement le compteur de nouveaux
// constats non encore consultés. Le droit de lecture déjà vérifié plus haut suffit : acquitter un
// simple indicateur de lecture n'exige pas le droit d'écriture UPDATE.
$config = Config::getConfig();
$watchState = json_decode((string) ($config->fields['audit_watch_state'] ?? ''), true);
$watchState = is_array($watchState) ? $watchState : [];
$newCriticalCount = (int) ($watchState['unseen_new_critical_count'] ?? 0);

$newCriticalMessage = null;

if ($newCriticalCount > 0) {
    // Rendu en PHP, pas en Twig : extract-locales n'extrait `_n()` que depuis le code PHP, jamais
    // depuis un appel Twig équivalent (confirmé en direct : un essai précédent avec `_n()` appelé
    // depuis le template ne remontait tout simplement pas dans la liste des chaînes à traduire).
    $newCriticalMessage = sprintf(
        _n(
            '%d nouveau constat critique détecté depuis la dernière vérification automatique.',
            '%d nouveaux constats critiques détectés depuis la dernière vérification automatique.',
            $newCriticalCount,
            'configurationglpiauto'
        ),
        $newCriticalCount
    );

    $watchState['unseen_new_critical_count'] = 0;
    $config->update(['id' => $config->getID(), 'audit_watch_state' => json_encode($watchState)]);
}

$driftApplied = BlueprintDrift::wasApplied();

global $DB, $CFG_GLPI;
$reportNotificationId = 0;
foreach ($DB->request([
    'SELECT' => ['id'],
    'FROM'   => Notification::getTable(),
    'WHERE'  => ['itemtype' => Config::class, 'event' => AuditReportCron::EVENT],
    'LIMIT'  => 1,
]) as $row) {
    $reportNotificationId = (int) $row['id'];
}
$reportState = json_decode((string) ($config->fields['audit_report_state'] ?? ''), true);
$reportFrequency = 'off';
if ($reportTaskFound && (int) $reportTask->fields['state'] !== CronTask::STATE_DISABLE) {
    $reportFrequency = (int) $reportTask->fields['frequency'] >= MONTH_TIMESTAMP ? 'month' : 'week';
}

\Glpi\Application\View\TemplateRenderer::getInstance()->display('@configurationglpiauto/audit.html.twig', [
    'report' => [
        'frequency'          => $reportFrequency,
        'only_on_change'     => !empty($config->fields['audit_report_only_on_change']),
        'emails'             => implode("\n", $config->getAuditReportEmails()),
        'last_sent'          => is_array($reportState) && isset($reportState['date']) ? Html::convDateTime($reportState['date']) : null,
        'notification_url'   => $reportNotificationId > 0 ? Notification::getFormURLWithID($reportNotificationId) : null,
        'notifications_on'   => !empty($CFG_GLPI['use_notifications']) && !empty($CFG_GLPI['notifications_mailing']),
    ],
    'findings'              => $findings,
    // Issue #468 : écarts avec le dernier Blueprint appliqué (lecture seule).
    'drift'                 => [
        'applied'    => $driftApplied,
        'items'      => $driftApplied ? BlueprintDrift::detect($config) : [],
        'deploy_url' => Session::haveRight(Config::$rightname, UPDATE) ? \GlpiPlugin\Configurationglpiauto\FrontUrl::get('deploy.php') . '?drift=1' : null,
        'wizard_url' => \GlpiPlugin\Configurationglpiauto\FrontUrl::get('wizard.php'),
    ],
    'park'                  => [
        'ghost_days'    => (int) ($config->fields['audit_ghost_days'] ?? 30),
        'warranty_days' => (int) ($config->fields['audit_warranty_days'] ?? 30),
    ],
    'eol'                   => [
        'enabled' => !empty($config->fields['audit_eol_enabled']),
        'offline' => !empty($config->fields['audit_eol_offline']),
        'days'    => (int) ($config->fields['audit_eol_days'] ?? 180),
        'mapping' => (string) ($config->fields['audit_eol_mapping'] ?? ''),
        'cache'   => $eolCache,
    ],
    'can_fix'               => Session::haveRight(Config::$rightname, UPDATE) && Session::haveRight('config', UPDATE),
    'csrf_token'            => Session::getNewCSRFToken(),
    'new_critical_message'  => $newCriticalMessage,
]);

Html::footer();
