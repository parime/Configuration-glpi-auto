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

namespace GlpiPlugin\Configurationglpiauto;

use Entity;
use Rule;
use RuleAction;
use RuleCollection;
use RuleCriteria;
use RuleImportEntity;
use RuleImportEntityCollection;
use Session;

/**
 * Issue #307 : rattacher chaque machine inventoriée à la bonne entité, d'après sa plage d'adresses
 * IP ou le tag de son agent (Administration > Règles > Règles d'affectation d'un élément à une
 * entité). En multi-entité, c'est la configuration la plus pénible à faire à la main.
 *
 * Une règle par entité, critères en OU : « Adresse IP est dans le réseau » (CIDR) pour chaque
 * plage saisie, « Tag d'inventaire est » pour le tag ; action « Entité ». GLPI s'arrête à la
 * première règle qui correspond : les règles de l'assistant sont placées avant celles de
 * l'administrateur, plus générales en général. Une machine qui ne correspond à aucune règle reste
 * dans l'entité par défaut ; une machine déjà rattachée n'est jamais déplacée par l'assistant
 * (les règles ne jouent qu'aux prochains inventaires).
 */
class EntityInventoryRuleBuilder
{
    public const RULE_PREFIX = 'Entité — inventaire';

    /**
     * Plages IPv4 valides d'une saisie libre (« 192.168.10.0/24, 10.1.0.0/16 »).
     *
     * @return list<string>
     */
    public static function parseRanges(string $value): array
    {
        $ranges = [];
        foreach (preg_split('/[\s,;]+/', $value) ?: [] as $range) {
            if (!preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})/(\d{1,2})$#', $range, $m)) {
                continue;
            }
            if (max((int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]) > 255 || (int) $m[5] > 32) {
                continue;
            }
            $ranges[] = $range;
        }

        return array_values(array_unique($ranges));
    }

    /**
     * @param array<int|string, array{ranges: list<string>, tag: string}> $byPath chemin d'entité
     *        (même encodage que LocationBuilder / EntityAddressBuilder) => plages et tag
     * @return int Number of rules created/updated.
     */
    public function build(Config $config, array $byPath): int
    {
        if (empty($config->fields['entity_inventory_rules_enabled']) || $byPath === []) {
            return 0;
        }

        $count = 0;
        foreach ($config->getEntityTree() as $i => $node) {
            $count += $this->buildNode($node, 0, (string) $i, $byPath);
        }

        return $count;
    }

    /**
     * @param array{name?: string, children?: array} $node
     * @param array<int|string, array{ranges: list<string>, tag: string}> $byPath
     */
    private function buildNode(array $node, int $parentEntityId, string $path, array $byPath): int
    {
        $name = (string) ($node['name'] ?? '');
        if ($name === '') {
            return 0;
        }
        $entity = new Entity();
        if (!Lookup::loadFirst($entity, ['name' => $name, 'entities_id' => $parentEntityId])) {
            return 0;
        }
        $entityId = (int) $entity->getID();

        $count = 0;
        $data = $byPath[$path] ?? ['ranges' => [], 'tag' => ''];
        // Resolved by name: an entity of the same name may already exist outside the operator's
        // own scope — never route inventoried machines into an entity they cannot access.
        if (($data['ranges'] !== [] || $data['tag'] !== '') && Session::haveAccessToEntity($entityId)) {
            $this->ensureRule($entityId, (string) $entity->fields['completename'], $data['ranges'], $data['tag']);
            $count = 1;
        }

        foreach ($node['children'] ?? [] as $i => $child) {
            $count += $this->buildNode($child, $entityId, $path . '-' . $i, $byPath);
        }

        return $count;
    }

    /**
     * @param list<string> $ranges
     */
    public function ensureRule(int $entityId, string $entityName, array $ranges, string $tag): int
    {
        $ruleName = sprintf('%s « %s »', self::RULE_PREFIX, $entityName);
        $rule = new Rule();
        if (!Lookup::loadFirst($rule, ['name' => $ruleName, 'sub_type' => RuleImportEntity::class])) {
            $rulesId = (int) $rule->add([
                'name' => $ruleName,
                'sub_type' => RuleImportEntity::class,
                'match' => Rule::OR_MATCHING,
                'is_active' => 1,
                'entities_id' => 0,
                'is_recursive' => 1,
            ]);
            if ($rulesId <= 0) {
                return 0;
            }
            (new RuleAction())->add(['rules_id' => $rulesId, 'action_type' => 'assign', 'field' => 'entities_id', 'value' => $entityId]);
            $this->moveBeforeAdministratorRules($rulesId);
        } else {
            $rulesId = (int) $rule->getID();
        }

        $existing = [];
        foreach ((new RuleCriteria())->find(['rules_id' => $rulesId]) as $row) {
            $existing[$row['criteria'] . "\0" . $row['condition'] . "\0" . mb_strtolower((string) $row['pattern'])] = true;
        }
        $wanted = array_map(static fn (string $range): array => ['ip', Rule::PATTERN_CIDR, $range], $ranges);
        if ($tag !== '') {
            $wanted[] = ['tag', Rule::PATTERN_IS, $tag];
        }
        foreach ($wanted as [$criteria, $condition, $pattern]) {
            if (isset($existing[$criteria . "\0" . $condition . "\0" . mb_strtolower($pattern)])) {
                continue;
            }
            (new RuleCriteria())->add(['rules_id' => $rulesId, 'criteria' => $criteria, 'condition' => $condition, 'pattern' => $pattern]);
        }

        return $rulesId;
    }

    /** Place une nouvelle règle avant la première règle d'entité qui n'est pas de l'assistant. */
    private function moveBeforeAdministratorRules(int $rulesId): void
    {
        global $DB;

        $first = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => Rule::getTable(),
            'WHERE'  => [
                'sub_type' => RuleImportEntity::class,
                'NOT'      => ['name' => ['LIKE', self::RULE_PREFIX . '%']],
            ],
            'ORDER'  => 'ranking ASC',
            'LIMIT'  => 1,
        ])->current();
        if ($first !== null) {
            (new RuleImportEntityCollection())->moveRule($rulesId, (int) $first['id'], RuleCollection::MOVE_BEFORE);
        }
    }
}
