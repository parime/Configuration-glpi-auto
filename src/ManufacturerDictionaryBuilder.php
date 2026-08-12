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

use Rule;
use RuleAction;
use RuleCriteria;
use RuleDictionnaryManufacturer;

/**
 * Turns on `manufacturer_dictionary_enabled` into `RuleDictionnaryManufacturer` rows (Configuration
 * > Intitulés > Dictionnaires > Fabricants) — GLPI's native mechanism for normalizing the
 * inconsistent manufacturer strings hardware inventory (dmidecode/WMI/SNMP) actually reports into
 * a single canonical `Manufacturer` (confirmed real: `RuleDictionnaryManufacturer::getActions()`
 * supports the `assign` force_action on the `name` field — the exact mechanism this needs).
 *
 * Explicitly re-scoped after user feedback: the project's ROADMAP previously rejected *all*
 * dictionary rules with "nothing to normalize yet on a fresh install without inventory data" — true
 * for logiciels/matériel dictionaries (their variant spellings are whatever a specific org's real
 * hardware happens to report, unknowable in advance), but *not* true here. `ManufacturerBuilder`'s
 * own 29-entry list is already the canonical target; the raw strings real inventory tools report
 * for the biggest vendors (`Hewlett-Packard`, `HP Inc.`, `ASUSTeK Computer Inc.`...) are extremely
 * well-documented and stable across organizations — this doesn't require *this org's* messy data to
 * write rules for, unlike a real per-org software-naming dictionary would.
 *
 * One rule per canonical manufacturer that has known messy variants (not all 29 — many, like
 * "Canon" or "APC", are already reported consistently and don't need a rule). OR-matched criteria
 * (`Rule::PATTERN_IS`, exact match — deliberately not `PATTERN_CONTAIN`: a substring match on
 * something as short as "LG" or "HP" would misfire on unrelated strings that merely contain those
 * letters) against each known variant, single `assign` action normalizing to the canonical name
 * already created by `ManufacturerBuilder`.
 */
class ManufacturerDictionaryBuilder
{
    /**
     * Canonical name (must match `ManufacturerBuilder::MANUFACTURERS`) => real-world variant
     * strings, gathered from how these vendors actually self-report in firmware/OS inventory data
     * (dmidecode `sys_vendor`/BIOS vendor strings, Windows WMI `Manufacturer`, SNMP sysDescr) — not
     * a guess at every possible legal-entity-name permutation.
     *
     * @var array<string, array<int, string>>
     */
    private const VARIANTS = [
        'HP' => ['Hewlett-Packard', 'Hewlett Packard', 'HP Inc.', 'HP Inc', 'Hewlett-Packard Company'],
        'Dell' => ['Dell Inc.', 'Dell Inc', 'Dell Technologies', 'Dell Computer Corporation'],
        'Lenovo' => ['LENOVO', 'Lenovo Group Limited'],
        'Apple' => ['Apple Inc.', 'Apple Computer, Inc.'],
        'Microsoft' => ['Microsoft Corporation'],
        'ASUS' => ['ASUSTeK Computer Inc.', 'ASUSTeK COMPUTER INC.', 'ASUSTek Computer Inc.'],
        'Acer' => ['Acer Inc.', 'Acer America Corporation'],
        'Cisco' => ['Cisco Systems, Inc.', 'Cisco Systems'],
        'Samsung' => ['Samsung Electronics Co., Ltd', 'Samsung Electronics Co., Ltd.', 'SAMSUNG ELECTRONICS'],
        'LG' => ['LG Electronics', 'LGE'],
        'Epson' => ['Seiko Epson Corporation', 'EPSON'],
        'Xerox' => ['Xerox Corporation'],
        'Synology' => ['Synology Inc.', 'Synology Incorporated'],
        'VMware' => ['VMware, Inc.'],
        'NetApp' => ['NetApp, Inc.'],
    ];

    /**
     * @return int Number of dictionary rules created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['manufacturer_dictionary_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::VARIANTS as $canonical => $variants) {
            $this->createRule($canonical, $variants);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function getPreview(): array
    {
        return self::VARIANTS;
    }

    /**
     * @param array<int, string> $variants
     */
    private function createRule(string $canonical, array $variants): void
    {
        $ruleName = sprintf('Fabricant — normalisation « %s »', $canonical);

        $rule = new Rule();
        if ($rule->getFromDBByCrit(['name' => $ruleName])) {
            return;
        }

        $rulesId = $rule->add([
            'name' => $ruleName,
            'sub_type' => RuleDictionnaryManufacturer::class,
            'match' => Rule::OR_MATCHING,
            'is_active' => 1,
        ]);

        foreach ($variants as $variant) {
            (new RuleCriteria())->add([
                'rules_id' => $rulesId,
                'criteria' => 'name',
                'condition' => Rule::PATTERN_IS,
                'pattern' => $variant,
            ]);
        }

        (new RuleAction())->add([
            'rules_id' => $rulesId,
            'action_type' => 'assign',
            'field' => 'name',
            'value' => $canonical,
        ]);
    }
}
