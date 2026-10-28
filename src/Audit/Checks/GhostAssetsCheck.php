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

namespace GlpiPlugin\Configurationglpiauto\Audit\Checks;

use Computer;
use GlpiPlugin\Configurationglpiauto\Audit\AuditCheckInterface;
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\ParkAudit;
use GlpiPlugin\Configurationglpiauto\Config;
use NetworkEquipment;
use Printer;

/**
 * Issue #306 : matériel inventorié qui ne remonte plus depuis X jours (Config::audit_ghost_days,
 * 0 = contrôle désactivé) — perdu, volé, agent en panne, ou sorti du parc sans être déclaré.
 * Hors statuts « hors parc » (en stock, donné, vendu, détruit...).
 *
 * Correction : un ticket par matériel, lié à lui. Rien n'est modifié sur le matériel.
 */
final class GhostAssetsCheck implements AuditCheckInterface
{
    public const TICKET_PREFIX = '[Audit] Ne remonte plus à l\'inventaire';

    /** Types alimentés par l'inventaire (agent, découverte réseau). */
    private const ITEMTYPES = [Computer::class, NetworkEquipment::class, Printer::class];

    public function getKey(): string
    {
        return 'ghost_assets';
    }

    public function getDomain(): string
    {
        return __('Parc', 'configurationglpiauto');
    }

    public static function days(): int
    {
        return (int) (Config::getConfig()->fields['audit_ghost_days'] ?? 30);
    }

    /**
     * @return list<array{itemtype: string, row: array<string, mixed>}>
     */
    public static function findGhosts(): array
    {
        global $DB;

        $days = self::days();
        if ($days <= 0) {
            return [];
        }
        $limit = date('Y-m-d H:i:s', strtotime("-$days days"));
        $ghosts = [];
        foreach (self::ITEMTYPES as $itemtype) {
            $table = $itemtype::getTable();
            if (!$DB->fieldExists($table, 'last_inventory_update')) {
                continue;
            }
            $rows = $DB->request([
                'SELECT' => ["$table.id", "$table.name", "$table.entities_id", "$table.users_id", "$table.last_inventory_update"],
                'FROM'   => $table,
                'WHERE'  => array_merge(ParkAudit::inServiceCriteria($itemtype), [
                    "$table.is_dynamic"            => 1,
                    "$table.last_inventory_update" => ['<', $limit],
                ]),
                'ORDER'  => "$table.last_inventory_update ASC",
            ]);
            foreach ($rows as $row) {
                $ghosts[] = ['itemtype' => $itemtype, 'row' => $row];
            }
        }

        return $ghosts;
    }

    public function analyze(): array
    {
        $ghosts = self::findGhosts();
        if ($ghosts === []) {
            return [];
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_WARNING,
            title: sprintf(
                _n('%1$d matériel ne remonte plus à l\'inventaire depuis %2$d jours', '%1$d matériels ne remontent plus à l\'inventaire depuis %2$d jours', count($ghosts), 'configurationglpiauto'),
                count($ghosts),
                self::days()
            ),
            description: ParkAudit::summarize(array_map(
                static fn (array $g): string => sprintf(__('%1$s (dernier inventaire le %2$s)', 'configurationglpiauto'), $g['row']['name'] ?: '#' . $g['row']['id'], ParkAudit::date($g['row']['last_inventory_update'])),
                $ghosts
            )),
            recommendation: __(
                'Perdu, volé, en panne, ou sorti du parc sans être déclaré ? « Créer les tickets » ouvre un ticket par matériel, lié à lui, pour que l\'équipe vérifie. Rien n\'est modifié sur le matériel. Le délai se règle plus bas, dans « Contrôles du parc ».',
                'configurationglpiauto'
            ),
            fixable: true,
            fixLabel: __('Créer les tickets', 'configurationglpiauto')
        )];
    }

    public function canFix(): bool
    {
        return true;
    }

    public function fix(): void
    {
        $created = 0;
        foreach (self::findGhosts() as $ghost) {
            $itemtype = $ghost['itemtype'];
            $row = $ghost['row'];
            if (ParkAudit::openTicketFor($itemtype, (int) $row['id'], self::TICKET_PREFIX) !== null) {
                continue;
            }
            $user = (int) $row['users_id'] > 0 ? getUserName((int) $row['users_id']) : __('personne', 'configurationglpiauto');
            $html = ParkAudit::ticketContent(
                sprintf(
                    __('%1$s ne remonte plus à l\'inventaire depuis le %2$s. Utilisateur dans GLPI : %3$s.', 'configurationglpiauto'),
                    ParkAudit::label($itemtype, $row),
                    ParkAudit::date($row['last_inventory_update']),
                    $user
                ),
                [],
                __('Vérifiez si le matériel est perdu, volé, en panne, ou sorti du parc sans être déclaré, puis mettez à jour sa fiche. Ce ticket a été ouvert par le mode Audit de Configuration GLPI Auto : aucune donnée du matériel n\'a été modifiée.', 'configurationglpiauto')
            );
            $ticketsId = ParkAudit::openTicket(
                self::TICKET_PREFIX . ' : ' . ($row['name'] ?: '#' . $row['id']),
                $html,
                [['itemtype' => $itemtype, 'items_id' => (int) $row['id']]],
                (int) $row['entities_id']
            );
            if ($ticketsId > 0) {
                $created++;
            }
        }
        ParkAudit::reportCreated($created);
    }
}
