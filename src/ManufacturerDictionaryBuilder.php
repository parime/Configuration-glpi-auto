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
 * One rule per canonical manufacturer that has known messy variants — all 29 as of the second
 * pass (2026-08-13), after a real org's own glpi-agent-populated Manufacturer export surfaced
 * genuine duplicates (e.g. "Acer, Inc" alongside the already-covered "Acer Inc.") and four
 * canonical manufacturers with zero coverage at all (Fortinet/Logitech/Oracle/Red Hat). OR-matched
 * criteria
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
        // 'Acer, Inc' confirmed via a real org's own glpi-agent-populated Manufacturer list (no
        // period, comma before "Inc" — differs from the two already-covered variants).
        'Acer' => ['Acer Inc.', 'Acer America Corporation', 'Acer, Inc'],
        // 'Cisco Systems Inc' (no comma, no trailing period) confirmed the same way — a third real
        // shape distinct from the two already covered.
        'Cisco' => ['Cisco Systems, Inc.', 'Cisco Systems', 'Cisco Systems Inc'],
        // 'Samsung Electric Company' and 'Samsung Electronics Co Ltd' (no periods) confirmed via the
        // same real org export, alongside the three already-covered dotted/uppercase variants.
        'Samsung' => ['Samsung Electronics Co., Ltd', 'Samsung Electronics Co., Ltd.', 'SAMSUNG ELECTRONICS', 'Samsung Electric Company', 'Samsung Electronics Co Ltd'],
        'LG' => ['LG Electronics', 'LGE'],
        'Epson' => ['Seiko Epson Corporation', 'EPSON'],
        'Xerox' => ['Xerox Corporation'],
        'Synology' => ['Synology Inc.', 'Synology Incorporated'],
        // 'VMware Virtual RAM' confirmed via the same real export — dmidecode's memory-module
        // manufacturer field on a VM reports this literal string, not a company-name variant, but it
        // clearly identifies VMware-originated virtual hardware the same way the others identify a
        // real vendor.
        'VMware' => ['VMware, Inc.', 'VMware Virtual RAM'],
        'NetApp' => ['NetApp, Inc.', 'NetApp Inc'],
        // Below: the remaining 9 of the 29 canonical manufacturers (ManufacturerBuilder::MANUFACTURERS)
        // that had zero dictionary coverage at all until now — 4 of the 9 (Fortinet/Logitech/Oracle/
        // Red Hat) confirmed via the same real glpi-agent-populated export as the additions above;
        // the other 5 are well-documented self-report strings for these vendors, same standard as
        // the original 15 (not a per-org guess).
        'Fortinet' => ['Fortinet Inc', 'Fortinet, Inc.'],
        'Logitech' => ['Logitech, Inc.', 'Logitech International S.A.'],
        'Oracle' => ['Oracle Corporation', 'Oracle America, Inc.'],
        'Red Hat' => ['Red Hat, Inc.'],
        'HPE Aruba' => ['Hewlett Packard Enterprise', 'Aruba Networks', 'Aruba, a Hewlett Packard Enterprise Company', 'HPE'],
        'Ubiquiti' => ['Ubiquiti Networks, Inc.', 'Ubiquiti Networks Inc', 'Ubiquiti Inc.'],
        'Netgear' => ['NETGEAR, Inc.', 'NETGEAR Inc'],
        'Canon' => ['Canon Inc.', 'CANON INC.'],
        'Brother' => ['Brother Industries, Ltd.', 'Brother Industries Ltd'],
        'QNAP' => ['QNAP Systems, Inc.', 'QNAP Systems Inc'],
        // Jabra and Poly are themselves brand names their parent company's inventory-reported name
        // sometimes replaces — kept distinct per canonical brand (not merged into each other), since
        // an org's asset list still needs to tell a Jabra headset apart from a Poly one.
        'Jabra' => ['GN Audio', 'GN Netcom'],
        'Poly' => ['Plantronics', 'Plantronics, Inc.', 'HP Poly'],
        'APC' => ['American Power Conversion', 'APC by Schneider Electric'],
        'Eaton' => ['Eaton Corporation', 'Eaton Corporation plc'],
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
     * @param array<int, string> $variants
     */
    private function createRule(string $canonical, array $variants): void
    {
        $ruleName = sprintf('Fabricant — normalisation « %s »', $canonical);

        $rule = new Rule();
        if ($rule->getFromDBByCrit(['name' => $ruleName])) {
            // A prior run already created this rule — add only the variants it doesn't have yet,
            // so expanding VARIANTS (e.g. this file's 2026-08-13 pass) actually benefits an admin
            // re-running the wizard after a plugin upgrade, not just a genuinely fresh install.
            $this->addMissingCriteria((int) $rule->getID(), $variants);

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

    /**
     * @param array<int, string> $variants
     */
    private function addMissingCriteria(int $rulesId, array $variants): void
    {
        $existing = [];
        foreach ((new RuleCriteria())->find(['rules_id' => $rulesId, 'criteria' => 'name']) as $row) {
            $existing[] = $row['pattern'];
        }

        foreach ($variants as $variant) {
            if (!in_array($variant, $existing, true)) {
                (new RuleCriteria())->add([
                    'rules_id' => $rulesId,
                    'criteria' => 'name',
                    'condition' => Rule::PATTERN_IS,
                    'pattern' => $variant,
                ]);
            }
        }
    }
}
