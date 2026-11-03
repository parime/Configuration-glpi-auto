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
        'security_account'         => 'Compte compromis / Fuite de données',
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
