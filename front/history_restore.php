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

use GlpiPlugin\Configurationglpiauto\Blueprint\BlueprintSerializer;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigHistory;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;

Session::checkRight(Config::$rightname, READ);

/**
 * Regroupement purement visuel par préfixe de nom de champ (calculé, jamais une table de
 * correspondance figée à maintenir) — voir la docblock de BlueprintSerializer::diff(). Repli sur le
 * préfixe brut si absent de cette petite table de libellés lisibles : dégradation propre, pas une
 * exigence d'exhaustivité dès le jour 1 (un futur champ `Config` tombe automatiquement dans son
 * groupe, avec ou sans libellé traduit). Une fermeture locale plutôt qu'une fonction/constante
 * globale : ce fichier n'est pas namespacé (convention de ce plugin pour tous les `front/*.php`),
 * une déclaration globale polluerait inutilement cet espace de noms partagé.
 */
$groupLabels = [
    'entity' => __('Entités', 'configurationglpiauto'), 'calendar' => __('Calendrier', 'configurationglpiauto'), 'sla' => __('SLA', 'configurationglpiauto'), 'ola' => __('OLA', 'configurationglpiauto'),
    'escalation' => __('Escalade', 'configurationglpiauto'), 'category' => __('Catégories', 'configurationglpiauto'), 'state' => __('Statuts', 'configurationglpiauto'),
    'branding' => __('Personnalisation', 'configurationglpiauto'), 'notifications' => __('Notifications', 'configurationglpiauto'), 'ldap' => __('LDAP', 'configurationglpiauto'),
    'location' => __('Lieux', 'configurationglpiauto'), 'ticket' => __('Tickets', 'configurationglpiauto'), 'task' => __('Tâches', 'configurationglpiauto'), 'validation' => __('Validations', 'configurationglpiauto'),
    'change' => __('Changements', 'configurationglpiauto'), 'project' => __('Projets', 'configurationglpiauto'), 'kb' => __('Base de connaissances', 'configurationglpiauto'),
    'manufacturer' => __('Fabricants', 'configurationglpiauto'), 'document' => __('Documents', 'configurationglpiauto'), 'planning' => __('Planning', 'configurationglpiauto'),
    'satisfaction' => __('Satisfaction', 'configurationglpiauto'), 'committee' => __('Comité de validation', 'configurationglpiauto'), 'wait' => __('Raisons d\'attente', 'configurationglpiauto'),
    'service' => __('Catalogue de services', 'configurationglpiauto'), 'abroad' => __('Missions à l\'étranger', 'configurationglpiauto'), 'solution' => __('Solutions', 'configurationglpiauto'),
    'followup' => __('Suivis', 'configurationglpiauto'), 'user' => __('Utilisateurs', 'configurationglpiauto'), 'field' => __('Unicité des champs', 'configurationglpiauto'),
    'rss' => __('Flux RSS', 'configurationglpiauto'), 'line' => __('Lignes téléphoniques', 'configurationglpiauto'), 'asset' => __('Types d\'actifs', 'configurationglpiauto'),
    'software' => __('Licences logicielles', 'configurationglpiauto'), 'certificate' => __('Certificats', 'configurationglpiauto'), 'recurring' => __('Tickets récurrents', 'configurationglpiauto'),
    'country' => __('Jours fériés', 'configurationglpiauto'), 'vip' => __('Groupe VIP', 'configurationglpiauto'), 'tag' => __('Étiquettes', 'configurationglpiauto'), 'fire' => __('Sécurité incendie', 'configurationglpiauto'),
    'physical' => __('Sécurité physique', 'configurationglpiauto'), 'general' => __('Réglages généraux', 'configurationglpiauto'), 'financial' => __('Informations financières', 'configurationglpiauto'),
    'inventory' => __('Inventaire', 'configurationglpiauto'), 'request' => __('Types de requête', 'configurationglpiauto'),
];
$groupLabelFor = static function (string $field) use ($groupLabels): string {
    $prefix = substr($field, 0, strpos($field, '_') ?: strlen($field));

    return $groupLabels[$prefix] ?? ucfirst($prefix);
};

$item = new ConfigHistory();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$item->check($id, READ);

$decoded = json_decode((string) $item->fields['snapshot'], true);
if (!is_array($decoded)) {
    Session::addMessageAfterRedirect(__('Cet historique ne contient aucun Blueprint exploitable.', 'configurationglpiauto'), false, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/configurationglpiauto/front/history.php');
}

if (isset($_POST['restore_all']) || isset($_POST['restore_selected'])) {
    Session::checkRight(Config::$rightname, UPDATE);

    $onlyFields = isset($_POST['restore_selected'])
        ? array_map('strval', (array) ($_POST['fields'] ?? []))
        : null;

    $config = Config::getConfig();
    // La restauration est elle-même un changement réel de Config : elle laisse donc elle aussi une
    // trace automatique, permettant d'annuler la restauration comme n'importe quel autre changement.
    ConfigHistory::captureAutomatic($config);
    $fields = BlueprintSerializer::import($decoded, Config::getDefaults(), $onlyFields);
    $config->update($fields + ['id' => $config->getID()]);

    Session::addMessageAfterRedirect(__('Restauration appliquée. Vérifiez chaque étape avant de valider.', 'configurationglpiauto'));
    Html::redirect(ConfigurationProfile::getSearchURL());
}

Html::header(__('Restaurer une configuration', 'configurationglpiauto'), $_SERVER['PHP_SELF'], 'config', ConfigHistory::class);

$fullyDefaulted = BlueprintSerializer::import($decoded, Config::getDefaults());
$diff = BlueprintSerializer::diff(Config::getConfig()->fields, $fullyDefaulted);

$groups = [];
foreach ($diff as $field => $values) {
    $groups[$groupLabelFor($field)][$field] = $values;
}
ksort($groups);

\Glpi\Application\View\TemplateRenderer::getInstance()->display('@configurationglpiauto/history_restore.html.twig', [
    'item'       => $item,
    'groups'     => $groups,
    'csrf_token' => Session::getNewCSRFToken(),
]);

Html::footer();
