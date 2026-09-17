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
 * `admin_email` reste à sa valeur d'usine `admsys@localhost` sur une installation GLPI fraîche —
 * confirmé en conditions réelles contre une instance GLPI 11 réelle (jamais vide/NULL par défaut,
 * contrairement à ce qu'on pourrait supposer). Cette adresse sert aux notifications système et aux
 * réinitialisations de mot de passe : la laisser telle quelle signifie qu'aucun humain ne la reçoit
 * réellement. Jamais corrigée automatiquement : la vraie adresse est propre à l'organisation, ce
 * plugin ne peut pas la deviner.
 */
final class DefaultAdminEmailCheck implements AuditCheckInterface
{
    private const FACTORY_DEFAULT = 'admsys@localhost';

    public function getKey(): string
    {
        return 'default_admin_email';
    }

    public function getDomain(): string
    {
        return __('Configuration générale', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        $values = Config::getConfigurationValues('core', ['admin_email']);
        $current = trim((string) ($values['admin_email'] ?? ''));

        if ($current !== '' && $current !== self::FACTORY_DEFAULT) {
            return [];
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_WARNING,
            title: __('Adresse email d\'administration non configurée', 'configurationglpiauto'),
            description: $current === ''
                ? __('Aucune adresse email d\'administration n\'est renseignée.', 'configurationglpiauto')
                : sprintf(
                    __('L\'adresse email d\'administration est toujours celle par défaut (%s).', 'configurationglpiauto'),
                    self::FACTORY_DEFAULT
                ),
            recommendation: __(
                'Renseignez une véritable adresse email d\'administration dans la configuration générale de GLPI.',
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
