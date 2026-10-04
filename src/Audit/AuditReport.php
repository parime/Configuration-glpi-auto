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

/**
 * Issue #285 : synthèse pure (sans GLPI) d'une exécution de l'audit, pour le rapport périodique
 * envoyé par AuditReportCron. Un contrôle sans constat est « OK » ; un constat critique le met
 * « en échec » ; un constat d'avertissement ou d'information le met « en alerte ».
 *
 * Seuls le titre, le domaine, la gravité et la recommandation de chaque constat sont repris dans
 * le rapport, jamais la description détaillée : un e-mail sort de GLPI, il ne doit contenir que
 * des constats, aucun secret ni valeur de configuration.
 */
final class AuditReport
{
    public const STATUS_OK       = 'ok';
    public const STATUS_WARNING  = 'warning';
    public const STATUS_CRITICAL = 'critical';

    private const RANK = [self::STATUS_OK => 0, self::STATUS_WARNING => 1, self::STATUS_CRITICAL => 2];

    /**
     * @param list<string>       $checkKeys clé de chaque contrôle enregistré
     * @param list<AuditFinding> $findings  constats de l'exécution
     * @return array{
     *     statuses: array<string, string>,
     *     counts: array{ok: int, warning: int, critical: int, total: int},
     *     problems: list<array{severity: string, domain: string, title: string, recommendation: string}>
     * }
     */
    public static function build(array $checkKeys, array $findings): array
    {
        $statuses = array_fill_keys($checkKeys, self::STATUS_OK);
        $problems = [];

        foreach ($findings as $finding) {
            $status = $finding->severity === AuditFinding::SEVERITY_CRITICAL ? self::STATUS_CRITICAL : self::STATUS_WARNING;
            $current = $statuses[$finding->checkKey] ?? self::STATUS_OK;
            $statuses[$finding->checkKey] = self::RANK[$status] > self::RANK[$current] ? $status : $current;

            $problems[] = [
                'severity'       => $status,
                'domain'         => $finding->domain,
                'title'          => $finding->title,
                'recommendation' => $finding->recommendation,
            ];
        }

        usort($problems, static fn (array $a, array $b): int => self::RANK[$b['severity']] <=> self::RANK[$a['severity']]);

        $counts = array_count_values($statuses) + [self::STATUS_OK => 0, self::STATUS_WARNING => 0, self::STATUS_CRITICAL => 0];

        return [
            'statuses' => $statuses,
            'counts'   => [
                'ok'       => $counts[self::STATUS_OK],
                'warning'  => $counts[self::STATUS_WARNING],
                'critical' => $counts[self::STATUS_CRITICAL],
                'total'    => count($statuses),
            ],
            'problems' => $problems,
        ];
    }

    /**
     * Contrôles dégradés / améliorés depuis le rapport précédent (null = premier rapport).
     *
     * @param array<string, string>|null $previousStatuses
     * @param array<string, string>      $currentStatuses
     * @return array{first_report: bool, worsened: list<string>, improved: list<string>}
     */
    public static function evolution(?array $previousStatuses, array $currentStatuses): array
    {
        if ($previousStatuses === null) {
            return ['first_report' => true, 'worsened' => [], 'improved' => []];
        }

        $worsened = [];
        $improved = [];
        foreach ($currentStatuses as $key => $status) {
            $before = self::RANK[$previousStatuses[$key] ?? self::STATUS_OK] ?? 0;
            $now    = self::RANK[$status];
            if ($now > $before) {
                $worsened[] = $key;
            } elseif ($now < $before) {
                $improved[] = $key;
            }
        }

        return ['first_report' => false, 'worsened' => $worsened, 'improved' => $improved];
    }

    /**
     * Option « n'envoyer que s'il y a un changement ou une anomalie » : sans l'option, toujours
     * envoyer ; avec, envoyer au premier rapport, dès qu'un contrôle n'est pas OK, ou dès qu'un
     * statut a changé depuis le rapport précédent.
     *
     * @param array{statuses: array<string, string>, counts: array{ok: int, warning: int, critical: int, total: int}} $report
     * @param array<string, string>|null $previousStatuses
     */
    public static function shouldSend(bool $onlyOnChangeOrAnomaly, array $report, ?array $previousStatuses): bool
    {
        if (!$onlyOnChangeOrAnomaly || $previousStatuses === null) {
            return true;
        }

        return $report['counts']['warning'] + $report['counts']['critical'] > 0
            || $report['statuses'] != $previousStatuses;
    }
}
