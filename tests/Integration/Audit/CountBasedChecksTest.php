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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration\Audit;

use GlpiPlugin\Configurationglpiauto\Audit\Checks\NoActiveCalendarCheck;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\NoSlaConfiguredCheck;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\NoStatesConfiguredCheck;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\NoTicketCategoriesCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `NoStatesConfiguredCheck`/`NoActiveCalendarCheck`/`NoSlaConfiguredCheck`/`NoTicketCategoriesCheck`
 * each flag a *globally empty* table (`glpi_states`/`glpi_calendars`/`glpi_slas`/
 * `glpi_itilcategories`). Deliberately NOT forced into either branch here: this suite runs against
 * the same shared, persistent instance as every other Integration test in this plugin, and whether
 * these tables already have rows depends on which other suites (StateBuilderTest,
 * CalendarBuilderTest, SlaBuilderTest, CategoryBuilderTest...) happened to run first — true on the
 * shared dev instance, NOT guaranteed on a fresh CI database where PHPUnit's execution order is
 * unspecified. Forcibly emptying real shared tables to exercise the "problem detected" branch would
 * also be destructive to other suites' and any real organization's own data.
 *
 * What IS safely verifiable regardless of table state or execution order: each check runs without
 * error, returns at most one finding (never one row per missing item), and never claims to be
 * fixable — none of these four can guess what the right states/calendar/SLA/categories should be
 * for a given organization (see each class's own docblock).
 */
final class CountBasedChecksTest extends TestCase
{
    /**
     * @return array<string, array{0: callable(): \GlpiPlugin\Configurationglpiauto\Audit\AuditCheckInterface}>
     */
    public static function checkProvider(): array
    {
        return [
            'states'            => [static fn () => new NoStatesConfiguredCheck()],
            'calendars'         => [static fn () => new NoActiveCalendarCheck()],
            'slas'              => [static fn () => new NoSlaConfiguredCheck()],
            'ticket_categories' => [static fn () => new NoTicketCategoriesCheck()],
        ];
    }

    #[DataProvider('checkProvider')]
    public function testIsNeverFixable(callable $makeCheck): void
    {
        $this->assertFalse($makeCheck()->canFix());
    }

    #[DataProvider('checkProvider')]
    public function testReturnsAtMostOneFindingOfTheExpectedShape(callable $makeCheck): void
    {
        $check = $makeCheck();
        $findings = $check->analyze();

        $this->assertLessThanOrEqual(1, count($findings));

        foreach ($findings as $finding) {
            $this->assertSame($check->getKey(), $finding->checkKey);
            $this->assertFalse($finding->fixable);
            $this->assertNotSame('', trim($finding->recommendation));
        }
    }
}
