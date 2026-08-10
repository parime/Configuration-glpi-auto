<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Parime
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
 * Declares this plugin's rights in GLPI's standard profile matrix (Administration > Profils).
 * Deliberately a dedicated right rather than reusing GLPI's core 'config' right: 'config' only
 * grants READ/UPDATE (it models a per-entity singleton), never CREATE/PURGE — confirmed against a
 * real GLPI 11 instance while validating the profile CRUD screens, where "Ajouter" 403'd for the
 * built-in super-admin user despite it having full 'config' access.
 */
class Profile
{
    public const RIGHT_PROFILE = 'plugin_configurationglpiauto_profile';

    public const RIGHT_CONFIG = 'plugin_configurationglpiauto_config';

    private const ALL_RIGHTS = [self::RIGHT_PROFILE, self::RIGHT_CONFIG];

    public static function install(\Migration $migration): void
    {
        global $DB;

        // \ProfileRight::addProfileRights() does a raw INSERT per profile with no existence
        // check — fine on a first install, but fatals with a duplicate-key error if install()
        // ever runs again (e.g. plugin:install --force, or a future version bump). Insert only
        // the missing profile/right pairs instead, same fix already applied on remise-glpi.
        $existing = [];
        foreach ($DB->request(['FROM' => \ProfileRight::getTable(), 'WHERE' => ['name' => self::ALL_RIGHTS]]) as $row) {
            $existing[$row['profiles_id'] . '|' . $row['name']] = true;
        }

        foreach ($DB->request(['FROM' => \Profile::getTable()]) as $profile) {
            foreach (self::ALL_RIGHTS as $right) {
                if (!isset($existing[$profile['id'] . '|' . $right])) {
                    $DB->insert(\ProfileRight::getTable(), [
                        'profiles_id' => $profile['id'],
                        'name'        => $right,
                    ]);
                }
            }
        }

        // Grants full rights to the "Super-Admin" profile so the plugin is usable immediately
        // after install. updateProfileRights() IS idempotent, unlike addProfileRights() above.
        $rows = $DB->request(['FROM' => \Profile::getTable(), 'WHERE' => ['name' => 'Super-Admin']]);
        foreach ($rows as $row) {
            \ProfileRight::updateProfileRights((int) $row['id'], array_fill_keys(self::ALL_RIGHTS, ALLSTANDARDRIGHT));
        }
    }

    public static function uninstall(): void
    {
        \ProfileRight::deleteProfileRights(self::ALL_RIGHTS);
    }
}
