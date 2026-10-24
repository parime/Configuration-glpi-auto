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
use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Configurationglpiauto\AddressPlan\AddressPlanImporter;
use GlpiPlugin\Configurationglpiauto\AddressPlan\AddressPlanParser;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\FrontUrl;

// Issue #343 : plan d'adressage. Droit de modification de la configuration du plugin ET droits
// natifs de créer des VLAN et des réseaux IP.
Session::checkRight(Config::$rightname, UPDATE);
if (!AddressPlanImporter::canImport()) {
    throw new AccessDeniedHttpException();
}

$url = FrontUrl::get('addressplan.php');
$text = (string) ($_POST['plan'] ?? '');
$file = $_FILES['plan_file'] ?? null;
if (
    is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
    && is_uploaded_file((string) $file['tmp_name']) && (int) $file['size'] <= 2 * 1024 * 1024
) {
    $text = (string) file_get_contents((string) $file['tmp_name']);
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }
}

$roleNames = AddressPlanImporter::roleNames();
$templateSite = trim((string) ($_POST['template_site'] ?? ''));
$templateBase = trim((string) ($_POST['template_base'] ?? ''));
$templateError = false;
if (isset($_POST['make_template'])) {
    $template = AddressPlanParser::template($templateSite, $templateBase, $roleNames);
    $templateError = $template === null || $templateSite === '';
    if (!$templateError) {
        $text = (string) $template;
    }
}

$rows = null;
$truncated = false;
$result = null;
if ((isset($_POST['preview']) || isset($_POST['import'])) && trim($text) !== '') {
    $parsed = AddressPlanParser::parse($text);
    $truncated = $parsed['truncated'];
    $rows = AddressPlanImporter::preview($parsed['rows']);
    if (isset($_POST['import'])) {
        $result = AddressPlanImporter::import($rows);
        $rows = AddressPlanImporter::preview($parsed['rows']);
    }
}

Html::header(__('Plan d\'adressage', 'configurationglpiauto'), $url, 'config', ConfigurationProfile::class);

TemplateRenderer::getInstance()->display('@configurationglpiauto/address_plan.html.twig', [
    'action'         => $url,
    'back_url'       => FrontUrl::get('profile.php'),
    'text'           => $text,
    'rows'           => $rows === null ? null : AddressPlanImporter::withMessages($rows),
    'valid_count'    => $rows === null ? 0 : count(array_filter($rows, static fn (array $r): bool => $r['errors'] === [])),
    'truncated'      => $truncated,
    'result'         => $result,
    'template_site'  => $templateSite,
    'template_base'  => $templateBase,
    'template_error' => $templateError,
    'sites'          => AddressPlanImporter::siteNames(),
    'roles'          => $roleNames,
    'max_rows'       => AddressPlanParser::MAX_ROWS,
]);

Html::footer();
