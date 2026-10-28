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
use GlpiPlugin\Configurationglpiauto\Config;
use Infocom;

/**
 * Issue #306 : garantie expirée, ou qui expire dans les X prochains jours
 * (Config::audit_warranty_days, 0 = contrôle désactivé), sur du matériel en service. Fin de
 * garantie = date de début de garantie + durée en mois (onglet Gestion du matériel) ; une garantie
 * « à vie » (durée négative dans GLPI) n'expire pas.
 *
 * Correction : un ticket groupé, lié à chaque matériel concerné (ou complété s'il est déjà ouvert).
 */
final class WarrantyCheck implements AuditCheckInterface
{
    public const TICKET_PREFIX = '[Audit] Garanties expirées ou bientôt expirées';

    public function getKey(): string
    {
        return 'warranty_expiring';
    }

    public function getDomain(): string
    {
        return __('Parc', 'configurationglpiauto');
    }

    public static function days(): int
    {
        return (int) (Config::getConfig()->fields['audit_warranty_days'] ?? 30);
    }

    /**
     * @return list<array{itemtype: string, row: array<string, mixed>, end: string}> fin la plus proche d'abord
     */
    public static function findItems(): array
    {
        global $DB;

        $days = self::days();
        if ($days <= 0) {
            return [];
        }
        $horizon = date('Y-m-d', strtotime("+$days days"));
        $found = [];
        foreach (ParkAudit::ITEMTYPES as $itemtype) {
            $table = $itemtype::getTable();
            $infocoms = Infocom::getTable();
            $rows = $DB->request([
                'SELECT'     => ["$table.id", "$table.name", "$table.entities_id", "$infocoms.warranty_date", "$infocoms.warranty_duration"],
                'FROM'       => $infocoms,
                'INNER JOIN' => [$table => ['FKEY' => [$infocoms => 'items_id', $table => 'id']]],
                'WHERE'      => array_merge(ParkAudit::inServiceCriteria($itemtype), [
                    "$infocoms.itemtype"          => $itemtype,
                    "$infocoms.warranty_duration" => ['>', 0],
                    'NOT'                         => ["$infocoms.warranty_date" => null],
                ]),
            ]);
            foreach ($rows as $row) {
                $end = date('Y-m-d', strtotime(sprintf('%s +%d months', $row['warranty_date'], (int) $row['warranty_duration'])));
                if ($end <= $horizon) {
                    $found[] = ['itemtype' => $itemtype, 'row' => $row, 'end' => $end];
                }
            }
        }
        usort($found, static fn (array $a, array $b): int => strcmp($a['end'], $b['end']));

        return $found;
    }

    public function analyze(): array
    {
        $items = self::findItems();
        if ($items === []) {
            return [];
        }
        $today = date('Y-m-d');
        $expired = count(array_filter($items, static fn (array $i): bool => $i['end'] < $today));

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_INFO,
            title: sprintf(
                __('Garanties : %1$d expirée(s), %2$d expirent dans les %3$d jours', 'configurationglpiauto'),
                $expired,
                count($items) - $expired,
                self::days()
            ),
            description: ParkAudit::summarize(array_map(
                static fn (array $i): string => sprintf(__('%1$s (fin le %2$s)', 'configurationglpiauto'), $i['row']['name'] ?: '#' . $i['row']['id'], ParkAudit::date($i['end'])),
                $items
            )),
            recommendation: __(
                'Prolonger la garantie, prévoir le remplacement, ou accepter le risque ? « Créer les tickets » ouvre un ticket regroupant ces matériels, lié à chacun. Rien n\'est modifié sur le matériel. Le délai se règle plus bas, dans « Contrôles du parc ».',
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
                __('%1$s : fin de garantie le %2$s', 'configurationglpiauto'),
                ParkAudit::label($i['itemtype'], $i['row']),
                ParkAudit::date($i['end'])
            );
        }
        if ($toLink === []) {
            ParkAudit::reportCreated(0);
            return;
        }
        $html = ParkAudit::ticketContent(
            __('Ces matériels en service ont une garantie expirée ou qui expire bientôt :', 'configurationglpiauto'),
            $lines,
            __('Pour chacun : prolonger la garantie, prévoir le remplacement, ou accepter le risque. Ce ticket a été ouvert par le mode Audit de Configuration GLPI Auto : aucune donnée du matériel n\'a été modifiée.', 'configurationglpiauto')
        );
        ParkAudit::reportCreated(ParkAudit::openTicket(self::TICKET_PREFIX . ' (' . date('Y-m-d') . ')', $html, $toLink, 0) > 0 ? 1 : 0);
    }
}
