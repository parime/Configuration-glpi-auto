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

namespace GlpiPlugin\Configurationglpiauto\Audit\Checks;

use GlpiPlugin\Configurationglpiauto\Audit\AuditCheckInterface;
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\ParkAudit;
use GlpiPlugin\Configurationglpiauto\Audit\PrivilegedAccounts;
use User;

/**
 * Revue des comptes à privilèges (issue #465), sur la même liste de comptes que le contrôle de
 * double authentification (PrivilegedAccounts) :
 * - trop de comptes Super-Admin (profil pouvant modifier la configuration générale) ;
 * - comptes à privilèges sans connexion depuis plus de INACTIVE_DAYS jours ;
 * - comptes à privilèges jamais connectés, créés depuis plus de NEVER_LOGGED_GRACE_DAYS jours ;
 * - comptes locaux à privilèges alors qu'un annuaire LDAP est actif (un compte de secours local
 *   reste normal).
 *
 * Jamais corrigé automatiquement : désactiver un compte ou retirer un profil change l'accès de
 * vraies personnes, l'administrateur décide compte par compte.
 */
final class PrivilegedAccountsReviewCheck implements AuditCheckInterface
{
    public const MAX_SUPER_ADMINS = 3;

    public const INACTIVE_DAYS = 90;

    public const NEVER_LOGGED_GRACE_DAYS = 30;

    /** Comptes locaux tolérés avec un annuaire actif : le compte de secours. */
    public const MAX_LOCAL_WITH_LDAP = 1;

    public function getKey(): string
    {
        return 'privileged_accounts_review';
    }

    public function getDomain(): string
    {
        return __('Comptes à privilèges', 'configurationglpiauto');
    }

    /**
     * Classement pur, sans GLPI : comptes concernés par chaque constat.
     *
     * @param list<array{id: int, name: string, authtype: int, last_login: ?string, date_creation: ?string, super_admin: bool}> $accounts
     * @return array{super_admins: list<array<string, mixed>>, inactive: list<array<string, mixed>>, never_logged: list<array<string, mixed>>, local: list<array<string, mixed>>}
     */
    public static function classify(array $accounts, bool $ldapActive, int $now): array
    {
        $inactiveLimit = $now - self::INACTIVE_DAYS * 86400;
        $graceLimit = $now - self::NEVER_LOGGED_GRACE_DAYS * 86400;
        $result = ['super_admins' => [], 'inactive' => [], 'never_logged' => [], 'local' => []];

        foreach ($accounts as $account) {
            if ($account['super_admin']) {
                $result['super_admins'][] = $account;
            }
            if ($account['last_login'] === null) {
                // Sans date de création (comptes fournis à l'installation), le compte est ancien.
                $created = $account['date_creation'] === null ? false : strtotime($account['date_creation']);
                if ($created === false || $created < $graceLimit) {
                    $result['never_logged'][] = $account;
                }
            } else {
                $lastLogin = strtotime($account['last_login']);
                if ($lastLogin !== false && $lastLogin < $inactiveLimit) {
                    $result['inactive'][] = $account;
                }
            }
            if ($ldapActive && PrivilegedAccounts::isLocal($account['authtype'])) {
                $result['local'][] = $account;
            }
        }

        if (count($result['super_admins']) <= self::MAX_SUPER_ADMINS) {
            $result['super_admins'] = [];
        }
        if (count($result['local']) <= self::MAX_LOCAL_WITH_LDAP) {
            $result['local'] = [];
        }

        return $result;
    }

    public function analyze(): array
    {
        $groups = self::classify(PrivilegedAccounts::findActive(), PrivilegedAccounts::isLdapActive(), time());
        $findings = [];

        if ($groups['super_admins'] !== []) {
            $findings[] = $this->finding(
                AuditFinding::SEVERITY_WARNING,
                sprintf(
                    __('%1$d comptes Super-Admin actifs (%2$d au plus recommandés)', 'configurationglpiauto'),
                    count($groups['super_admins']),
                    self::MAX_SUPER_ADMINS
                ),
                $groups['super_admins'],
                __('Un compte Super-Admin peut tout modifier, y compris les droits des autres. Gardez-en le moins possible : donnez le profil Admin ou Technicien aux personnes qui n\'administrent pas GLPI lui-même (fiche de l\'utilisateur, onglet « Habilitations »).', 'configurationglpiauto')
            );
        }

        if ($groups['inactive'] !== []) {
            $findings[] = $this->finding(
                AuditFinding::SEVERITY_WARNING,
                sprintf(
                    _n(
                        '%1$d compte à privilèges sans connexion depuis plus de %2$d jours',
                        '%1$d comptes à privilèges sans connexion depuis plus de %2$d jours',
                        count($groups['inactive']),
                        'configurationglpiauto'
                    ),
                    count($groups['inactive']),
                    self::INACTIVE_DAYS
                ),
                $groups['inactive'],
                __('Un compte inutilisé garde ses droits et peut être détourné sans que personne ne le remarque. Vérifiez avec le responsable de chaque personne : désactivez le compte (case « Actif » de la fiche) ou retirez son profil s\'il n\'est plus utile.', 'configurationglpiauto'),
                true
            );
        }

        if ($groups['never_logged'] !== []) {
            $findings[] = $this->finding(
                AuditFinding::SEVERITY_INFO,
                sprintf(
                    _n(
                        '%d compte à privilèges ne s\'est jamais connecté',
                        '%d comptes à privilèges ne se sont jamais connectés',
                        count($groups['never_logged']),
                        'configurationglpiauto'
                    ),
                    count($groups['never_logged'])
                ),
                $groups['never_logged'],
                sprintf(
                    __('Ces comptes existent depuis plus de %d jours sans aucune connexion : comptes créés par erreur, en double ou oubliés. Désactivez ceux qui ne servent pas.', 'configurationglpiauto'),
                    self::NEVER_LOGGED_GRACE_DAYS
                )
            );
        }

        if ($groups['local'] !== []) {
            $findings[] = $this->finding(
                AuditFinding::SEVERITY_INFO,
                sprintf(
                    __('%d comptes locaux à privilèges alors qu\'un annuaire LDAP est actif', 'configurationglpiauto'),
                    count($groups['local'])
                ),
                $groups['local'],
                __('Le mot de passe de ces comptes est géré par GLPI et non par l\'annuaire : ils restent ouverts après le départ de la personne. Gardez un seul compte local de secours, documenté, et rattachez les autres à l\'annuaire.', 'configurationglpiauto')
            );
        }

        return $findings;
    }

    /**
     * @param list<array<string, mixed>> $accounts
     */
    private function finding(string $severity, string $title, array $accounts, string $recommendation, bool $withLastLogin = false): AuditFinding
    {
        $labels = [];
        $links = [];
        foreach ($accounts as $account) {
            $name = (string) $account['name'];
            $labels[] = $withLastLogin
                ? sprintf(__('%1$s (dernière connexion le %2$s)', 'configurationglpiauto'), $name, ParkAudit::date((string) $account['last_login']))
                : $name;
            $links[$name] = User::getFormURLWithID((int) $account['id']);
        }

        return new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: $severity,
            title: $title,
            description: ParkAudit::summarize($labels),
            recommendation: $recommendation,
            fixable: false,
            links: $links
        );
    }

    public function canFix(): bool
    {
        return false;
    }

    public function fix(): void
    {
        throw new \LogicException(self::class . ' cannot be auto-fixed.');
    }
}
