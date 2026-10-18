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
use RuleSoftwareCategoryCollection;
use Software;

/**
 * Issue #304 : logiciels déjà en base, sans catégorie, que les règles « Catégories de logiciels »
 * savent classer (créés avant les règles, ou par un import). Correction : la catégorie trouvée
 * par les règles est appliquée — et rien d'autre ; un logiciel qui a déjà une catégorie n'est
 * jamais touché.
 */
final class UncategorizedSoftwareCheck implements AuditCheckInterface
{
    public function getKey(): string
    {
        return 'uncategorized_software';
    }

    public function getDomain(): string
    {
        return __('Parc', 'configurationglpiauto');
    }

    /**
     * @return list<array{id: int, name: string, category: int}>
     */
    public static function findClassifiable(): array
    {
        global $DB;

        $rules = new RuleSoftwareCategoryCollection();
        $found = [];
        $rows = $DB->request([
            'SELECT' => ['id', 'name', 'manufacturers_id', 'comment'],
            'FROM'   => Software::getTable(),
            'WHERE'  => ['softwarecategories_id' => 0, 'is_deleted' => 0, 'is_template' => 0],
            'ORDER'  => 'name',
        ]);
        foreach ($rows as $row) {
            $result = $rules->processAllRules([], [], [
                'name'             => (string) $row['name'],
                'manufacturers_id' => (int) $row['manufacturers_id'],
                'comment'          => (string) ($row['comment'] ?? ''),
            ]);
            if ((int) ($result['softwarecategories_id'] ?? 0) > 0) {
                $found[] = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'category' => (int) $result['softwarecategories_id']];
            }
        }

        return $found;
    }

    public function analyze(): array
    {
        $found = self::findClassifiable();
        if ($found === []) {
            return [];
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_INFO,
            title: sprintf(_n('%d logiciel sans catégorie peut être classé', '%d logiciels sans catégorie peuvent être classés', count($found), 'configurationglpiauto'), count($found)),
            description: ParkAudit::summarize(array_column($found, 'name')),
            recommendation: __(
                'Les règles « Catégories de logiciels » de l\'assistant savent les classer (navigateurs, bureautique, sécurité...). « Classer » leur applique la catégorie trouvée, sans toucher aux logiciels qui en ont déjà une.',
                'configurationglpiauto'
            ),
            fixable: true,
            fixLabel: __('Classer', 'configurationglpiauto')
        )];
    }

    public function canFix(): bool
    {
        return true;
    }

    public function fix(): void
    {
        $software = new Software();
        foreach (self::findClassifiable() as $item) {
            $software->update(['id' => $item['id'], 'softwarecategories_id' => $item['category']]);
        }
    }
}
