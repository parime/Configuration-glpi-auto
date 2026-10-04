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

namespace GlpiPlugin\Configurationglpiauto\Tests\Unit;

use GlpiPlugin\Configurationglpiauto\AssetsignTriggerBuilder;
use GlpiPlugin\Configurationglpiauto\StateBuilder;
use PHPUnit\Framework\TestCase;

final class AssetsignTriggerBuilderTest extends TestCase
{
    private const STATE_IDS = ['Attribué' => 1, 'En stock' => 2, 'Obsolète' => 3, 'Donné' => 4,
        'Attente restitution' => 7, 'Vendu' => 14, 'Détruit' => 15];

    public function testEveryMappedStateIsOneTheWizardCanCreate(): void
    {
        foreach (AssetsignTriggerBuilder::TRIGGERS as $field => $stateName) {
            $this->assertContains($stateName, StateBuilder::getStateNames(), $field);
        }
    }

    public function testEmptyTriggersAreFilledWithTheMatchingState(): void
    {
        $emptyRow = array_fill_keys(array_keys(AssetsignTriggerBuilder::TRIGGERS), '[]');

        $plan = AssetsignTriggerBuilder::plan($emptyRow, self::STATE_IDS, array_keys(AssetsignTriggerBuilder::TRIGGERS));

        $this->assertSame(['state' => 'Attribué', 'state_id' => 1, 'action' => 'set'], $plan['handover_states']);
        $this->assertSame(['state' => 'Attente restitution', 'state_id' => 7, 'action' => 'set'], $plan['return_states']);
        $this->assertSame('set', $plan['destruction_states']['action']);
    }

    public function testAnAdminSettingIsNeverRewritten(): void
    {
        $row = ['handover_states' => '[9]', 'return_states' => '[]', 'donation_states' => null];

        $plan = AssetsignTriggerBuilder::plan($row, self::STATE_IDS, ['handover_states', 'return_states', 'donation_states']);

        $this->assertSame('kept', $plan['handover_states']['action']);
        $this->assertSame('set', $plan['return_states']['action']);
        $this->assertSame('set', $plan['donation_states']['action'], 'NULL (no value yet) counts as empty.');
    }

    public function testIdempotentSecondRunChangesNothing(): void
    {
        $fields = array_keys(AssetsignTriggerBuilder::TRIGGERS);
        $row = array_fill_keys($fields, '[]');

        foreach (AssetsignTriggerBuilder::plan($row, self::STATE_IDS, $fields) as $field => $step) {
            if ($step['action'] === AssetsignTriggerBuilder::ACTION_SET) {
                $row[$field] = json_encode([$step['state_id']]);
            }
        }
        $secondRun = AssetsignTriggerBuilder::plan($row, self::STATE_IDS, $fields);

        $this->assertSame(
            array_fill_keys($fields, 'kept'),
            array_map(static fn (array $step): string => $step['action'], $secondRun)
        );
    }

    public function testMissingStateAndUncheckedTriggerAreLeftAlone(): void
    {
        $row = ['handover_states' => '[]', 'vente_states' => '[]'];

        $plan = AssetsignTriggerBuilder::plan($row, ['Attribué' => 1], ['vente_states']);

        $this->assertArrayNotHasKey('handover_states', $plan, 'Unchecked in the wizard.');
        $this->assertSame(['state' => 'Vendu', 'state_id' => null, 'action' => 'missing'], $plan['vente_states']);
    }
}
