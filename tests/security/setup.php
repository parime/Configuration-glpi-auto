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

$profile = new Profile();
if (!$profile->getFromDBByCrit(['name' => 'Securite - delegue assistant'])) {
    $profile->add(['name' => 'Securite - delegue assistant', 'interface' => 'central']);
}
$profileId = (int) $profile->getID();
ProfileRight::updateProfileRights($profileId, [
    'plugin_configurationglpiauto_config'  => ALLSTANDARDRIGHT,
    'plugin_configurationglpiauto_profile' => ALLSTANDARDRIGHT,
    'config' => 0, 'entity' => 0, 'rule_ldap' => 0, 'rule_ticket' => 0, 'rule_import' => 0,
    'rule_dictionnary_dropdown' => 0, 'rule_dictionnary_software' => 0,
    'rule_dictionnary_printer' => 0, 'rule_softwarecategories' => 0,
]);

$user = new User();
if (!$user->getFromDBbyName('securite-delegue')) {
    $user->add([
        'name' => 'securite-delegue', 'password' => 'Secrev#2026', 'password2' => 'Secrev#2026',
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

echo json_encode(['user' => 'securite-delegue']), "\n";
