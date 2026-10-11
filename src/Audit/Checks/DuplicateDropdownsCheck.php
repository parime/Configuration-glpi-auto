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
use GlpiPlugin\Configurationglpiauto\DuplicateMerger;

/**
 * Issue #299 : fabricants / éditeurs, modèles, types ou systèmes d'exploitation en double, déjà
 * en base. Corrigible en un clic : chaque doublon est supprimé en reportant ses références sur
 * l'entrée gardée (voir DuplicateMerger).
 */
final class DuplicateDropdownsCheck implements AuditCheckInterface
{
    /** Nombre de groupes cités dans la description, le reste est résumé. */
    private const LISTED = 8;

    public function getKey(): string
    {
        return 'duplicate_dropdowns';
    }

    public function getDomain(): string
    {
        return __('Parc', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        $groups = DuplicateMerger::findGroups();
        if ($groups === []) {
            return [];
        }

        $lines = [];
        $total = 0;
        foreach ($groups as $group) {
            $total += count($group['duplicates']);
            if (count($lines) < self::LISTED) {
                $lines[] = sprintf(
                    '%s « %s » ← %s',
                    $group['itemtype']::getTypeName(1),
                    $group['rename_to'] ?? $group['keep']['name'],
                    implode(', ', array_map(static fn (array $d): string => '« ' . $d['name'] . ' »', $group['duplicates']))
                );
            }
        }
        if (count($groups) > self::LISTED) {
            $lines[] = sprintf(__('… et %d autre(s) groupe(s).', 'configurationglpiauto'), count($groups) - self::LISTED);
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_WARNING,
            title: sprintf(__('%d entrée(s) en double (fabricants, modèles, types...)', 'configurationglpiauto'), $total),
            description: implode(' ; ', $lines),
            recommendation: __(
                'Corrigez en un clic : pour chaque groupe, une seule entrée est gardée (le nom exact de la marque s\'il existe, sinon la plus ancienne) et tous les matériels, modèles et logiciels qui utilisaient les doublons sont reportés dessus, puis les doublons sont supprimés.',
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
        DuplicateMerger::mergeAll();
    }
}
