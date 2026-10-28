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

use GlpiPlugin\Configurationglpiauto\Audit\AuditCheckInterface;
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\ParkAudit;
use State;

/**
 * Issue #306 : matériel incomplet — sans statut, sans lieu, ou au statut « Attribué » sans
 * utilisateur. Silencieux si aucun statut n'existe (NoStatesConfiguredCheck couvre ce cas).
 *
 * Correction : un ticket groupé, lié à chaque matériel concerné. Rien n'est complété à la place
 * de l'équipe : le bon statut, le bon lieu ou le bon utilisateur ne se devinent pas.
 */
final class IncompleteAssetsCheck implements AuditCheckInterface
{
    public const TICKET_PREFIX = '[Audit] Matériel à compléter';

    public function getKey(): string
    {
        return 'incomplete_assets';
    }

    public function getDomain(): string
    {
        return __('Parc', 'configurationglpiauto');
    }

    /**
     * @return list<array{itemtype: string, row: array<string, mixed>, missing: list<string>}>
     */
    public static function findItems(): array
    {
        global $DB;

        if (countElementsInTable(State::getTable()) === 0) {
            return [];
        }
        $assigned = [];
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => State::getTable(), 'WHERE' => ['name' => 'Attribué']]) as $row) {
            $assigned[] = (int) $row['id'];
        }

        $found = [];
        foreach (ParkAudit::ITEMTYPES as $itemtype) {
            $table = $itemtype::getTable();
            $alerts = [["$table.states_id" => 0], ["$table.locations_id" => 0]];
            if ($assigned !== []) {
                $alerts[] = ["$table.states_id" => $assigned, "$table.users_id" => 0];
            }
            $rows = $DB->request([
                'SELECT' => ["$table.id", "$table.name", "$table.states_id", "$table.locations_id", "$table.users_id"],
                'FROM'   => $table,
                'WHERE'  => array_merge(ParkAudit::inServiceCriteria($itemtype), [['OR' => $alerts]]),
                'ORDER'  => "$table.name",
            ]);
            foreach ($rows as $row) {
                $missing = [];
                if ((int) $row['states_id'] === 0) {
                    $missing[] = __('statut', 'configurationglpiauto');
                }
                if ((int) $row['locations_id'] === 0) {
                    $missing[] = __('lieu', 'configurationglpiauto');
                }
                if (in_array((int) $row['states_id'], $assigned, true) && (int) $row['users_id'] === 0) {
                    $missing[] = __('utilisateur (statut « Attribué »)', 'configurationglpiauto');
                }
                $found[] = ['itemtype' => $itemtype, 'row' => $row, 'missing' => $missing];
            }
        }

        return $found;
    }

    public function analyze(): array
    {
        $items = self::findItems();
        if ($items === []) {
            return [];
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_INFO,
            title: sprintf(_n('%d matériel à compléter', '%d matériels à compléter', count($items), 'configurationglpiauto'), count($items)),
            description: ParkAudit::summarize(array_map(
                static fn (array $i): string => sprintf(__('%1$s (sans %2$s)', 'configurationglpiauto'), $i['row']['name'] ?: '#' . $i['row']['id'], implode(', ', $i['missing'])),
                $items
            )),
            recommendation: __(
                'Sans statut, sans lieu ou « Attribué » sans utilisateur : ce matériel échappe aux recherches et aux rapports. « Créer les tickets » ouvre un ticket regroupant ces matériels, lié à chacun. Rien n\'est complété automatiquement.',
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
        $toLink = [];
        $lines = [];
        foreach (self::findItems() as $i) {
            if (ParkAudit::openTicketFor($i['itemtype'], (int) $i['row']['id'], self::TICKET_PREFIX) !== null) {
                continue;
            }
            $toLink[] = ['itemtype' => $i['itemtype'], 'items_id' => (int) $i['row']['id']];
            $lines[] = sprintf(
                __('%1$s : sans %2$s', 'configurationglpiauto'),
                ParkAudit::label($i['itemtype'], $i['row']),
                implode(', ', $i['missing'])
            );
        }
        if ($toLink === []) {
            ParkAudit::reportCreated(0);
            return;
        }
        $html = ParkAudit::ticketContent(
            __('Ces matériels sont incomplets dans GLPI :', 'configurationglpiauto'),
            $lines,
            __('Complétez chaque fiche (statut, lieu, utilisateur). Ce ticket a été ouvert par le mode Audit de Configuration GLPI Auto : aucune donnée du matériel n\'a été modifiée.', 'configurationglpiauto')
        );
        ParkAudit::reportCreated(ParkAudit::openTicket(self::TICKET_PREFIX . ' (' . date('Y-m-d') . ')', $html, $toLink, 0) > 0 ? 1 : 0);
    }
}
