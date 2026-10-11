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

use RuleDictionnaryManufacturer;

/**
 * Turns on `manufacturer_dictionary_enabled` into `RuleDictionnaryManufacturer` rows (Administration
 * > Dictionnaires > Fabricants) — GLPI's native mechanism for normalizing the inconsistent
 * manufacturer strings hardware inventory (dmidecode/WMI/SNMP/EDID) actually reports into a single
 * canonical `Manufacturer`. The inventory runs every manufacturer name through this dictionary
 * before looking for an existing one (`CommonDropdown::importExternal()`), so a variant the
 * dictionary knows never becomes a second manufacturer record.
 *
 * Explicitly re-scoped after user feedback: the project's ROADMAP previously rejected *all*
 * dictionary rules with "nothing to normalize yet on a fresh install without inventory data" — true
 * for software dictionaries (whatever a specific org's real software happens to report), but *not*
 * true here: the raw strings inventory tools report for the major vendors are well documented and
 * stable across organizations.
 *
 * One rule per canonical manufacturer, OR-matched criteria:
 * - exact names (`Rule::PATTERN_IS`, case-insensitive in GLPI) gathered from real inventories;
 * - since issue #297, one "starts with the brand" regular expression, so a variant nobody listed
 *   (« Lenovo (Beijing) Limited », « Hewlett-Packard Development Company, L.P. » reported by
 *   network printers...) is still caught. Anchored at the start and closed by `\b`, never a plain
 *   substring match: « LG » or « HP » must not match unrelated names that merely contain those
 *   letters. HP's expression explicitly excludes HPE and « HP Poly », which have their own rules
 *   (GLPI stops at the first matching dictionary rule).
 */
