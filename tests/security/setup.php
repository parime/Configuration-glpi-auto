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

/**
 * Donnees du test de controle d'acces de bout en bout (tests/security/access-matrix.sh), lance
 * par la CI dans une instance GLPI jetable : un profil « delegue » qui detient tous les droits de
 * ce plugin mais aucun droit natif GLPI privilegie (config, entity, regles...) — le cas exact de
 * la delegation que Profile.php documente.
 *
 * Usage (en www-data, depuis la racine GLPI) :
 *   php plugins/configurationglpiauto/tests/security/setup.php           cree le profil et le compte
 *   php plugins/configurationglpiauto/tests/security/setup.php snapshot  instantane des objets natifs
 *   php plugins/configurationglpiauto/tests/security/setup.php xss       charge XSS dans chaque champ
 *   php plugins/configurationglpiauto/tests/security/setup.php uploads   nombre de profils
 *   php plugins/configurationglpiauto/tests/security/setup.php blueprint fichier Blueprint piege
 */

require dirname(__DIR__, 4) . '/vendor/autoload.php';

$kernel = new \Glpi\Kernel\Kernel();
$kernel->boot();
Session::start();
if (!(new Auth())->login('glpi', 'glpi', false)) {
    fwrite(STDERR, "Connexion glpi/glpi impossible\n");
    exit(1);
}

global $DB;

if (($argv[1] ?? '') === 'xss') {
    // Charge piegee dans chaque champ texte libre (configuration, arborescence d'entites, type de
    // carburant, point d'historique, blueprint) : tests/security/xss-check.sh verifie qu'aucune
    // page ne la restitue telle quelle. Affiche les ids des objets pieges en JSON.
    $canary = "<svg/onload=alert('xss-canary')>";
    $config = \GlpiPlugin\Configurationglpiauto\Config::getConfig();
    $DB->update(\GlpiPlugin\Configurationglpiauto\Config::getTable(), [
        'calendar_name'              => $canary,
        'ldap_rights_group_template' => $canary,
        'entity_tree'                => json_encode([['name' => $canary, 'children' => [['name' => $canary, 'children' => []]]]]),
    ], ['id' => $config->getID()]);
    $config->getFromDB($config->getID());
    $blueprint = \GlpiPlugin\Configurationglpiauto\Blueprint\BlueprintSerializer::export(
        $config->fields,
        $canary,
        PLUGIN_CONFIGURATIONGLPIAUTO_VERSION
    );
    $profileItem = new \GlpiPlugin\Configurationglpiauto\ConfigurationProfile();
    $ids = [
        'profile'  => (int) $profileItem->add([
            'name' => $canary, 'description' => $canary, 'comment' => $canary, 'type' => 'custom',
            'snapshot' => json_encode($blueprint),
        ]),
        'history'  => \GlpiPlugin\Configurationglpiauto\ConfigHistory::captureManual($config, $canary),
        'fueltype' => (int) (new \GlpiPlugin\Configurationglpiauto\FuelType())->add(['name' => $canary, 'comment' => $canary]),
    ];
    echo json_encode($ids), "\n";
    exit(0);
}

if (($argv[1] ?? '') === 'uploads') {
    // Etat observe par tests/security/upload-check.sh : profils (blueprints importes), taille du
    // contenu stocke pour le dernier, service de geocodage configure.
    $table = \GlpiPlugin\Configurationglpiauto\ConfigurationProfile::getTable();
    $latest = $DB->request(['SELECT' => ['snapshot'], 'FROM' => $table, 'ORDER' => 'id DESC', 'LIMIT' => 1])->current();
    echo json_encode([
        'profiles' => countElementsInTable($table),
        'latest'   => strlen((string) ($latest['snapshot'] ?? '')),
        'endpoint' => (string) \GlpiPlugin\Configurationglpiauto\Config::getConfig()->fields['location_geocoding_endpoint'],
    ]), "\n";
    exit(0);
}

if (($argv[1] ?? '') === 'blueprint') {
    // Fichier Blueprint piege televerse par tests/security/upload-check.sh : charges XSS sous les
    // noms de colonnes et dans le nom, champs propres a l'instance, service de geocodage interne.
    $config = \GlpiPlugin\Configurationglpiauto\Config::getConfig();
    $blueprint = \GlpiPlugin\Configurationglpiauto\Blueprint\BlueprintSerializer::export(
        $config->fields,
        "<svg/onload=alert('xss-canary')>",
        PLUGIN_CONFIGURATIONGLPIAUTO_VERSION
    );
    $blueprint['config']['calendar_days'] = '0;alert(document.cookie)//';
    $blueprint['config']['calendar_day_hours'] = '1;alert(1)//';
    $blueprint['config']['location_geocoding_endpoint'] = 'http://169.254.169.254';
    $blueprint['config']['id'] = 999999;
    echo json_encode($blueprint), "\n";
    exit(0);
}

