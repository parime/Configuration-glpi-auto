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
use GlpiPlugin\Configurationglpiauto\RequestTypeTranslationBuilder;
use PHPUnit\Framework\TestCase;
use RequestType;

/**
 * Runs against a real GLPI instance — exercises the real `DropdownTranslation` add/update calls,
 * not just the builder's own return value.
 */
final class RequestTypeTranslationBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        global $DB;
        $DB->delete('glpi_dropdowntranslations', ['itemtype' => RequestType::class]);
        foreach ([1 => 'Helpdesk', 2 => 'E-Mail', 3 => 'Phone', 4 => 'Direct', 5 => 'Written', 6 => 'Other'] as $id => $name) {
            (new RequestType())->update(['id' => $id, 'name' => $name]);
        }
    }

    private function buildConfig(bool $translationsEnabled, bool $iconsEnabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'request_type_translations_enabled' => $translationsEnabled ? 1 : 0,
            'request_type_icons_enabled' => $iconsEnabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testDisabledReturnsZeroAndCreatesNothing(): void
    {
        $count = (new RequestTypeTranslationBuilder())->build($this->buildConfig(false, false));

        $this->assertSame(0, $count);
        global $DB;
        $this->assertSame(0, $DB->request(['FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => RequestType::class]])->count());
    }

    public function testEnabledWithoutIconsTranslatesAllSixTypes(): void
    {
        $count = (new RequestTypeTranslationBuilder())->build($this->buildConfig(true, false));

        $this->assertSame(6, $count);

        $type = new RequestType();
        $type->getFromDBByCrit(['name' => 'Helpdesk']);
        $this->assertSame('Helpdesk', $type->fields['name'], 'No icon requested: native name stays bare.');

        global $DB;
        $tr = $DB->request(['FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => RequestType::class, 'items_id' => $type->getID(), 'language' => 'fr_FR']])->current();
        $this->assertSame('Formulaire web', $tr['value']);
    }

    /**
     * Regression guard for a real bug found manually: `DropdownTranslation::update()` silently
     * does nothing (returns false, no exception) when the input omits itemtype/items_id/field/
     * language — `checkBeforeAddorUpdate()` re-derives those from $input, not from the loaded
     * item, so an id+value-only update() call (the usual CommonDBTM shape) is rejected. Confirmed
     * live before fixing: toggling icons on after a prior translation-only run left the old,
     * un-iconed value in place with no error anywhere. This test builds *twice* (translations
     * first, then icons) specifically to exercise the update path, not just add().
     */
    public function testTogglingIconsOnAfterAPriorRunActuallyUpdatesTheExistingTranslation(): void
    {
        $builder = new RequestTypeTranslationBuilder();
        $builder->build($this->buildConfig(true, false));
        $builder->build($this->buildConfig(true, true));

        $type = new RequestType();
        $type->getFromDBByCrit(['name' => ['Helpdesk', '🖥️ Helpdesk']]);
        $this->assertSame('🖥️ Helpdesk', $type->fields['name']);

        global $DB;
        $tr = $DB->request(['FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => RequestType::class, 'items_id' => $type->getID(), 'language' => 'fr_FR']])->current();
        $this->assertSame('🖥️ Formulaire web', $tr['value']);

        // And back off again — same update path, opposite direction.
        $builder->build($this->buildConfig(true, false));
        $type->getFromDB($type->getID());
        $this->assertSame('Helpdesk', $type->fields['name']);
        $tr2 = $DB->request(['FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => RequestType::class, 'items_id' => $type->getID(), 'language' => 'fr_FR']])->current();
        $this->assertSame('Formulaire web', $tr2['value']);
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateTranslations(): void
    {
        $builder = new RequestTypeTranslationBuilder();
        $config = $this->buildConfig(true, true);

        $builder->build($config);
        $builder->build($config);

        global $DB;
        $count = $DB->request(['COUNT' => 'c', 'FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => RequestType::class]])->current()['c'];
        $this->assertSame(24, $count, '6 types x 4 languages, no duplicates.');

        $count = $DB->request(['COUNT' => 'c', 'FROM' => 'glpi_requesttypes'])->current()['c'];
        $this->assertSame(6, $count, 'Never creates new RequestType rows.');
    }

    public function testSearchStillWorksWithIconsEnabled(): void
    {
        (new RequestTypeTranslationBuilder())->build($this->buildConfig(true, true));

        ob_start();
        \Search::show(RequestType::class);
        ob_end_clean();
        $this->addToAssertionCount(1);
    }
}
