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

use GlpiPlugin\Configurationglpiauto\Audit\AuditControlCatalog;
use GlpiPlugin\Configurationglpiauto\Audit\AuditHistory;
use GlpiPlugin\Configurationglpiauto\Audit\AuditReportCron;
use GlpiPlugin\Configurationglpiauto\Audit\AuditService;
use GlpiPlugin\Configurationglpiauto\Audit\BlueprintDrift;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\EndOfLifeCheck;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeCache;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeClient;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeMapping;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigSettings;
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

// Issue #468 : « Adopter » un délai de SLA ou d'OLA modifié à la main (jeton produit par la détection).
if (isset($_POST['adopt_drift'])) {
    Session::checkRight(Config::$rightname, UPDATE);
    if (BlueprintDrift::adopt(Config::getConfig(), (string) $_POST['adopt_drift'])) {
        Session::addMessageAfterRedirect(__('Nouvelle valeur adoptée : elle devient la référence du Blueprint.', 'configurationglpiauto'));
    } else {
        Session::addMessageAfterRedirect(__('Cet écart ne peut pas être adopté ici : reportez la valeur dans l\'assistant.', 'configurationglpiauto'), false, ERROR);
    }

    Html::redirect(\GlpiPlugin\Configurationglpiauto\FrontUrl::get('audit.php') . '#blueprint-drift');
}

// Issue #495 : les réglages (contrôles du parc, fin de support, rapport périodique) sont dans la page
// de configuration du plugin. Les formulaires de l'ancienne page restent acceptés pendant une
// version : enregistrés de la même façon (mêmes droits), puis renvoyés vers l'onglet du réglage.
if (ConfigSettings::isSettingsPost($_POST)) {
    $tab = ConfigSettings::handle($_POST, $_FILES);
    Session::addMessageAfterRedirect(__('Ces réglages sont maintenant dans Configuration > Plugins > Configuration GLPI Auto.', 'configurationglpiauto'), false, INFO);

    Html::redirect(ConfigSettings::url($tab));
}

