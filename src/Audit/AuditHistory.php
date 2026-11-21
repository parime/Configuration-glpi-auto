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

use Html;
use Session;

/**
 * Issue #466 : historique des audits (une photo par jour : note et statut de chaque contrôle),
 * pour comparer deux audits. Écrit à l'affichage de la page d'audit et par le rapport périodique ;
 * ne contient que des statuts, aucun constat détaillé ni valeur de configuration.
 */
final class AuditHistory
{
    public const TABLE = 'glpi_plugin_configurationglpiauto_auditruns';

    /** Nombre de photos conservées (les plus anciennes sont effacées au-delà). */
    public const KEEP = 400;

    /**
     * Enregistre la photo du jour (remplace celle déjà prise aujourd'hui).
     *
     * @param array<string, string> $statuses
     */
    public static function record(?int $score, array $statuses, string $source): int
    {
        global $DB;

        if (!$DB->tableExists(self::TABLE)) {
            return 0;
        }

        $now    = date('Y-m-d H:i:s');
        $fields = [
            'date_creation' => $now,
            'users_id'      => (int) Session::getLoginUserID(),
            'source'        => $source,
            'score'         => $score,
            'statuses'      => (string) json_encode($statuses),
        ];

        $todayId = 0;
        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::TABLE,
            'WHERE'  => ['date_creation' => ['>=', date('Y-m-d 00:00:00')]],
            'ORDER'  => ['id DESC'],
            'LIMIT'  => 1,
        ]) as $row) {
            $todayId = (int) $row['id'];
        }

        if ($todayId > 0) {
            $DB->update(self::TABLE, $fields, ['id' => $todayId]);
        } else {
            $DB->insert(self::TABLE, $fields);
            $todayId = (int) $DB->insertId();
            self::purge();
        }

        return $todayId;
    }

    private static function purge(): void
    {
        global $DB;

        $ids = [];
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => self::TABLE, 'ORDER' => ['id DESC'], 'START' => self::KEEP, 'LIMIT' => 1000]) as $row) {
            $ids[] = (int) $row['id'];
        }
        if ($ids !== []) {
            $DB->delete(self::TABLE, ['id' => $ids]);
        }
    }

    /**
     * Photos les plus récentes d'abord.
     *
     * @return list<array{id: int, date: string, date_label: string, score: ?int, level: string, source: string, statuses: array<string, string>}>
     */
    public static function list(int $limit = 30): array
    {
        global $DB;

        if (!$DB->tableExists(self::TABLE)) {
            return [];
        }

        $runs = [];
        foreach ($DB->request(['FROM' => self::TABLE, 'ORDER' => ['date_creation DESC', 'id DESC'], 'LIMIT' => max(1, $limit)]) as $row) {
            $statuses = json_decode((string) $row['statuses'], true);
            $score    = $row['score'] === null ? null : (int) $row['score'];
            $runs[] = [
                'id'         => (int) $row['id'],
                'date'       => (string) $row['date_creation'],
                'date_label' => (string) Html::convDateTime($row['date_creation']),
                'score'      => $score,
                'level'      => HardeningScore::level($score),
                'source'     => (string) $row['source'],
                'statuses'   => is_array($statuses) ? array_map('strval', $statuses) : [],
            ];
        }

        return $runs;
    }

    /**
     * Comparaison de deux photos de la liste (l'ancienne puis la récente, quel que soit l'ordre
     * choisi). Null si l'une d'elles est absente.
     *
     * @param list<array{id: int, date: string, date_label: string, score: ?int, level: string, source: string, statuses: array<string, string>}> $runs
     * @return array<string, mixed>|null
     */
    public static function comparison(array $runs, int $fromId, int $toId): ?array
    {
        $byId = [];
        foreach ($runs as $run) {
            $byId[$run['id']] = $run;
        }
        if (!isset($byId[$fromId], $byId[$toId]) || $fromId === $toId) {
            return null;
        }
        [$before, $after] = $byId[$fromId]['date'] <= $byId[$toId]['date']
            ? [$byId[$fromId], $byId[$toId]]
            : [$byId[$toId], $byId[$fromId]];

        $diff   = HardeningScore::compare($before['statuses'], $after['statuses']);
        $labels = [];
        foreach ($diff as $group => $keys) {
            $labels[$group] = array_map(AuditControlCatalog::label(...), $keys);
        }

        return [
            'before' => $before,
            'after'  => $after,
            'delta'  => $before['score'] !== null && $after['score'] !== null ? $after['score'] - $before['score'] : null,
            'groups' => $labels,
        ];
    }
}
