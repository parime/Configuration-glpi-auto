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

use CronTask;
use GlpiPlugin\Configurationglpiauto\Compat\GlpiVersion;

/**
 * Issue #470 : sessions actives et ré-authentification, propres à GLPI 12 (gestionnaire de
 * sessions `glpi_users_sessions`, ré-authentification avant les actions sensibles). Sous GLPI 11
 * ces mécanismes n'existent pas : le contrôle est « sans objet », jamais « conforme ».
 *
 * Le classement (`evaluateSettings`, `classifySessions`) est pur, testable sans GLPI ; `collect()`
 * lit l'état réel. Lecture seule : aucune session n'est fermée, aucun réglage n'est modifié.
 */
final class SessionSecurity
{
    /** Session d'un compte ordinaire ouverte depuis plus de ce nombre de jours : signalée. */
    public const OLD_SESSION_DAYS = 7;

    /** Session d'un compte à privilèges ouverte depuis plus de ce nombre d'heures : signalée. */
    public const ADMIN_SESSION_HOURS = 24;

    /** Durée « Se souvenir de moi » au-delà de laquelle le réglage est signalé (jours). */
    public const REMEMBER_MAX_DAYS = 7;

    /** Garde-fou de lecture : jamais plus de sessions chargées que cela. */
    private const MAX_SESSIONS = 2000;

    public const STATUS_OK      = 'ok';
    public const STATUS_INFO    = 'info';
    public const STATUS_WARNING = 'warning';
    public const STATUS_DANGER  = 'danger';

    public const TABLE = 'glpi_users_sessions';

    /**
     * Vrai si l'instance a le gestionnaire de sessions de GLPI 12.
     */
    public static function isAvailable(): bool
    {
        global $DB;

        return GlpiVersion::isAtLeast12() && $DB->tableExists(self::TABLE);
    }

    /**
     * État de chaque réglage lié aux sessions.
     *
     * @param array{reauth_disabled: bool, session_cron_enabled: bool, remember_seconds: int, history_days: int} $settings
     * @return array{reauth: string, session_cron: string, remember: string, history: string}
     */
    public static function evaluateSettings(array $settings): array
    {
        return [
            'reauth'       => $settings['reauth_disabled'] ? self::STATUS_DANGER : self::STATUS_OK,
            'session_cron' => $settings['session_cron_enabled'] ? self::STATUS_OK : self::STATUS_WARNING,
            'remember'     => $settings['remember_seconds'] > self::REMEMBER_MAX_DAYS * 86400 ? self::STATUS_INFO : self::STATUS_OK,
            'history'      => $settings['history_days'] <= 0 ? self::STATUS_WARNING : self::STATUS_OK,
        ];
    }

    /**
     * Sessions anciennes regroupées par compte : `admin` pour les comptes à privilèges ouverts
     * depuis plus de ADMIN_SESSION_HOURS heures, `old` pour les autres comptes ouverts depuis plus
     * de OLD_SESSION_DAYS jours. Chaque compte n'apparaît qu'une fois, avec sa plus vieille session.
     *
     * @param list<array{users_id: int, name: string, created_at: ?string}> $sessions
     * @param list<int>                                                      $privilegedIds
     * @return array{admin: list<array{users_id: int, name: string, sessions: int, oldest: string}>, old: list<array{users_id: int, name: string, sessions: int, oldest: string}>}
     */
    public static function classifySessions(array $sessions, array $privilegedIds, int $now): array
    {
        $adminLimit = $now - self::ADMIN_SESSION_HOURS * 3600;
        $oldLimit   = $now - self::OLD_SESSION_DAYS * 86400;
        $groups     = ['admin' => [], 'old' => []];

        foreach ($sessions as $session) {
            $created = $session['created_at'] === null ? false : strtotime($session['created_at']);
            if ($created === false) {
                continue;
            }
            $privileged = in_array($session['users_id'], $privilegedIds, true);
            if ($privileged ? $created >= $adminLimit : $created >= $oldLimit) {
                continue;
            }
            $group = $privileged ? 'admin' : 'old';
            $id    = $session['users_id'];
            if (!isset($groups[$group][$id])) {
                $groups[$group][$id] = ['users_id' => $id, 'name' => $session['name'], 'sessions' => 0, 'oldest' => (string) $session['created_at']];
            }
            $groups[$group][$id]['sessions']++;
            if ($created < (int) strtotime($groups[$group][$id]['oldest'])) {
                $groups[$group][$id]['oldest'] = (string) $session['created_at'];
            }
        }

        $sort = static fn (array $a, array $b): int => [$a['oldest'], $a['name']] <=> [$b['oldest'], $b['name']];
        $admin = array_values($groups['admin']);
        $old   = array_values($groups['old']);
        usort($admin, $sort);
        usort($old, $sort);

        return ['admin' => $admin, 'old' => $old];
    }

