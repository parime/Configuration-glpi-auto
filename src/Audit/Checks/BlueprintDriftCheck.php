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
use GlpiPlugin\Configurationglpiauto\Audit\BlueprintDrift;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\FrontUrl;

/**
 * Issue #468 : SLA, calendrier, catégories ou statuts modifiés à la main depuis le dernier Blueprint
 * appliqué. Jamais corrigé d'office : le détail s'affiche sur la page d'audit, et la réapplication
 * passe par l'écran de déploiement, après relecture.
 */
final class BlueprintDriftCheck implements AuditCheckInterface
{
    public function getKey(): string
    {
        return 'blueprint_drift';
    }

    public function getDomain(): string
    {
        return __('Blueprint', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        if (!BlueprintDrift::wasApplied()) {
            return [];
        }
        $drift = BlueprintDrift::detect(Config::getConfig());
        if ($drift === []) {
            return [];
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_WARNING,
            title: __('La configuration s\'écarte du dernier Blueprint appliqué', 'configurationglpiauto'),
            description: sprintf(
                _n(
                    '%d écart détecté (SLA, calendrier, catégories, statuts ou règles modifiés à la main).',
                    '%d écarts détectés (SLA, calendrier, catégories, statuts ou règles modifiés à la main).',
                    count($drift),
                    'configurationglpiauto'
                ),
                count($drift)
            ),
            recommendation: __(
                'Relisez les écarts plus bas sur cette page : réappliquez le Blueprint si la modification n\'était pas voulue, sinon reportez la nouvelle valeur dans l\'assistant.',
                'configurationglpiauto'
            ),
            links: [
                __('Voir les écarts', 'configurationglpiauto') => FrontUrl::get('audit.php') . '#blueprint-drift',
            ]
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
