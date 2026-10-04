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

/**
 * Issue #283 : quand le plugin jumeau assetsign-glpi est installé et actif, relie ses
 * déclencheurs « changement d'État » aux statuts créés par StateBuilder — sinon l'administrateur
 * devait refaire cette correspondance à la main dans Assetsign & signature > Configuration.
 *
 * Contrat : la table `glpi_plugin_assetsign_configs` (une ligne par entité, la ligne racine
 * `entities_id = 0` est créée par l'installation d'assetsign), dont les colonnes `*_states` sont des
 * listes JSON d'ID `glpi_states` (Config::getHandoverStates() & co. côté assetsign, qui
 * décodent exactement ce format). Même choix que SatisfactionSurveyBuilder : écriture via `$DB`
 * sur le schéma, jamais de `use` d'une classe d'un plugin qui peut être absent.
 *
 * Correspondance par défaut (TRIGGERS) : celle qu'un administrateur avait réglée à la main sur
 * l'instance de recette, vérifiée en base. « Attente restitution » et non « En stock » pour la
 * restitution : assetsign ne crée une fiche déclenchée par un État que si un utilisateur est
 * encore affecté au matériel (Assetsign::handleStateBasedTrigger()), or le passage en stock
 * s'accompagne en général du retrait de l'utilisateur — cas déjà couvert par le déclencheur
 * « désaffectation » d'assetsign lui-même.
 *
 * Idempotent et non destructif : un déclencheur déjà réglé (liste non vide) n'est jamais modifié,
 * seul un déclencheur vide reçoit le statut correspondant, s'il existe.
 */
class AssetsignTriggerBuilder
{
    public const TABLE = 'glpi_plugin_assetsign_configs';

    /** @var array<string, string> colonne assetsign => nom du statut StateBuilder */
    public const TRIGGERS = [
        'handover_states'    => 'Attribué',
        'return_states'      => 'Attente restitution',
        'donation_states'    => 'Donné',
        'vente_states'       => 'Vendu',
        'destruction_states' => 'Détruit',
        'reforme_states'     => 'Obsolète',
    ];

    public const ACTION_SET     = 'set';
    public const ACTION_KEPT    = 'kept';
    public const ACTION_MISSING = 'missing';

    /**
     * Pure : ce que l'assistant fera pour chaque déclencheur sélectionné.
     *
     * @param array<string, mixed> $assetsignRow ligne racine actuelle de la config assetsign
     * @param array<string, int>   $stateIdsByName statuts GLPI existants, nom => ID
     * @param string[]             $selectedFields déclencheurs cochés dans l'assistant
     * @return array<string, array{state: string, state_id: int|null, action: string}>
     */
    public static function plan(array $assetsignRow, array $stateIdsByName, array $selectedFields): array
    {
        $plan = [];
        foreach (self::TRIGGERS as $field => $stateName) {
            if (!in_array($field, $selectedFields, true)) {
                continue;
            }
            $current = json_decode((string) ($assetsignRow[$field] ?? ''), true);
            $stateId = $stateIdsByName[$stateName] ?? null;

            if (is_array($current) && $current !== []) {
                $action = self::ACTION_KEPT;
            } elseif ($stateId === null) {
                $action = self::ACTION_MISSING;
            } else {
                $action = self::ACTION_SET;
            }
            $plan[$field] = ['state' => $stateName, 'state_id' => $stateId, 'action' => $action];
        }

        return $plan;
    }

    /**
     * @return int nombre de déclencheurs réglés (0 si l'option est décochée, si assetsign est
     *             absent ou inactif, ou si tout était déjà réglé).
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['assetsign_triggers_enabled']) || !self::isThirdPartyPluginActive()) {
            return 0;
        }

        $row = self::loadRootRow();
        if ($row === null) {
            return 0;
        }

        global $DB;
        $updates = [];
        foreach (self::plan($row, self::loadStateIds(), $config->getAssetsignTriggerFields()) as $field => $step) {
            if ($step['action'] === self::ACTION_SET) {
                $updates[$field] = json_encode([$step['state_id']]);
            }
        }
        if ($updates !== []) {
            $DB->update(self::TABLE, $updates + ['date_mod' => date('Y-m-d H:i:s')], ['id' => $row['id']]);
        }

        return count($updates);
    }

    /**
     * Aperçu affiché par l'assistant avant application (tous les déclencheurs, cochés ou non).
     *
     * @return array<string, array{state: string, state_id: int|null, action: string}>
     */
    public static function preview(): array
    {
        if (!self::isThirdPartyPluginActive()) {
            return [];
        }

        return self::plan(self::loadRootRow() ?? [], self::loadStateIds(), array_keys(self::TRIGGERS));
    }

    public static function isThirdPartyPluginActive(): bool
    {
        return class_exists('Plugin') && \Plugin::isPluginActive('assetsign');
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadRootRow(): ?array
    {
        global $DB;

        if (!$DB->tableExists(self::TABLE)) {
            return null;
        }
        foreach ($DB->request(['FROM' => self::TABLE, 'WHERE' => ['entities_id' => 0], 'LIMIT' => 1]) as $row) {
            return $row;
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private static function loadStateIds(): array
    {
        global $DB;

        $ids = [];
        foreach ($DB->request(['SELECT' => ['id', 'name'], 'FROM' => 'glpi_states']) as $row) {
            $ids[(string) $row['name']] ??= (int) $row['id'];
        }

        return $ids;
    }
}
