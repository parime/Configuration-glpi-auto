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
 * `glpi_slas` — aucun SLA défini signifie qu'aucun engagement de délai n'est suivi sur les
 * tickets, un des piliers ITIL de la gestion des incidents. Jamais corrigée automatiquement :
 * les délais cibles (temps de résolution, d'escalade N1-N3...) sont des engagements propres à
 * l'organisation, pas une valeur générique que ce plugin pourrait proposer à la place d'un humain
 * (voir `SlaBuilder`, qui les rend entièrement paramétrables plutôt que d'imposer des délais).
 */
final class NoSlaConfiguredCheck implements AuditCheckInterface
{
    public function getKey(): string
    {
        return 'no_sla_configured';
    }

    public function getDomain(): string
    {
        return __('SLA', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        global $DB;

        $count = $DB->request(['COUNT' => 'c', 'FROM' => 'glpi_slas'])->current()['c'];

        if ((int) $count > 0) {
            return [];
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_INFO,
            title: __('Aucun SLA n\'est configuré', 'configurationglpiauto'),
            description: __(
                'Aucun engagement de délai (temps de résolution, escalade...) n\'est suivi sur les tickets.',
                'configurationglpiauto'
            ),
            recommendation: __(
                'Définissez au moins un SLA via l\'assistant de configuration si des engagements de délai s\'appliquent.',
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
