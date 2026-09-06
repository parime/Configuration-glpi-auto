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
use GlpiPlugin\Configurationglpiauto\GeneralSettingsBuilder;
use GlpiPlugin\Configurationglpiauto\ValidationTemplateBuilder;
use ITILValidationTemplate;
use PHPUnit\Framework\TestCase;
use ValidationStep;

final class ValidationTemplateBuilderTest extends TestCase
{
    private const COMMITTEE_NAME = 'Validation comité';

    private const SIMPLE_NAME = 'Validation simple';

    /**
     * The "Validation comité (2/3)" `ValidationStep` is created by `GeneralSettingsBuilder`
     * (`committee_validation_enabled`), not by `ValidationTemplateBuilder` itself — it only looks
     * the step up by name if it happens to exist (see that class's own docblock). On a fresh install
     * (unlike this project's own long-lived shared dev instance, where the step already exists from
     * an earlier run) it genuinely doesn't exist yet, so this suite must build it itself.
     */
    protected function setUp(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['committee_validation_enabled' => 1]);
        (new GeneralSettingsBuilder())->apply($config);
    }

    private function buildConfig(bool $enabled, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'validation_templates_enabled' => $enabled ? 1 : 0,
            'validation_template_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new ValidationTemplateBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresAllFiveTemplatesExist(): void
    {
        $count = (new ValidationTemplateBuilder())->build($this->buildConfig(true));

        $this->assertSame(5, $count);
    }

    public function testCommitteeTemplateIsLinkedToTheCommitteeValidationStepWhenItExists(): void
    {
        (new ValidationTemplateBuilder())->build($this->buildConfig(true));

        $step = new ValidationStep();
        $this->assertTrue($step->getFromDBByCrit(['name' => 'Validation comité (2/3)']), 'Precondition: the committee step must exist on this instance.');

        $template = new ITILValidationTemplate();
        $template->getFromDBByCrit(['name' => self::COMMITTEE_NAME]);
        $this->assertSame((int) $step->getID(), (int) $template->fields['validationsteps_id']);
    }

    public function testNonCommitteeTemplateIsLeftOnTheDefaultStep(): void
    {
        (new ValidationTemplateBuilder())->build($this->buildConfig(true));

        $template = new ITILValidationTemplate();
        $template->getFromDBByCrit(['name' => self::SIMPLE_NAME]);
        $this->assertSame(0, (int) $template->fields['validationsteps_id']);
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateTemplates(): void
    {
        $builder = new ValidationTemplateBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(5, $second);

        global $DB;
        $count = $DB->request(['FROM' => ITILValidationTemplate::getTable(), 'WHERE' => ['name' => self::COMMITTEE_NAME]])->count();
        $this->assertSame(1, $count, 'Exactly one row must exist — no duplicate.');
    }

    public function testTogglingIconsOnThenOffUpdatesTheTranslation(): void
    {
        $builder = new ValidationTemplateBuilder();
        $builder->build($this->buildConfig(true, icons: true));

        $template = new ITILValidationTemplate();
        $template->getFromDBByCrit(['name' => self::SIMPLE_NAME]);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => ITILValidationTemplate::class,
            'items_id' => $template->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]));
        $this->assertStringStartsWith('✅', $translation->fields['value']);

        $builder->build($this->buildConfig(true, icons: false));

        $translation = new DropdownTranslation();
        $translation->getFromDBByCrit([
            'itemtype' => ITILValidationTemplate::class,
            'items_id' => $template->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]);
        $this->assertSame(self::SIMPLE_NAME, $translation->fields['value']);
    }
}
