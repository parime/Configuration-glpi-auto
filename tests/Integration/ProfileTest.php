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

use GlpiPlugin\Configurationglpiauto\Profile;
use Migration;
use PHPUnit\Framework\TestCase;
use ProfileRight;

/**
 * The plugin is actually installed and active on this instance (real `ProfileRight` rows already
 * exist from the real activation), so `testUninstallThenReinstallRestoresState()` wraps its own
 * `uninstall()` call in try/finally to guarantee `install()` runs again before the test ends even
 * if an assertion fails in between — leaving the live plugin's own Super-Admin rights intact is
 * more important than a clean early-return on failure.
 */
final class ProfileTest extends TestCase
{
    public function testInstallIsIdempotentAndGrantsSuperAdminAllStandardRightsOnEveryRealProfile(): void
    {
        Profile::install(new Migration('1.0.0'));
        Profile::install(new Migration('1.0.0'));

        global $DB;

        $profileCount = $DB->request(['FROM' => \Profile::getTable()])->count();
        foreach ([Profile::RIGHT_PROFILE, Profile::RIGHT_CONFIG] as $right) {
            $count = $DB->request(['FROM' => ProfileRight::getTable(), 'WHERE' => ['name' => $right]])->count();
            $this->assertSame($profileCount, $count, "Exactly one '$right' row per real profile — no duplicate from running install() twice.");
        }

        $superAdmin = $DB->request(['FROM' => \Profile::getTable(), 'WHERE' => ['name' => 'Super-Admin']])->current();
        foreach ([Profile::RIGHT_PROFILE, Profile::RIGHT_CONFIG] as $right) {
            $row = $DB->request(['FROM' => ProfileRight::getTable(), 'WHERE' => ['profiles_id' => $superAdmin['id'], 'name' => $right]])->current();
            $this->assertSame(ALLSTANDARDRIGHT, (int) $row['rights']);
        }
    }

    public function testUninstallThenReinstallRestoresState(): void
    {
        global $DB;

        try {
            Profile::uninstall();

            foreach ([Profile::RIGHT_PROFILE, Profile::RIGHT_CONFIG] as $right) {
                $count = $DB->request(['FROM' => ProfileRight::getTable(), 'WHERE' => ['name' => $right]])->count();
                $this->assertSame(0, $count, "uninstall() must remove every '$right' row.");
            }
        } finally {
            Profile::install(new Migration('1.0.0'));
        }

        $superAdmin = $DB->request(['FROM' => \Profile::getTable(), 'WHERE' => ['name' => 'Super-Admin']])->current();
        foreach ([Profile::RIGHT_PROFILE, Profile::RIGHT_CONFIG] as $right) {
            $row = $DB->request(['FROM' => ProfileRight::getTable(), 'WHERE' => ['profiles_id' => $superAdmin['id'], 'name' => $right]])->current();
            $this->assertNotNull($row, "install() must recreate the '$right' row after uninstall().");
            $this->assertSame(ALLSTANDARDRIGHT, (int) $row['rights']);
        }
    }
}
