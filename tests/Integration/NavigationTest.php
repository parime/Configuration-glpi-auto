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

use CronTask;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Configurationglpiauto\Audit\AuditReportCron;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigSettings;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\FuelType;
use GlpiPlugin\Configurationglpiauto\UsageTabs;
use PHPUnit\Framework\TestCase;

/**
 * Issue #495 : réglages dans la page de configuration du plugin (en onglets), usage sous une seule
 * entrée de menu avec des onglets communs, types de carburant dans les intitulés. Aucune assertion
 * sur un libellé traduit (la CI tourne en anglais).
 */
final class NavigationTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testConfigurePointsToTheSettingsPage(): void
    {
        global $PLUGIN_HOOKS;

        if (!isset($PLUGIN_HOOKS['config_page']['configurationglpiauto']) && function_exists('plugin_init_configurationglpiauto')) {
            plugin_init_configurationglpiauto();
        }
        $this->assertSame('front/config.php', $PLUGIN_HOOKS['config_page']['configurationglpiauto'] ?? null);
        $this->assertFileExists(self::ROOT . '/front/config.php');
    }

    public function testMenuEntryListsEveryUsageScreen(): void
    {
        $menu = ConfigurationProfile::getMenuContent();
        $this->assertIsArray($menu);
        $this->assertStringEndsWith('/front/wizard.php', (string) $menu['page']);
        $this->assertNotSame(ConfigurationProfile::getTypeName(2), $menu['title'], 'l\'entrée porte le nom du plugin');

        $pages = array_map(static fn (array $option): string => basename((string) $option['page']), $menu['options']);
        foreach (['profile.php', 'history.php', 'audit.php', 'addressplan.php', 'jiraimport.php', 'csvimport.php'] as $page) {
            $this->assertContains($page, $pages);
            $this->assertFileExists(self::ROOT . '/front/' . $page);
        }
    }

    public function testUsageTabsStayUnderSevenAndPointToExistingPages(): void
    {
        $tabs = UsageTabs::tabs();
        $this->assertLessThanOrEqual(7, count($tabs));
        foreach ($tabs as [, $page]) {
            $this->assertFileExists(self::ROOT . '/front/' . $page);
        }

        // Super-administrateur : tous les onglets, un seul actif.
        $visible = UsageTabs::visible('audit');
        $this->assertCount(count($tabs), $visible);
        $active = array_values(array_filter($visible, static fn (array $tab): bool => $tab['active']));
        $this->assertCount(1, $active);
        $this->assertSame('audit', $active[0]['key']);
    }

    public function testUsageTabsRenderAsAnAccessibleTabBar(): void
    {
        $html = TemplateRenderer::getInstance()->render('@configurationglpiauto/_usage_tabs.html.twig', [
            'usage_tabs'  => UsageTabs::visible('import'),
            'import_page' => 'csvimport.php',
            'jira_url'    => '/plugins/configurationglpiauto/front/jiraimport.php',
            'csv_url'     => '/plugins/configurationglpiauto/front/csvimport.php',
        ]);

        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertSame(count(UsageTabs::tabs()), substr_count($html, 'role="tab"'));
        $this->assertSame(1, substr_count($html, 'aria-selected="true"'));
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    public function testSettingsPageTabsRenderWithoutAnyOperationalAction(): void
    {
        foreach (array_keys(ConfigSettings::tabs()) as $tab) {
            $html = TemplateRenderer::getInstance()->render('@configurationglpiauto/config.html.twig', ConfigSettings::view($tab));

            $this->assertStringContainsString('role="tablist"', $html);
            $this->assertStringNotContainsString('send_report_now', $html);
            $this->assertStringNotContainsString('refresh_eol', $html);
            $this->assertStringNotContainsString('front/audit.php', $html);
        }
        $this->assertStringContainsString('configurationglpiauto-plugin-version', TemplateRenderer::getInstance()->render(
            '@configurationglpiauto/config.html.twig',
            ConfigSettings::view(ConfigSettings::TAB_GENERAL)
        ));
        $this->assertStringContainsString('name="save_park"', TemplateRenderer::getInstance()->render(
            '@configurationglpiauto/config.html.twig',
            ConfigSettings::view(ConfigSettings::TAB_AUDIT)
        ));
        $this->assertStringContainsString('name="save_eol"', TemplateRenderer::getInstance()->render(
            '@configurationglpiauto/config.html.twig',
            ConfigSettings::view(ConfigSettings::TAB_EOL)
        ));
    }

    public function testUnknownTabFallsBackToGeneral(): void
    {
        $this->assertSame(ConfigSettings::TAB_GENERAL, ConfigSettings::tab('<script>'));
        $this->assertStringEndsWith('/front/config.php?tab=eol', ConfigSettings::url(ConfigSettings::TAB_EOL));
    }

    public function testParkSettingsAreSavedFromTheSettingsPage(): void
    {
        $config = Config::getConfig();
        $before = [
            'audit_ghost_days'    => $config->fields['audit_ghost_days'],
            'audit_warranty_days' => $config->fields['audit_warranty_days'],
        ];

        try {
            $tab = ConfigSettings::save(['save_park' => 1, 'audit_ghost_days' => 45, 'audit_warranty_days' => 99999], []);

            $this->assertSame(ConfigSettings::TAB_AUDIT, $tab);
            $config = Config::getConfig();
            $this->assertSame(45, (int) $config->fields['audit_ghost_days']);
            $this->assertSame(3650, (int) $config->fields['audit_warranty_days'], 'borné à 10 ans');
        } finally {
            $config = Config::getConfig();
            $config->update(['id' => $config->getID()] + $before);
        }
    }

    public function testReportFrequencyDrivesTheAutomaticAction(): void
    {
        $task = new CronTask();
        if (!$task->getFromDBbyName(AuditReportCron::class, AuditReportCron::TASK_NAME)) {
            $this->markTestSkipped('action automatique du rapport absente');
        }
        $before = ['state' => $task->fields['state'], 'frequency' => $task->fields['frequency']];
        $config = Config::getConfig();
        $beforeConfig = [
            'audit_report_only_on_change' => $config->fields['audit_report_only_on_change'],
            'audit_report_emails'         => $config->fields['audit_report_emails'],
        ];

        try {
            $tab = ConfigSettings::save(['save_report' => 1, 'audit_report_frequency' => 'month', 'audit_report_only_on_change' => 1], []);

            $this->assertSame(ConfigSettings::TAB_AUDIT, $tab);
            $task->getFromDB($task->getID());
            $this->assertSame(CronTask::STATE_WAITING, (int) $task->fields['state']);
            $this->assertSame(MONTH_TIMESTAMP, (int) $task->fields['frequency']);
            $this->assertSame('month', ConfigSettings::view(ConfigSettings::TAB_AUDIT)['report']['frequency']);
        } finally {
            $task->update(['id' => $task->getID()] + $before);
            $config = Config::getConfig();
            $config->update(['id' => $config->getID()] + $beforeConfig);
        }
    }

    public function testEndOfLifeSettingsAreSavedFromTheSettingsPage(): void
    {
        $config = Config::getConfig();
        $before = [
            'audit_eol_enabled' => $config->fields['audit_eol_enabled'],
            'audit_eol_offline' => $config->fields['audit_eol_offline'],
            'audit_eol_days'    => $config->fields['audit_eol_days'],
            'audit_eol_mapping' => $config->fields['audit_eol_mapping'],
        ];

        try {
            $tab = ConfigSettings::save([
                'save_eol'          => 1,
                'audit_eol_enabled' => 1,
                'audit_eol_offline' => 1,
                'audit_eol_days'    => 90,
                'audit_eol_mapping' => 'Debian GNU/Linux = debian',
            ], []);

            $this->assertSame(ConfigSettings::TAB_EOL, $tab);
            $eol = ConfigSettings::view(ConfigSettings::TAB_EOL)['eol'];
            $this->assertTrue($eol['enabled']);
            $this->assertTrue($eol['offline']);
            $this->assertSame(90, $eol['days']);
            $this->assertSame('Debian GNU/Linux = debian', $eol['mapping']);
        } finally {
            $config = Config::getConfig();
            $config->update(['id' => $config->getID()] + $before);
        }
    }

    public function testFuelTypesAreListedInDropdowns(): void
    {
        if (!function_exists('plugin_configurationglpiauto_getDropdown')) {
            require_once self::ROOT . '/hook.php';
        }
        $this->assertArrayHasKey(FuelType::class, plugin_configurationglpiauto_getDropdown());
    }
}
