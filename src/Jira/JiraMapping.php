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

namespace GlpiPlugin\Configurationglpiauto\Jira;

/**
 * Issue #345 : correspondances proposées par défaut entre Jira et GLPI, sans dépendance à GLPI
 * (testées unitairement). Les valeurs sont celles des constantes de GLPI (statuts de
 * CommonITILObject, types de Ticket) ; l'aperçu de l'import les rend modifiables.
 */
final class JiraMapping
{
    public const STATUS_NEW = 1;
    public const STATUS_ASSIGNED = 2;
    public const STATUS_WAITING = 4;
    public const STATUS_SOLVED = 5;
    public const STATUS_CLOSED = 6;
    /** Clos avec le gabarit de solution « Demande non retenue » (pas un statut GLPI à part). */
    public const STATUS_NOT_RETAINED = 'not_retained';

    public const TYPE_INCIDENT = 1;
    public const TYPE_REQUEST = 2;

    /** Mots-clés (sans accents, en minuscules) => statut proposé, dans l'ordre de priorité. */
    private const STATUS_KEYWORDS = [
        [['non retenu', 'won t do', 'wont do', 'rejet', 'refus', 'abandon', 'annul', 'declined', 'cancel'],
            self::STATUS_NOT_RETAINED],
        [['attente', 'att retour', 'retour', 'feedback', 'waiting', 'pending', 'blocked', 'bloque', 'on hold'],
            self::STATUS_WAITING],
        [['en cours', 'in progress', 'progress', 'review', 'revue', 'test', 'recette'], self::STATUS_ASSIGNED],
        [['fini', 'termine', 'done', 'resolved', 'resolu', 'closed', 'clos', 'ferme', 'livre'], self::STATUS_CLOSED],
        [['a faire', 'to do', 'todo', 'open', 'ouvert', 'backlog', 'nouveau', 'new', 'selected'], self::STATUS_NEW],
    ];

    private const INCIDENT_KEYWORDS = ['incident', 'bug', 'panne', 'anomalie', 'defaut', 'erreur', 'probleme', 'error'];

    /** Mots-clés de priorité Jira => priorité GLPI (1 très basse à 5 très haute). */
    private const PRIORITY_KEYWORDS = [
        [['highest', 'critique', 'critical', 'bloquant', 'blocker', 'urgent', 'tres haute'], 5],
        [['lowest', 'tres basse', 'trivial'], 1],
        [['high', 'haute', 'elevee', 'major', 'majeure'], 4],
        [['low', 'basse', 'faible', 'minor', 'mineure'], 2],
        [['medium', 'moyenne', 'normal', 'normale'], 3],
    ];

    public static function defaultStatus(string $jiraStatus): int|string
    {
        $slug = JiraCsvParser::slug($jiraStatus);
        foreach (self::STATUS_KEYWORDS as [$keywords, $status]) {
            foreach ($keywords as $keyword) {
                if (str_contains($slug, $keyword)) {
                    return $status;
                }
            }
        }

        return self::STATUS_NEW;
    }

    public static function defaultType(string $jiraType): int
    {
        $slug = JiraCsvParser::slug($jiraType);
        foreach (self::INCIDENT_KEYWORDS as $keyword) {
            if (str_contains($slug, $keyword)) {
                return self::TYPE_INCIDENT;
            }
        }

        return self::TYPE_REQUEST;
    }

    public static function defaultPriority(string $jiraPriority): int
    {
        $slug = JiraCsvParser::slug($jiraPriority);
        foreach (self::PRIORITY_KEYWORDS as [$keywords, $priority]) {
            foreach ($keywords as $keyword) {
                if (str_contains($slug, $keyword)) {
                    return $priority;
                }
            }
        }

        return 3;
    }

    /**
     * Une correspondance choisie dans l'aperçu : seulement les valeurs connues, sinon le défaut.
     */
    public static function normalizeStatus(mixed $value, string $jiraStatus): int|string
    {
        if ($value === self::STATUS_NOT_RETAINED) {
            return $value;
        }
        $value = (int) $value;

        return in_array($value, [self::STATUS_NEW, self::STATUS_ASSIGNED, self::STATUS_WAITING, self::STATUS_SOLVED,
            self::STATUS_CLOSED], true) ? $value : self::defaultStatus($jiraStatus);
    }

    public static function normalizeType(mixed $value, string $jiraType): int
    {
        $value = (int) $value;

        return in_array($value, [self::TYPE_INCIDENT, self::TYPE_REQUEST], true) ? $value : self::defaultType($jiraType);
    }

    /**
     * Synthèse de l'aperçu : nombre de tickets par statut, type et projet Jira.
     *
     * @param list<array<string, mixed>> $issues
     * @return array{statuses: array<string, int>, types: array<string, int>, projects: array<string, int>}
     */
    public static function summarize(array $issues): array
    {
        $summary = ['statuses' => [], 'types' => [], 'projects' => []];
        foreach ($issues as $issue) {
            foreach (['statuses' => 'status', 'types' => 'type', 'projects' => 'project'] as $bucket => $field) {
                $value = (string) ($issue[$field] ?? '');
                $value = $value === '' ? '—' : $value;
                $summary[$bucket][$value] = ($summary[$bucket][$value] ?? 0) + 1;
            }
        }
        foreach ($summary as &$counts) {
            arsort($counts);
        }

        return $summary;
    }

    /**
     * Personne Jira (nom affiché, éventuellement adresse e-mail) comparable à un compte GLPI :
     * clé « prénom nom » sans casse ni accents, dans les deux ordres.
     *
     * @return list<string>
     */
    public static function nameKeys(string $displayName): array
    {
        $words = array_values(array_filter(explode(' ', JiraCsvParser::slug($displayName))));
        if (count($words) < 2) {
            return $words === [] ? [] : [$words[0]];
        }

        return array_values(array_unique([implode(' ', $words), implode(' ', array_reverse($words))]));
    }
}