if (($argv[1] ?? '') === 'snapshot') {
    // Ce que l'assistant ne doit jamais ecrire sans le droit natif correspondant.
    $snapshot = [];
    foreach ($DB->request(['SELECT' => ['sub_type'], 'COUNT' => 'n', 'FROM' => 'glpi_rules', 'GROUPBY' => 'sub_type']) as $row) {
        $snapshot['rules:' . $row['sub_type']] = (int) $row['n'];
    }
    $snapshot['fieldunicities'] = countElementsInTable('glpi_fieldunicities');
    $snapshot['entities'] = countElementsInTable('glpi_entities');
    $snapshot['assetdefinitions'] = countElementsInTable('glpi_assets_assetdefinitions');
    $snapshot['core:palette'] = (string) (\Config::getConfigurationValues('core', ['palette'])['palette'] ?? '');
    if ($DB->tableExists('glpi_plugin_assetsign_configs')) {
        foreach ($DB->request(['FROM' => 'glpi_plugin_assetsign_configs', 'WHERE' => ['entities_id' => 0]]) as $row) {
            $snapshot['assetsign:handover_states'] = (string) $row['handover_states'];
        }
    }
    ksort($snapshot);
    echo json_encode($snapshot), "\n";
    exit(0);
}

$account = static function (string $login, string $profileName, array $rights) use ($DB): int {
    $profile = new Profile();
    if (!$profile->getFromDBByCrit(['name' => $profileName])) {
        $profile->add(['name' => $profileName, 'interface' => 'central']);
    }
    $profileId = (int) $profile->getID();
    ProfileRight::updateProfileRights($profileId, $rights);

    $user = new User();
    if (!$user->getFromDBbyName($login)) {
        $user->add([
            'name' => $login, 'password' => 'Secrev#2026', 'password2' => 'Secrev#2026',
            'entities_id' => 0, '_profiles_id' => 0,
        ]);
    }
    $userId = (int) $user->getID();
    $DB->delete('glpi_profiles_users', ['users_id' => $userId]);
    $DB->insert('glpi_profiles_users', [
        'users_id' => $userId, 'profiles_id' => $profileId, 'entities_id' => 0,
        'is_recursive' => 1, 'is_dynamic' => 0, 'is_default_profile' => 1,
    ]);
    $DB->update('glpi_users', ['profiles_id' => $profileId], ['id' => $userId]);

    return $userId;
};

$noNativeRight = [
    'config' => 0, 'entity' => 0, 'rule_ldap' => 0, 'rule_ticket' => 0, 'rule_import' => 0,
    'rule_dictionnary_dropdown' => 0, 'rule_dictionnary_software' => 0,
    'rule_dictionnary_printer' => 0, 'rule_softwarecategories' => 0,
];
// Délégué : tous les droits de ce plugin, aucun droit natif privilégié.
$account('securite-delegue', 'Securite - delegue assistant', [
    'plugin_configurationglpiauto_config'  => ALLSTANDARDRIGHT,
    'plugin_configurationglpiauto_profile' => ALLSTANDARDRIGHT,
] + $noNativeRight);
// Sans droit : aucun droit sur ce plugin, chaque page doit lui être refusée.
$account('securite-sans-droit', 'Securite - sans droit assistant', [
    'plugin_configurationglpiauto_config'  => 0,
    'plugin_configurationglpiauto_profile' => 0,
] + $noNativeRight);

// Service de géocodage pointé vers une adresse interne : le proxy doit refuser de l'appeler (SSRF).
$config = \GlpiPlugin\Configurationglpiauto\Config::getConfig();
$DB->update(
    \GlpiPlugin\Configurationglpiauto\Config::getTable(),
    ['location_geocoding_endpoint' => 'https://127.0.0.1'],
    ['id' => $config->getID()]
);
$config->getFromDB($config->getID());

// Blueprint piégé : charges XSS sous les noms de COLONNES (contournement de prepareInput() de la
// 1.3.0). Restauré via front/history_restore.php, il doit être neutralisé.
$blueprint = \GlpiPlugin\Configurationglpiauto\Blueprint\BlueprintSerializer::export(
    $config->fields,
    'Blueprint securite',
    PLUGIN_CONFIGURATIONGLPIAUTO_VERSION
);
$blueprint['config']['calendar_days'] = '0;alert(document.cookie)//';
$blueprint['config']['calendar_day_hours'] = '1;alert(1)//';
$profileItem = new \GlpiPlugin\Configurationglpiauto\ConfigurationProfile();
$ids = [
    'profile' => (int) $profileItem->add([
        'name' => 'Blueprint securite', 'type' => 'custom', 'snapshot' => json_encode($blueprint),
    ]),
    'history' => \GlpiPlugin\Configurationglpiauto\ConfigHistory::captureManual($config, 'Point securite'),
    'fueltype' => (int) (new \GlpiPlugin\Configurationglpiauto\FuelType())->add(['name' => 'Carburant securite']),
];

echo json_encode($ids), "\n";
