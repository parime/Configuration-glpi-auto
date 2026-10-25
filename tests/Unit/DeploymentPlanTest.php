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

namespace GlpiPlugin\Configurationglpiauto\Tests\Unit;

use GlpiPlugin\Configurationglpiauto\Blueprint\DeploymentPlan;
use PHPUnit\Framework\TestCase;

/**
 * Issue #338 : champs transmis au déploiement direct d'une configuration chargée.
 */
final class DeploymentPlanTest extends TestCase
{
    public function testFormFieldsSkipTechnicalAndEmptyValues(): void
    {
        $fields = DeploymentPlan::formFields([
            'id'               => 1,
            'date_mod'         => '2026-10-25 10:00:00',
            'date_creation'    => null,
            'sla_enabled'      => 1,
            'entity_tree'      => '[{"name":"Client démo"}]',
            'smtp_host'        => null,
            'calendar_enabled' => 0,
        ]);

        $this->assertSame([
            'calendar_enabled' => '0',
            'entity_tree'      => '[{"name":"Client démo"}]',
            'sla_enabled'      => '1',
        ], $fields);
    }
}
