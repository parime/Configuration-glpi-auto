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

use GlpiPlugin\Configurationglpiauto\Translations;
use PHPUnit\Framework\TestCase;
use State;

/**
 * Regression guard for a real bug found manually (2026-08-23) while building
 * `RequestTypeTranslationBuilder`'s own icon toggle: `applyIcon()`/`applyContent()` used to only
 * ever `add()` a `DropdownTranslation` row, never `update()` one that already existed — a name/icon
 * change between plugin versions (or the shared `MAP` itself changing) left the old value stuck
 * forever across every one of the ~20 builders that call this helper, with no error anywhere.
 */
final class TranslationsTest extends TestCase
{
    private ?int $stateId = null;

    protected function tearDown(): void
    {
        if ($this->stateId !== null) {
            global $DB;
            $DB->delete('glpi_dropdowntranslations', ['itemtype' => State::class, 'items_id' => $this->stateId]);
            (new State())->delete(['id' => $this->stateId], true);
        }
    }

    public function testApplyIconUpdatesAnExistingTranslationInsteadOfLeavingItStale(): void
    {
        $state = new State();
        $this->stateId = (int) $state->add(['name' => 'TestStateCGA_' . uniqid()]);

        Translations::applyIcon(State::class, $this->stateId, 'Attribué', '🔵');
        global $DB;
        $first = $DB->request(['FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => State::class, 'items_id' => $this->stateId, 'language' => 'fr_FR', 'field' => 'name']])->current();
        $this->assertSame('🔵 Attribué', $first['value']);

        // Second call with a different icon — must actually change the stored value, not
        // silently no-op (the exact bug: DropdownTranslation::update() rejects an id+value-only
        // input, needing itemtype/items_id/field/language resent alongside it).
        Translations::applyIcon(State::class, $this->stateId, 'Attribué', '🔴');
        $second = $DB->request(['FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => State::class, 'items_id' => $this->stateId, 'language' => 'fr_FR', 'field' => 'name']])->current();
        $this->assertSame('🔴 Attribué', $second['value']);

        // No duplicate row was created — same id, still exactly one 'name' row for this language
        // (State also gets its own GLPI-managed 'completename' translation row since it's a
        // CommonTreeDropdown — unrelated to this fix, deliberately excluded via the field filter).
        $this->assertSame($first['id'], $second['id']);
        $count = $DB->request(['COUNT' => 'c', 'FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => State::class, 'items_id' => $this->stateId, 'language' => 'fr_FR', 'field' => 'name']])->current()['c'];
        $this->assertSame(1, $count);
    }

    /**
     * Regression guard for #178: unchecking a builder's icon toggle after a prior run used to
     * leave the icon stuck forever, because every builder only ever called `applyIcon()` when its
     * toggle was *checked* — nothing ever ran to undo it when unchecked. The fix is this method's
     * own trim-to-empty-icon behaviour (already used for icon-less leaf nodes): every builder now
     * always calls `applyIcon()`, passing `''` instead of skipping the call when unchecked.
     */
    public function testApplyIconWithEmptyIconStripsAPreviouslyAppliedIcon(): void
    {
        $state = new State();
        $this->stateId = (int) $state->add(['name' => 'TestStateCGA_' . uniqid()]);

        Translations::applyIcon(State::class, $this->stateId, 'Attribué', '🔵');
        global $DB;
        $withIcon = $DB->request(['FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => State::class, 'items_id' => $this->stateId, 'language' => 'fr_FR', 'field' => 'name']])->current();
        $this->assertSame('🔵 Attribué', $withIcon['value']);

        Translations::applyIcon(State::class, $this->stateId, 'Attribué', '');
        $withoutIcon = $DB->request(['FROM' => 'glpi_dropdowntranslations', 'WHERE' => ['itemtype' => State::class, 'items_id' => $this->stateId, 'language' => 'fr_FR', 'field' => 'name']])->current();
        $this->assertSame('Attribué', $withoutIcon['value']);
        // Same row updated in place, not a new one added alongside the old.
        $this->assertSame($withIcon['id'], $withoutIcon['id']);
    }
}
