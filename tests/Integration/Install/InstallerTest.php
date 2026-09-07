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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration\Install;

use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\Install\Installer;
use Migration;
use PHPUnit\Framework\TestCase;

/**
 * The plugin is already installed and active on this instance — every `CREATE TABLE` branch in
 * `install()` is guarded by `tableExists()` and every `addField()`/`dropField()` migration call is
 * itself idempotent, so re-running `install()` here is a safe, real re-application of the exact
 * same code path an upgrade runs, not a simulation. `uninstall()` is deliberately NOT exercised
 * here: it does a real `DROP TABLE` on this instance's actual live configuration/profile/fuel-type
 * tables, which every other test in this suite (including this file's own) depends on continuing
 * to exist. That destructive path is already exercised for real on every CI run instead, in a
 * throwaway container — confirmed in this project's own `ci.yml` "real-glpi-install" job, which
 * installs, activates, deactivates, uninstalls, and then asserts zero leftover tables/rights/cron
 * tasks — a materially safer place for a `DROP TABLE` to run than this shared, long-lived instance.
 */
final class InstallerTest extends TestCase
{
    public function testInstallReturnsTrueAndDoesNotThrowOnAnAlreadyInstalledInstance(): void
    {
        $result = (new Installer())->install(new Migration(PLUGIN_CONFIGURATIONGLPIAUTO_VERSION));

        $this->assertTrue($result);
    }

    public function testInstallIsIdempotentAndDoesNotDuplicateTheMultiSiteConfigurationProfile(): void
    {
        (new Installer())->install(new Migration(PLUGIN_CONFIGURATIONGLPIAUTO_VERSION));
        (new Installer())->install(new Migration(PLUGIN_CONFIGURATIONGLPIAUTO_VERSION));

        global $DB;
        $count = $DB->request(['FROM' => ConfigurationProfile::getTable(), 'WHERE' => ['type' => 'multi_site']])->count();
        $this->assertSame(1, $count, 'Exactly one "multi_site" profile must exist — no duplicate from running install() twice.');
    }

    /**
     * ITIL/ISO27001 (practice frameworks, not org sizes) and the old PME/ETI/Grande-entreprise
     * split (superseded by the plain-language "multi_site" merge) are retired via a soft
     * `is_active = 0`, not deleted — a real `Config` row could still reference one via
     * `configurationprofiles_id`. This instance's own profiles were seeded by the *current*
     * `insertDefaultProfiles()` (which no longer creates these legacy types at all), so a fake row
     * of each retired type is inserted first to exercise the actual upgrade path a genuinely old
     * install would hit.
     */
    public function testInstallDeactivatesEveryRetiredProfileType(): void
    {
        global $DB;
        $ids = [];
        foreach (['iso27001', 'itil', 'sme', 'eti', 'enterprise'] as $type) {
            $DB->insert(ConfigurationProfile::getTable(), ['name' => "Test — $type", 'type' => $type, 'is_active' => 1]);
            $ids[$type] = $DB->insertId();
        }

        try {
            (new Installer())->install(new Migration(PLUGIN_CONFIGURATIONGLPIAUTO_VERSION));

            foreach ($ids as $type => $id) {
                $row = $DB->request(['FROM' => ConfigurationProfile::getTable(), 'WHERE' => ['id' => $id]])->current();
                $this->assertSame(0, (int) $row['is_active'], "The '$type' profile must be deactivated by install().");
            }
        } finally {
            foreach ($ids as $id) {
                $DB->delete(ConfigurationProfile::getTable(), ['id' => $id]);
            }
        }
    }

    public function testInstallKeepsTheVendorNeutralAndPlainLanguageProfileNamesStable(): void
    {
        (new Installer())->install(new Migration(PLUGIN_CONFIGURATIONGLPIAUTO_VERSION));

        $minimal = new ConfigurationProfile();
        $this->assertTrue($minimal->getFromDBByCrit(['type' => 'minimal']));
        $this->assertSame('Installation simple', $minimal->fields['name']);

        $msp = new ConfigurationProfile();
        $this->assertTrue($msp->getFromDBByCrit(['type' => 'msp']));
        $this->assertSame('Plusieurs entreprises clientes', $msp->fields['name']);
    }

    /**
     * Regression guard for the real bug documented directly in `install()`: `SlaBuilder`-created
     * `RuleTicket` rows missing `is_recursive = 1` used to only ever be evaluated for the rule's
     * own root entity, never for a ticket created in any sub-entity — the only kind of entity this
     * plugin actually assigns an SLA to.
     */
    public function testInstallFixesUpAnySlaStandardRuleMissingIsRecursive(): void
    {
        global $DB;
        $DB->insert('glpi_rules', [
            'name' => 'SLA standard — entité #999998 — Test',
            'sub_type' => 'RuleTicket',
            'match' => 'AND',
            'condition' => 1,
            'is_active' => 1,
            'is_recursive' => 0,
        ]);

        try {
            (new Installer())->install(new Migration(PLUGIN_CONFIGURATIONGLPIAUTO_VERSION));

            $row = $DB->request(['FROM' => 'glpi_rules', 'WHERE' => ['name' => 'SLA standard — entité #999998 — Test']])->current();
            $this->assertSame(1, (int) $row['is_recursive']);
        } finally {
            $DB->delete('glpi_rules', ['name' => 'SLA standard — entité #999998 — Test']);
        }
    }

    public function testInstallLeavesTheConfigSingletonIntactWithNoDuplicate(): void
    {
        (new Installer())->install(new Migration(PLUGIN_CONFIGURATIONGLPIAUTO_VERSION));

        $config = Config::getConfig();
        $this->assertSame(1, (int) $config->getID());

        global $DB;
        $this->assertSame(1, $DB->request(['FROM' => Config::getTable()])->count());
    }
}
