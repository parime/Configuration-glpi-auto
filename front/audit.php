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

\Glpi\Application\View\TemplateRenderer::getInstance()->display('@configurationglpiauto/audit.html.twig', [
    'findings'              => $findings,
    'can_fix'               => Session::haveRight(Config::$rightname, UPDATE),
    'csrf_token'            => Session::getNewCSRFToken(),
    'new_critical_message'  => $newCriticalMessage,
]);

Html::footer();
