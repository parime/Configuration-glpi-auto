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

use GlpiPlugin\Configurationglpiauto\AssetsignTriggerBuilder;
use GlpiPlugin\Configurationglpiauto\Audit\AuditCheckInterface;
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\StateBuilder;

/**
 * Issue #293 : statuts recommandés absents (ex. « Détruit », créé par l'assistant seulement depuis
 * le 21/09/2026 — #276) et, si assetsign est actif, déclencheurs assetsign laissés vides alors que
 * leur statut existe. Corrigible en un clic : création des statuts manquants comme le fait
 * l'assistant, puis relais vers assetsign sans écraser un réglage existant.
 *
 * Silencieux quand aucun statut n'existe : NoStatesConfiguredCheck couvre déjà ce cas.
 */
final class MissingRecommendedStatesCheck implements AuditCheckInterface
{
    public function getKey(): string
    {
        return 'missing_recommended_states';
    }

    public function getDomain(): string
    {
        return __('Statuts des éléments', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        global $DB;
        if ((int) $DB->request(['COUNT' => 'c', 'FROM' => 'glpi_states'])->current()['c'] === 0) {
            return [];
        }

        $missing = StateBuilder::getMissingRecommendedNames();
        $unlinked = AssetsignTriggerBuilder::getLinkableEmptyTriggers();
        if ($missing === [] && $unlinked === []) {
            return [];
        }

        $parts = [];
        if ($missing !== []) {
            $parts[] = sprintf(
                __('Statuts recommandés absents : %s.', 'configurationglpiauto'),
                implode(', ', $missing)
            );
        }
        if ($unlinked !== [] || ($missing !== [] && AssetsignTriggerBuilder::isThirdPartyPluginActive())) {
            $parts[] = __(
                'Le plugin assetsign ne peut pas déclencher toutes ses fiches (attribution, restitution, don, vente, destruction...) sans ces statuts.',
                'configurationglpiauto'
            );
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_WARNING,
            title: __('Des statuts recommandés manquent', 'configurationglpiauto'),
            description: implode(' ', $parts),
            recommendation: __(
                'Corrigez en un clic : les statuts manquants sont créés comme le fait l\'assistant, puis reliés aux déclencheurs d\'assetsign encore vides (un réglage existant n\'est jamais remplacé).',
                'configurationglpiauto'
            ),
            fixable: true
        )];
    }

    public function canFix(): bool
    {
        return true;
    }

    public function fix(): void
    {
        StateBuilder::createMissingRecommended(!empty(Config::getConfig()->fields['state_icons_enabled']));
        AssetsignTriggerBuilder::linkEmptyTriggers();
    }
}
