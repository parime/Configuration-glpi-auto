<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Parime
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

/**
 * Turns a Config's entity_tree (an arbitrary tree of named nodes — see
 * Config::getEntityTree()) into real GLPI Entities, exactly matching whatever shape the admin
 * built in the live preview: any node can have any number of children, at any depth, unrelated
 * to its siblings (e.g. "Client A" has 6 children, one of which has 3 children of its own, while
 * "Client B" has none). An empty tree (mono-entité, or nothing built yet) creates nothing.
 * Idempotent: reuses an existing entity of the same name under the same parent instead of
 * duplicating, so re-running after editing the tree only creates what's actually new.
 */
class EntityBuilder
{
    private const DEFAULT_ROOT_ENTITY_ID = 0;

    /**
     * @return array<int, array{name: string, entities_id: int, count: int}> One entry per
     *         top-level node, `entities_id` is that node's own entity, `count` is how many
     *         descendant entities were created/reused beneath it.
     */
    public function build(Config $config, int $rootEntityId = self::DEFAULT_ROOT_ENTITY_ID): array
    {
        $results = [];

        foreach ($config->getEntityTree() as $node) {
            $name = (string) ($node['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $topEntityId = $this->getOrCreateChild($rootEntityId, $name);
            $count = $this->createChildren($topEntityId, is_array($node['children'] ?? null) ? $node['children'] : []);

            $results[] = ['name' => $name, 'entities_id' => $topEntityId, 'count' => $count];
        }

        return $results;
    }

    /**
     * Human-readable summary of what build() created/reused, e.g. "Client A (9 sous-entités) ;
     * Client B" — for the "structure applied" confirmation message. Deliberately not translated
     * (plain string, no __()/_n()) so it stays a pure function testable without a GLPI bootstrap
     * — see EntityBuilderTest.
     *
     * @param array<int, array{name: string, entities_id: int, count: int}> $results
     */
    public static function describe(array $results): string
    {
        return implode(' ; ', array_map(
            static fn (array $r): string => $r['count'] > 0
                ? sprintf('%s (%d sous-entité%s)', $r['name'], $r['count'], $r['count'] > 1 ? 's' : '')
                : $r['name'],
            $results
        ));
    }

    /**
     * Top-level entity IDs — the ones to hang a calendar/SLA/branding setting off, since
     * sub-entities inherit from their parent by default.
     *
     * @param array<int, array{name: string, entities_id: int, count: int}> $results
     * @return int[]
     */
    public static function topEntityIds(array $results): array
    {
        return array_map(static fn (array $r): int => $r['entities_id'], $results);
    }

    /**
     * @return int Number of descendant entities created/reused under $parentId.
     */
    private function createChildren(int $parentId, array $nodes): int
    {
        $count = 0;

        foreach ($nodes as $node) {
            $name = (string) ($node['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $childId = $this->getOrCreateChild($parentId, $name);
            $count++;
            $count += $this->createChildren($childId, is_array($node['children'] ?? null) ? $node['children'] : []);
        }

        return $count;
    }

    private function getOrCreateChild(int $parentId, string $name): int
    {
        $entity = new Entity();
        if ($entity->getFromDBByCrit(['name' => $name, 'entities_id' => $parentId])) {
            return (int) $entity->getID();
        }

        $id = $entity->add([
            'name' => $name,
            'entities_id' => $parentId,
        ]);

        return (int) $id;
    }
}
