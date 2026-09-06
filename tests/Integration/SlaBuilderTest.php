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
use GlpiPlugin\Configurationglpiauto\SlaBuilder;
use GlpiPlugin\Configurationglpiauto\SupportTierBuilder;
use OLA;
use OlaLevel;
use PHPUnit\Framework\TestCase;
use RuleTicket;
use SLA;
use SlaLevel;
use SLM;

/**
 * `SlaBuilder::build()` always targets the same fixed SLM name (`SlaBuilder::SLM_NAME`, "SLA
 * standard") — on this project's shared, long-lived dev instance that row already exists from
 * earlier real wizard runs, with whatever tier hours were configured *at the time* (idempotent
 * reuse never updates them — see `getOrCreateLevelAgreement()`). Asserting exact hour values
 * against that shared name would be asserting on leftover real data, not on this test's own setup.
 * So every value-sensitive assertion here goes through `buildFromOverride()` instead, with a
 * per-test unique client name (`uniqueClientName()`) — guaranteed to create a fresh SLM every time,
 * exactly like a real per-client SLA override would. `assignOne()`'s `RuleTicket` rows are also
 * matched against a unique fake entity id per test for the same reason (the shared instance already
 * has real rules for entity 0).
 */
final class SlaBuilderTest extends TestCase
{
    private const MAX_PRIORITY = 6;

    private function uniqueClientName(): string
    {
        return 'Test ' . uniqid();
    }

    private function uniqueEntityId(): int
    {
        return random_int(800000, 899999);
    }

