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
use GlpiPlugin\Configurationglpiauto\FollowupLibraryBuilder;
use ITILFollowupTemplate;
use PHPUnit\Framework\TestCase;

final class FollowupLibraryBuilderTest extends TestCase
{
    private const NAME = 'Relance — informations complémentaires demandées';

    private function buildConfig(bool $enabled, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'followup_library_enabled' => $enabled ? 1 : 0,
            'followup_library_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new FollowupLibraryBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildEnsuresAllFiveTemplatesExist(): void
    {
        $count = (new FollowupLibraryBuilder())->build($this->buildConfig(true));

        $this->assertSame(5, $count);

        $template = new ITILFollowupTemplate();
        $this->assertTrue($template->getFromDBByCrit(['name' => self::NAME]));
        $this->assertStringContainsString("Bonjour {{ requesters|first.fullname }}", $template->fields['content']);
    }

    public function testBuildIsIdempotentAndDoesNotDuplicate(): void
    {
        $builder = new FollowupLibraryBuilder();
        $builder->build($this->buildConfig(true));
        $second = $builder->build($this->buildConfig(true));

        $this->assertSame(5, $second);

        global $DB;
        $this->assertSame(1, $DB->request(['FROM' => ITILFollowupTemplate::getTable(), 'WHERE' => ['name' => self::NAME]])->count());
    }

    /**
     * `Translations::applyContent()` — the same distinct-from-`applyIcon()` mechanism already
     * regression-tested on `TaskTemplateBuilder`, used here for the full multilingual message text.
     */
    public function testAppliesContentTranslationsForEveryLanguage(): void
    {
        (new FollowupLibraryBuilder())->build($this->buildConfig(true));

        $template = new ITILFollowupTemplate();
        $template->getFromDBByCrit(['name' => self::NAME]);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => ITILFollowupTemplate::class,
            'items_id' => $template->getID(),
            'language' => 'en_GB',
            'field' => 'content',
        ]));
        $this->assertStringContainsString('We need additional information', $translation->fields['value']);
    }

    public function testTogglingIconsOnThenOffUpdatesTheNameTranslation(): void
    {
        $builder = new FollowupLibraryBuilder();
        $builder->build($this->buildConfig(true, icons: true));

        $template = new ITILFollowupTemplate();
        $template->getFromDBByCrit(['name' => self::NAME]);

        $translation = new DropdownTranslation();
        $this->assertTrue($translation->getFromDBByCrit([
            'itemtype' => ITILFollowupTemplate::class,
            'items_id' => $template->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]));
        $this->assertStringStartsWith('❓', $translation->fields['value']);

        $builder->build($this->buildConfig(true, icons: false));

        $translation = new DropdownTranslation();
        $translation->getFromDBByCrit([
            'itemtype' => ITILFollowupTemplate::class,
            'items_id' => $template->getID(),
            'language' => 'fr_FR',
            'field' => 'name',
        ]);
        $this->assertSame(self::NAME, $translation->fields['value']);
    }
}