class ManufacturerDictionaryBuilder
{
    /**
     * Canonical name => [exact variants, regex]. The first 29 entries are
     * `ManufacturerBuilder::MANUFACTURERS`; the others (#297) are brands that show up in almost
     * every inventory (components, monitors, printers, network) without being in the wizard's
     * starter list — the dictionary creates them on first import under their canonical name.
     *
     * Variant sources: dmidecode `sys_vendor`/BIOS vendor strings, Windows WMI `Manufacturer`,
     * SNMP sysDescr, and the EDID vendor names glpi-agent reports for monitors (« Goldstar Company
     * Ltd » for LG, « Ancor Communications Inc » for ASUS...).
     *
     * @var array<string, array{0: list<string>, 1: string}>
     */
    private const RULES = [
        'HP' => [['Hewlett-Packard', 'Hewlett Packard', 'HP Inc.', 'HP Inc', 'Hewlett-Packard Company', 'Hewlett-Packard Development Company, L.P.', 'HP Development Company, L.P.'], '/^(hp|hewlett[- ]?packard)\b(?!\s*(enterprise|poly)\b)/'],
        'Dell' => [['Dell Inc.', 'Dell Inc', 'Dell Technologies', 'Dell Computer Corporation'], '/^dell\b/'],
        'Lenovo' => [['LENOVO', 'Lenovo Group Limited'], '/^lenovo\b/'],
        'Apple' => [['Apple Inc.', 'Apple Computer, Inc.'], '/^apple\b/'],
        'Microsoft' => [['Microsoft Corporation'], '/^microsoft\b/'],
        'ASUS' => [['ASUSTeK Computer Inc.', 'ASUSTeK COMPUTER INC.', 'ASUSTek Computer Inc.', 'Ancor Communications Inc'], '/^(asus|asustek|ancor communications)\b/'],
        // 'Acer, Inc' confirmed via a real org's own glpi-agent-populated Manufacturer list.
        'Acer' => [['Acer Inc.', 'Acer America Corporation', 'Acer, Inc', 'Acer Technologies'], '/^acer\b/'],
        'Cisco' => [['Cisco Systems, Inc.', 'Cisco Systems', 'Cisco Systems Inc'], '/^cisco\b/'],
        'HPE Aruba' => [['Hewlett Packard Enterprise', 'Aruba Networks', 'Aruba, a Hewlett Packard Enterprise Company', 'HPE'], '/^(hpe|hewlett[- ]?packard enterprise|hp enterprise|aruba)\b/'],
        'Fortinet' => [['Fortinet Inc', 'Fortinet, Inc.'], '/^fortinet\b/'],
        'Ubiquiti' => [['Ubiquiti Networks, Inc.', 'Ubiquiti Networks Inc', 'Ubiquiti Inc.'], '/^(ubiquiti|ubnt)\b/'],
        'Netgear' => [['NETGEAR, Inc.', 'NETGEAR Inc'], '/^netgear\b/'],
        'Canon' => [['Canon Inc.', 'CANON INC.'], '/^canon\b/'],
        'Epson' => [['Seiko Epson Corporation', 'EPSON'], '/^(seiko )?epson\b/'],
        'Brother' => [['Brother Industries, Ltd.', 'Brother Industries Ltd'], '/^brother\b/'],
        'Xerox' => [['Xerox Corporation'], '/^xerox\b/'],
        'Samsung' => [['Samsung Electronics Co., Ltd', 'Samsung Electronics Co., Ltd.', 'SAMSUNG ELECTRONICS', 'Samsung Electric Company', 'Samsung Electronics Co Ltd'], '/^samsung\b/'],
        'LG' => [['LG Electronics', 'LGE', 'Goldstar Company Ltd'], '/^(lg|lge|goldstar)\b/'],
        'Synology' => [['Synology Inc.', 'Synology Incorporated'], '/^synology\b/'],
        'QNAP' => [['QNAP Systems, Inc.', 'QNAP Systems Inc'], '/^qnap\b/'],
        'NetApp' => [['NetApp, Inc.', 'NetApp Inc'], '/^netapp\b/'],
        'Logitech' => [['Logitech, Inc.', 'Logitech International S.A.'], '/^logitech\b/'],
        // Jabra and Poly are kept distinct from their parent companies: an asset list still needs
        // to tell a Jabra headset apart from a Poly one.
        'Jabra' => [['GN Audio', 'GN Netcom'], '/^(jabra|gn audio|gn netcom)\b/'],
        'Poly' => [['Plantronics', 'Plantronics, Inc.', 'HP Poly', 'Polycom'], '/^(poly|polycom|plantronics|hp poly)\b/'],
        'APC' => [['American Power Conversion', 'APC by Schneider Electric'], '/^(apc|american power conversion)\b/'],
        'Eaton' => [['Eaton Corporation', 'Eaton Corporation plc'], '/^eaton\b/'],
        // VirtualBox VMs report « innotek GmbH », Oracle's subsidiary that built it.
        'Oracle' => [['Oracle Corporation', 'Oracle America, Inc.', 'innotek GmbH'], '/^(oracle|innotek)\b/'],
        // 'VMware Virtual RAM': dmidecode's memory-module manufacturer field on a VM.
        'VMware' => [['VMware, Inc.', 'VMware Virtual RAM'], '/^vmware\b/'],
        'Red Hat' => [['Red Hat, Inc.'], '/^red ?hat\b/'],

        // Issue #297 : marques courantes absentes de la liste de départ.
        'Intel' => [['Intel Corporation', 'Intel(R) Corporation', 'GenuineIntel'], '/^intel\b/'],
        'AMD' => [['Advanced Micro Devices, Inc.', 'AuthenticAMD'], '/^(amd|advanced micro devices)\b/'],
        'NVIDIA' => [['NVIDIA Corporation'], '/^nvidia\b/'],
        'Realtek' => [['Realtek Semiconductor Corp.'], '/^realtek\b/'],
        'Kingston' => [['Kingston Technology'], '/^kingston\b/'],
        'Micron' => [['Micron Technology'], '/^micron\b/'],
        'SK hynix' => [['Hynix Semiconductor', 'SK Hynix'], '/^(sk ?hynix|hynix)\b/'],
        'Western Digital' => [['WDC'], '/^(western digital|wdc)\b/'],
        'Seagate' => [['Seagate Technology'], '/^seagate\b/'],
        'Toshiba' => [['TOSHIBA'], '/^toshiba\b/'],
        'Fujitsu' => [['FUJITSU', 'FUJITSU CLIENT COMPUTING LIMITED', 'Fujitsu Siemens Computers'], '/^fujitsu\b/'],
        'Panasonic' => [['Panasonic Corporation'], '/^panasonic\b/'],
        'Sony' => [['Sony Corporation'], '/^sony\b/'],
        'MSI' => [['Micro-Star International Co., Ltd.'], '/^(msi|micro-star)\b/'],
        'Gigabyte' => [['Gigabyte Technology Co., Ltd.'], '/^gigabyte\b/'],
        'Supermicro' => [['Super Micro Computer, Inc.'], '/^super ?micro\b/'],
        'Huawei' => [['Huawei Technologies Co., Ltd.'], '/^huawei\b/'],
        'Juniper' => [['Juniper Networks, Inc.'], '/^juniper\b/'],
        'TP-Link' => [['TP-LINK Technologies Co., Ltd.'], '/^tp-?link\b/'],
        'D-Link' => [['D-Link Corporation'], '/^d-?link\b/'],
        'Zyxel' => [['ZyXEL Communications Corp.'], '/^zyxel\b/'],
        'Lexmark' => [['Lexmark International'], '/^lexmark\b/'],
        'Kyocera' => [['KYOCERA Document Solutions Inc.'], '/^kyocera\b/'],
        'Ricoh' => [['RICOH COMPANY, LTD.'], '/^ricoh\b/'],
        'Konica Minolta' => [['KONICA MINOLTA'], '/^konica[- ]?minolta\b/'],
        'Sharp' => [['Sharp Corporation'], '/^sharp\b/'],
        'Philips' => [['Philips Consumer Electronics Company'], '/^philips\b/'],
        'ViewSonic' => [['ViewSonic Corporation'], '/^viewsonic\b/'],
        'BenQ' => [['BenQ Corporation'], '/^benq\b/'],
        'iiyama' => [['Iiyama North America'], '/^iiyama\b/'],
        'Eizo' => [['Eizo Nanao Corporation'], '/^eizo\b/'],
        'AOC' => [['AOC International (USA) Ltd'], '/^aoc\b/'],
        'NEC' => [['NEC Corporation'], '/^nec\b/'],
        'Google' => [['Google Inc.', 'Google LLC'], '/^google\b/'],
        'Xiaomi' => [['Xiaomi Communications Co Ltd'], '/^xiaomi\b/'],
        'Motorola' => [['Motorola Mobility LLC'], '/^motorola\b/'],
        'Zebra' => [['Zebra Technologies'], '/^zebra\b/'],
    ];

    /**
     * @return int Number of dictionary rules created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['manufacturer_dictionary_enabled'])) {
            return 0;
        }

        foreach (self::RULES as $canonical => [$variants, $regex]) {
            DictionaryRules::ensure(RuleDictionnaryManufacturer::class, self::getRuleName($canonical), $canonical, $variants, $regex);
        }

        return count(self::RULES);
    }

    /**
     * @return list<string> noms canoniques couverts par le dictionnaire
     */
    public static function getCanonicalNames(): array
    {
        return array_keys(self::RULES);
    }

    public static function getRuleName(string $canonical): string
    {
        return sprintf('Fabricant — normalisation « %s »', $canonical);
    }
}
