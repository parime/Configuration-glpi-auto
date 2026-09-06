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

use DropdownTranslation;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\PlanningEventBuilder;
use PHPUnit\Framework\TestCase;
use Planning;
use PlanningEventCategory;
use PlanningExternalEventTemplate;

final class PlanningEventBuilderTest extends TestCase
{
    private const CATEGORY_NAME = 'Astreinte / Garde';

    private const TEMPLATE_NAME = 'Astreinte';

    private function buildConfig(bool $enabled, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'planning_events_enabled' => $enabled ? 1 : 0,
            'planning_events_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new PlanningEventBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildCreatesFiveCategoriesAndThreeTemplates(): void
    {
        $count = (new PlanningEventBuilder())->build($this->buildConfig(true));

        $this->assertSame(8, $count);

        $category = new PlanningEventCategory();
        $this->assertTrue($category->getFromDBByCrit(['name' => self::CATEGORY_NAME]));
        $this->assertSame('#e03131', $category->fields['color']);
    }

    /**
     * `background = 1` on "Astreinte" only — GLPI's own meaning is "renders as a busy-background
     * block rather than a discrete event", the correct on-call-coverage convention (see class
     * docblock). The real regression to guard is that this specific template gets it and the others
     * don't.
     */
    public function testAstreinteTemplateIsLinkedToItsCategoryAndUsesBackgroundRendering(): void
    {
        (new PlanningEventBuilder())->build($this->buildConfig(true));

        $category = new PlanningEventCategory();
        $category->getFromDBByCrit(['name' => self::CATEGORY_NAME]);

        $template = new PlanningExternalEventTemplate();
        $this->assertTrue($template->getFromDBByCrit(['name' => self::TEMPLATE_NAME]));
        $this->assertSame((int) $category->getID(), (int) $template->fields['planningeventcategories_id']);
        $this->assertSame(1, (int) $template->fields['background']);
        $this->assertSame(86400, (int) $template->fields['duration']);
        $this->assertSame(Planning::TODO, (int) $template->fields['state']);

        $meetingTemplate = new PlanningExternalEventTemplate();
        $this->assertTrue($meetingTemplate->getFromDBByCrit(['name' => "Réunion d'équipe"]));
        $this->assertSame(0, (int) $meetingTemplate->fields['background']);
    }

    public function testBuildIsIdempotentAndDoesNotDuplicate(): void
    {
        $builder = new PlanningEventBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(8, $second);

        global $DB;
        $this->assertSame(1, $DB->request(['FROM' => PlanningEventCategory::getTable(), 'WHERE' => ['name' => self::CATEGORY_NAME]])->count());
        $this->assertSame(1, $DB->request(['FROM' => PlanningExternalEventTemplate::getTable(), 'WHERE' => ['name' => self::TEMPLATE_NAME]])->count());
    }

    public function testTogglingIconsOnThenOffUpdatesTheTranslation(): void
    {
        $builder = new PlanningEventBuilder();
        $builder->build($this->buildConfig(true, icons: true));

        $category = new PlanningEventCategory();
        $category->getFromDBByCrit(['name' => self::CATEGORY_NAME]);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => PlanningEventCategory::class,
            'items_id' => $category->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]));
        $this->assertStringStartsWith('🚨', $translation->fields['value']);

        $builder->build($this->buildConfig(true, icons: false));

        $translation = new DropdownTranslation();
        $translation->getFromDBByCrit([
            'itemtype' => PlanningEventCategory::class,
            'items_id' => $category->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]);
        $this->assertSame(self::CATEGORY_NAME, $translation->fields['value']);
    }
}
