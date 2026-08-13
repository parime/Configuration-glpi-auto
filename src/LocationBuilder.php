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
use Location;

/**
 * Turns on `locations_enabled` into real `Location` rows — one per entity/sub-entity where the
 * admin actually filled in location data at the "Lieux" step, at *any* depth of the tree the admin
 * already built in step 2 (`Config::getEntityTree()`, `EntityBuilder`), not just the top-level
 * client/site nodes.
 *
 * Deliberately NOT a blind 1:1 mirror of the whole entity tree (an earlier version of this builder
 * did that): an internal department/service entity rarely has its own street address, and forcing
 * a `Location` row to exist for every single entity regardless produced a lieux list cluttered with
 * entries nobody meant to be physical places. A `Location` is only created where the admin entered
 * *something* for that specific node (any of the fields below) — every other entity in the tree
 * simply has no location, exactly like a fresh GLPI install.
 *
 * All of `glpi_locations`' user-fillable native columns are exposed, not just address/postcode/
 * town/country: `code`/`alias`/`comment` (free text), `state` (region, distinct from `country`),
 * `building`/`room`, and `latitude`/`longitude`/`altitude`. Nominatim can suggest address/postcode/
 * town/state/country/latitude/longitude; the rest (code, alias, comment, building, room, altitude —
 * no geocoding service returns elevation) are plain manual entry, same as on GLPI's own native
 * Location form.
 *
 * `entities_id`-scoped (not global) so an MSP client only ever sees its own location, same
 * data-isolation reasoning as `RuleRightBuilder`/`SlaBuilder`. Locations nest to mirror the tree
 * shape, but only among nodes that actually got a `Location` — a node with no data of its own never
 * becomes an empty placeholder level; its own children (if they have data) attach directly to the
 * nearest ancestor that does have a `Location`, or to the root (`locations_id = 0`) if none do.
 */
class LocationBuilder
{
    /**
     * @param array<string, array{address?: string, postcode?: string, town?: string, state?: string, country?: string, building?: string, room?: string, latitude?: string, longitude?: string, altitude?: string, code?: string, alias?: string, comment?: string}> $dataByPath
     *        Location data collected by the wizard's "Lieux" step, keyed by the node's own path in
     *        the entity tree (root-to-node child indices joined by `-`, e.g. `"1-0"` for the first
     *        child of the second top-level node) — the same path encoding the wizard's JS builds
     *        while walking `window.cgaTree`. A node with no entry in this array (or an empty one)
     *        gets no `Location` at all.
     * @return int Number of locations actually created/updated (i.e. nodes with real data — not a
     *             count of every entity in the tree).
     */
    public function build(Config $config, array $dataByPath = []): int
    {
        if (empty($config->fields['locations_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach ($config->getEntityTree() as $i => $node) {
            $count += $this->buildNode($node, 0, 0, (string) $i, $dataByPath);
        }

        return $count;
    }

    /**
     * @param array{name: string, children: array} $node
     * @param array<string, array<string, string>> $dataByPath
     */
    private function buildNode(array $node, int $parentEntityId, int $parentLocationId, string $path, array $dataByPath): int
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

        $data = $dataByPath[$path] ?? [];
        $count = 0;
        // A node with nothing filled in isn't meant to be a physical place — no Location for it,
        // and its children (if any of them do have data) attach to the nearest ancestor that does,
        // via $locationId staying at $parentLocationId unchanged below.
        $locationId = $parentLocationId;
        if ($data !== []) {
            $location = new Location();
            $crit = ['name' => $name, 'locations_id' => $parentLocationId, 'entities_id' => $entityId];
            if (!$location->getFromDBByCrit($crit)) {
                $id = $location->add($crit + ['is_recursive' => 1] + $data);
                $location->getFromDB($id);
            } else {
                // Re-run of the wizard on an already-scaffolded site: the admin may have just
                // filled in (or corrected) data on a location created by an earlier pass — same
                // "latest input wins" behaviour as BrandingBuilder::applyPerClientColors().
                $location->update(['id' => $location->getID()] + $data);
            }
            $locationId = (int) $location->getID();
            $count = 1;
        }

        foreach ($node['children'] ?? [] as $i => $child) {
            $count += $this->buildNode($child, $entityId, $locationId, $path . '-' . $i, $dataByPath);
        }

        return $count;
    }
}
