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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration;

use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ProjectTaxonomyBuilder;
use PHPUnit\Framework\TestCase;
use ProjectState;
use ProjectTaskType;
use ProjectType;

final class ProjectTaxonomyBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        $preview = ProjectTaxonomyBuilder::getPreview();
        foreach ($preview['project_types'] as $type) {
            $this->deleteIfExists(ProjectType::class, $type['name']);
        }
        foreach ($preview['task_types'] as $type) {
            $this->deleteIfExists(ProjectTaskType::class, $type['name']);
        }
        foreach ($preview['project_states'] as $state) {
            $this->deleteIfExists(ProjectState::class, $state['name']);
        }
    }

    private function deleteIfExists(string $itemtype, string $name): void
    {
        $item = new $itemtype();
        if ($item->getFromDBByCrit(['name' => $name])) {
            $item->delete(['id' => $item->getID()], true);
        }
    }

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'project_taxonomy_enabled' => $enabled ? 1 : 0,
            'project_taxonomy_icons_enabled' => 0,
        ]);

        return $config;
    }

    public function testDisabledReturnsZeroAndCreatesNothing(): void
    {
        $count = (new ProjectTaxonomyBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    /**
     * `ProjectState` is a real GLPI native table (3 rows shipped by default: New/Processing/
     * Closed) — this plugin adds 3 more (#155). Confirms the additions land with the right
     * color/is_finished, and that GLPI's 3 native rows are left untouched (found by name, never
     * re-created).
     */
    public function testCreatesExtraProjectStatesWithColorAndIsFinished(): void
    {
        (new ProjectTaxonomyBuilder())->build($this->buildConfig(true));

        $onHold = new ProjectState();
        $this->assertTrue($onHold->getFromDBByCrit(['name' => 'En pause']));
        $this->assertSame(0, (int) $onHold->fields['is_finished']);

        $cancelled = new ProjectState();
        $this->assertTrue($cancelled->getFromDBByCrit(['name' => 'Annulé']));
        $this->assertSame(1, (int) $cancelled->fields['is_finished']);
        $this->assertNotSame('', $cancelled->fields['color']);

        // Native rows still present, untouched.
        $native = new ProjectState();
        $this->assertTrue($native->getFromDBByCrit(['name' => 'Closed']));
    }

    public function testBuildIsIdempotent(): void
    {
        $builder = new ProjectTaxonomyBuilder();
        $config = $this->buildConfig(true);

        $first = $builder->build($config);
        $second = $builder->build($config);

        $this->assertSame($first, $second);

        global $DB;
        $count = $DB->request(['COUNT' => 'c', 'FROM' => 'glpi_projectstates', 'WHERE' => ['name' => 'Annulé']])->current()['c'];
        $this->assertSame(1, $count);
    }

    /**
     * Regression guard for #178: unchecking "Ajouter des icônes" and re-running the wizard used to
     * never remove icons already written on a prior run — `build()` skipped the `applyIcon()` call
     * entirely instead of calling it with an empty icon.
     */
    public function testTogglingIconsOffRemovesAPreviouslyAppliedIcon(): void
    {
        $builder = new ProjectTaxonomyBuilder();

        $withIcons = new Config();
        $withIcons->fields = array_merge(Config::getDefaults(), [
            'project_taxonomy_enabled' => 1,
            'project_taxonomy_icons_enabled' => 1,
        ]);
        $builder->build($withIcons);

        $type = new ProjectType();
        $type->getFromDBByCrit(['name' => 'Interne']);
        global $DB;
        $withIcon = $DB->request(['FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => ProjectType::class, 'items_id' => $type->getID(), 'language' => 'fr_FR', 'field' => 'name']])->current();
        $this->assertSame('🏠 Interne', $withIcon['value']);

        $withoutIcons = new Config();
        $withoutIcons->fields = array_merge(Config::getDefaults(), [
            'project_taxonomy_enabled' => 1,
            'project_taxonomy_icons_enabled' => 0,
        ]);
        $builder->build($withoutIcons);

        $withoutIcon = $DB->request(['FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => ProjectType::class, 'items_id' => $type->getID(), 'language' => 'fr_FR', 'field' => 'name']])->current();
        $this->assertSame('Interne', $withoutIcon['value']);
    }
}
