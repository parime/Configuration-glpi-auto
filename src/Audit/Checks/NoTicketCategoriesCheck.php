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
 * `glpi_itilcategories` — aucune catégorie de ticket signifie qu'aucun ticket ne peut être
 * classifié ni routé automatiquement vers la bonne équipe, un des piliers ITIL de la gestion des
 * incidents/demandes (au même titre que les statuts ou le SLA, déjà couverts par
 * `NoStatesConfiguredCheck`/`NoSlaConfiguredCheck`). Jamais corrigée automatiquement : le libellé
 * et l'arborescence des catégories dépendent entièrement de l'organisation (voir `CategoryBuilder`,
 * qui les rend entièrement paramétrables plutôt que d'imposer une liste).
 */
final class NoTicketCategoriesCheck implements AuditCheckInterface
{
    public function getKey(): string
    {
        return 'no_ticket_categories_configured';
    }

    public function getDomain(): string
    {
        return __('Catégories de tickets', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        global $DB;

        $count = $DB->request(['COUNT' => 'c', 'FROM' => 'glpi_itilcategories'])->current()['c'];

        if ((int) $count > 0) {
            return [];
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_INFO,
            title: __('Aucune catégorie de ticket n\'est configurée', 'configurationglpiauto'),
            description: __(
                'Les tickets ne peuvent pas être classifiés ni routés automatiquement vers la bonne équipe sans au moins une catégorie.',
                'configurationglpiauto'
            ),
            recommendation: __(
                'Utilisez l\'assistant de configuration (étape Catégories de tickets) ou créez-en manuellement.',
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
