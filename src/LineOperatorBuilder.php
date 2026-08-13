<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Parime
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

namespace GlpiPlugin\Configurationglpiauto;

use LineOperator;

/**
 * Turns on `line_operators_enabled` into real `LineOperator` rows (`glpi_lineoperators`) — native
 * GLPI, empty by default on a fresh install. Scoped to France's four major mobile carriers
 * (Orange, SFR, Bouygues Telecom, Free) rather than a global list: unlike a hardware manufacturer,
 * a telecom operator is meaningful only within one country, so there's no single "universal
 * default" the way `ManufacturerBuilder` has — France-first, same reasoning already used elsewhere
 * in this plugin (public holidays, CERT-FR RSS feed).
 *
 * `mcc`/`mnc` (Mobile Country/Network Code) **must** be set explicitly, and to genuinely distinct
 * values per operator — `glpi_lineoperators` has a real `UNIQUE(mcc, mnc)` index (confirmed via
 * `SHOW INDEX`), and GLPI's own `add()` defaults any unset `getAdditionalFields()` integer to `0`
 * rather than leaving it `NULL`. First discovered the hard way: omitting them entirely silently
 * created only the first operator (`0, 0`) and silently dropped the other three on the unique-index
 * collision, with no visible error anywhere in the wizard's success message or GLPI's own logs —
 * only caught by checking the row count in the database directly. MNC values cross-checked across
 * three independent sources (two MCC/MNC lookup services + Wikipedia's regional MNC table) for the
 * one each source agreed was that operator's primary/operational allocation — each carrier does
 * hold several MNC blocks in reality (legacy 2G/3G, MVNO hosting...), so this is "a real, currently
 * operational one for that carrier", not necessarily the only one a given SIM will report.
 *
 * `LineOperator::$can_be_translated` is `false` (confirmed in GLPI core), so no icon toggle, same
 * reasoning as `FieldUnicityBuilder`.
 */
class LineOperatorBuilder
{
    private const OPERATORS = [
        ['name' => 'Orange', 'mnc' => 1],
        ['name' => 'SFR', 'mnc' => 10],
        ['name' => 'Bouygues Telecom', 'mnc' => 20],
        ['name' => 'Free', 'mnc' => 15],
    ];

    private const MCC_FRANCE = 208;

    /**
     * @return int Number of operators created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['line_operators_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::OPERATORS as $operator) {
            $this->getOrCreate($operator['name'], $operator['mnc']);
            $count++;
        }

        return $count;
    }

    /**
     * @return string[]
     */
    public static function getOperatorsPreview(): array
    {
        return array_column(self::OPERATORS, 'name');
    }

    private function getOrCreate(string $name, int $mnc): void
    {
        $operator = new LineOperator();
        if ($operator->getFromDBByCrit(['name' => $name, 'entities_id' => 0])) {
            return;
        }

        $operator->add([
            'name' => $name,
            'mcc' => self::MCC_FRANCE,
            'mnc' => $mnc,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
    }
}
