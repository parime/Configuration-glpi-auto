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

use Config as CoreConfig;
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\StaffMovement;
use GlpiPlugin\Configurationglpiauto\TaskTemplateBuilder;
use ITILCategory;
use PHPUnit\Framework\TestCase;
use TaskTemplate;

/**
 * grcmanager #362 : identifiants des gabarits et catégories d'arrivée et de départ, lus par
 * assetsign sans dépendre du nom affiché.
 */
final class StaffMovementTest extends TestCase
{
    protected function tearDown(): void
    {
        StaffMovement::uninstall();
    }

    public function testBuildersRecordTheArrivalAndDepartureIds(): void
    {
        StaffMovement::uninstall();
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'task_templates_enabled' => 1,
            'category_enabled'       => 1,
        ]);
        $config->fields['category_branches'] = $config->fields['category_branches'] ?? '';
        (new TaskTemplateBuilder())->build($config);
        (new CategoryBuilder())->build($config);

        $ids = StaffMovement::ids();
        foreach (['arrival', 'departure'] as $event) {
            $template = new TaskTemplate();
            $this->assertTrue($template->getFromDB($ids[$event]['tasktemplates_id']), $event);
        }
        $category = new ITILCategory();
        if ($ids['arrival']['itilcategories_id'] > 0) {
            $this->assertTrue($category->getFromDB($ids['arrival']['itilcategories_id']));
            $this->assertStringContainsString('Onboarding', $category->fields['name']);
        }

        // Contrat lu par assetsign : clés de configuration stables.
        $values = CoreConfig::getConfigurationValues('plugin:configurationglpiauto', ['arrival_tasktemplates_id']);
        $this->assertSame($ids['arrival']['tasktemplates_id'], (int) $values['arrival_tasktemplates_id']);
    }

    public function testExistingObjectsAreRecordedOnceAndNeverReplaced(): void
    {
        StaffMovement::uninstall();
        $template = new TaskTemplate();
        $found = $template->getFromDBByCrit(['name' => 'Offboarding : Départ collaborateur']);
        if (!$found) {
            $this->assertGreaterThan(0, (int) $template->add([
                'name' => 'Offboarding : Départ collaborateur', 'content' => 'Test', 'entities_id' => 0, 'is_recursive' => 1,
            ]));
        }

        StaffMovement::recordExisting();
        $this->assertSame((int) $template->getID(), StaffMovement::ids()['departure']['tasktemplates_id']);

        // Un identifiant enregistré n'est pas remplacé par un objet homonyme créé ensuite.
        $homonym = new TaskTemplate();
        $homonymId = (int) $homonym->add([
            'name' => 'Offboarding : Départ collaborateur', 'content' => 'Copie', 'entities_id' => 0, 'is_recursive' => 1,
        ]);
        StaffMovement::recordExisting();
        $stored = CoreConfig::getConfigurationValues('plugin:configurationglpiauto', ['departure_tasktemplates_id']);
        $this->assertNotSame($homonymId, (int) $stored['departure_tasktemplates_id']);
        $homonym->delete(['id' => $homonymId], true);

        // Objet supprimé : l'identifiant enregistré ne pointe plus vers rien, 0 est renvoyé.
        StaffMovement::record('departure', TaskTemplate::class, 999999999);
        $this->assertSame(0, StaffMovement::ids()['departure']['tasktemplates_id']);
    }
}
