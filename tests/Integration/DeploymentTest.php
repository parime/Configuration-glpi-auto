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

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Configurationglpiauto\Blueprint\DeploymentPlan;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\DeploymentRights;
use PHPUnit\Framework\TestCase;

/**
 * Issue #338 : récapitulatif du déploiement direct d'un Blueprint importé.
 */
final class DeploymentTest extends TestCase
{
    public function testEnabledFeaturesAreGroupedWithTranslatedLabels(): void
    {
        $groups = DeploymentPlan::enabledGroups([
            'sla_enabled'        => 1,
            'calendar_enabled'   => '1',
            'locations_enabled'  => 0,
            'sla_update_enabled' => 1,
            'sla_tiers'          => '[]',
        ]);

        $this->assertSame([__('Calendrier', 'configurationglpiauto'), __('SLA', 'configurationglpiauto')], array_keys($groups));
        $this->assertSame(['sla_enabled', 'sla_update_enabled'], $groups[__('SLA', 'configurationglpiauto')]);
    }

    public function testProfileFormUrlPointsToAnExistingPage(): void
    {
        // Retour après import d'un Blueprint : la page déduite du nom de classe n'existe pas.
        $url = \GlpiPlugin\Configurationglpiauto\ConfigurationProfile::getFormURLWithID(7, false);
        $this->assertSame('/plugins/configurationglpiauto/front/profile.form.php?id=7', $url);
        $this->assertFileExists(dirname(__DIR__, 2) . '/front/profile.form.php');
    }

    public function testSuperAdminHasEveryNativeRight(): void
    {
        $rights = DeploymentRights::check();
        $this->assertNotEmpty($rights);
        $this->assertSame([], array_values(array_filter($rights, static fn (array $r): bool => !$r['ok'])));
    }

    public function testSummaryPostsTheLoadedConfigurationToTheWizardFinishStep(): void
    {
        $fields = Config::getConfig()->fields;
        $html = TemplateRenderer::getInstance()->render('@configurationglpiauto/deploy.html.twig', [
            'fields'      => DeploymentPlan::formFields($fields),
            'groups'      => DeploymentPlan::enabledGroups($fields),
            'entities'    => 2,
            'rights'      => [['label' => 'Droit fictif', 'ok' => false, 'hint' => 'indice']],
            'wizard_url'  => '/plugins/configurationglpiauto/front/wizard.php',
            'restore_url' => '/plugins/configurationglpiauto/front/profile.php',
        ]);

        $this->assertStringContainsString('action="/plugins/configurationglpiauto/front/wizard.php"', $html);
        $this->assertStringContainsString('name="finish"', $html);
        $this->assertStringContainsString('name="entity_tree"', $html);
        $this->assertStringNotContainsString('name="id"', $html);
        $this->assertStringContainsString('Droit fictif', $html);
    }
}
