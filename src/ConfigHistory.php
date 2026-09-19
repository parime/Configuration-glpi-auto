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

use CommonDBTM;
use GlpiPlugin\Configurationglpiauto\Blueprint\BlueprintSerializer;

/**
 * Fonctionnalité de Rollback (issue #114) : une ligne de cette table EST un Blueprint complet
 * (même format que BlueprintSerializer::export(), voir ConfigurationProfile.snapshot pour le
 * précédent) — soit un instantané automatique (`is_manual=0`, capturé juste avant chaque écrasement
 * réel de `Config`, voir `captureAutomatic()`), soit un point de sauvegarde créé explicitement par
 * un administrateur (`is_manual=1`, jamais purgé automatiquement).
 *
 * Volontairement une table à part de `ConfigurationProfile` plutôt qu'une extension de celle-ci :
 * un profil est une bibliothèque nommée et rarement supprimée, alors que l'historique automatique
 * doit être purgé agressivement — mélanger les deux cycles de vie noierait la liste des profils
 * sous des lignes générées automatiquement.
 *
 * Volontairement dans le namespace racine du plugin (pas `Blueprint\`, où vit `BlueprintSerializer`)
 * : GLPI dérive `getFormURL()`/`getSearchURL()` par défaut à partir des segments de namespace après
 * le préfixe du plugin (`Toolbox::getItemTypeFormURL()`) — un namespace imbriqué produirait une URL
 * par défaut sous `front/blueprint/...` qui n'existe pas. `ConfigurationProfile`/`Config`/`FuelType`
 * vivent tous dans ce même namespace racine pour la même raison. `getFormURL()` est de toute façon
 * explicitement surchargé ci-dessous (cette entité n'a pas de fichier `*.form.php` conventionnel),
 * mais rester dans le namespace racine évite la même catégorie de piège pour tout le reste
 * (`getTypeName()`, la matrice de droits...), même précaution que documentée sur `getTable()`
 * ci-dessous.
 */
final class ConfigHistory extends CommonDBTM
{
    public static $rightname = Profile::RIGHT_CONFIG;

    private const MAX_AUTOMATIC_ENTRIES = 20;

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_configurationglpiauto_history';
    }

    public static function getTypeName($nb = 0): string
    {
        return _n('Historique de configuration', 'Historique de configuration', $nb, 'configurationglpiauto');
    }

    public static function getIcon(): string
    {
        return 'fas fa-history';
    }

    // Pas de front/confighistory.form.php conventionnel : une ligne d'historique n'est jamais
    // éditée, seulement créée/consultée/restaurée via front/history_restore.php (confirmation +
    // application, voir ce fichier) — d'où cette surcharge explicite, même précaution que
    // ConfigurationProfile::getSearchURL() pour une raison symétrique (pointer vers un écran dédié
    // plutôt que la convention de nommage par défaut).
    public static function getFormURL($full = true): string
    {
        global $CFG_GLPI;

        $dir = $full ? $CFG_GLPI['root_doc'] : '';

        return $dir . '/plugins/configurationglpiauto/front/history_restore.php';
    }

    public function rawSearchOptions(): array
    {
        return [
            ['id' => 'common', 'name' => self::getTypeName(1)],
            ['id' => 1, 'table' => self::getTable(), 'field' => 'label', 'name' => __('Label du point de sauvegarde', 'configurationglpiauto'), 'datatype' => 'itemlink', 'itemtype' => self::class],
            ['id' => 2, 'table' => self::getTable(), 'field' => 'is_manual', 'name' => __('Point de sauvegarde manuel', 'configurationglpiauto'), 'datatype' => 'bool'],
            ['id' => 19, 'table' => self::getTable(), 'field' => 'date_creation', 'name' => __('Date de création'), 'datatype' => 'datetime'],
        ];
    }

    /**
     * Appelé juste avant chaque écrasement réel de la ligne `Config` vivante (front/wizard.php à
     * l'étape "finish", front/profile.form.php à l'action `apply_snapshot`, front/history_restore.php
     * à l'action `restore`) — capture l'état COURANT (avant écrasement), jamais l'état qui va être
     * appliqué. Purge les entrées automatiques au-delà de MAX_AUTOMATIC_ENTRIES à chaque appel : pas
     * de CronTask dédié, la purge synchrone à la capture suffit pour les deux seuls points d'appel
     * réels de ce plugin.
     */
    public static function captureAutomatic(Config $config): void
    {
        self::capture($config, false, null);
        self::pruneAutomatic();
    }

    /**
     * Appelé depuis le bouton "Créer un point de sauvegarde" (front/history.php) — jamais purgé
     * automatiquement, contrairement à captureAutomatic().
     */
    public static function captureManual(Config $config, string $label): int
    {
        return self::capture($config, true, $label);
    }

    private static function capture(Config $config, bool $isManual, ?string $label): int
    {
        $snapshot = BlueprintSerializer::export($config->fields, $label ?? '', PLUGIN_CONFIGURATIONGLPIAUTO_VERSION);

        return (int) (new self())->add([
            'is_manual' => $isManual ? 1 : 0,
            'label'     => $label,
            'snapshot'  => json_encode($snapshot, JSON_PRETTY_PRINT),
        ]);
    }

    private static function pruneAutomatic(): void
    {
        global $DB;

        // GLPI's query builder only applies 'START' (OFFSET) when 'LIMIT' is also set
        // (DBmysqlIterator::handleLimits() no-ops the OFFSET clause otherwise) — this table never
        // holds more than a handful of automatic rows, so fetching every id and slicing in PHP
        // avoids that pitfall entirely rather than fighting a combined LIMIT/OFFSET.
        $ids = [];
        $rows = $DB->request([
            'SELECT' => 'id',
            'FROM'   => self::getTable(),
            'WHERE'  => ['is_manual' => 0],
            'ORDER'  => 'id DESC',
        ]);
        foreach ($rows as $row) {
            $ids[] = $row['id'];
        }

        $idsToPrune = array_slice($ids, self::MAX_AUTOMATIC_ENTRIES);

        if ($idsToPrune !== []) {
            $DB->delete(self::getTable(), ['id' => $idsToPrune]);
        }
    }
}
