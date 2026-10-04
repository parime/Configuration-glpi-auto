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
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;

// Même droit que l'assistant de configuration (front/wizard.php) : l'audit lit/corrige l'état
// général de GLPI, pas une frontière d'accès propre à ce plugin.
Session::checkRight(Config::$rightname, READ);

if (isset($_POST['fix'])) {
    Session::checkRight(Config::$rightname, UPDATE);

    $checkKey = (string) $_POST['fix'];

    try {
        AuditService::fix($checkKey);
        Session::addMessageAfterRedirect(__('Correction appliquée.', 'configurationglpiauto'));
    } catch (\LogicException $e) {
        Session::addMessageAfterRedirect($e->getMessage(), false, ERROR);
    }

    Html::redirect($_SERVER['PHP_SELF']);
}

// Issue #285 : réglages du rapport d'audit périodique par e-mail. La fréquence est celle de la
// tâche planifiée GLPI elle-même (désactivée / hebdomadaire / mensuelle), pour qu'elle reste
// cohérente avec Configuration > Actions automatiques.
$reportTask = new CronTask();
$reportTaskFound = $reportTask->getFromDBbyName(AuditReportCron::class, AuditReportCron::TASK_NAME);

if (isset($_POST['save_report']) || isset($_POST['send_report_now'])) {
    Session::checkRight(Config::$rightname, UPDATE);

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

    Html::redirect($_SERVER['PHP_SELF']);
}

Html::header(__('Audit de configuration', 'configurationglpiauto'), $_SERVER['PHP_SELF'], 'config', ConfigurationProfile::class);

$findings = AuditService::runAll();

$severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];
usort($findings, static fn ($a, $b) => $severityOrder[$a->severity] <=> $severityOrder[$b->severity]);

// Analyse continue (issue #131) : le compteur accumulé par AuditWatchCron est affiché une fois puis
// remis à zéro ici (vu = acquitté) — jamais `last_critical_keys`, uniquement le compteur de nouveaux
// constats non encore consultés. Le droit de lecture déjà vérifié plus haut suffit : acquitter un
// simple indicateur de lecture n'exige pas le droit d'écriture UPDATE.
$config = Config::getConfig();
$watchState = json_decode((string) ($config->fields['audit_watch_state'] ?? ''), true);
$watchState = is_array($watchState) ? $watchState : [];
$newCriticalCount = (int) ($watchState['unseen_new_critical_count'] ?? 0);

$newCriticalMessage = null;

if ($newCriticalCount > 0) {
    // Rendu en PHP, pas en Twig : extract-locales n'extrait `_n()` que depuis le code PHP, jamais
    // depuis un appel Twig équivalent (confirmé en direct — un essai précédent avec `_n()` appelé
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
    'can_fix'               => Session::haveRight(Config::$rightname, UPDATE),
    'csrf_token'            => Session::getNewCSRFToken(),
    'new_critical_message'  => $newCriticalMessage,
]);

Html::footer();
