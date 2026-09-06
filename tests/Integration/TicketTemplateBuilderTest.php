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
use GlpiPlugin\Configurationglpiauto\TicketTemplateBuilder;
use PHPUnit\Framework\TestCase;
use TicketTemplate;
use TicketTemplateHiddenField;
use TicketTemplateMandatoryField;

/**
 * The real point of `TicketTemplateBuilder` is the split it wires up (see its own docblock): base
 * users (Self-Service/Read-Only) get a minimal template, everyone else gets the full one — resolved
 * via `TicketTemplate::getAllowedFields(true)`, the same authoritative source GLPI's own admin UI
 * uses (the class's docblock explains a real earlier mistake from hand-deriving these IDs instead).
 * This suite resolves search option ids the same way, rather than hardcoding numbers.
 */
final class TicketTemplateBuilderTest extends TestCase
{
    private const SIMPLIFIED_NAME = 'Ticket simplifié (libre-service)';

    private const COMPLETE_NAME = 'Ticket complet (support)';

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'ticket_template_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsFalseWhenDisabled(): void
    {
        $applied = (new TicketTemplateBuilder())->apply($this->buildConfig(false));

        $this->assertFalse($applied);
    }

    public function testSimplifiedTemplateHidesQualificationFieldsButKeepsCategoryVisible(): void
    {
        $this->assertTrue((new TicketTemplateBuilder())->apply($this->buildConfig(true)));

        $so = array_flip(TicketTemplate::getAllowedFields(true));

        $simplified = new TicketTemplate();
        $simplified->getFromDBByCrit(['name' => self::SIMPLIFIED_NAME, 'entities_id' => 0]);

        foreach (['urgency', 'status', 'slas_id_tto', '_users_id_assign'] as $key) {
            $hidden = new TicketTemplateHiddenField();
            $this->assertTrue(
                $hidden->getFromDBByCrit(['tickettemplates_id' => $simplified->getID(), 'num' => $so[$key]]),
                "'$key' must be hidden on the simplified template."
            );
        }

        $categoryHidden = new TicketTemplateHiddenField();
        $this->assertFalse(
            $categoryHidden->getFromDBByCrit(['tickettemplates_id' => $simplified->getID(), 'num' => $so['itilcategories_id']]),
            'itilcategories_id must stay visible — scoped via is_helpdeskvisible, not this hidden-field mechanism.'
        );

        $mandatory = new TicketTemplateMandatoryField();
        $this->assertTrue($mandatory->getFromDBByCrit(['tickettemplates_id' => $simplified->getID(), 'num' => $so['content']]));
    }

    public function testCompleteTemplateRequiresCategoryAndUrgency(): void
    {
        (new TicketTemplateBuilder())->apply($this->buildConfig(true));

        $so = array_flip(TicketTemplate::getAllowedFields(true));
        $complete = new TicketTemplate();
        $complete->getFromDBByCrit(['name' => self::COMPLETE_NAME, 'entities_id' => 0]);

        foreach (['content', 'itilcategories_id', 'urgency'] as $key) {
            $mandatory = new TicketTemplateMandatoryField();
            $this->assertTrue(
                $mandatory->getFromDBByCrit(['tickettemplates_id' => $complete->getID(), 'num' => $so[$key]]),
                "'$key' must be mandatory on the complete template."
            );
        }
    }

    public function testSelfServiceAndReadOnlyGetTheSimplifiedTemplateEveryOtherProfileGetsTheComplete(): void
    {
        (new TicketTemplateBuilder())->apply($this->buildConfig(true));

        $simplified = new TicketTemplate();
        $simplified->getFromDBByCrit(['name' => self::SIMPLIFIED_NAME, 'entities_id' => 0]);
        $complete = new TicketTemplate();
        $complete->getFromDBByCrit(['name' => self::COMPLETE_NAME, 'entities_id' => 0]);

        global $DB;
        foreach (['Self-Service', 'Read-Only'] as $name) {
            $row = $DB->request(['FROM' => 'glpi_profiles', 'WHERE' => ['name' => $name]])->current();
            $this->assertSame((int) $simplified->getID(), (int) $row['tickettemplates_id'], "$name must use the simplified template.");
        }

        $superAdmin = $DB->request(['FROM' => 'glpi_profiles', 'WHERE' => ['name' => 'Super-Admin']])->current();
        $this->assertSame((int) $complete->getID(), (int) $superAdmin['tickettemplates_id']);
    }

    public function testApplyIsIdempotentAndDoesNotDuplicateFieldRows(): void
    {
        $builder = new TicketTemplateBuilder();
        $builder->apply($this->buildConfig(true));
        $this->assertTrue($builder->apply($this->buildConfig(true)));

        $so = array_flip(TicketTemplate::getAllowedFields(true));
        $simplified = new TicketTemplate();
        $simplified->getFromDBByCrit(['name' => self::SIMPLIFIED_NAME, 'entities_id' => 0]);

        global $DB;
        $count = $DB->request([
            'FROM' => TicketTemplateHiddenField::getTable(),
            'WHERE' => ['tickettemplates_id' => $simplified->getID(), 'num' => $so['urgency']],
        ])->count();
        $this->assertSame(1, $count, 'Exactly one hidden-field row must exist — no duplicate.');
    }
}
