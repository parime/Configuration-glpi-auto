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
use GlpiPlugin\Configurationglpiauto\TwoFactorBuilder;

/**
 * Comptes à privilèges sans double authentification (issue #464) : comptes actifs ayant un profil
 * de l'interface standard, sans application d'authentification enregistrée et à qui GLPI ne
 * l'imposera pas à la prochaine connexion (ni réglage global, ni entité, ni profil, ni groupe).
 * Un compte déjà soumis à l'obligation n'est pas signalé : GLPI lui demandera de la configurer.
 *
 * Jamais corrigé automatiquement : imposer la 2FA change la connexion de vraies personnes. La
 * correction est proposée dans l'assistant (étape Réglages généraux) et l'administrateur choisit
 * les profils.
 */
final class PrivilegedAccountsWithout2faCheck implements AuditCheckInterface
{
    public function getKey(): string
    {
        return 'privileged_accounts_without_2fa';
    }

    public function getDomain(): string
    {
        return __('Double authentification', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        $names = [];
        foreach (TwoFactorBuilder::findPrivilegedUsersWithout2fa() as $user) {
            if (!$user['covered']) {
                $names[] = $user['name'];
            }
        }

        if ($names === []) {
            return [];
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_WARNING,
            title: sprintf(
                _n(
                    '%d compte à privilèges n\'a pas de double authentification',
                    '%d comptes à privilèges n\'ont pas de double authentification',
                    count($names),
                    'configurationglpiauto'
                ),
                count($names)
            ),
            description: ParkAudit::summarize($names),
            recommendation: __(
                'Imposez la double authentification native de GLPI aux profils de l\'interface standard (Super-Admin, Admin, Technicien) : dans l\'assistant, étape « Réglages généraux GLPI », bloc « Double authentification », ou dans l\'onglet « Sécurité » de chaque profil (case « Forcer 2FA »). Chaque personne configure son application à sa prochaine connexion.',
                'configurationglpiauto'
            ),
            fixable: false
        )];
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
