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

use Glpi\Dashboard\Dashboard;

/**
 * Guide ITIL complet (issue #124), volet "métriques et rapports" : GLPI cœur fournit déjà deux
 * cartes de tableau de bord natives sur la conformité SLA — "Nombre de tickets par statut SLA et
 * technicien"/"...et groupe de techniciens" (`bn_count_tickets_expired_by_tech`/
 * `bn_count_tickets_expired_by_tech_group`, confirmées dans `Glpi\Dashboard\Grid`) — mais aucun
 * tableau de bord livré par défaut (dont "Assistance") ne les affiche. Ce constructeur crée un
 * tableau de bord natif GLPI dédié qui les met en avant, sans dupliquer aucune logique de GLPI :
 * uniquement des cartes déjà existantes, assemblées différemment.
 *
 * Idempotence : `Dashboard::saveItems()` (cœur GLPI, confirmé en le lisant) fait un
 * `deleteChildrenAndRelationsFromDb()` puis recrée entièrement les items — un remplacement complet,
 * jamais une fusion. Rappeler `saveNew()` à chaque exécution de l'assistant écraserait donc
 * silencieusement toute personnalisation qu'un administrateur aurait faite sur ce tableau de bord
 * (widgets déplacés/ajoutés) — `build()` vérifie donc l'existence avant de créer, jamais après,
 * même principe que chaque autre `*Builder` de ce plugin (`getFromDBByCrit()` avant `add()`).
 *
 * La clé technique du tableau de bord (`KEY`) est calculée une fois pour toutes à partir d'un
 * intitulé fixe non traduit — jamais du nom affiché traduit (`__('Tableau de bord ITIL', ...)`) —
 * pour que la clé de recherche reste stable quelle que soit la langue de session au moment de la
 * création (`Dashboard::saveNew()` dérive sa clé du titre passé via `Toolbox::slugify()`).
 */
final class DashboardBuilder
{
    private const KEY = 'configuration-glpi-auto-itil-dashboard';

    public function build(): bool
    {
        if ((new Dashboard())->getFromDB(self::KEY)) {
            return false;
        }

        $items = [
            [
                'gridstack_id' => 'cga-itil-1',
                'card_id' => 'bn_count_tickets_late',
                'x' => 0, 'y' => 0, 'width' => 3, 'height' => 2,
                'card_options' => [],
            ],
            [
                'gridstack_id' => 'cga-itil-2',
                'card_id' => 'ticket_evolution',
                'x' => 3, 'y' => 0, 'width' => 12, 'height' => 6,
                'card_options' => [],
            ],
            [
                'gridstack_id' => 'cga-itil-3',
                'card_id' => 'top_ticket_ITILCategory',
                'x' => 15, 'y' => 0, 'width' => 6, 'height' => 6,
                'card_options' => [],
            ],
            [
                'gridstack_id' => 'cga-itil-4',
                'card_id' => 'bn_count_tickets_expired_by_tech',
                'x' => 0, 'y' => 6, 'width' => 12, 'height' => 4,
                'card_options' => [],
            ],
            [
                'gridstack_id' => 'cga-itil-5',
                'card_id' => 'bn_count_tickets_expired_by_tech_group',
                'x' => 12, 'y' => 6, 'width' => 12, 'height' => 4,
                'card_options' => [],
            ],
        ];

        // Titre technique fixe (jamais traduit) pour que Toolbox::slugify() reproduise toujours
        // self::KEY, quelle que soit la langue active au moment de la création — voir la docblock
        // de cette classe. Renommé juste après vers le nom affiché réellement traduit, sans
        // toucher à la clé déjà enregistrée (Dashboard::saveTitle() ne modifie que `name`).
        $dashboard = new Dashboard();
        $dashboard->saveNew('Configuration GLPI Auto - ITIL Dashboard', 'core', $items);
        $dashboard->saveTitle(__('Tableau de bord ITIL', 'configurationglpiauto'));

        return true;
    }
}
