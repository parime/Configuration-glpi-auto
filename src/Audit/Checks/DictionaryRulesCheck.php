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
use GlpiPlugin\Configurationglpiauto\DictionaryRules;
use Rule;
use RuleAction;
use SoftwareCategory;

/**
 * Issue #323 : règles de dictionnaire et de catégories de logiciels créées par l'assistant qui ne
 * s'appliquent plus. Quand une catégorie de logiciel est supprimée, GLPI désactive les règles qui
 * la visent : relancer l'assistant les répare, mais rien ne prévenait l'administrateur entre-temps.
 *
 * - orpheline (avertissement) : l'action vise une catégorie qui n'existe plus ;
 * - désactivée (information) : peut être volontaire, d'où l'absence de correction automatique.
 *
 * Les règles de GLPI et celles de l'administrateur ne sont pas jugées
 * (DictionaryRules::isPluginRule()).
 */
final class DictionaryRulesCheck implements AuditCheckInterface
{
    public function getKey(): string
    {
        return 'dictionary_rules';
    }

    public function getDomain(): string
    {
        return __('Parc', 'configurationglpiauto');
    }

    /**
     * @return array{orphan: list<array{id: int, name: string, sub_type: string}>, inactive: list<array{id: int, name: string, sub_type: string}>}
     */
    public static function findBrokenRules(): array
    {
        global $DB;

        $broken = ['orphan' => [], 'inactive' => []];
        $rows = $DB->request([
            'SELECT' => ['id', 'name', 'sub_type', 'is_active'],
            'FROM'   => Rule::getTable(),
            'WHERE'  => ['sub_type' => array_keys(DictionaryRules::PLUGIN_RULE_PREFIXES)],
            'ORDER'  => ['sub_type', 'ranking'],
        ]);
        foreach ($rows as $row) {
            if (!DictionaryRules::isPluginRule((string) $row['sub_type'], (string) $row['name'])) {
                continue;
            }
            $rule = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'sub_type' => (string) $row['sub_type']];
            if (self::targetsDeletedCategory($rule['id'])) {
                $broken['orphan'][] = $rule;
            } elseif ((int) $row['is_active'] === 0) {
                $broken['inactive'][] = $rule;
            }
        }

        return $broken;
    }

    private static function targetsDeletedCategory(int $rulesId): bool
    {
        foreach ((new RuleAction())->find(['rules_id' => $rulesId, 'field' => 'softwarecategories_id']) as $action) {
            $categoryId = (int) $action['value'];
            if ($categoryId > 0 && !(new SoftwareCategory())->getFromDB($categoryId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{id: int, name: string, sub_type: string}> $rules
     * @return array<string, string> nom de la règle => URL de sa fiche
     */
    private static function links(array $rules): array
    {
        $links = [];
        foreach (array_slice($rules, 0, ParkAudit::LISTED) as $rule) {
            $subType = $rule['sub_type'];
            $links[$rule['name']] = is_a($subType, Rule::class, true)
                ? $subType::getFormURLWithID($rule['id'])
                : Rule::getFormURLWithID($rule['id']);
        }

        return $links;
    }

    public function analyze(): array
    {
        $broken = self::findBrokenRules();
        $rerun = __(
            'Relancez l\'assistant (étape « Général & Outils », dictionnaires et catégories de logiciels) : il recrée les catégories manquantes et réactive les règles concernées. Aucune réactivation automatique ici.',
            'configurationglpiauto'
        );
        $findings = [];

        if ($broken['orphan'] !== []) {
            $count = count($broken['orphan']);
            $findings[] = new AuditFinding(
                checkKey: $this->getKey(),
                domain: $this->getDomain(),
                severity: AuditFinding::SEVERITY_WARNING,
                title: sprintf(_n(
                    '%d règle de catégorie de logiciel vise une catégorie supprimée',
                    '%d règles de catégorie de logiciel visent une catégorie supprimée',
                    $count,
                    'configurationglpiauto'
                ), $count),
                description: ParkAudit::summarize(array_column($broken['orphan'], 'name')),
                recommendation: $rerun,
                links: self::links($broken['orphan'])
            );
        }
        if ($broken['inactive'] !== []) {
            $count = count($broken['inactive']);
            $findings[] = new AuditFinding(
                checkKey: $this->getKey(),
                domain: $this->getDomain(),
                severity: AuditFinding::SEVERITY_INFO,
                title: sprintf(_n(
                    '%d règle de dictionnaire de l\'assistant est désactivée',
                    '%d règles de dictionnaire de l\'assistant sont désactivées',
                    $count,
                    'configurationglpiauto'
                ), $count),
                description: ParkAudit::summarize(array_column($broken['inactive'], 'name')),
                recommendation: __(
                    'Si ce n\'est pas volontaire (une suppression de catégorie, par exemple, désactive les règles qui la visent), réactivez-les depuis leur fiche ou relancez l\'assistant.',
                    'configurationglpiauto'
                ),
                links: self::links($broken['inactive'])
            );
        }

        return $findings;
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
