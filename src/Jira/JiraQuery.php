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
 * Issue #503 : requête JQL bornée envoyée à l'API de Jira.
 *
 * Jira Cloud refuse une requête sans restriction sur la recherche « enhanced search »
 * (GET /rest/api/3/search/jql) : « For performance reasons, this parameter requires a bounded
 * query. A bounded query is a query with a search restriction. », exemple de requête non bornée
 * `order by key desc`, et 7 champs au plus dans ORDER BY.
 * - Référence de l'API : https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-search/#api-rest-api-3-search-jql-get
 * - Spécification OpenAPI officielle : https://dac-static.atlassian.com/cloud/jira/platform/swagger-v3.v3.json
 * - Formats de date du champ « updated » ("yyyy-MM-dd", entre guillemets) :
 *   https://support.atlassian.com/jira-software-cloud/docs/jql-fields/
 *
 * La requête est donc toujours construite ici : projet(s) obligatoire(s), date de mise à jour
 * facultative, filtre libre facultatif entre parenthèses, et un tri stable par date de création
 * puis clé (la pagination par jeton reste cohérente d'une page à l'autre).
 */
final class JiraQuery
{
    /** Tri imposé : la création ne change pas pendant la lecture, la clé départage. */
    public const ORDER_BY = 'ORDER BY created ASC, key ASC';

    /** Nombre de projets accepté dans une seule lecture. */
    public const MAX_PROJECTS = 20;

    /**
     * Clés de projet saisies (séparées par des virgules, points-virgules ou espaces), en
     * majuscules, sans doublon. Null si une clé n'a pas le format de Jira (une lettre puis des
     * lettres, chiffres ou « _ ») ou si aucune n'est donnée.
     *
     * @return list<string>|null
     */
    public static function projectKeys(string $input): ?array
    {
        $keys = [];
        foreach (preg_split('/[\s,;]+/', strtoupper(trim($input))) ?: [] as $key) {
            if ($key === '') {
                continue;
            }
            if (preg_match('/^[A-Z][A-Z0-9_]{0,254}$/', $key) !== 1) {
                return null;
            }
            $keys[$key] = true;
        }
        if ($keys === [] || count($keys) > self::MAX_PROJECTS) {
            return null;
        }

        return array_keys($keys);
    }

    /**
     * Requête bornée, ou null si les projets ou la date sont invalides.
     *
     * @param string $projects     clés de projet (« SUP, OPS »)
     * @param string $updatedSince date AAAA-MM-JJ (vide : pas de borne de date)
     * @param string $filter       filtre JQL libre (vide : aucun) ; son ORDER BY éventuel est retiré
     */
    public static function build(string $projects, string $updatedSince = '', string $filter = ''): ?string
    {
        $keys = self::projectKeys($projects);
        if ($keys === null) {
            return null;
        }
        $clauses = ['project in (' . implode(', ', array_map(static fn (string $k): string => '"' . $k . '"', $keys)) . ')'];

        $updatedSince = trim($updatedSince);
        if ($updatedSince !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $updatedSince);
            if ($date === false || $date->format('Y-m-d') !== $updatedSince) {
                return null;
            }
            $clauses[] = 'updated >= "' . $updatedSince . '"';
        }

        $filter = self::withoutOrderBy($filter);
        if (!self::balanced($filter)) {
            return null;
        }
        if ($filter !== '') {
            $clauses[] = '(' . $filter . ')';
        }

        return implode(' AND ', $clauses) . ' ' . self::ORDER_BY;
    }

    /**
     * Vrai si la requête contient une restriction avant son éventuel ORDER BY : dernier garde-fou
     * avant l'appel (une requête vide ou réduite à un tri est refusée par Jira Cloud).
     */
    public static function isBounded(string $jql): bool
    {
        return self::withoutOrderBy($jql) !== '';
    }

    /**
     * Parenthèses équilibrées hors des chaînes entre guillemets : un filtre « x) OR (y » ne peut
     * pas sortir de ses parenthèses et lever la borne de projet.
     */
    private static function balanced(string $jql): bool
    {
        $depth = 0;
        $quote = '';
        $length = strlen($jql);
        for ($i = 0; $i < $length; $i++) {
            $char = $jql[$i];
            if ($quote !== '') {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')' && --$depth < 0) {
                return false;
            }
        }

        return $depth === 0 && $quote === '';
    }

    /** Filtre sans sa clause ORDER BY finale ni espaces superflus. */
    private static function withoutOrderBy(string $jql): string
    {
        return trim((string) preg_replace('/(^|\s)order\s+by\s.*$/is', '', trim($jql)));
    }
}
