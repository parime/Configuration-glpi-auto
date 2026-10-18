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

namespace GlpiPlugin\Configurationglpiauto\Audit;

use CommonDBTM;
use CommonITILObject;
use Computer;
use Html;
use Item_Ticket;
use Monitor;
use NetworkEquipment;
use Peripheral;
use Phone;
use Printer;
use Session;
use State;
use Ticket;

/**
 * Issue #306 : socle commun des contrôles du parc (machines qui ne remontent plus, garanties,
 * matériel incomplet).
 *
 * Règle du projet : la correction ne modifie jamais le matériel (statut, utilisateur, lieu) ; elle
 * ouvre des tickets liés au matériel, que l'équipe traite. Un matériel déjà lié à un ticket ouvert
 * du même contrôle (même préfixe de titre) n'en reçoit pas un second.
 */
final class ParkAudit
{
    /** Types de matériel contrôlés. */
    public const ITEMTYPES = [Computer::class, Monitor::class, NetworkEquipment::class, Peripheral::class, Phone::class, Printer::class];

    /**
     * Statuts « hors parc » (créés par l'assistant, StateBuilder) : un matériel en stock, donné,
     * vendu, détruit, volé ou rendu au fournisseur n'a ni à remonter à l'inventaire ni à être
     * garanti.
     */
    public const OUT_OF_SERVICE_STATES = ['En stock', 'Donné', 'Vendu', 'Détruit', 'Volé / perdu', 'Retour fournisseur'];

    /** Nombre de matériels cités dans la description d'un constat. */
    public const LISTED = 8;

    /**
     * @return list<int>
     */
    public static function outOfServiceStateIds(): array
    {
        global $DB;

        $ids = [];
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => State::getTable(), 'WHERE' => ['name' => self::OUT_OF_SERVICE_STATES]]) as $row) {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * Critères communs : matériel réel (ni modèle ni corbeille), hors statuts « hors parc ».
     *
     * @param class-string<CommonDBTM> $itemtype
     * @return array<int|string, mixed>
     */
    public static function inServiceCriteria(string $itemtype): array
    {
        $table = $itemtype::getTable();
        $where = ["$table.is_deleted" => 0, "$table.is_template" => 0];
        $out = self::outOfServiceStateIds();
        if ($out !== []) {
            $where[] = ['NOT' => ["$table.states_id" => $out]];
        }

        return $where;
    }

    /**
     * Résumé « A, B, C… et N autre(s) » pour la description d'un constat.
     *
     * @param list<string> $labels
     */
    public static function summarize(array $labels): string
    {
        $shown = array_slice($labels, 0, self::LISTED);
        $text = implode(', ', $shown);
        if (count($labels) > self::LISTED) {
            $text .= ' ' . sprintf(__('… et %d autre(s)', 'configurationglpiauto'), count($labels) - self::LISTED);
        }

        return $text;
    }

    /**
     * Ticket ouvert (ni résolu ni clos) lié à ce matériel, dont le titre commence par $prefix.
     */
    public static function openTicketFor(string $itemtype, int $itemsId, string $prefix): ?int
    {
        global $DB;

        $row = $DB->request([
            'SELECT'     => [Ticket::getTable() . '.id'],
            'FROM'       => Ticket::getTable(),
            'INNER JOIN' => [Item_Ticket::getTable() => ['FKEY' => [Item_Ticket::getTable() => 'tickets_id', Ticket::getTable() => 'id']]],
            'WHERE'      => [
                Item_Ticket::getTable() . '.itemtype' => $itemtype,
                Item_Ticket::getTable() . '.items_id' => $itemsId,
                Ticket::getTable() . '.is_deleted'    => 0,
                Ticket::getTable() . '.name'          => ['LIKE', $prefix . '%'],
                'NOT'                                 => [Ticket::getTable() . '.status' => [CommonITILObject::SOLVED, CommonITILObject::CLOSED]],
            ],
            'LIMIT'      => 1,
        ])->current();

        return $row === null ? null : (int) $row['id'];
    }

    /**
     * Ticket de demande lié aux matériels donnés.
     *
     * @param list<array{itemtype: string, items_id: int}> $items
     */
    public static function openTicket(string $name, string $html, array $items, int $entitiesId): int
    {
        $input = [
            'name'        => $name,
            'content'     => $html,
            'type'        => Ticket::DEMAND_TYPE,
            'entities_id' => $entitiesId,
        ];
        $requester = (int) Session::getLoginUserID();
        if ($requester > 0) {
            $input['_users_id_requester'] = $requester;
        }
        $messagesBefore = $_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [];
        $ticketsId = (int) (new Ticket())->add($input);
        $_SESSION['MESSAGE_AFTER_REDIRECT'] = $messagesBefore;
        if ($ticketsId > 0) {
            self::linkItems($ticketsId, $items);
        }

        return $ticketsId;
    }

    /**
     * @param list<array{itemtype: string, items_id: int}> $items
     */
    public static function linkItems(int $ticketsId, array $items): void
    {
        foreach ($items as $item) {
            $link = ['tickets_id' => $ticketsId, 'itemtype' => $item['itemtype'], 'items_id' => $item['items_id']];
            if (countElementsInTable(Item_Ticket::getTable(), $link) === 0) {
                (new Item_Ticket())->add($link);
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function label(string $itemtype, array $row): string
    {
        return $itemtype::getTypeName(1) . ' « ' . ((string) ($row['name'] ?? '') !== '' ? $row['name'] : '#' . $row['id']) . ' »';
    }

    public static function date(?string $value): string
    {
        return $value === null || $value === '' ? '—' : (string) Html::convDate(substr($value, 0, 10));
    }

    /** Message de fin de correction (nombre de tickets ouverts). */
    public static function reportCreated(int $created): void
    {
        Session::addMessageAfterRedirect($created > 0
            ? sprintf(_n('%d ticket ouvert.', '%d tickets ouverts.', $created, 'configurationglpiauto'), $created)
            : __('Aucun nouveau ticket : les matériels concernés ont déjà un ticket ouvert.', 'configurationglpiauto'));
    }
}
