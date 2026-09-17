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
 * `glpi_states` ("Statuts des éléments") — GLPI n'en crée aucun par défaut (confirmé sur une
 * instance GLPI 11 fraîche, voir aussi le docblock de `StateBuilder`), pourtant chaque actif du
 * parc (`Computer`, `Monitor`...) porte un champ `states_id`. Une instance avec zéro statut ne peut
 * tout simplement pas exprimer "en stock"/"attribué"/"en panne" — pas une question de choix
 * d'organisation, contrairement à *quels* statuts créer (ce que `canFix()` refuse de deviner ici :
 * le catalogue proposé par `StateBuilder` est une liste de départ à valider par un humain, jamais
 * une injection automatique silencieuse par ce constat).
 */
final class NoStatesConfiguredCheck implements AuditCheckInterface
{
    public function getKey(): string
    {
        return 'no_states_configured';
    }

    public function getDomain(): string
    {
        return __('Statuts des éléments', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        global $DB;

        $count = $DB->request(['COUNT' => 'c', 'FROM' => 'glpi_states'])->current()['c'];

        if ((int) $count > 0) {
            return [];
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_WARNING,
            title: __('Aucun statut d\'élément n\'est défini', 'configurationglpiauto'),
            description: __(
                'Le parc ne peut pas exprimer "en stock", "attribué", "en panne"... sans au moins un statut.',
                'configurationglpiauto'
            ),
            recommendation: __(
                'Utilisez l\'assistant de configuration (étape Statuts) ou créez-en manuellement.',
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
