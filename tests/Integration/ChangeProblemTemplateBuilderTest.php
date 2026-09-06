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

use ChangeTemplate;
use ChangeTemplateMandatoryField;
use GlpiPlugin\Configurationglpiauto\ChangeProblemTemplateBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use PHPUnit\Framework\TestCase;
use ProblemTemplate;
use ProblemTemplateMandatoryField;

/**
 * Unlike `TicketTemplateBuilder`'s two-tier split, `impact` is mandatory on Change only, not
 * Problem — the real distinction this class's own docblock explains (risk/impact assessment before
 * approval is Change Management's defining practice, Problem has no approval gate the same way).
 * The core regression to guard is that this asymmetry is really wired in the DB, not just the
 * template count.
 */
final class ChangeProblemTemplateBuilderTest extends TestCase
{
    private const CHANGE_NAME = 'Changement standard';

    private const PROBLEM_NAME = 'Problème standard';

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'change_problem_templates_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsFalseWhenDisabled(): void
    {
        $applied = (new ChangeProblemTemplateBuilder())->apply($this->buildConfig(false));

        $this->assertFalse($applied);
    }

    public function testChangeTemplateRequiresContentAndImpact(): void
    {
        $this->assertTrue((new ChangeProblemTemplateBuilder())->apply($this->buildConfig(true)));

        $so = array_flip(ChangeTemplate::getAllowedFields(true));
        $change = new ChangeTemplate();
        $change->getFromDBByCrit(['name' => self::CHANGE_NAME, 'entities_id' => 0]);

        foreach (['content', 'impact'] as $key) {
            $mandatory = new ChangeTemplateMandatoryField();
            $this->assertTrue(
                $mandatory->getFromDBByCrit(['changetemplates_id' => $change->getID(), 'num' => $so[$key]]),
                "'$key' must be mandatory on the change template."
            );
        }
    }

    public function testProblemTemplateRequiresContentOnlyNotImpact(): void
    {
        (new ChangeProblemTemplateBuilder())->apply($this->buildConfig(true));

        $so = array_flip(ProblemTemplate::getAllowedFields(true));
        $problem = new ProblemTemplate();
        $problem->getFromDBByCrit(['name' => self::PROBLEM_NAME, 'entities_id' => 0]);

        $contentMandatory = new ProblemTemplateMandatoryField();
        $this->assertTrue($contentMandatory->getFromDBByCrit(['problemtemplates_id' => $problem->getID(), 'num' => $so['content']]));

        if (isset($so['impact'])) {
            $impactMandatory = new ProblemTemplateMandatoryField();
            $this->assertFalse(
                $impactMandatory->getFromDBByCrit(['problemtemplates_id' => $problem->getID(), 'num' => $so['impact']]),
                'Problem has no approval gate the way Change does — impact must not be forced mandatory.'
            );
        }
    }

    public function testEveryProfileGetsAssignedBothTemplates(): void
    {
        (new ChangeProblemTemplateBuilder())->apply($this->buildConfig(true));

        $change = new ChangeTemplate();
        $change->getFromDBByCrit(['name' => self::CHANGE_NAME, 'entities_id' => 0]);
        $problem = new ProblemTemplate();
        $problem->getFromDBByCrit(['name' => self::PROBLEM_NAME, 'entities_id' => 0]);

        global $DB;
        $superAdmin = $DB->request(['FROM' => 'glpi_profiles', 'WHERE' => ['name' => 'Super-Admin']])->current();
        $this->assertSame((int) $change->getID(), (int) $superAdmin['changetemplates_id']);
        $this->assertSame((int) $problem->getID(), (int) $superAdmin['problemtemplates_id']);
    }

    public function testApplyIsIdempotentAndDoesNotDuplicateFieldRows(): void
    {
        $builder = new ChangeProblemTemplateBuilder();
        $builder->apply($this->buildConfig(true));
        $this->assertTrue($builder->apply($this->buildConfig(true)));

        $so = array_flip(ChangeTemplate::getAllowedFields(true));
        $change = new ChangeTemplate();
        $change->getFromDBByCrit(['name' => self::CHANGE_NAME, 'entities_id' => 0]);

        global $DB;
        $count = $DB->request([
            'FROM' => ChangeTemplateMandatoryField::getTable(),
            'WHERE' => ['changetemplates_id' => $change->getID(), 'num' => $so['impact']],
        ])->count();
        $this->assertSame(1, $count, 'Exactly one mandatory-field row must exist — no duplicate.');
    }
}
