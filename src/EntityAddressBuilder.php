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

/**
 * Turns on `entity_native_address_enabled` into real values on `Entity`'s own native address
 * fields — a completely separate mechanism from `Location` (`LocationBuilder`), confirmed by
 * reading `glpi_entities`' own schema: `address`/`postcode`/`town`/`state`/`country`/`latitude`/
 * `longitude`/`altitude` (its own Leaflet map on the entity's "Adresse" tab, same map component
 * `LocationBuilder`-created locations already use) plus `phonenumber`/`fax`/`website`/`email`,
 * which `Location` has no equivalent of at all.
 *
 * Deliberately reuses the *same* address data the admin already typed in the "Lieux" step's
 * per-node panel (`LocationBuilder`'s own `$dataByPath`) rather than asking for it a second time —
 * an entity's own address and its top-level `Location`'s address are, in the overwhelming majority
 * of cases, the exact same physical place. Only `phonenumber`/`fax`/`website`/`email` are genuinely
 * new fields with no `Location` equivalent, collected separately.
 *
 * Independent resolution pass (its own `Entity::getFromDBByCrit()` walk over the same
 * `Config::getEntityTree()`, same path encoding as `LocationBuilder::buildNode()`) rather than
 * piggy-backing on `LocationBuilder`'s own tree walk — keeps each builder scoped to the one GLPI
 * object type it writes to, same "resolve what you need yourself" convention already used by
 * `TaskTemplateBuilder` resolving `TaskCategory` independently of `TaskCategoryBuilder`.
 */
class EntityAddressBuilder
{
    private const LOCATION_FIELDS = ['address', 'postcode', 'town', 'state', 'country', 'latitude', 'longitude', 'altitude'];

    /**
     * @param array<string, array<string, string>> $dataByPath Same shape/keys as
     *        `LocationBuilder::build()`'s own `$dataByPath` — only the fields also present on
     *        `Entity` (address/postcode/town/state/country/latitude/longitude/altitude) are used;
     *        `Location`-only fields (building, room, code, alias, comment) are ignored here.
     * @param array<string, array{phonenumber?: string, fax?: string, website?: string, email?: string}> $commsByPath
     *        Entity-only fields with no `Location` equivalent, keyed by the same path.
     * @return int Number of entities actually updated (i.e. with real data to apply — not a count
     *             of every entity in the tree).
     */
    public function build(Config $config, array $dataByPath = [], array $commsByPath = []): int
    {
        if (empty($config->fields['entity_native_address_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach ($config->getEntityTree() as $i => $node) {
            $count += $this->buildNode($node, 0, (string) $i, $dataByPath, $commsByPath);
        }

        return $count;
    }

    /**
     * @param array{name: string, children: array} $node
     * @param array<string, array<string, string>> $dataByPath
     * @param array<string, array<string, string>> $commsByPath
     */
    private function buildNode(array $node, int $parentEntityId, string $path, array $dataByPath, array $commsByPath): int
    {
        $name = (string) ($node['name'] ?? '');
        if ($name === '') {
            return 0;
        }

        $entity = new Entity();
        if (!$entity->getFromDBByCrit(['name' => $name, 'entities_id' => $parentEntityId])) {
            return 0;
        }
        $entityId = (int) $entity->getID();

        $fields = array_intersect_key($dataByPath[$path] ?? [], array_flip(self::LOCATION_FIELDS));
        $fields += $commsByPath[$path] ?? [];
        $count = 0;
        if ($fields !== []) {
            $entity->update(['id' => $entityId] + $fields);
            $count = 1;
        }

        foreach ($node['children'] ?? [] as $i => $child) {
            $count += $this->buildNode($child, $entityId, $path . '-' . $i, $dataByPath, $commsByPath);
        }

        return $count;
    }
}
