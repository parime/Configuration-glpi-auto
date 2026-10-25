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

use GlpiPlugin\Configurationglpiauto\Blueprint\DeploymentPlan;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\DeploymentRights;
use GlpiPlugin\Configurationglpiauto\FrontUrl;

// Issue #338 : récapitulatif puis déploiement de la configuration chargée, sans l'assistant. Le
// formulaire poste vers l'étape « Terminer » de l'assistant, qui garde ses propres contrôles.
Session::checkRight(Config::$rightname, UPDATE);

$fields = Config::getConfig()->fields;
$entityTree = json_decode((string) ($fields['entity_tree'] ?? '[]'), true);

Html::header(
    __('Déployer la configuration', 'configurationglpiauto'),
    FrontUrl::get('deploy.php'),
    'config',
    ConfigurationProfile::class
);

\Glpi\Application\View\TemplateRenderer::getInstance()->display('@configurationglpiauto/deploy.html.twig', [
    'fields'      => DeploymentPlan::formFields($fields),
    'groups'      => DeploymentPlan::enabledGroups($fields),
    'entities'    => is_array($entityTree) ? count($entityTree) : 0,
    'rights'      => DeploymentRights::check(),
    'wizard_url'  => FrontUrl::get('wizard.php'),
    'restore_url' => ConfigurationProfile::getSearchURL(),
]);

Html::footer();
