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

use Entity;
use GlpiPlugin\Configurationglpiauto\BrandingBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard for a real bug reported by the user: unchecking "Personnaliser la couleur
 * principale" (or "Ajouter un logo par entité") in the wizard never removed the CSS block a
 * previous run had already written into `glpi_entities.custom_css_code` — `apply()`/`applyLogos()`
 * were simply never called again once their toggle was off, so the color/logo stayed visually
 * applied forever. Same bug class already fixed on `PaletteBuilder` (v0.65.1) for `core.palette`,
 * fixed here the same way: actively strip the plugin's own marker-delimited block instead of just
 * skipping the write.
 */
final class BrandingBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        $entity = new Entity();
        $entity->update(['id' => 0, 'custom_css_code' => '']);
    }

    private function buildConfig(bool $brandingEnabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'branding_enabled' => $brandingEnabled ? 1 : 0,
            'branding_primary_color' => '#ff0000',
        ]);

        return $config;
    }

    public function testEnablingWritesAColorBlock(): void
    {
        (new BrandingBuilder())->apply($this->buildConfig(true), [0]);

        $entity = new Entity();
        $entity->getFromDB(0);
        $this->assertStringContainsString('configurationglpiauto:branding-color:start', (string) $entity->fields['custom_css_code']);
    }

    public function testDisablingAfterEnablingActivelyRemovesTheColorBlock(): void
    {
        $builder = new BrandingBuilder();
        $builder->apply($this->buildConfig(true), [0]);
        $builder->apply($this->buildConfig(false), [0]);

        $entity = new Entity();
        $entity->getFromDB(0);
        $this->assertStringNotContainsString('configurationglpiauto:branding-color', (string) $entity->fields['custom_css_code']);
    }

    public function testDisablingColorDoesNotTouchAnIndependentlyAppliedLogoBlock(): void
    {
        $builder = new BrandingBuilder();
        $builder->applyLogos([0 => 'data:image/png;base64,AAAA']);
        $builder->apply($this->buildConfig(true), [0]);
        $builder->apply($this->buildConfig(false), [0]);

        $entity = new Entity();
        $entity->getFromDB(0);
        $css = (string) $entity->fields['custom_css_code'];
        $this->assertStringNotContainsString('configurationglpiauto:branding-color', $css);
        $this->assertStringContainsString('configurationglpiauto:branding-logo:start', $css);
    }

    public function testRemoveLogosActivelyRemovesTheLogoBlock(): void
    {
        $builder = new BrandingBuilder();
        $builder->applyLogos([0 => 'data:image/png;base64,AAAA']);
        $removed = $builder->removeLogos([0]);

        $this->assertSame(1, $removed);

        $entity = new Entity();
        $entity->getFromDB(0);
        $this->assertStringNotContainsString('configurationglpiauto:branding-logo', (string) $entity->fields['custom_css_code']);
    }

    public function testRemoveLogosOnAnEntityWithNoLogoBlockIsANoOp(): void
    {
        $removed = (new BrandingBuilder())->removeLogos([0]);

        $this->assertSame(0, $removed);
    }
}
