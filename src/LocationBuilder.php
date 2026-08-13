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
 * Turns on `locations_enabled` into a `Location` tree mirroring the entity tree the admin already
 * built in step 2 (`Config::getEntityTree()`, `EntityBuilder`) — one `Location` per entity node,
 * same name, same nesting, scoped to that entity (`entities_id` = the real entity's own ID, not
 * root+recursive like most of this plugin's other builders).
 *
 * Unlike `TaskCategoryBuilder`/`ManufacturerBuilder`, there's no universal "good practice" list of
 * location names to invent — a `Location` is real address/premises data (`glpi_locations` has
 * `address`/`postcode`/`town`/`country`/`building`/`room`/`latitude`/`longitude` columns),
 * inherently specific to each organization. What *is* universal is that whatever site/client
 * structure the admin already described as an entity tree almost always corresponds 1:1 to
 * physical locations too — so this builder connects the dots rather than asking the admin to
 * re-type the same site names a second time as locations. `entities_id`-scoped (not global) so an
 * MSP client only ever sees its own location, same data-isolation reasoning as
 * `RuleRightBuilder`/`SlaBuilder`.
 *
 * No-op on an empty entity tree (mono-entité) — same "nothing to scaffold without at least one
 * site" rule as `RuleRightBuilder`, no location name to invent out of thin air.
 */
class LocationBuilder
{
    /**
     * @param array<int, array{address?: string, postcode?: string, town?: string, country?: string, latitude?: string, longitude?: string}> $topLevelAddresses
     *        Real address data collected by the wizard's "Lieux" step (street-autocomplete +
     *        postcode→town assistant), keyed by the same top-level-entity index used everywhere
     *        else in the wizard (`entity_color_N`/`entity_logo_N`). Only ever applied to the
     *        top-level `Location` of each tree — a street address describes a site, not each
     *        internal department/service nested under it, so children never inherit it.
     * @return int Number of locations created/reused.
     */
    public function build(Config $config, array $topLevelAddresses = []): int
    {
        if (empty($config->fields['locations_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach ($config->getEntityTree() as $i => $node) {
            $count += $this->buildNode($node, 0, 0, $topLevelAddresses[$i] ?? []);
        }

        return $count;
    }

    /**
     * @param array{name: string, children: array} $node
     * @param array{address?: string, postcode?: string, town?: string, country?: string, latitude?: string, longitude?: string} $address
     */
    private function buildNode(array $node, int $parentEntityId, int $parentLocationId, array $address = []): int
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

        $location = new Location();
        $crit = ['name' => $name, 'locations_id' => $parentLocationId, 'entities_id' => $entityId];
        if (!$location->getFromDBByCrit($crit)) {
            $id = $location->add($crit + ['is_recursive' => 1] + $address);
            $location->getFromDB($id);
        } elseif ($address !== []) {
            // Re-run of the wizard on an already-scaffolded site: the admin may have just filled in
            // (or corrected) the address on a location created by an earlier pass without one —
            // same "latest input wins" behaviour as BrandingBuilder::applyPerClientColors().
            $location->update(['id' => $location->getID()] + $address);
        }
        $locationId = (int) $location->getID();
        $count = 1;

        foreach ($node['children'] ?? [] as $child) {
            $count += $this->buildNode($child, $entityId, $locationId);
        }

        return $count;
    }
}
