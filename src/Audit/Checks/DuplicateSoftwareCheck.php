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
use GlpiPlugin\Configurationglpiauto\Audit\ParkAudit;
use GlpiPlugin\Configurationglpiauto\SoftwareMerger;

/**
 * Issue #305 : logiciels en double déjà en base (même nom, même éditeur, même entité). La
 * description sert de prévisualisation : logiciels concernés et nombre d'installations qui seront
 * reportées. Correction : fusion (voir SoftwareMerger), aucune installation n'est perdue.
 */
final class DuplicateSoftwareCheck implements AuditCheckInterface
{
    public function getKey(): string
    {
        return 'duplicate_software';
    }

    public function getDomain(): string
    {
        return __('Parc', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        $groups = SoftwareMerger::findGroups();
        if ($groups === []) {
            return [];
        }
        $duplicates = array_sum(array_map(static fn (array $g): int => count($g['duplicates']), $groups));
        $installations = array_sum(array_column($groups, 'installations'));

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_WARNING,
            title: sprintf(_n('%d logiciel en double', '%d logiciels en double', $duplicates, 'configurationglpiauto'), $duplicates),
            description: ParkAudit::summarize(array_map(
                static fn (array $g): string => sprintf(
                    __('%1$s%2$s : %3$d fiche(s) en trop, %4$d installation(s) à reporter', 'configurationglpiauto'),
                    $g['keep']['name'],
                    $g['publisher'] !== '' ? ' (' . $g['publisher'] . ')' : '',
                    count($g['duplicates']),
                    $g['installations']
                ),
                $groups
            )),
            recommendation: sprintf(
                __('« Fusionner » garde la plus ancienne fiche de chaque logiciel et y reporte les versions, les %d installation(s), les licences, documents et tickets des doublons, puis supprime les doublons. Aucune installation n\'est perdue.', 'configurationglpiauto'),
                $installations
            ),
            fixable: true,
            fixLabel: __('Fusionner', 'configurationglpiauto')
        )];
    }

    public function canFix(): bool
    {
        return true;
    }

    public function fix(): void
    {
        SoftwareMerger::mergeAll();
    }
}
