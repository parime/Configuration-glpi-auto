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

/**
 * `glpi_calendars` — GLPI n'en crée aucun par défaut sur une instance fraîche. Sans au moins un
 * calendrier, les échéances SLA/OLA (heures ouvrées) ne peuvent pas être calculées correctement.
 * Jamais corrigée automatiquement : quels jours/horaires ouvrés créer dépend entièrement de
 * l'organisation, ce plugin ne peut pas les deviner (voir `CalendarBuilder`, qui les rend
 * configurables un par un plutôt que d'imposer un calendrier générique).
 */
final class NoActiveCalendarCheck implements AuditCheckInterface
{
    public function getKey(): string
    {
        return 'no_active_calendar';
    }

    public function getDomain(): string
    {
        return __('Calendriers', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        global $DB;

        $count = $DB->request(['COUNT' => 'c', 'FROM' => 'glpi_calendars'])->current()['c'];

        if ((int) $count > 0) {
            return [];
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_WARNING,
            title: __('Aucun calendrier n\'est configuré', 'configurationglpiauto'),
            description: __(
                'Les échéances SLA/OLA (heures ouvrées) ne peuvent pas être calculées sans calendrier.',
                'configurationglpiauto'
            ),
            recommendation: __(
                'Créez au moins un calendrier via l\'assistant de configuration ou manuellement.',
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
