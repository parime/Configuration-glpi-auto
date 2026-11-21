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

namespace GlpiPlugin\Configurationglpiauto\Audit;

use Auth;
use AuthLDAP;
use ProfileRight;

/**
 * Comptes à privilèges (issues #464 et #465) : comptes actifs, hors compte interne de GLPI, ayant
 * au moins un profil de l'interface standard (`interface = central`). C'est elle qui ouvre l'accès
 * à l'administration et au traitement des tickets, contrairement à l'interface simplifiée des
 * demandeurs. Requête unique partagée par le contrôle de double authentification et la revue des
 * comptes à privilèges : lecture seule.
 */
final class PrivilegedAccounts
{
    /**
     * Compte interne de GLPI (tâches automatiques) : n'ouvre jamais de session interactive.
     */
    public const SYSTEM_USER_NAME = 'glpi-system';

    /**
     * @return list<array{id: int, name: string, profiles: list<int>, authtype: int, last_login: ?string, date_creation: ?string, has_2fa: bool, super_admin: bool}>
     */
    public static function findActive(): array
    {
        global $DB;

        $select = [
            'glpi_users.id',
            'glpi_users.name',
            'glpi_users.authtype',
            'glpi_users.last_login',
            'glpi_users.date_creation',
            'glpi_profiles_users.profiles_id',
        ];
        $withTwoFactor = $DB->fieldExists('glpi_users', '2fa');
        if ($withTwoFactor) {
            $select[] = 'glpi_users.2fa';
        }

        $superAdminProfiles = self::superAdminProfileIds();
        $users = [];
        foreach (
            $DB->request([
                'SELECT'     => $select,
                'FROM'       => 'glpi_users',
                'INNER JOIN' => [
                    'glpi_profiles_users' => ['ON' => ['glpi_profiles_users' => 'users_id', 'glpi_users' => 'id']],
                    'glpi_profiles'       => ['ON' => ['glpi_profiles' => 'id', 'glpi_profiles_users' => 'profiles_id']],
                ],
                'WHERE'      => [
                    'glpi_users.is_active'    => 1,
                    'glpi_users.is_deleted'   => 0,
                    'glpi_profiles.interface' => 'central',
                    'NOT'                     => ['glpi_users.name' => self::SYSTEM_USER_NAME],
                ],
                'ORDER'      => 'glpi_users.name',
            ]) as $row
        ) {
            $id = (int) $row['id'];
            $users[$id] ??= [
                'id'            => $id,
                'name'          => (string) $row['name'],
                'profiles'      => [],
                'authtype'      => (int) $row['authtype'],
                'last_login'    => self::dateOrNull($row['last_login'] ?? null),
                'date_creation' => self::dateOrNull($row['date_creation'] ?? null),
                'has_2fa'       => $withTwoFactor && trim((string) ($row['2fa'] ?? '')) !== '',
                'super_admin'   => false,
            ];
            $profileId = (int) $row['profiles_id'];
            if (!in_array($profileId, $users[$id]['profiles'], true)) {
                $users[$id]['profiles'][] = $profileId;
            }
            if (in_array($profileId, $superAdminProfiles, true)) {
                $users[$id]['super_admin'] = true;
            }
        }

        return array_values($users);
    }

    /**
     * Profils « Super-Admin » au sens des droits, quel que soit leur nom : ceux qui peuvent
     * modifier la configuration générale (droit `config` avec écriture).
     *
     * @return list<int>
     */
    public static function superAdminProfileIds(): array
    {
        global $DB;

        $ids = [];
        foreach (
            $DB->request([
                'SELECT' => ['profiles_id', 'rights'],
                'FROM'   => ProfileRight::getTable(),
                'WHERE'  => ['name' => 'config'],
            ]) as $row
        ) {
            if (((int) $row['rights'] & UPDATE) === UPDATE) {
                $ids[] = (int) $row['profiles_id'];
            }
        }

        return $ids;
    }

    /**
     * Vrai si au moins un annuaire LDAP actif est déclaré.
     */
    public static function isLdapActive(): bool
    {
        return countElementsInTable(AuthLDAP::getTable(), ['is_active' => 1]) > 0;
    }

    /**
     * Vrai pour un compte dont le mot de passe est géré par GLPI lui-même (base locale).
     */
    public static function isLocal(int $authtype): bool
    {
        return $authtype === Auth::DB_GLPI;
    }

    private static function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return ($value === '' || str_starts_with($value, '0000')) ? null : $value;
    }
}
