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

use Glpi\Application\View\TemplateRenderer;

/**
 * Declares this plugin's rights in GLPI's standard profile matrix (Administration > Profils).
 * Deliberately a dedicated right rather than reusing GLPI's core 'config' right: 'config' only
 * grants READ/UPDATE (it models a per-entity singleton), never CREATE/PURGE — confirmed against a
 * real GLPI 11 instance while validating the profile CRUD screens, where "Ajouter" 403'd for the
 * built-in super-admin user despite it having full 'config' access.
 *
 * Also the plugin's tab on GLPI profiles: the core rights matrix, saved by the core
 * front/profile.form.php (core 'profile' right), so both rights can be granted from the UI.
 */
class Profile extends \CommonGLPI
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

    public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0)
    {
        return self::canManage($item) ? self::createTabEntry(self::pluginName(), 0, null, 'ti ti-settings-automation') : '';
    }

    public static function displayTabContentForItem(\CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        // ajax/common.tabs.php serves the content without calling getTabNameForItem().
        if (!self::canManage($item)) {
            return false;
        }
        TemplateRenderer::getInstance()->display('@configurationglpiauto/profile_rights.html.twig', [
            'item'   => $item,
            'title'  => self::pluginName(),
            'rights' => self::getRightsMatrix(),
        ]);

        return true;
    }

    /**
     * One row per plugin right (shared "profile-right" check: every right must be listed here).
     *
     * @return list<array<string, mixed>>
     */
    public static function getRightsMatrix(): array
    {
        return [
            [
                'itemtype' => ConfigurationProfile::class,
                'label'    => ConfigurationProfile::getTypeName(\Session::getPluralNumber()),
                'field'    => self::RIGHT_PROFILE,
            ],
            [
                'itemtype' => Config::class,
                'label'    => Config::getTypeName(),
                'field'    => self::RIGHT_CONFIG,
            ],
        ];
    }

    private static function pluginName(): string
    {
        return (string) (plugin_version_configurationglpiauto()['name'] ?? 'Configuration GLPI Auto');
    }

    /**
     * Core 'profile' right, plus access to the plugin: rights over a module are not delegated by
     * someone who cannot see it. Simplified-interface profiles never reach the plugin.
     */
    private static function canManage(\CommonGLPI $item): bool
    {
        return $item instanceof \Profile && !$item->isNewItem()
            && ($item->fields['interface'] ?? '') === 'central'
            && \Profile::canView()
            && (\Session::haveRight(self::RIGHT_PROFILE, READ) || \Session::haveRight(self::RIGHT_CONFIG, READ));
    }

    public static function uninstall(): void
    {
        \ProfileRight::deleteProfileRights(self::ALL_RIGHTS);
    }
}
