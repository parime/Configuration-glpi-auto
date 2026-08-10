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
 * Turns a Config (entity_mode/entity_levels/level_labels) into real GLPI Entities, matching
 * exactly the shape shown in the live preview on the settings screen. Deliberately simple: one
 * template branch, not a bulk multi-client generator — mono-entité creates nothing (the GLPI
 * root entity already is the single entity), multi-entité (same company) creates one chain of
 * entities (one per configured level), and multi-entité (MSP) creates one "Client" placeholder
 * entity with that same chain nested under it. The admin renames/duplicates the result via
 * GLPI's own Entity screens afterward — this only has to get the *shape* right.
 */
class EntityBuilder
{
    private const DEFAULT_ROOT_ENTITY_ID = 0;

    /**
     * @return string[] Names of the entities that now exist along the built branch, root first.
     */
    public function build(Config $config, int $rootEntityId = self::DEFAULT_ROOT_ENTITY_ID): array
    {
        $mode = $config->fields['entity_mode'] ?? Config::MODE_MONO;

        if ($mode === Config::MODE_MONO) {
            return [];
        }

        $labels = $config->getLevelLabels();
        $parentId = $rootEntityId;
        $created = [];

        if ($mode === Config::MODE_MULTI_MSP) {
            $parentId = $this->getOrCreateChild($parentId, __('Client', 'configurationglpiauto'));
            $created[] = __('Client', 'configurationglpiauto');
        }

        foreach ($labels as $label) {
            $parentId = $this->getOrCreateChild($parentId, $label);
            $created[] = $label;
        }

        return $created;
    }

    /**
     * Idempotent: re-running build() (e.g. after tweaking a level's label) never creates a
     * duplicate sibling, it reuses the existing entity of that name under that parent.
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
