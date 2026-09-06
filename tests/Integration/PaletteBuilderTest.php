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

use Config as GlpiConfig;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\PaletteBuilder;
use PHPUnit\Framework\TestCase;

/**
 * `PaletteBuilder` writes a real file under `GLPI_THEMES_DIR` and flips the instance-wide
 * `core.palette` config — both real, global, shared-instance side effects. `tearDown()` restores
 * the exact value this instance had before the test (confirmed empty — no custom palette was set
 * here previously) and removes the generated file, so this suite leaves no lasting visual change on
 * the shared dev instance for other tests or real usage.
 *
 * Regression-guards the real bug documented directly in the class's own docblock (found 2026-08-20):
 * unchecking the custom palette after a previous run must actively reset `core.palette` back to ''
 * (GLPI's own "no override" value), not just skip re-applying it and leave the old choice stuck.
 */
final class PaletteBuilderTest extends TestCase
{
    private const THEME_PATH = GLPI_THEMES_DIR . '/cga_custom.scss';

    protected function tearDown(): void
    {
        GlpiConfig::setConfigurationValues('core', ['palette' => '']);
        if (is_file(self::THEME_PATH)) {
            unlink(self::THEME_PATH);
        }
    }

    private function buildConfig(bool $customEnabled, string $color = '#206bc4', string $nativePalette = ''): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'custom_palette_enabled' => $customEnabled ? 1 : 0,
            'branding_primary_color' => $color,
            'native_palette' => $nativePalette,
        ]);

        return $config;
    }

    public function testCustomPaletteWritesAThemeFileAndSetsItAsTheInstanceDefault(): void
    {
        $applied = (new PaletteBuilder())->apply($this->buildConfig(true, '#dc3545'));

        $this->assertTrue($applied);
        $this->assertFileExists(self::THEME_PATH);
        $this->assertStringContainsString('--tblr-primary: #dc3545;', file_get_contents(self::THEME_PATH));

        $values = GlpiConfig::getConfigurationValues('core', ['palette']);
        $this->assertSame('cga_custom', $values['palette']);
    }

    public function testLightPrimaryColorGetsADarkForegroundAndDarkColorGetsALightForeground(): void
    {
        (new PaletteBuilder())->apply($this->buildConfig(true, '#ffffff'));
        $lightBg = file_get_contents(self::THEME_PATH);
        $this->assertStringContainsString('--tblr-primary-fg: #1e293b;', $lightBg, 'A near-white background needs a dark foreground to stay readable.');

        (new PaletteBuilder())->apply($this->buildConfig(true, '#000000'));
        $darkBg = file_get_contents(self::THEME_PATH);
        $this->assertStringContainsString('--tblr-primary-fg: #ffffff;', $darkBg, 'A near-black background needs a light foreground to stay readable.');
    }

    /**
     * Real regression guard (see class docblock, bug found 2026-08-20): disabling the custom
     * palette (and no native palette picked) must actively reset the instance-wide default, not
     * just leave whatever a previous run applied stuck in place.
     */
    public function testDisablingResetsTheInstanceDefaultAndDeletesTheThemeFile(): void
    {
        (new PaletteBuilder())->apply($this->buildConfig(true));
        $this->assertFileExists(self::THEME_PATH);

        $applied = (new PaletteBuilder())->apply($this->buildConfig(false));

        $this->assertFalse($applied);
        $this->assertFileDoesNotExist(self::THEME_PATH, 'The stale generated theme file must be removed once it is no longer the default.');

        $values = GlpiConfig::getConfigurationValues('core', ['palette']);
        $this->assertSame('', $values['palette'], 'core.palette must be actively reset to GLPI\'s own "no override" value.');
    }

    public function testDisablingCustomButPickingANativePaletteAppliesThatInstead(): void
    {
        $applied = (new PaletteBuilder())->apply($this->buildConfig(false, nativePalette: 'midnight'));

        $this->assertTrue($applied);
        $values = GlpiConfig::getConfigurationValues('core', ['palette']);
        $this->assertSame('midnight', $values['palette']);
    }
}
