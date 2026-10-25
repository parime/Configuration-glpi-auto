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

namespace GlpiPlugin\Configurationglpiauto;

use Session;

/**
 * Droits natifs de GLPI qu'exige chaque partie du déploiement (bouton « Terminer » de
 * l'assistant). Sans l'un d'eux, la partie correspondante est ignorée sans erreur : l'assistant
 * (issue #115) et le déploiement direct d'un Blueprint (issue #338) affichent donc la même liste
 * avant de lancer quoi que ce soit. Informatif, jamais bloquant.
 */
final class DeploymentRights
{
    /**
     * @return array<int, array{label: string, ok: bool, hint: string}>
     */
    public static function check(): array
    {
        return [
            [
                'label' => __('Créer des entités', 'configurationglpiauto'),
                'ok' => Session::haveRight('entity', CREATE),
                'hint' => __('nécessaire pour construire l\'arborescence (étape 2) — les entités déjà existantes restent utilisables, mais aucune nouvelle ne sera créée', 'configurationglpiauto'),
            ],
            [
                'label' => __('Modifier des entités', 'configurationglpiauto'),
                'ok' => Session::haveRight('entity', UPDATE),
                'hint' => __('nécessaire pour la personnalisation par entité (adresses, logos, calendrier/SLA par client...)', 'configurationglpiauto'),
            ],
            [
                'label' => __('Gérer les règles d\'affectation LDAP', 'configurationglpiauto'),
                'ok' => Session::haveRight('rule_ldap', UPDATE),
                'hint' => __('nécessaire aux droits LDAP par entité (étape 13)', 'configurationglpiauto'),
            ],
            [
                'label' => __('Modifier la configuration générale de GLPI', 'configurationglpiauto'),
                'ok' => Session::haveRight('config', UPDATE),
                'hint' => __('nécessaire aux réglages généraux (étape 14)', 'configurationglpiauto'),
            ],
            [
                'label' => __('Gérer les règles métier pour les tickets', 'configurationglpiauto'),
                'ok' => Session::haveRight('rule_ticket', UPDATE),
                'hint' => __('nécessaire à l\'affectation automatique des SLA/OLA et au circuit de validation', 'configurationglpiauto'),
            ],
            [
                'label' => __('Créer des niveaux de service (SLM)', 'configurationglpiauto'),
                'ok' => Session::haveRight('slm', CREATE),
                'hint' => __('nécessaire aux SLA, OLA et à leurs niveaux d\'escalade (étape SLA)', 'configurationglpiauto'),
            ],
            [
                'label' => __('Créer des calendriers', 'configurationglpiauto'),
                'ok' => Session::haveRight('calendar', CREATE),
                'hint' => __('nécessaire aux horaires ouvrés et jours fériés (étape Calendrier)', 'configurationglpiauto'),
            ],
            [
                'label' => __('Gérer les règles d\'affectation d\'un élément à une entité', 'configurationglpiauto'),
                'ok' => Session::haveRight('rule_import', UPDATE),
                'hint' => __('nécessaire au rattachement des machines inventoriées par plage IP ou tag', 'configurationglpiauto'),
            ],
            [
                'label' => __('Gérer les dictionnaires et les catégories de logiciels', 'configurationglpiauto'),
                'ok' => Session::haveRight('rule_dictionnary_dropdown', UPDATE)
                    && Session::haveRight('rule_dictionnary_software', UPDATE)
                    && Session::haveRight('rule_dictionnary_printer', UPDATE)
                    && Session::haveRight('rule_softwarecategories', UPDATE),
                'hint' => __('chaque dictionnaire (fabricants, logiciels, systèmes d\'exploitation, imprimantes, types d\'ordinateurs) est ignoré si le droit correspondant manque', 'configurationglpiauto'),
            ],
            ...(AssetsignTriggerBuilder::isThirdPartyPluginActive() ? [[
                'label' => __('Modifier la configuration d\'assetsign', 'configurationglpiauto'),
                'ok' => Session::haveRight('plugin_assetsign_config', UPDATE),
                'hint' => __('nécessaire pour relier les déclencheurs d\'assetsign aux statuts créés', 'configurationglpiauto'),
            ]] : []),
        ];
    }
}
