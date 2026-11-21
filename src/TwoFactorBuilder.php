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

use Glpi\Security\TOTPManager;
use Profile;

/**
 * Double authentification native de GLPI (issue #464) : GLPI 11 sait imposer un code TOTP aux
 * membres d'un profil (`glpi_profiles.2fa_enforced`, onglet « Sécurité » du
 * profil). Ce plugin n'invente aucun mécanisme : l'assistant propose de cocher cette case native
 * sur les profils à privilèges, l'administrateur choisit lesquels.
 *
 * Un profil « à privilèges » est ici un profil de l'interface standard (`interface = central`) :
 * c'est elle qui ouvre l'accès à l'administration et au traitement des tickets, contrairement à
 * l'interface simplifiée (`helpdesk`) des demandeurs.
 *
 * Purement additif : un profil décoché n'est jamais modifié, une obligation déjà en place n'est
 * jamais retirée. Chaque utilisateur concerné configure lui-même son application à sa prochaine
 * connexion (écran natif de GLPI) : aucun secret n'est créé ni lu par ce plugin.
 */
class TwoFactorBuilder
{
    /**
     * Noms des profils natifs de GLPI cochés par défaut dans l'assistant (proposés, jamais
     * appliqués sans la case maîtresse de l'étape).
     */
    public const RECOMMENDED_PROFILE_NAMES = ['Super-Admin', 'Admin', 'Technician'];

    /**
     * Compte interne de GLPI (tâches automatiques) : n'ouvre jamais de session interactive.
     */
    private const SYSTEM_USER_NAME = 'glpi-system';

    /**
     * Vrai si l'instance connaît la 2FA native (GLPI 11 et plus).
     */
    public static function isAvailable(): bool
    {
        global $DB;

        return class_exists(TOTPManager::class)
            && $DB->fieldExists(Profile::getTable(), '2fa_enforced')
            && $DB->fieldExists('glpi_users', '2fa');
    }

    /**
     * Profils de l'interface standard, pour l'étape de l'assistant.
     *
     * @return list<array{id: int, name: string, enforced: bool, recommended: bool, users_without_2fa: int}>
     */
    public static function getProfilesPreview(): array
    {
        global $DB;

        if (!self::isAvailable()) {
            return [];
        }

        $withoutTwoFactor = [];
        foreach (self::findPrivilegedUsersWithout2fa() as $user) {
            foreach ($user['profiles'] as $profileId) {
                $withoutTwoFactor[$profileId] = ($withoutTwoFactor[$profileId] ?? 0) + 1;
            }
        }

        $profiles = [];
        foreach (
            $DB->request([
                'SELECT' => ['id', 'name', '2fa_enforced'],
                'FROM'   => Profile::getTable(),
                'WHERE'  => ['interface' => 'central'],
                'ORDER'  => 'name',
            ]) as $row
        ) {
            $id = (int) $row['id'];
            $profiles[] = [
                'id'                => $id,
                'name'              => (string) $row['name'],
                'enforced'          => (int) $row['2fa_enforced'] === 1,
                'recommended'       => in_array((string) $row['name'], self::RECOMMENDED_PROFILE_NAMES, true),
                'users_without_2fa' => $withoutTwoFactor[$id] ?? 0,
            ];
        }

        return $profiles;
    }

    /**
     * Comptes actifs ayant au moins un profil de l'interface standard et aucune application
     * d'authentification enregistrée. `covered` indique si GLPI leur imposera la 2FA à la
     * prochaine connexion (réglage global, entité, profil ou groupe), selon le calcul de GLPI.
     *
     * @return list<array{id: int, name: string, profiles: list<int>, covered: bool}>
     */
    public static function findPrivilegedUsersWithout2fa(): array
    {
        global $DB;

        if (!self::isAvailable()) {
            return [];
        }

        $users = [];
        foreach (
            $DB->request([
                'SELECT'     => ['glpi_users.id', 'glpi_users.name', 'glpi_profiles_users.profiles_id'],
                'FROM'       => 'glpi_users',
                'INNER JOIN' => [
                    'glpi_profiles_users' => ['ON' => ['glpi_profiles_users' => 'users_id', 'glpi_users' => 'id']],
                    'glpi_profiles'       => ['ON' => ['glpi_profiles' => 'id', 'glpi_profiles_users' => 'profiles_id']],
                ],
                'WHERE'      => [
                    'glpi_users.is_active'   => 1,
                    'glpi_users.is_deleted'  => 0,
                    'glpi_profiles.interface' => 'central',
                    'NOT'                    => ['glpi_users.name' => self::SYSTEM_USER_NAME],
                    'OR'                     => [
                        ['glpi_users.2fa' => null],
                        ['glpi_users.2fa' => ''],
                    ],
                ],
                'ORDER'      => 'glpi_users.name',
            ]) as $row
        ) {
            $id = (int) $row['id'];
            $users[$id] ??= ['id' => $id, 'name' => (string) $row['name'], 'profiles' => [], 'covered' => false];
            $profileId = (int) $row['profiles_id'];
            if (!in_array($profileId, $users[$id]['profiles'], true)) {
                $users[$id]['profiles'][] = $profileId;
            }
        }

        $totp = new TOTPManager();
        foreach ($users as $id => $user) {
            $users[$id]['covered'] = $totp->get2FAEnforcement($id) !== TOTPManager::ENFORCEMENT_OPTIONAL;
        }

        return array_values($users);
    }

    /**
     * Noms des profils retenus, sans doublon ni valeur vide. Des noms plutôt que des identifiants :
     * un Blueprint exporté reste valable sur une autre instance.
     *
     * @param mixed $raw Liste reçue du formulaire, ou JSON enregistré.
     * @return list<string>
     */
    public static function normalizeProfileNames(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $names = [];
        foreach ($raw as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $name = trim((string) $value);
            if ($name !== '' && mb_strlen($name) <= 255 && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Coche l'obligation native sur les profils retenus. Renvoie le nombre de profils modifiés.
     * Un profil que l'utilisateur courant ne peut pas modifier (droits supérieurs aux siens) ou
     * d'interface simplifiée est ignoré.
     */
    public function apply(Config $config): int
    {
        if (empty($config->fields['twofa_enforcement_enabled']) || !self::isAvailable()) {
            return 0;
        }

        $updated = 0;
        $profile = new Profile();
        foreach (self::normalizeProfileNames($config->fields['twofa_profiles'] ?? '[]') as $name) {
            if (!Lookup::loadFirst($profile, ['name' => $name, 'interface' => 'central'])) {
                continue;
            }
            $profileId = (int) $profile->getID();
            if ((int) ($profile->fields['2fa_enforced'] ?? 0) === 1) {
                continue;
            }
            if (!Profile::currentUserHaveMoreRightThan([$profileId => $profileId])) {
                continue;
            }
            if ($profile->update(['id' => $profileId, '2fa_enforced' => 1])) {
                $updated++;
            }
        }

        return $updated;
    }
}
