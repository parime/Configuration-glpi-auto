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
use GlpiPlugin\Configurationglpiauto\WaitReasonBuilder;
use ITILFollowupTemplate;
use PendingReason;
use PHPUnit\Framework\TestCase;
use SolutionTemplate;

/**
 * Only "Attente de retour utilisateur" gets full automation (auto follow-up + auto-resolve) — the
 * real distinction this class's own docblock explains (auto-closing while waiting on a supplier or
 * an internal approval would be inappropriate). The core regression to guard is that this
 * distinction actually lands correctly in the DB (`followup_frequency`/`followups_before_resolution`
 * and which linked templates exist), not just that 4 rows get created.
 */
final class WaitReasonBuilderTest extends TestCase
{
    private const FULLY_AUTOMATED = 'Attente de retour utilisateur';

    private const MANUAL_ONLY = 'Attente livraison fournisseur';

    private const REMINDER_ONLY = 'Validation interne en attente';

    private function buildConfig(bool $enabled, bool $icons = false): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'wait_reasons_enabled' => $enabled ? 1 : 0,
            'wait_reason_icons_enabled' => $icons ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new WaitReasonBuilder())->build($this->buildConfig(false));

        $this->assertSame(0, $count);
    }

    public function testBuildCreatesExactlyFourReasons(): void
    {
        $count = (new WaitReasonBuilder())->build($this->buildConfig(true));

        $this->assertSame(4, $count);
    }

    public function testFullyAutomatedReasonHasFollowupAndAutoResolveWired(): void
    {
        (new WaitReasonBuilder())->build($this->buildConfig(true));

        $reason = new PendingReason();
        $this->assertTrue($reason->getFromDBByCrit(['name' => self::FULLY_AUTOMATED, 'entities_id' => 0]));
        $this->assertSame(2 * WEEK_TIMESTAMP, (int) $reason->fields['followup_frequency']);
        $this->assertSame(3, (int) $reason->fields['followups_before_resolution']);

        $followupId = (int) $reason->fields['itilfollowuptemplates_id'];
        $solutionId = (int) $reason->fields['solutiontemplates_id'];
        $this->assertGreaterThan(0, $followupId, 'Auto follow-up requires a linked follow-up template.');
        $this->assertGreaterThan(0, $solutionId, 'Auto-resolution requires a linked solution template.');

        $followup = new ITILFollowupTemplate();
        $this->assertTrue($followup->getFromDB($followupId));
        $this->assertSame((int) $reason->getID(), (int) $followup->fields['pendingreasons_id']);
    }

    public function testManualOnlyReasonHasNoAutomationAndNoLinkedTemplates(): void
    {
        (new WaitReasonBuilder())->build($this->buildConfig(true));

        $reason = new PendingReason();
        $this->assertTrue($reason->getFromDBByCrit(['name' => self::MANUAL_ONLY, 'entities_id' => 0]));
        $this->assertSame(0, (int) $reason->fields['followup_frequency']);
        $this->assertSame(0, (int) $reason->fields['followups_before_resolution']);
        $this->assertSame(0, (int) $reason->fields['itilfollowuptemplates_id']);
        $this->assertSame(0, (int) $reason->fields['solutiontemplates_id']);
    }

    public function testReminderOnlyReasonHasFollowupButNoAutoResolve(): void
    {
        (new WaitReasonBuilder())->build($this->buildConfig(true));

        $reason = new PendingReason();
        $this->assertTrue($reason->getFromDBByCrit(['name' => self::REMINDER_ONLY, 'entities_id' => 0]));
        $this->assertSame(WEEK_TIMESTAMP, (int) $reason->fields['followup_frequency']);
        $this->assertSame(0, (int) $reason->fields['followups_before_resolution'], 'Reminder-only: never auto-closed.');
        $this->assertGreaterThan(0, (int) $reason->fields['itilfollowuptemplates_id']);
        $this->assertSame(0, (int) $reason->fields['solutiontemplates_id'], 'No solution template: this reason never auto-resolves.');
    }

    public function testBuildIsIdempotentAndDoesNotDuplicateReasonsOrTemplates(): void
    {
        $builder = new WaitReasonBuilder();
        $builder->build($this->buildConfig(true));
        $reasonBefore = new PendingReason();
        $reasonBefore->getFromDBByCrit(['name' => self::FULLY_AUTOMATED, 'entities_id' => 0]);
        $idBefore = (int) $reasonBefore->getID();

        $second = $builder->build($this->buildConfig(true));
        $this->assertSame(4, $second);

        $reasonAfter = new PendingReason();
        $reasonAfter->getFromDBByCrit(['name' => self::FULLY_AUTOMATED, 'entities_id' => 0]);
        $this->assertSame($idBefore, (int) $reasonAfter->getID());

        global $DB;
        $this->assertSame(
            1,
            $DB->request(['FROM' => PendingReason::getTable(), 'WHERE' => ['name' => self::FULLY_AUTOMATED]])->count()
        );
        $this->assertSame(
            1,
            $DB->request(['FROM' => ITILFollowupTemplate::getTable(), 'WHERE' => ['name' => self::FULLY_AUTOMATED]])->count(),
            'The linked follow-up template must not be duplicated on a second run.'
        );
        $this->assertSame(
            1,
            $DB->request(['FROM' => SolutionTemplate::getTable(), 'WHERE' => ['name' => self::FULLY_AUTOMATED]])->count(),
            'The linked solution template must not be duplicated on a second run.'
        );
    }

    /**
     * Real regression: toggling icons must also refresh the two linked templates' own translations,
     * not just the `PendingReason` itself (see the class's own `getOrCreateReason()` comment).
     */
    public function testTogglingIconsOnThenOffUpdatesTranslationsOnReasonAndLinkedTemplates(): void
    {
        $builder = new WaitReasonBuilder();
        $builder->build($this->buildConfig(true, icons: true));

        $reason = new PendingReason();
        $reason->getFromDBByCrit(['name' => self::FULLY_AUTOMATED, 'entities_id' => 0]);

        foreach ([
            [PendingReason::class, (int) $reason->getID()],
            [ITILFollowupTemplate::class, (int) $reason->fields['itilfollowuptemplates_id']],
            [SolutionTemplate::class, (int) $reason->fields['solutiontemplates_id']],
        ] as [$itemtype, $id]) {
            $translation = new DropdownTranslation();
            $this->assertTrue($translation->getFromDBByCrit([
                'itemtype' => $itemtype, 'items_id' => $id, 'language' => 'fr_FR', 'field' => 'name',
            ]));
            $this->assertStringStartsWith('⏳', $translation->fields['value'], "$itemtype must carry the icon.");
        }

        $builder->build($this->buildConfig(true, icons: false));

        foreach ([
            [PendingReason::class, (int) $reason->getID()],
            [ITILFollowupTemplate::class, (int) $reason->fields['itilfollowuptemplates_id']],
            [SolutionTemplate::class, (int) $reason->fields['solutiontemplates_id']],
        ] as [$itemtype, $id]) {
            $translation = new DropdownTranslation();
            $translation->getFromDBByCrit(['itemtype' => $itemtype, 'items_id' => $id, 'language' => 'fr_FR', 'field' => 'name']);
            $this->assertSame(self::FULLY_AUTOMATED, $translation->fields['value'], "$itemtype's icon must be stripped back off.");
        }
    }
}