// Issue #467 : fin de support (endoflife.date). Téléchargement des dates à la demande (action
// d'usage) ; jamais sans activation ni en mode hors ligne, réglés dans la page de configuration.
if (isset($_POST['refresh_eol'])) {
    Session::checkRight(Config::$rightname, UPDATE);
    Session::checkRight('config', UPDATE);

    $config = Config::getConfig();
    $products = EndOfLifeMapping::products(EndOfLifeCheck::mappingText());
    if (!EndOfLifeCheck::enabled()) {
        Session::addMessageAfterRedirect(__('Activez d\'abord le contrôle de fin de support.', 'configurationglpiauto'), false, ERROR);
    } elseif (!empty($config->fields['audit_eol_offline'])) {
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

    Html::redirect(\GlpiPlugin\Configurationglpiauto\FrontUrl::get('audit.php'));
}

// Issue #285 : rapport d'audit périodique par e-mail. Fréquence et destinataires se règlent dans la
// page de configuration ; l'envoi immédiat reste ici (action d'usage).
$reportTask = new CronTask();
$reportTaskFound = $reportTask->getFromDBbyName(AuditReportCron::class, AuditReportCron::TASK_NAME);

if (isset($_POST['send_report_now'])) {
    Session::checkRight(Config::$rightname, UPDATE);
    Session::checkRight('config', UPDATE);

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

    Html::redirect(\GlpiPlugin\Configurationglpiauto\FrontUrl::get('audit.php'));
}

Html::header(__('Audit de configuration', 'configurationglpiauto'), \GlpiPlugin\Configurationglpiauto\FrontUrl::get('audit.php'), 'config', ConfigurationProfile::class, 'audit');
\GlpiPlugin\Configurationglpiauto\UsageTabs::display('audit');

// Issue #466 : note de durcissement (lecture seule) et photo du jour dans l'historique des audits.
$evaluation = AuditControlCatalog::evaluate();
$findings = $evaluation['findings'];
AuditHistory::record($evaluation['score'], $evaluation['statuses'], 'page');
$auditRuns = AuditHistory::list(30);
$compareFrom = (int) ($_GET['compare_from'] ?? ($auditRuns[1]['id'] ?? 0));
$compareTo = (int) ($_GET['compare_to'] ?? ($auditRuns[0]['id'] ?? 0));
$comparison = AuditHistory::comparison($auditRuns, $compareFrom, $compareTo);

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

global $CFG_GLPI;
$reportState = json_decode((string) ($config->fields['audit_report_state'] ?? ''), true);
$reportFrequency = 'off';
if ($reportTaskFound && (int) $reportTask->fields['state'] !== CronTask::STATE_DISABLE) {
    $reportFrequency = (int) $reportTask->fields['frequency'] >= MONTH_TIMESTAMP ? 'month' : 'week';
}

\Glpi\Application\View\TemplateRenderer::getInstance()->display('@configurationglpiauto/audit.html.twig', [
    'report' => [
        'frequency'        => $reportFrequency,
        'last_sent'        => is_array($reportState) && isset($reportState['date']) ? Html::convDateTime($reportState['date']) : null,
        'notifications_on' => !empty($CFG_GLPI['use_notifications']) && !empty($CFG_GLPI['notifications_mailing']),
    ],
    'findings'              => $findings,
    'hardening'             => [
        'score'          => $evaluation['score'],
        'level'          => $evaluation['level'],
        'domains'        => $evaluation['domains'],
        'applicable'     => $evaluation['applicable'],
        'not_applicable' => $evaluation['not_applicable'],
        'weights'        => [
            'security'   => AuditControlCatalog::WEIGHT_SECURITY,
            'operations' => AuditControlCatalog::WEIGHT_OPERATIONS,
            'data'       => AuditControlCatalog::WEIGHT_DATA,
        ],
    ],
    'audit_runs'            => $auditRuns,
    'compare_from'          => $compareFrom,
    'compare_to'            => $compareTo,
    'comparison'            => $comparison,
    // Issue #468 : écarts avec le dernier Blueprint appliqué (lecture seule).
    'drift'                 => [
        'applied'    => $driftApplied,
        'items'      => $driftApplied ? BlueprintDrift::detect($config) : [],
        'deploy_url' => Session::haveRight(Config::$rightname, UPDATE) ? \GlpiPlugin\Configurationglpiauto\FrontUrl::get('deploy.php') . '?drift=1' : null,
        'wizard_url'   => \GlpiPlugin\Configurationglpiauto\FrontUrl::get('wizard.php'),
        'audit_url'    => \GlpiPlugin\Configurationglpiauto\FrontUrl::get('audit.php'),
        'can_adopt'    => (bool) Session::haveRight(Config::$rightname, UPDATE),
        'settings_url' => (bool) Session::haveRight(Config::$rightname, UPDATE)
            ? ConfigSettings::url(ConfigSettings::TAB_AUDIT) . '#cga-drift-settings'
            : null,
    ],
    // Issue #470 : sessions actives et ré-authentification (GLPI 12, sans objet sous GLPI 11).
    'sessions'              => \GlpiPlugin\Configurationglpiauto\Audit\SessionSecurity::view(),
    'eol'                   => [
        'enabled' => !empty($config->fields['audit_eol_enabled']),
        'offline' => !empty($config->fields['audit_eol_offline']),
        'cache'   => $eolCache,
    ],
    // Issue #495 : réglages de l'audit, dans la page de configuration du plugin.
    'settings_url'          => [
        'controls' => ConfigSettings::url(ConfigSettings::TAB_AUDIT),
        'eol'      => ConfigSettings::url(ConfigSettings::TAB_EOL),
    ],
    'can_fix'               => Session::haveRight(Config::$rightname, UPDATE) && Session::haveRight('config', UPDATE),
    'csrf_token'            => Session::getNewCSRFToken(),
    'new_critical_message'  => $newCriticalMessage,
]);

Html::footer();