    private function buildConfig(array $overrides = []): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['sla_enabled' => 1], $overrides);

        return $config;
    }

    private function sla(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'astreinte' => false,
            'tiers' => Config::getDefaultSlaTiers(),
            'ola_enabled' => false,
            'ola_tiers' => Config::getDefaultOlaTiers(),
        ], $overrides);
    }

    public function testBuildReturnsNullWhenDisabled(): void
    {
        $this->assertNull((new SlaBuilder())->build($this->buildConfig(['sla_enabled' => 0])));
    }

    public function testBuildUsesTheFixedSlmNameAndForcesNoCalendarForAstreinte(): void
    {
        (new SlaBuilder())->build($this->buildConfig(['sla_astreinte' => 1]), 999999);

        $slm = new SLM();
        $this->assertTrue($slm->getFromDBByCrit(['name' => 'SLA standard']));
        $this->assertSame(0, (int) $slm->fields['calendars_id']);
    }

    public function testBuildFromOverrideReturnsNullWhenDisabled(): void
    {
        $this->assertNull((new SlaBuilder())->buildFromOverride($this->uniqueClientName(), $this->sla(['enabled' => false])));
    }

    public function testBuildFromOverrideCreatesSlmWithOneTtoTtrPairPerPriority(): void
    {
        $clientName = $this->uniqueClientName();
        $result = (new SlaBuilder())->buildFromOverride($clientName, $this->sla());

        $this->assertNotNull($result);
        $this->assertSame(Config::PRIORITY_LEVELS, array_keys($result));

        $slm = new SLM();
        $this->assertTrue($slm->getFromDBByCrit(['name' => sprintf('SLA — %s', $clientName)]));

        // Priority 6 (Major) — DEFAULT_SLA_TIERS: tto_hours=1, ttr_hours=4.
        $tto = new SLA();
        $this->assertTrue($tto->getFromDB($result[self::MAX_PRIORITY]['tto']));
        $this->assertSame(1, (int) $tto->fields['number_time']);
        $this->assertSame('hour', $tto->fields['definition_time']);
        $this->assertSame((int) $slm->getID(), (int) $tto->fields['slms_id']);
        $this->assertSame(SLM::TTO, (int) $tto->fields['type']);

        $ttr = new SLA();
        $this->assertTrue($ttr->getFromDB($result[self::MAX_PRIORITY]['ttr']));
        $this->assertSame(4, (int) $ttr->fields['number_time']);
        $this->assertSame(SLM::TTR, (int) $ttr->fields['type']);

        $this->assertNull($result[self::MAX_PRIORITY]['ola_tto']);
        $this->assertNull($result[self::MAX_PRIORITY]['ola_ttr']);

        global $DB;
        $this->assertSame(count(Config::PRIORITY_LEVELS) * 2, $DB->request(['FROM' => SLA::getTable(), 'WHERE' => ['slms_id' => $slm->getID()]])->count());
    }

    public function testBuildFromOverrideCreatesOlaInTheSameSlmWhenEnabled(): void
    {
        $clientName = $this->uniqueClientName();
        $result = (new SlaBuilder())->buildFromOverride($clientName, $this->sla(['ola_enabled' => true]));

        // Priority 6 — DEFAULT_OLA_TIERS: tto_hours=1, ttr_hours=2.
        $olaTto = new OLA();
        $this->assertTrue($olaTto->getFromDB($result[self::MAX_PRIORITY]['ola_tto']));
        $this->assertSame(1, (int) $olaTto->fields['number_time']);

        $olaTtr = new OLA();
        $this->assertTrue($olaTtr->getFromDB($result[self::MAX_PRIORITY]['ola_ttr']));
        $this->assertSame(2, (int) $olaTtr->fields['number_time']);

        $slm = new SLM();
        $slm->getFromDBByCrit(['name' => sprintf('SLA — %s', $clientName)]);
        $this->assertSame((int) $slm->getID(), (int) $olaTtr->fields['slms_id'], 'OLA lives in the same SLM as the SLA — not a second container.');
    }

    public function testBuildFromOverrideAstreinteForcesNoCalendarEvenWhenOneIsPassed(): void
    {
        $clientName = $this->uniqueClientName();
        (new SlaBuilder())->buildFromOverride($clientName, $this->sla(['astreinte' => true]), 999999);

        $slm = new SLM();
        $slm->getFromDBByCrit(['name' => sprintf('SLA — %s', $clientName)]);
        $this->assertSame(0, (int) $slm->fields['calendars_id']);
    }

    public function testBuildFromOverrideIsIdempotentAndDoesNotDuplicateSlmOrLevels(): void
    {
        $builder = new SlaBuilder();
        $clientName = $this->uniqueClientName();
        $sla = $this->sla(['ola_enabled' => true]);

        $first = $builder->buildFromOverride($clientName, $sla);
        $second = $builder->buildFromOverride($clientName, $sla);

        $this->assertSame($first, $second);

        global $DB;
        $slmName = sprintf('SLA — %s', $clientName);
        $this->assertSame(1, $DB->request(['FROM' => SLM::getTable(), 'WHERE' => ['name' => $slmName]])->count());

        $slm = new SLM();
        $slm->getFromDBByCrit(['name' => $slmName]);
        $this->assertSame(count(Config::PRIORITY_LEVELS) * 2, $DB->request(['FROM' => SLA::getTable(), 'WHERE' => ['slms_id' => $slm->getID()]])->count(), 'TTO + TTR per priority — no duplicate on the second run.');
        $this->assertSame(count(Config::PRIORITY_LEVELS) * 2, $DB->request(['FROM' => OLA::getTable(), 'WHERE' => ['slms_id' => $slm->getID()]])->count(), 'OLA lives in its own table — TTO + TTR per priority, no duplicate either.');
    }

    /**
     * `assignOne()` creates one RuleTicket per (entity × priority), each with two criteria
     * (entities_id, priority) and one `assign` action per non-null SLA/OLA id.
     */
    public function testAssignToEntitiesCreatesOneRuleTicketPerEntityAndPriorityWithCorrectCriteriaAndActions(): void
    {
        $builder = new SlaBuilder();
        $ids = $builder->buildFromOverride($this->uniqueClientName(), $this->sla(['ola_enabled' => true]));
        $entityId = $this->uniqueEntityId();

        $builder->assignToEntities($ids, [$entityId]);

        $ruleName = sprintf('SLA standard — entité #%d — %s', $entityId, \CommonITILObject::getPriorityName(self::MAX_PRIORITY));
        $rule = new RuleTicket();
        $this->assertTrue($rule->getFromDBByCrit(['name' => $ruleName]));
        $this->assertSame(1, (int) $rule->fields['is_recursive']);
        $this->assertSame(RuleTicket::ONADD, $rule->fields['condition']);

        global $DB;
        $criteriaCount = $DB->request(['FROM' => 'glpi_rulecriterias', 'WHERE' => ['rules_id' => $rule->getID()]])->count();
        $this->assertSame(2, $criteriaCount);

        $actionsCount = $DB->request(['FROM' => 'glpi_ruleactions', 'WHERE' => ['rules_id' => $rule->getID()]])->count();
        $this->assertSame(4, $actionsCount, 'slas_id_tto + slas_id_ttr + olas_id_tto + olas_id_ttr.');
    }

    public function testAssignToEntitiesIsIdempotentAndDoesNotDuplicateRules(): void
    {
        $builder = new SlaBuilder();
        $ids = $builder->buildFromOverride($this->uniqueClientName(), $this->sla());
        $entityId = $this->uniqueEntityId();

        $builder->assignToEntities($ids, [$entityId]);
        $builder->assignToEntities($ids, [$entityId]);

        $ruleName = sprintf('SLA standard — entité #%d — %s', $entityId, \CommonITILObject::getPriorityName(self::MAX_PRIORITY));
        global $DB;
        $count = $DB->request(['FROM' => RuleTicket::getTable(), 'WHERE' => ['name' => $ruleName]])->count();
        $this->assertSame(1, $count);
    }

    /**
     * `assignMap()` is the per-client variant: different entities can get different SLA ids and
     * different tier group ids (falling back to `$defaultTierGroupIds` when absent from the map).
     */
    public function testAssignMapUsesPerEntitySlaIdsAndFallsBackToTheDefaultTierGroupIds(): void
    {
        $builder = new SlaBuilder();
        $idsA = $builder->buildFromOverride($this->uniqueClientName(), $this->sla());
        $idsB = $builder->buildFromOverride($this->uniqueClientName(), $this->sla());
        $entityA = $this->uniqueEntityId();
        $entityB = $this->uniqueEntityId();

        $tierGroupIds = (new SupportTierBuilder())->build($this->buildConfig(['escalation_enabled' => 1]));

        $builder->assignMap([$entityA => $idsA, $entityB => $idsB], $tierGroupIds);

        global $DB;
        foreach ([$entityA => $idsA, $entityB => $idsB] as $entityId => $ids) {
            $ruleName = sprintf('SLA standard — entité #%d — %s', $entityId, \CommonITILObject::getPriorityName(self::MAX_PRIORITY));
            $rule = new RuleTicket();
            $this->assertTrue($rule->getFromDBByCrit(['name' => $ruleName]));

            $action = $DB->request(['FROM' => 'glpi_ruleactions', 'WHERE' => ['rules_id' => $rule->getID(), 'field' => 'slas_id_ttr']])->current();
            $this->assertSame((string) $ids[self::MAX_PRIORITY]['ttr'], (string) $action['value']);

            $groupAction = $DB->request(['FROM' => 'glpi_ruleactions', 'WHERE' => ['rules_id' => $rule->getID(), 'field' => '_groups_id_assign']])->current();
            $this->assertNotFalse($groupAction, 'Falls back to the default tier group ids when the entity has no override.');
        }
    }

    /**
     * Regression guard for the N1-assignment action documented in `assignOne()`: without
     * `is_recursive = 1` on the rule itself, GLPI only evaluates it for entity 0 — confirmed real
     * in the class's own docblock. Also checks the `_groups_id_assign` action lands when a tier
     * group id is passed.
     */
    public function testAssignToEntitiesAddsN1GroupAssignmentActionWhenTierGroupIdsProvided(): void
    {
        $builder = new SlaBuilder();
        $ids = $builder->buildFromOverride($this->uniqueClientName(), $this->sla());
        $tierGroupIds = (new SupportTierBuilder())->build($this->buildConfig(['escalation_enabled' => 1]));
        $entityId = $this->uniqueEntityId();

        $builder->assignToEntities($ids, [$entityId], $tierGroupIds);

        $ruleName = sprintf('SLA standard — entité #%d — %s', $entityId, \CommonITILObject::getPriorityName(self::MAX_PRIORITY));
        $rule = new RuleTicket();
        $rule->getFromDBByCrit(['name' => $ruleName]);

        global $DB;
        $action = $DB->request(['FROM' => 'glpi_ruleactions', 'WHERE' => ['rules_id' => $rule->getID(), 'field' => '_groups_id_assign']])->current();
        $this->assertNotFalse($action);
        $this->assertSame((string) $tierGroupIds['n1'], (string) $action['value']);
    }

    /**
     * `escalation_enabled` (priority raise) creates a "before breach" `SlaLevel` on every TTR
     * except the already-highest priority (nothing higher to escalate to).
     */
    public function testEscalationEnabledAddsAPriorityRaiseLevelSkippingTheMaxPriority(): void
    {
        $result = (new SlaBuilder())->buildFromOverride(
            $this->uniqueClientName(),
            $this->sla(),
            null,
            escalationEnabled: true
        );

        global $DB;

        // Priority 5 (not max) gets a priority-raise level.
        $level = new SlaLevel();
        $this->assertTrue($level->getFromDBByCrit(['slas_id' => $result[5]['ttr']]));
        $action = $DB->request(['FROM' => 'glpi_slalevelactions', 'WHERE' => ['slalevels_id' => $level->getID(), 'field' => 'priority']])->current();
        $this->assertNotFalse($action);
        $this->assertSame('6', (string) $action['value']);

        // Priority 6 (max) has no level at all — nothing higher to escalate to, and no tier
        // reassignment requested in this config.
        $maxLevelCount = $DB->request(['FROM' => 'glpi_slalevels', 'WHERE' => ['slas_id' => $result[self::MAX_PRIORITY]['ttr']]])->count();
        $this->assertSame(0, $maxLevelCount);
    }

    /**
     * `escalation_auto_n1_n2` rides the *same* before-breach level as the priority raise, applied
     * to every priority including the max one (unlike the priority raise itself).
     */
    public function testAutoN1N2AddsGroupReassignActionOnTheSameBeforeBreachLevelForEveryPriority(): void
    {
        $tierGroupIds = (new SupportTierBuilder())->build($this->buildConfig(['escalation_enabled' => 1]));

        $result = (new SlaBuilder())->buildFromOverride(
            $this->uniqueClientName(),
            $this->sla(),
            null,
            escalationEnabled: true,
            tierGroupIds: $tierGroupIds,
            autoN1N2: true
        );

        global $DB;

        // Priority 5: single level carries both the priority-raise and the N1->N2 actions.
        $level5 = new SlaLevel();
        $level5->getFromDBByCrit(['slas_id' => $result[5]['ttr']]);
        $count5 = $DB->request(['FROM' => 'glpi_slalevels', 'WHERE' => ['slas_id' => $result[5]['ttr']]])->count();
        $this->assertSame(1, $count5, 'Priority raise and N1->N2 share the same before-breach level.');
        $groupAction5 = $DB->request(['FROM' => 'glpi_slalevelactions', 'WHERE' => ['slalevels_id' => $level5->getID(), 'field' => '_groups_id_assign']])->current();
        $this->assertNotFalse($groupAction5);
        $this->assertSame((string) $tierGroupIds['n2'], (string) $groupAction5['value']);

        // Priority 6 (max): no priority-raise action possible, but N1->N2 still applies — a level
        // must still exist, carrying only the group action.
        $level6 = new SlaLevel();
        $this->assertTrue($level6->getFromDBByCrit(['slas_id' => $result[self::MAX_PRIORITY]['ttr']]));
        $priorityAction6 = $DB->request(['FROM' => 'glpi_slalevelactions', 'WHERE' => ['slalevels_id' => $level6->getID(), 'field' => 'priority']])->current();
        $this->assertNull($priorityAction6, 'Already-max priority has nothing to raise to.');
        $groupAction6 = $DB->request(['FROM' => 'glpi_slalevelactions', 'WHERE' => ['slalevels_id' => $level6->getID(), 'field' => '_groups_id_assign']])->current();
        $this->assertNotFalse($groupAction6);
    }

    /**
     * `escalation_auto_n2_n3` fires on a second, distinct level at the deadline itself
     * (`execution_time = 0`), separate from the before-breach level.
     */
    public function testAutoN2N3AddsASecondAtBreachLevelDistinctFromTheBeforeBreachLevel(): void
    {
        $tierGroupIds = (new SupportTierBuilder())->build($this->buildConfig(['escalation_enabled' => 1]));

        $result = (new SlaBuilder())->buildFromOverride(
            $this->uniqueClientName(),
            $this->sla(),
            null,
            escalationEnabled: true,
            tierGroupIds: $tierGroupIds,
            autoN1N2: true,
            autoN2N3: true
        );

        global $DB;
        $levels = $DB->request(['FROM' => 'glpi_slalevels', 'WHERE' => ['slas_id' => $result[5]['ttr']]]);
        $this->assertSame(2, $levels->count(), 'Before-breach (priority raise + N1->N2) and at-breach (N2->N3) are two distinct levels.');

        $atBreach = null;
        foreach ($levels as $row) {
            if ((int) $row['execution_time'] === 0) {
                $atBreach = $row;
            }
        }
        $this->assertNotNull($atBreach, 'The N2->N3 level must fire exactly at the deadline (execution_time = 0).');

        $n3Action = $DB->request(['FROM' => 'glpi_slalevelactions', 'WHERE' => ['slalevels_id' => $atBreach['id'], 'field' => '_groups_id_assign']])->current();
        $this->assertNotFalse($n3Action);
        $this->assertSame((string) $tierGroupIds['n3'], (string) $n3Action['value']);
    }

    /**
     * Same escalation machinery mirrored onto OLA levels (`OlaLevel`/`OlaLevelAction`) when OLA is
     * enabled — a distinct table, not reused rows on the SLA side.
     */
    public function testEscalationAlsoAppliesToOlaLevelsWhenOlaIsEnabled(): void
    {
        $result = (new SlaBuilder())->buildFromOverride(
            $this->uniqueClientName(),
            $this->sla(['ola_enabled' => true]),
            null,
            escalationEnabled: true
        );

        $level = new OlaLevel();
        $this->assertTrue($level->getFromDBByCrit(['olas_id' => $result[5]['ola_ttr']]));

        global $DB;
        $action = $DB->request(['FROM' => 'glpi_olalevelactions', 'WHERE' => ['olalevels_id' => $level->getID(), 'field' => 'priority']])->current();
        $this->assertNotFalse($action);
        $this->assertSame('6', (string) $action['value']);
    }
}