    /**
     * Lit l'état réel (GLPI 12 uniquement ; null sous GLPI 11).
     *
     * @return null|array{
     *     settings: array{reauth_disabled: bool, session_cron_enabled: bool, remember_seconds: int, history_days: int},
     *     session_cron_id: int,
     *     active_sessions: int,
     *     sessions: array{admin: list<array{users_id: int, name: string, sessions: int, oldest: string}>, old: list<array{users_id: int, name: string, sessions: int, oldest: string}>}
     * }
     */
    public static function collect(): ?array
    {
        global $DB, $CFG_GLPI;

        if (!self::isAvailable()) {
            return null;
        }

        $cron = new CronTask();
        $cronFound = (bool) $cron->getFromDBbyName(CronTask::class, 'session');

        $settings = [
            // Constante de GLPI 12 (vouée à disparaître) : lue par son nom pour rester compatible.
            'reauth_disabled'      => defined('GLPI_DISABLE_REAUTH') && constant('GLPI_DISABLE_REAUTH') === true,
            'session_cron_enabled' => $cronFound && (int) $cron->fields['state'] !== CronTask::STATE_DISABLE,
            'remember_seconds'     => (int) ($CFG_GLPI['login_remember_time'] ?? 0),
            'history_days'         => (int) ($CFG_GLPI['login_history_retention_days'] ?? 0),
        ];

        $sessions = [];
        foreach (
            $DB->request([
                'SELECT'     => [self::TABLE . '.users_id', self::TABLE . '.created_at', 'glpi_users.name'],
                'FROM'       => self::TABLE,
                'INNER JOIN' => [
                    'glpi_users' => ['ON' => ['glpi_users' => 'id', self::TABLE => 'users_id']],
                ],
                'WHERE'      => [
                    self::TABLE . '.created_at' => ['<', date('Y-m-d H:i:s', time() - self::ADMIN_SESSION_HOURS * 3600)],
                    'NOT'                       => ['glpi_users.name' => PrivilegedAccounts::SYSTEM_USER_NAME],
                ],
                'ORDER'      => self::TABLE . '.created_at',
                'LIMIT'      => self::MAX_SESSIONS,
            ]) as $row
        ) {
            $sessions[] = [
                'users_id'   => (int) $row['users_id'],
                'name'       => (string) $row['name'],
                'created_at' => $row['created_at'] === null ? null : (string) $row['created_at'],
            ];
        }

        $privilegedIds = array_map(static fn (array $a): int => $a['id'], PrivilegedAccounts::findActive());

        return [
            'settings'        => $settings,
            'session_cron_id' => $cronFound ? (int) $cron->getID() : 0,
            'active_sessions' => countElementsInTable(self::TABLE),
            'sessions'        => self::classifySessions($sessions, $privilegedIds, time()),
        ];
    }

    /**
     * Données de la section « Sessions et ré-authentification » de la page d'audit : état de
     * chaque réglage, compteurs et liens vers les écrans natifs de GLPI 12 où ils se règlent.
     *
     * @return array<string, mixed>
     */
    public static function view(): array
    {
        global $CFG_GLPI;

        $state = self::collect();
        if ($state === null) {
            return ['available' => false];
        }

        $root = (string) $CFG_GLPI['root_doc'];
        $tab  = static fn (string $path, string $tab): string => $root . $path . '?forcetab=' . urlencode($tab);

        return [
            'available'        => true,
            'settings'         => $state['settings'],
            'statuses'         => self::evaluateSettings($state['settings']),
            'remember_days'    => intdiv($state['settings']['remember_seconds'], 86400),
            'remember_hours'   => intdiv($state['settings']['remember_seconds'], 3600),
            'active_sessions'  => $state['active_sessions'],
            'admin_accounts'   => count($state['sessions']['admin']),
            'old_accounts'     => count($state['sessions']['old']),
            'thresholds'       => [
                'admin_hours'   => self::ADMIN_SESSION_HOURS,
                'old_days'      => self::OLD_SESSION_DAYS,
                'remember_days' => self::REMEMBER_MAX_DAYS,
            ],
            'urls'             => [
                'sessions'     => $tab('/front/security/securityconfig.form.php', 'Glpi\\Security\\SecurityConfig$2'),
                'general'      => $tab('/front/config.form.php', 'Config$1'),
                'privacy'      => $tab('/front/config/dataandprivacyconfig.form.php', 'Glpi\\Config\\DataAndPrivacyConfig$1'),
                'session_cron' => $state['session_cron_id'] > 0 ? CronTask::getFormURLWithID($state['session_cron_id']) : $root . '/front/crontask.php',
            ],
        ];
    }
}
