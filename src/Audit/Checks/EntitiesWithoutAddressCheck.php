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
use QueryExpression;

/**
 * `glpi_entities.address` — une entité sans adresse renseignée est un constat, jamais une donnée à
 * inventer à sa place (une fausse adresse serait pire que son absence, notamment pour des documents
 * générés ou des obligations réglementaires) : cette vérification n'est donc jamais corrigible
 * automatiquement (`canFix()` retourne toujours faux), contrairement à
 * `NotificationsDisabledCheck`.
 */
final class EntitiesWithoutAddressCheck implements AuditCheckInterface
{
    public function getKey(): string
    {
        return 'entities_without_address';
    }

    public function getDomain(): string
    {
        return __('Entités', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        global $DB;

        $names = [];
        foreach (
            $DB->request([
                'SELECT' => ['name'],
                'FROM'   => 'glpi_entities',
                'WHERE'  => [
                    new QueryExpression('COALESCE(address, \'\') = \'\''),
                ],
                'ORDER'  => 'name ASC',
            ]) as $row
        ) {
            $names[] = $row['name'];
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
                    '%d entité sans adresse renseignée',
                    '%d entités sans adresse renseignée',
                    count($names),
                    'configurationglpiauto'
                ),
                count($names)
            ),
            description: implode(', ', $names),
            recommendation: __(
                'Renseignez l\'adresse de chaque entité concernée, utile pour les documents générés et la conformité.',
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
