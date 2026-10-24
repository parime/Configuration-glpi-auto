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

use DateTimeImmutable;

/**
 * Issue #345 : lecture d'un export CSV de Jira (« Toutes les colonnes »), sans dépendance à GLPI
 * (testée unitairement). En-têtes anglais ou français ; les colonnes répétées de Jira
 * (commentaires, étiquettes) sont regroupées ; les dates sont lues dans les formats d'export de
 * Jira Cloud et Data Center, mois anglais ou français.
 */
final class JiraCsvParser
{
    /** Colonne logique => en-têtes reconnus (comparés sans casse ni accents). */
    private const HEADERS = [
        'key'         => ['issue key', 'cle de ticket', 'cle', 'key', 'cle du ticket'],
        'summary'     => ['summary', 'resume', 'titre'],
        'description' => ['description'],
        'type'        => ['issue type', 'type de ticket', 'type'],
        'status'      => ['status', 'etat', 'statut'],
        'priority'    => ['priority', 'priorite'],
        'project'     => ['project name', 'nom du projet', 'projet', 'project', 'nom de l espace', 'espace'],
        'reporter'    => ['reporter', 'rapporteur'],
        'assignee'    => ['assignee', 'responsable', 'personne assignee'],
        'created'     => ['created', 'creation', 'cree', 'date de creation'],
        'resolved'    => ['resolved', 'resolu', 'date de resolution', 'resolution date'],
        'labels'      => ['labels', 'etiquettes', 'etiquette'],
        'comment'     => ['comment', 'commentaire', 'comments', 'commentaires'],
        'reporter_email' => ['reporter email', 'e-mail du rapporteur', 'reporter e-mail'],
        'assignee_email' => ['assignee email', 'e-mail du responsable', 'assignee e-mail'],
    ];

    /** Colonnes que Jira répète : toutes leurs valeurs sont gardées. */
    private const REPEATED = ['labels', 'comment'];

    private const MONTHS = [
        'janv' => 'Jan', 'janvier' => 'Jan', 'fevr' => 'Feb', 'fevrier' => 'Feb', 'mars' => 'Mar', 'avr' => 'Apr',
        'avril' => 'Apr', 'mai' => 'May', 'juin' => 'Jun', 'juil' => 'Jul', 'juillet' => 'Jul', 'aout' => 'Aug',
        'sept' => 'Sep', 'septembre' => 'Sep', 'oct' => 'Oct', 'octobre' => 'Oct', 'nov' => 'Nov',
        'novembre' => 'Nov', 'dec' => 'Dec', 'decembre' => 'Dec',
    ];

    /**
     * @return array{issues: list<array<string, mixed>>, errors: list<array{line: int, message: string}>}
     */
    public static function parse(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return ['issues' => [], 'errors' => [['line' => 0, 'message' => 'unreadable']]];
        }
        fwrite($handle, $content);
        rewind($handle);

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $header = fgetcsv($handle, null, $delimiter, '"', '');
        if (!is_array($header)) {
            fclose($handle);
            return ['issues' => [], 'errors' => [['line' => 1, 'message' => 'empty']]];
        }
        $columns = self::mapHeader($header);
        if (!in_array('key', $columns, true) || !in_array('summary', $columns, true)) {
            fclose($handle);
            return ['issues' => [], 'errors' => [['line' => 1, 'message' => 'missing_columns']]];
        }

        $issues = [];
        $errors = [];
        $line = 1;
        while (($values = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $line++;
            if ($values === [null]) {
                continue;
            }
            $issue = self::buildIssue($columns, $values);
            if ($issue['key'] === '' || $issue['summary'] === '') {
                $errors[] = ['line' => $line, 'message' => 'missing_key_or_summary'];
                continue;
            }
            $issues[] = $issue;
        }
        fclose($handle);

        return ['issues' => $issues, 'errors' => $errors];
    }

    /**
     * @param list<?string> $header
     * @return array<int, string>
     */
    private static function mapHeader(array $header): array
    {
        $columns = [];
        foreach ($header as $position => $label) {
            $slug = self::slug((string) $label);
            $columns[$position] = '';
            foreach (self::HEADERS as $key => $aliases) {
                if (in_array($slug, $aliases, true)) {
                    $columns[$position] = $key;
                    break;
                }
            }
        }

        return $columns;
    }

    /**
     * @param array<int, string> $columns
     * @param list<?string> $values
     * @return array<string, mixed>
     */
    private static function buildIssue(array $columns, array $values): array
    {
        $issue = array_fill_keys(array_keys(self::HEADERS), '');
        $issue['labels'] = [];
        $issue['comment'] = [];
        foreach ($values as $position => $value) {
            $key = $columns[$position] ?? '';
            $value = trim((string) $value);
            if ($key === '' || $value === '') {
                continue;
            }
            if (in_array($key, self::REPEATED, true)) {
                $issue[$key][] = $value;
            } elseif ($issue[$key] === '') {
                $issue[$key] = $value;
            }
        }
        $issue['labels'] = array_values(array_unique(array_merge(
            ...array_map(static fn (string $v): array => preg_split('/\s+/', $v) ?: [], $issue['labels'])
        )));
        $issue['comments'] = array_values(array_filter(array_map([self::class, 'parseComment'], $issue['comment'])));
        unset($issue['comment']);
        $issue['created'] = self::parseDate($issue['created']);
        $issue['resolved'] = self::parseDate($issue['resolved']);

        return $issue;
    }

    /**
     * Commentaire Jira exporté : « date;auteur;texte » (le texte peut contenir des « ; »).
     *
     * @return array{date: ?string, author: string, body: string}|null
     */
    public static function parseComment(string $raw): ?array
    {
        $parts = explode(';', $raw, 3);
        if (count($parts) === 3 && self::parseDate($parts[0]) !== null) {
            return ['date' => self::parseDate($parts[0]), 'author' => trim($parts[1]), 'body' => trim($parts[2])];
        }
        $raw = trim($raw);

        return $raw === '' ? null : ['date' => null, 'author' => '', 'body' => $raw];
    }

    /**
     * Dates des exports Jira (« 24/Oct/26 3:15 PM », « 24/oct./26 15:15 », « 2026-10-24 15:15 »…)
     * au format Y-m-d H:i:s, ou null.
     */
    public static function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $normalized = (string) preg_replace_callback(
            '/(?<=\/)([[:alpha:]éèûô]+)\.?(?=\/)/u',
            static function (array $m): string {
                $month = self::slug($m[1]);
                return self::MONTHS[$month] ?? ucfirst($month);
            },
            $value
        );
        $formats = ['d/M/y g:i A', 'd/M/y h:i A', 'd/M/y H:i', 'd/M/Y g:i A', 'd/M/Y H:i', 'Y-m-d H:i', 'Y-m-d H:i:s',
            'd/m/Y H:i', 'd/m/Y', 'Y-m-d', 'Y-m-d\TH:i:s.vO', 'Y-m-d\TH:i:sP'];
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $normalized);
            $warnings = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($warnings === false || ($warnings['warning_count'] === 0 && $warnings['error_count'] === 0))) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    public static function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'â' => 'a', 'ô' => 'o', 'î' => 'i',
            'ï' => 'i', 'û' => 'u', 'ù' => 'u', 'ç' => 'c']);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }
}
