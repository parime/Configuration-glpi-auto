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

use Glpi\Dashboard\Dashboard;
use GlpiPlugin\Configurationglpiauto\DashboardBuilder;
use PHPUnit\Framework\TestCase;

/**
 * `Glpi\Dashboard\Dashboard` est un vrai objet natif GLPI partagé (visible dans le menu Tableaux
 * de bord de toute l'instance) — purgé dans `tearDown()`, même discipline que le reste de ce
 * plugin pour tout ce qui touche un objet réel plutôt qu'une fixture jetable propre au plugin.
 */
final class DashboardBuilderTest extends TestCase
{
    private const KEY = 'configuration-glpi-auto-itil-dashboard';

    protected function tearDown(): void
    {
        // Dashboard::getIndexName() returns "key" (not "id") — delete() looks up the row via
        // $input[getIndexName()], so it must be keyed 'key' here, never 'id', or it silently
        // matches nothing and returns false without deleting anything (confirmed the hard way:
        // an earlier version of this method left a real stray row on the shared instance).
        (new Dashboard())->delete(['key' => self::KEY], true);
    }

    public function testFirstCallCreatesARealDashboardWithTheExpectedCards(): void
    {
        $result = (new DashboardBuilder())->build();

        $this->assertTrue($result);

        $dashboard = new Dashboard();
        $this->assertTrue($dashboard->getFromDB(self::KEY));

        global $DB;
        $cardIds = [];
        foreach ($DB->request(['FROM' => 'glpi_dashboards_items', 'WHERE' => ['dashboards_dashboards_id' => $dashboard->getID()]]) as $row) {
            $cardIds[] = $row['card_id'];
        }
        $this->assertContains('bn_count_tickets_expired_by_tech', $cardIds);
        $this->assertContains('bn_count_tickets_expired_by_tech_group', $cardIds);
        $this->assertContains('ticket_evolution', $cardIds);
    }

    public function testSecondCallIsIdempotentAndReturnsFalse(): void
    {
        (new DashboardBuilder())->build();
        $result = (new DashboardBuilder())->build();

        $this->assertFalse($result);
    }

    /**
     * Non-régression directe sur le risque identifié dans la docblock de DashboardBuilder :
     * Dashboard::saveItems() remplace entièrement les items à chaque appel — build() ne doit donc
     * jamais rappeler saveNew()/saveItems() une fois le tableau de bord déjà créé, sous peine
     * d'écraser silencieusement une personnalisation de l'administrateur.
     */
    public function testSecondCallNeverOverwritesItemsCustomizedByAnAdministrator(): void
    {
        (new DashboardBuilder())->build();

        $dashboard = new Dashboard();
        $dashboard->getFromDB(self::KEY);
        $dashboard->saveItems([[
            'gridstack_id' => 'admin-custom',
            'card_id' => 'bn_count_Ticket',
            'x' => 0, 'y' => 0, 'width' => 3, 'height' => 2,
            'card_options' => [],
        ]]);

        (new DashboardBuilder())->build();

        global $DB;
        $cardIds = [];
        foreach ($DB->request(['FROM' => 'glpi_dashboards_items', 'WHERE' => ['dashboards_dashboards_id' => $dashboard->getID()]]) as $row) {
            $cardIds[] = $row['card_id'];
        }
        $this->assertSame(['bn_count_Ticket'], $cardIds, 'The administrator\'s own customization must survive a second wizard run.');
    }
}
