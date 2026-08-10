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
 * Turns a Config (entity_mode/entity_levels/level_labels/top_level_names) into real GLPI
 * Entities, matching exactly the shape shown in the live preview. mono-entité creates nothing
 * (the GLPI root entity already is the single entity). For the two multi-entité modes, the
 * *first* branching level — the MSP's client, or the same-company mode's first configured
 * level — is repeated once per name in top_level_names (e.g. real client names, or real site
 * names); the remaining configured levels are appended beneath each one as a template chain. If
 * top_level_names is empty (nothing decided yet), falls back to a single generic-named branch,
 * same behaviour as before this existed. The admin renames/duplicates the template chain's
 * entities afterward via GLPI's own Entity screens — this only has to get the *shape* right.
 */
class EntityBuilder
{
    private const DEFAULT_ROOT_ENTITY_ID = 0;

    /**
     * @return string[][] One array per created branch (root-relative, top name first), e.g.
     *                     [['Client A', 'Site', 'Service'], ['Client B', 'Site', 'Service']].
     */
    public function build(Config $config, int $rootEntityId = self::DEFAULT_ROOT_ENTITY_ID): array
    {
        $mode = $config->fields['entity_mode'] ?? Config::MODE_MONO;

        if ($mode === Config::MODE_MONO) {
            return [];
        }

        $labels = $config->getLevelLabels();
        $isMsp = $mode === Config::MODE_MULTI_MSP;

        // MSP: the client name is a level of its own, ABOVE the configured levels (all of
        // `$labels` still applies beneath each client). Same-company: the first configured
        // level IS the named entity, so only `$labels[1..]` remains to chain beneath it.
        $restLabels = $isMsp ? $labels : array_slice($labels, 1);
        $defaultTopName = $isMsp ? __('Client', 'configurationglpiauto') : ($labels[0] ?? __('Niveau 1', 'configurationglpiauto'));

        $topNames = $config->getTopLevelNames();
        if (empty($topNames)) {
            $topNames = [$defaultTopName];
        }

        $branches = [];
        foreach ($topNames as $topName) {
            $parentId = $rootEntityId;
            $branch = [];

            $parentId = $this->getOrCreateChild($parentId, $topName);
            $branch[] = $topName;

            foreach ($restLabels as $label) {
                $parentId = $this->getOrCreateChild($parentId, $label);
                $branch[] = $label;
            }

            $branches[] = $branch;
        }

        return $branches;
    }

    /**
     * Human-readable summary of what build() created/reused, e.g. "Client A > Site > Service ;
     * Client B > Site > Service" — for the "structure applied" confirmation message.
     */
    public static function describe(array $branches): string
    {
        return implode(' ; ', array_map(
            static fn (array $branch): string => implode(' > ', $branch),
            $branches
        ));
    }

    /**
     * Idempotent: re-running build() (e.g. after tweaking a label or adding a new client name)
     * never creates a duplicate sibling, it reuses the existing entity of that name under that
     * parent.
     */
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
