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

use GlpiPlugin\Configurationglpiauto\Compat\GlpiVersion;
use PHPUnit\Framework\TestCase;

/**
 * Raw POST answer of a `QuestionTypeItem` question, exactly as the browser sends it: GLPI 12 made
 * that question multi-select (`items_ids` array), GLPI 11 posts a single `items_id`. An answer in
 * the wrong shape is silently read as empty ("This field is mandatory").
 */
final class ItemAnswer
{
    /**
     * @return array{itemtype: string, items_id?: int, items_ids?: list<int>}
     */
    public static function of(string $itemtype, int $id): array
    {
        return GlpiVersion::isAtLeast12()
            ? ['itemtype' => $itemtype, 'items_ids' => [$id]]
            : ['itemtype' => $itemtype, 'items_id' => $id];
    }

    /**
     * GLPI 12.0.0-rc2 core bug: `AssociatedItemsFieldStrategy::isValidAnswer()` still expects the
     * GLPI 11 `items_id` key, so a `QuestionTypeItem` answer (now `items_ids`) is never linked to
     * the created ticket, whatever the strategy — the plugin's destination config is right, GLPI
     * drops it. Marks the test incomplete instead of failing while the link is missing on GLPI 12;
     * once GLPI fixes it the link exists and the caller's assertion is strict again.
     */
    public static function skipIfGlpi12DropsAssociatedItem(bool $linked): void
    {
        if (!$linked && GlpiVersion::isAtLeast12()) {
            TestCase::markTestIncomplete('GLPI 12 core bug: QuestionTypeItem answers (items_ids) are not linked by AssociatedItemsFieldStrategy, which still reads items_id.');
        }
    }
}
