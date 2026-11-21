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
 * Issue #466 : note de durcissement en pourcentage et comparaison entre deux audits. Calcul pur
 * (sans GLPI), testable seul ; les poids, libellés et contrôles non applicables viennent
 * d'AuditControlCatalog.
 *
 * Pondération : chaque contrôle applicable vaut son poids (3 sécurité, 2 exploitation, 1 qualité
 * des données). Un contrôle sans constat rapporte tout son poids, un contrôle en alerte la moitié,
 * un contrôle en échec rien. Une simple information ne retire aucun point. Note = points obtenus /
 * points possibles, arrondie à l'entier. Les contrôles non applicables (désactivés dans les
 * réglages) sortent du calcul, au numérateur comme au dénominateur.
 *
 * Lecture seule : rien n'est corrigé, la note ne fait que résumer les constats.
 */
final class HardeningScore
{
    public const STATUS_OK             = 'ok';
    public const STATUS_WARNING        = 'warning';
    public const STATUS_CRITICAL       = 'critical';
    public const STATUS_NOT_APPLICABLE = 'na';

    /** Part du poids obtenue selon le statut. */
    private const FACTOR = [self::STATUS_OK => 1.0, self::STATUS_WARNING => 0.5, self::STATUS_CRITICAL => 0.0];

    private const RANK = [self::STATUS_OK => 0, self::STATUS_WARNING => 1, self::STATUS_CRITICAL => 2];

    /**
     * Statut de chaque contrôle : le constat le plus grave l'emporte, une information seule laisse
     * le contrôle « OK », un contrôle non applicable est « na » même s'il a des constats.
     *
     * @param list<string>       $checkKeys
     * @param list<AuditFinding> $findings
     * @param list<string>       $notApplicable
     * @return array<string, string>
     */
    public static function statuses(array $checkKeys, array $findings, array $notApplicable = []): array
    {
        $statuses = array_fill_keys($checkKeys, self::STATUS_OK);
        foreach ($findings as $finding) {
            $status = match ($finding->severity) {
                AuditFinding::SEVERITY_CRITICAL => self::STATUS_CRITICAL,
                AuditFinding::SEVERITY_WARNING  => self::STATUS_WARNING,
                default                         => self::STATUS_OK,
            };
            $current = $statuses[$finding->checkKey] ?? self::STATUS_OK;
            $statuses[$finding->checkKey] = self::RANK[$status] > self::RANK[$current] ? $status : $current;
        }
        foreach ($notApplicable as $key) {
            if (isset($statuses[$key])) {
                $statuses[$key] = self::STATUS_NOT_APPLICABLE;
            }
        }

        return $statuses;
    }

    /**
     * Note sur 100, ou null si aucun contrôle n'est applicable.
     *
     * @param array<string, string> $statuses
     * @param array<string, int>    $weights  poids par contrôle (1 par défaut)
     */
    public static function score(array $statuses, array $weights): ?int
    {
        $possible = 0.0;
        $obtained = 0.0;
        foreach ($statuses as $key => $status) {
            if (!isset(self::FACTOR[$status])) {
                continue;
            }
            $weight = max(1, (int) ($weights[$key] ?? 1));
            $possible += $weight;
            $obtained += $weight * self::FACTOR[$status];
        }

        return $possible > 0 ? (int) round($obtained * 100 / $possible) : null;
    }

    /**
     * Note par domaine, domaines triés de la plus faible note à la plus haute.
     *
     * @param array<string, string> $statuses
     * @param array<string, int>    $weights
     * @param array<string, string> $domains domaine affiché de chaque contrôle
     * @return list<array{domain: string, score: int, controls: int}>
     */
    public static function byDomain(array $statuses, array $weights, array $domains): array
    {
        $grouped = [];
        foreach ($statuses as $key => $status) {
            if ($status === self::STATUS_NOT_APPLICABLE) {
                continue;
            }
            $grouped[$domains[$key] ?? ''][$key] = $status;
        }

        $result = [];
        foreach ($grouped as $domain => $domainStatuses) {
            $result[] = [
                'domain'   => (string) $domain,
                'score'    => (int) self::score($domainStatuses, $weights),
                'controls' => count($domainStatuses),
            ];
        }
        usort($result, static fn (array $a, array $b): int => [$a['score'], $a['domain']] <=> [$b['score'], $b['domain']]);

        return $result;
    }

    /**
     * Couleur du badge de la note (charte : vert, orange, rouge).
     */
    public static function level(?int $score): string
    {
        return match (true) {
            $score === null => 'secondary',
            $score >= 80    => 'success',
            $score >= 50    => 'warning',
            default         => 'danger',
        };
    }

    /**
     * Comparaison de deux audits : contrôles améliorés, dégradés, nouveaux (absents ou non
     * applicables dans le premier audit) et retirés (absents ou non applicables dans le second).
     *
     * @param array<string, string> $before
     * @param array<string, string> $after
     * @return array{improved: list<string>, worsened: list<string>, new: list<string>, removed: list<string>}
     */
    public static function compare(array $before, array $after): array
    {
        $result = ['improved' => [], 'worsened' => [], 'new' => [], 'removed' => []];
        foreach ($after as $key => $status) {
            $old = $before[$key] ?? self::STATUS_NOT_APPLICABLE;
            if (!isset(self::RANK[$status])) {
                if (isset(self::RANK[$old])) {
                    $result['removed'][] = (string) $key;
                }
                continue;
            }
            if (!isset(self::RANK[$old])) {
                $result['new'][] = (string) $key;
            } elseif (self::RANK[$status] < self::RANK[$old]) {
                $result['improved'][] = (string) $key;
            } elseif (self::RANK[$status] > self::RANK[$old]) {
                $result['worsened'][] = (string) $key;
            }
        }
        foreach ($before as $key => $status) {
            if (!array_key_exists($key, $after) && isset(self::RANK[$status])) {
                $result['removed'][] = (string) $key;
            }
        }

        return $result;
    }
}
