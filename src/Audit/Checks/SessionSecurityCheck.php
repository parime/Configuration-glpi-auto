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
use GlpiPlugin\Configurationglpiauto\Audit\SessionSecurity;
use Html;
use User;

/**
 * Issue #470 : sessions actives et ré-authentification (GLPI 12). Signale la ré-authentification
 * désactivée, le nettoyage des sessions arrêté, l'historique des connexions non conservé, une durée
 * « Se souvenir de moi » longue, les comptes à privilèges connectés depuis longtemps et les
 * vieilles sessions des autres comptes.
 *
 * Sous GLPI 11, aucun constat : le contrôle est déclaré sans objet (AuditControlCatalog). Jamais
 * corrigé automatiquement : fermer une session déconnecte une vraie personne, l'administrateur
 * le fait lui-même depuis GLPI.
 */
final class SessionSecurityCheck implements AuditCheckInterface
{
    public function getKey(): string
    {
        return 'session_security';
    }

    public function getDomain(): string
    {
        return __('Sessions et ré-authentification', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        $state = SessionSecurity::collect();
        if ($state === null) {
            return [];
        }

        $statuses = SessionSecurity::evaluateSettings($state['settings']);
        $findings = [];

        if ($statuses['reauth'] !== SessionSecurity::STATUS_OK) {
            $findings[] = $this->finding(
                AuditFinding::SEVERITY_CRITICAL,
                __('La ré-authentification avant les actions sensibles est désactivée', 'configurationglpiauto'),
                __('GLPI ne redemande plus le mot de passe avant une action sensible (droits, sécurité) : une session laissée ouverte suffit pour les modifier. La constante GLPI_DISABLE_REAUTH est déclarée dans un fichier de configuration du serveur.', 'configurationglpiauto'),
                __('Demandez à la personne qui gère le serveur de retirer GLPI_DISABLE_REAUTH du fichier config/local_define.php.', 'configurationglpiauto')
            );
        }

        if ($statuses['session_cron'] !== SessionSecurity::STATUS_OK) {
            $findings[] = $this->finding(
                AuditFinding::SEVERITY_WARNING,
                __('Le nettoyage automatique des sessions est arrêté', 'configurationglpiauto'),
                __('L\'action automatique « session » de GLPI ferme les sessions expirées. Désactivée, d\'anciennes sessions restent valables et la liste des sessions actives ne reflète plus la réalité.', 'configurationglpiauto'),
                __('Réactivez l\'action automatique « session » dans Configuration > Actions automatiques.', 'configurationglpiauto')
            );
        }

        if ($statuses['history'] !== SessionSecurity::STATUS_OK) {
            $findings[] = $this->finding(
                AuditFinding::SEVERITY_WARNING,
                __('L\'historique des connexions n\'est pas conservé', 'configurationglpiauto'),
                __('Avec une durée de conservation de 0 jour, GLPI efface la trace d\'une connexion dès sa fin : impossible ensuite de savoir qui s\'est connecté, quand et d\'où.', 'configurationglpiauto'),
                __('Indiquez une durée de conservation (90 jours par exemple) dans Configuration > Données et Confidentialité, onglet « Sessions de connexion ».', 'configurationglpiauto')
            );
        }

        if ($statuses['remember'] !== SessionSecurity::STATUS_OK) {
            $findings[] = $this->finding(
                AuditFinding::SEVERITY_INFO,
                sprintf(
                    __('« Se souvenir de moi » garde la connexion plus de %d jours', 'configurationglpiauto'),
                    SessionSecurity::REMEMBER_MAX_DAYS
                ),
                sprintf(
                    __('Durée actuelle : %d jours. Toute personne qui utilise l\'ordinateur pendant ce temps entre dans GLPI sans mot de passe.', 'configurationglpiauto'),
                    intdiv($state['settings']['remember_seconds'], 86400)
                ),
                __('Réduisez le « Temps de rétention se souvenir de moi » dans Configuration > Générale, section « Identification automatique ».', 'configurationglpiauto')
            );
        }

        if ($state['sessions']['admin'] !== []) {
            $count = count($state['sessions']['admin']);
            $findings[] = $this->finding(
                AuditFinding::SEVERITY_WARNING,
                sprintf(
                    _n(
                        '%1$d compte à privilèges connecté depuis plus de %2$d heures',
                        '%1$d comptes à privilèges connectés depuis plus de %2$d heures',
                        $count,
                        'configurationglpiauto'
                    ),
                    $count,
                    SessionSecurity::ADMIN_SESSION_HOURS
                ),
                $this->accountsText($state['sessions']['admin'], true),
                __('Une session d\'administrateur restée ouverte est la plus intéressante à voler. Vérifiez avec chaque personne qu\'elle est bien la sienne, puis fermez les sessions inutiles : fiche de l\'utilisateur, onglet « Sessions », bouton « Révoquer ».', 'configurationglpiauto'),
                $this->accountLinks($state['sessions']['admin'])
            );
        }

        if ($state['sessions']['old'] !== []) {
            $count = count($state['sessions']['old']);
            $findings[] = $this->finding(
                AuditFinding::SEVERITY_INFO,
                sprintf(
                    _n(
                        '%1$d compte a une session ouverte depuis plus de %2$d jours',
                        '%1$d comptes ont une session ouverte depuis plus de %2$d jours',
                        $count,
                        'configurationglpiauto'
                    ),
                    $count,
                    SessionSecurity::OLD_SESSION_DAYS
                ),
                $this->accountsText($state['sessions']['old'], false),
                __('Souvent un « Se souvenir de moi » sur un poste partagé ou perdu. Fermez les sessions qui ne sont plus utiles : fiche de l\'utilisateur, onglet « Sessions ».', 'configurationglpiauto'),
                $this->accountLinks($state['sessions']['old'])
            );
        }

        return $findings;
    }

    /**
     * @param list<array{users_id: int, name: string, sessions: int, oldest: string}> $accounts
     */
    private function accountsText(array $accounts, bool $withTime): string
    {
        $labels = [];
        foreach ($accounts as $account) {
            $labels[] = sprintf(
                __('%1$s (ouverte le %2$s)', 'configurationglpiauto'),
                $account['name'],
                $withTime ? (string) Html::convDateTime($account['oldest']) : ParkAudit::date($account['oldest'])
            );
        }

        return ParkAudit::summarize($labels);
    }

    /**
     * Lien direct vers l'onglet « Sessions » de la fiche (onglet 5 de User sous GLPI 12).
     *
     * @param list<array{users_id: int, name: string, sessions: int, oldest: string}> $accounts
     * @return array<string, string>
     */
    private function accountLinks(array $accounts): array
    {
        $links = [];
        foreach ($accounts as $account) {
            $links[$account['name']] = User::getFormURLWithID($account['users_id']) . '&forcetab=User$5';
        }

        return $links;
    }

    /**
     * @param array<string, string> $links
     */
    private function finding(string $severity, string $title, string $description, string $recommendation, array $links = []): AuditFinding
    {
        return new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: $severity,
            title: $title,
            description: $description,
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
