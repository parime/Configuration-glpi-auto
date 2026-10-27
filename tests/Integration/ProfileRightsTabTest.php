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
use PHPUnit\Framework\TestCase;

/**
 * The plugin's tab on GLPI profiles: both rights can be granted from the UI, and the tab (name and
 * content, which ajax/common.tabs.php serves without getTabNameForItem()) needs the core 'profile'
 * right plus access to the plugin. Only the session is changed, restored after each test.
 */
final class ProfileRightsTabTest extends TestCase
{
    private ?array $savedProfile = null;

    protected function setUp(): void
    {
        $this->savedProfile = $_SESSION['glpiactiveprofile'] ?? null;
        $_SESSION['glpiactiveprofile'] = array_merge($_SESSION['glpiactiveprofile'] ?? [], [
            'interface'           => 'central',
            'profile'             => ALLSTANDARDRIGHT,
            Profile::RIGHT_PROFILE => ALLSTANDARDRIGHT,
            Profile::RIGHT_CONFIG  => ALLSTANDARDRIGHT,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->savedProfile === null) {
            unset($_SESSION['glpiactiveprofile']);
        } else {
            $_SESSION['glpiactiveprofile'] = $this->savedProfile;
        }
    }

    private function superAdminProfile(): \Profile
    {
        $profile = new \Profile();
        $this->assertTrue($profile->getFromDBByCrit(['name' => 'Super-Admin']));

        return $profile;
    }

    /** @return array{0: mixed, 1: string} */
    private function tabContent(\Profile $profile): array
    {
        ob_start();
        $shown = Profile::displayTabContentForItem($profile);

        return [$shown, (string) ob_get_clean()];
    }

    public function testTabListsBothPluginRights(): void
    {
        $profile = $this->superAdminProfile();

        $this->assertNotSame('', (new Profile())->getTabNameForItem($profile));
        [$shown, $html] = $this->tabContent($profile);
        $this->assertTrue($shown);
        foreach ([Profile::RIGHT_PROFILE, Profile::RIGHT_CONFIG] as $right) {
            $this->assertStringContainsString('name="_' . $right . '[', $html);
        }
    }

    public function testTabIsRefusedWithoutTheCoreProfileRight(): void
    {
        $_SESSION['glpiactiveprofile']['profile'] = 0;

        $this->assertSame('', (new Profile())->getTabNameForItem($this->superAdminProfile()));
        $this->assertSame([false, ''], $this->tabContent($this->superAdminProfile()));
    }

    public function testTabIsRefusedWithoutAccessToThePlugin(): void
    {
        $_SESSION['glpiactiveprofile'][Profile::RIGHT_PROFILE] = 0;
        $_SESSION['glpiactiveprofile'][Profile::RIGHT_CONFIG] = 0;

        $this->assertSame('', (new Profile())->getTabNameForItem($this->superAdminProfile()));
        $this->assertSame([false, ''], $this->tabContent($this->superAdminProfile()));
    }
}
