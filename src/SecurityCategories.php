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

use Config;
use DropdownTranslation;
use ITILCategory;

/**
 * Catégories « Sécurité SI » (grcmanager #372) : identifiants de la branche et de ses
 * sous-catégories, enregistrés dans la configuration de GLPI au moment de leur création, pour que
 * grcmanager range ses incidents de sécurité et leurs tickets dans ces catégories au lieu d'en
 * créer d'autres.
 *
 * Contrat stable lu par les autres plugins sans dépendre de ce code, sur le modèle de
 * StaffMovement : contexte `plugin:configurationglpiauto`, clés `<clé>_itilcategories_id` pour
 * chaque clé de KEYS. On repère ainsi les catégories par identifiant, jamais par leur nom affiché
 * (traduit, et susceptible d'être renommé). La correspondance avec une classification d'incident
 * reste chez le plugin qui lit ces clés.
 */
final class SecurityCategories
{
    public const CONTEXT = StaffMovement::CONTEXT;

    /** Clé stable => nom donné par ce plugin (CategoryBuilder::CATEGORIES). */
    public const KEYS = [
        'security'                 => 'Sécurité SI',
        'security_endpoint'        => 'Antivirus & Endpoint',
        'security_phishing'        => 'Phishing & Mails suspects',
        'security_malware'         => 'Malware / Ransomware',
        'security_account'         => 'Compte compromis / Accès non autorisé',
        'security_data_breach'     => 'Fuite de données',
        'security_certificates'    => 'Certificats SSL',
        'security_removable'       => 'Périphériques & Supports amovibles',
        'security_vulnerabilities' => 'Vulnérabilités',
    ];

    /**
     * Anciens noms : une indisponibilité est l'effet d'un incident, pas un type d'incident de
     * sécurité (les pannes ont leurs catégories « Infrastructure & Serveurs » et « Supervision &
     * Alertes »).
     */
    private const RENAMED = [
        'Indisponibilité / Malware / Ransomware' => 'Malware / Ransomware',
        // Un compte compromis est un accès non autorisé ; une fuite est une perte de
        // confidentialité, avec ses propres obligations (notification RGPD) : elle a désormais sa
        // sous-catégorie, créée par addMissing().
        'Compte compromis / Fuite de données'    => 'Compte compromis / Accès non autorisé',
    ];

    /** Sous-catégories ajoutées après la création de la branche : clé => commentaire. */
    private const ADDED = [
        'security_data_breach' => 'Perte de confidentialité : notification RGPD sous 72 h si des données personnelles sont concernées',
    ];

    public static function configKey(string $key): string
    {
        return $key . '_itilcategories_id';
    }

    public static function record(string $key, int $id): void
    {
        if ($id > 0 && isset(self::KEYS[$key])) {
            Config::setConfigurationValues(self::CONTEXT, [self::configKey($key) => $id]);
        }
    }

    /**
     * Identifiants enregistrés et encore existants (0 sinon).
     *
     * @return array<string, int>
     */
    public static function ids(): array
    {
        $keys = array_map([self::class, 'configKey'], array_keys(self::KEYS));
        $values = Config::getConfigurationValues(self::CONTEXT, $keys);
        $result = [];
        foreach (array_keys(self::KEYS) as $key) {
            $id = (int) ($values[self::configKey($key)] ?? 0);
            $result[$key] = $id > 0 && (new ITILCategory())->getFromDB($id) ? $id : 0;
        }

        return $result;
    }

    /**
     * Mise à jour : renomme les sous-catégories de la branche dont le nom a changé, en place (les
     * tickets déjà classés ne bougent pas), traductions comprises. Limité aux enfants de la
     * branche « Sécurité SI » créée par ce plugin, et seulement si le nouveau nom n'y existe pas
     * déjà : une catégorie créée à la main n'est jamais touchée.
     */
    public static function renameLegacy(): void
    {
        $branch = new ITILCategory();
        if (!Lookup::loadFirst($branch, ['name' => self::KEYS['security']])) {
            return;
        }
        foreach (self::RENAMED as $old => $new) {
            $item = new ITILCategory();
            $parent = ['itilcategories_id' => (int) $branch->getID()];
            if (!Lookup::loadFirst($item, ['name' => $old] + $parent) || Lookup::loadFirst(new ITILCategory(), ['name' => $new] + $parent)) {
                continue;
            }
            $item->update(['id' => (int) $item->getID(), 'name' => $new]);
            // Le nom traduit suit, avec la même icône (aucune pour une sous-catégorie).
            Translations::applyIcon(ITILCategory::class, (int) $item->getID(), $new, '');
            DropdownTranslation::regenerateAllCompletenameTranslationsFor('ITILCategory', (int) $item->getID());
        }
    }

    /**
     * Mise à jour : crée sous la branche « Sécurité SI » déjà installée les sous-catégories ajoutées
     * depuis (mêmes réglages que CategoryBuilder), sans rien créer si la branche n'a pas été
     * choisie dans l'assistant ni si une sous-catégorie de ce nom y existe déjà.
     */
    public static function addMissing(): void
    {
        $branch = new ITILCategory();
        $branchId = self::ids()['security'];
        if ($branchId <= 0 && Lookup::loadFirst($branch, ['name' => self::KEYS['security']])) {
            $branchId = (int) $branch->getID();
        }
        if ($branchId <= 0) {
            return;
        }
        foreach (self::ADDED as $key => $comment) {
            $item = new ITILCategory();
            $criteria = ['name' => self::KEYS[$key], 'itilcategories_id' => $branchId];
            if (!Lookup::loadFirst($item, $criteria)) {
                $item->add($criteria + [
                    'comment'            => $comment,
                    'entities_id'        => 0,
                    'is_recursive'       => 1,
                    'is_incident'        => 1,
                    'is_request'         => 1,
                    'is_problem'         => 1,
                    'is_change'          => 1,
                    'is_helpdeskvisible' => 0,
                ]);
                Translations::applyIcon(ITILCategory::class, (int) $item->getID(), self::KEYS[$key], '');
            }
            self::record($key, (int) $item->getID());
        }
    }

    /**
     * Installation antérieure : retrouve une seule fois la branche et ses sous-catégories déjà
     * créées par ce plugin (par leurs noms) et enregistre leurs identifiants. Un identifiant déjà
     * enregistré n'est jamais remplacé.
     */
    public static function recordExisting(): void
    {
        $current = self::ids();
        $branch = new ITILCategory();
        if ($current['security'] > 0) {
            $branch->getFromDB($current['security']);
        } elseif (Lookup::loadFirst($branch, ['name' => self::KEYS['security']])) {
            self::record('security', (int) $branch->getID());
        } else {
            return;
        }
        foreach (self::KEYS as $key => $name) {
            if ($key === 'security' || $current[$key] > 0) {
                continue;
            }
            $item = new ITILCategory();
            if (Lookup::loadFirst($item, ['name' => $name, 'itilcategories_id' => (int) $branch->getID()])) {
                self::record($key, (int) $item->getID());
            }
        }
    }

    public static function uninstall(): void
    {
        Config::deleteConfigurationValues(self::CONTEXT, array_map([self::class, 'configKey'], array_keys(self::KEYS)));
    }
}
