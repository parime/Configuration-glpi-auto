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
 * `Config::getConfigurationValues('core', ['use_notifications'])` — l'interrupteur général des
 * notifications GLPI. Désactivé, plus aucune alerte (nouveau ticket, réponse, rappel SLA...) ne
 * part, en silence — confirmé en conditions réelles contre une instance GLPI 11 réelle. Seule
 * vérification de ce lot avec une correction non ambigüe : il n'existe aucune organisation pour
 * laquelle "aucune notification ne part jamais" est l'état souhaité, contrairement aux autres
 * vérifications (adresse, SLA...) où le bon réglage dépend de choix propres à l'organisation.
 */
final class NotificationsDisabledCheck implements AuditCheckInterface
{
    public function getKey(): string
    {
        return 'notifications_disabled';
    }

    public function getDomain(): string
    {
        return __('Notifications', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        if ($this->isEnabled()) {
            return [];
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_CRITICAL,
            title: __('Les notifications sont désactivées', 'configurationglpiauto'),
            description: __(
                'Aucune notification (nouveau ticket, réponse, rappel...) n\'est envoyée par GLPI.',
                'configurationglpiauto'
            ),
            recommendation: __('Activez les notifications dans la configuration générale de GLPI.', 'configurationglpiauto'),
            fixable: true
        )];
    }

    public function canFix(): bool
    {
        return true;
    }

    public function fix(): void
    {
        Config::setConfigurationValues('core', ['use_notifications' => 1]);
    }

    private function isEnabled(): bool
    {
        $values = Config::getConfigurationValues('core', ['use_notifications']);

        return (string) ($values['use_notifications'] ?? '0') === '1';
    }
}
