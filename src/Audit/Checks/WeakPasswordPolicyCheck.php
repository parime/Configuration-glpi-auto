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

use Config;
use GlpiPlugin\Configurationglpiauto\Audit\AuditCheckInterface;
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;

/**
 * `Config::getConfigurationValues('core', [...])` — les mêmes clés que GLPI cœur utilise lui-même
 * pour valider un nouveau mot de passe (voir `User::checkPassword()`). 8 caractères est retenu
 * comme plancher largement reconnu (ANSSI/NIST), pas une valeur propre à une organisation : cette
 * vérification ne signale que ce qui est *en dessous* de ce plancher, jamais une valeur exacte à
 * atteindre. Jamais corrigée automatiquement : relever ce seuil ou activer une exigence de
 * complexité est un choix de politique (impact immédiat sur tous les utilisateurs existants),
 * contrairement à `NotificationsDisabledCheck` où l'état correct ne fait aucun doute.
 */
final class WeakPasswordPolicyCheck implements AuditCheckInterface
{
    private const MINIMUM_RECOMMENDED_LENGTH = 8;

    public function getKey(): string
    {
        return 'weak_password_policy';
    }

    public function getDomain(): string
    {
        return __('Mots de passe', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        $values = Config::getConfigurationValues('core', [
            'password_min_length',
            'password_need_number',
            'password_need_letter',
            'password_need_caps',
            'password_need_symbol',
        ]);

        $issues = [];

        if ((int) ($values['password_min_length'] ?? 0) < self::MINIMUM_RECOMMENDED_LENGTH) {
            $issues[] = __('longueur minimale inférieure à 8 caractères', 'configurationglpiauto');
        }

        $hasComplexityRequirement = !empty($values['password_need_number'])
            || !empty($values['password_need_letter'])
            || !empty($values['password_need_caps'])
            || !empty($values['password_need_symbol']);

        if (!$hasComplexityRequirement) {
            $issues[] = __('aucune exigence de complexité (chiffre, lettre, majuscule ou symbole)', 'configurationglpiauto');
        }

        if ($issues === []) {
            return [];
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_WARNING,
            title: __('La politique de mot de passe est faible', 'configurationglpiauto'),
            description: implode(', ', $issues),
            recommendation: __(
                'Renforcez la politique de mot de passe dans la configuration générale de GLPI : au moins 8 caractères et une exigence de complexité.',
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
