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

namespace GlpiPlugin\Configurationglpiauto\Import;

use GlpiPlugin\Configurationglpiauto\Jira\JiraCsvParser;

/**
 * Issue #430 : import générique de tickets depuis un fichier CSV quelconque (autre outil
 * d'assistance, tableur), sans dépendance à GLPI (testé unitairement).
 *
 * - Correspondance des colonnes : champ du ticket => en-tête de colonne, proposée d'après les noms
 *   d'en-tête (français ou anglais) puis modifiable à l'écran et enregistrable comme modèle.
 * - Lecture des lignes en tickets de la même forme que l'export Jira (JiraCsvParser::parse()),
 *   pour réutiliser le moteur d'import (TicketImporter).
 * - Commentaires dans une colonne unique (séparateur au choix) ou dans un second fichier lié par
 *   l'identifiant d'origine.
 */
final class CsvColumns
{
    /** Champs importables => en-têtes reconnus (comparés après CsvReader::slug()), du plus précis au plus large. */
    public const FIELDS = [
        'key'            => ['identifiant', 'id', 'numero', 'numero de ticket', 'n ticket', 'no ticket', 'n', 'ref',
            'reference', 'ticket', 'ticket id', 'ticket number', 'number', 'issue key', 'key', 'cle', 'identifiant d origine',
            'request id', 'case id', 'incident id', 'id ticket', 'n de ticket', 'num'],
        'summary'        => ['titre', 'objet', 'sujet', 'resume', 'intitule', 'libelle', 'title', 'subject', 'summary',
            'short description', 'nom du ticket'],
        'description'    => ['description', 'detail', 'details', 'texte', 'contenu', 'message', 'body', 'content',
            'description detaillee'],
        'status'         => ['statut', 'etat', 'status', 'state'],
        'type'           => ['type', 'type de ticket', 'type de demande', 'nature', 'ticket type', 'issue type', 'kind'],
        'priority'       => ['priorite', 'priority', 'urgence', 'urgency', 'severite', 'severity', 'criticite'],
        'category'       => ['categorie', 'category', 'sous categorie', 'rubrique', 'theme', 'domaine', 'service',
            'categorie du ticket'],
        'reporter'       => ['demandeur', 'requester', 'requerant', 'auteur', 'createur', 'cree par', 'emetteur',
            'utilisateur', 'reporter', 'contact', 'client', 'opened by', 'created by', 'caller', 'nom du demandeur'],
        'reporter_email' => ['e mail du demandeur', 'email du demandeur', 'mail du demandeur', 'courriel du demandeur',
            'email demandeur', 'mail demandeur', 'requester email', 'requester e mail', 'email', 'e mail', 'mail',
            'courriel', 'contact email', 'reporter email'],
        'assignee'       => ['technicien', 'assigne a', 'attribue a', 'responsable', 'intervenant', 'agent', 'assignee',
            'assigned to', 'owner', 'traite par', 'technician', 'nom du technicien'],
        'assignee_email' => ['e mail du technicien', 'email du technicien', 'mail du technicien', 'email technicien',
            'courriel du technicien', 'assignee email', 'agent email', 'technician email'],
        'group'          => ['groupe', 'groupe de techniciens', 'equipe', 'groupe assigne', 'file', 'group', 'team',
            'assignment group', 'assigned group', 'support group'],
        'created'        => ['date d ouverture', 'ouvert le', 'date de creation', 'cree le', 'creation', 'date',
            'date de la demande', 'created', 'created at', 'creation date', 'opened', 'opened at', 'date opened',
            'date created', 'ouverture'],
        'resolved'       => ['date de resolution', 'resolu le', 'date de cloture', 'clos le', 'ferme le', 'date de fermeture',
            'resolved', 'resolved at', 'resolution date', 'closed', 'closed at', 'date closed', 'resolution', 'cloture'],
        'comments'       => ['commentaires', 'commentaire', 'suivis', 'suivi', 'notes', 'note', 'historique', 'echanges',
            'comments', 'comment', 'work notes', 'followups', 'journal'],
        'labels'         => ['etiquettes', 'etiquette', 'tags', 'tag', 'labels', 'label', 'mots cles', 'mot cle', 'keywords'],
    ];

    /** Champs sans lesquels un ticket ne peut être importé (réimport sans doublon, titre). */
    public const REQUIRED = ['key', 'summary'];

    /** Séparateurs de commentaires proposés dans une colonne unique. */
    public const SEPARATORS = ['none', 'newline', 'blankline', 'pipe', 'custom'];

    /** Colonnes du second fichier de commentaires => en-têtes reconnus. */
    private const COMMENT_FILE_FIELDS = [
        'key'    => ['identifiant', 'id', 'numero', 'ticket', 'ticket id', 'id ticket', 'n ticket', 'numero de ticket',
            'ref', 'reference', 'key', 'cle', 'request id', 'issue key', 'identifiant du ticket', 'identifiant d origine'],
        'date'   => ['date', 'date du commentaire', 'cree le', 'created', 'created at', 'date de creation', 'le'],
        'author' => ['auteur', 'author', 'par', 'redacteur', 'utilisateur', 'user', 'cree par', 'created by', 'nom'],
        'body'   => ['commentaire', 'texte', 'contenu', 'message', 'comment', 'body', 'text', 'content', 'suivi', 'note'],
    ];

    /**
     * Correspondance proposée : champ => position de la colonne. Un en-tête sert au plus un champ ;
     * l'égalité exacte l'emporte, puis l'en-tête qui commence par le nom du champ (« Statut du
     * ticket »).
     *
     * @param list<string> $header
     * @return array<string, int>
     */
    public static function suggest(array $header): array
    {
        return self::match($header, self::FIELDS);
    }

    /**
     * @param list<string> $header
     * @param array<string, list<string>> $fields
     * @return array<string, int>
     */
    private static function match(array $header, array $fields): array
    {
        $slugs = array_map([CsvReader::class, 'slug'], $header);
        $mapping = [];
        $used = [];
        // Égalité exacte d'abord, dans l'ordre des alias (le plus précis gagne), puis préfixe.
        foreach ([true, false] as $exact) {
            foreach ($fields as $field => $aliases) {
                if (isset($mapping[$field])) {
                    continue;
                }
                foreach ($aliases as $alias) {
                    foreach ($slugs as $position => $slug) {
                        if (isset($used[$position]) || $slug === '') {
                            continue;
                        }
                        $hit = $exact ? $slug === $alias : strlen($alias) >= 4 && str_starts_with($slug, $alias . ' ');
                        if ($hit) {
                            $mapping[$field] = $position;
                            $used[$position] = true;
                            continue 3;
                        }
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Correspondance d'un modèle enregistré (champ => en-tête) appliquée à l'en-tête d'un fichier :
     * les colonnes absentes du fichier sont ignorées.
     *
     * @param list<string> $header
     * @param array<mixed> $columns champ => libellé d'en-tête
     * @return array<string, int>
     */
    public static function fromProfile(array $header, array $columns): array
    {
        $slugs = array_map([CsvReader::class, 'slug'], $header);
        $mapping = [];
        foreach ($columns as $field => $label) {
            $position = array_search(CsvReader::slug((string) $label), $slugs, true);
            if (isset(self::FIELDS[$field]) && is_int($position)) {
                $mapping[(string) $field] = $position;
            }
        }

        return $mapping;
    }

    /**
     * Correspondance choisie à l'écran (champ => position, -1 ou vide pour « non importé »),
     * réduite aux champs connus et aux colonnes du fichier.
     *
     * @param array<mixed> $posted
     * @return array<string, int>
     */
    public static function normalize(array $posted, int $columnCount): array
    {
        $mapping = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $value = $posted[$field] ?? '';
            if (!is_numeric($value)) {
                continue;
            }
            $position = (int) $value;
            if ($position >= 0 && $position < $columnCount) {
                $mapping[$field] = $position;
            }
        }

        return $mapping;
    }

    /**
     * Champs obligatoires sans colonne.
     *
     * @param array<string, int> $mapping
     * @return list<string>
     */
    public static function missingRequired(array $mapping): array
    {
        return array_values(array_diff(self::REQUIRED, array_keys($mapping)));
    }

    /**
     * Correspondance en libellés d'en-tête, pour un modèle réutilisable sur un autre fichier.
     *
     * @param array<string, int> $mapping
     * @param list<string> $header
     * @return array<string, string>
     */
    public static function toLabels(array $mapping, array $header): array
    {
        $labels = [];
        foreach ($mapping as $field => $position) {
            if (isset($header[$position]) && $header[$position] !== '') {
                $labels[$field] = $header[$position];
            }
        }

        return $labels;
    }

    /** Séparateur réel des commentaires d'une colonne unique ('' : un seul commentaire par cellule). */
    public static function separator(string $choice, string $custom = ''): string
    {
        return match ($choice) {
            'newline'   => "\n",
            'blankline' => "\n\n",
            'pipe'      => '|',
            'custom'    => $custom,
            default     => '',
        };
    }

    /**
     * Tickets du fichier (forme de JiraCsvParser::parse()) selon la correspondance des colonnes.
     *
     * @param array<string, int> $mapping champ => position de la colonne
     * @param array<string, list<array{date: ?string, author: string, body: string}>> $extraComments
     *     commentaires du second fichier, par identifiant d'origine
     * @return array{issues: list<array<string, mixed>>, errors: list<array{line: int, message: string}>}
     */
    public static function parse(string $content, array $mapping, string $separator = '', array $extraComments = []): array
    {
        $table = CsvReader::read($content);
        if ($table['header'] === []) {
            return ['issues' => [], 'errors' => [['line' => 1, 'message' => 'empty']]];
        }
        if (self::missingRequired($mapping) !== []) {
            return ['issues' => [], 'errors' => [['line' => 1, 'message' => 'missing_columns']]];
        }
        $issues = [];
        $errors = [];
        $seen = [];
        foreach ($table['rows'] as $row) {
            $issue = self::buildIssue($mapping, $row['values'], $separator);
            if ($issue['key'] === '' || $issue['summary'] === '') {
                $errors[] = ['line' => $row['line'], 'message' => 'missing_key_or_summary'];
                continue;
            }
            if (isset($seen[$issue['key']])) {
                $errors[] = ['line' => $row['line'], 'message' => 'duplicate_key'];
                continue;
            }
            $seen[$issue['key']] = true;
            foreach ($extraComments[$issue['key']] ?? [] as $comment) {
                $issue['comments'][] = $comment;
            }
            $issues[] = $issue;
        }

        return ['issues' => $issues, 'errors' => $errors];
    }

    /**
     * @param array<string, int> $mapping
     * @param list<string> $values
     * @return array<string, mixed>
     */
    private static function buildIssue(array $mapping, array $values, string $separator): array
    {
        $get = static fn (string $field): string => isset($mapping[$field]) ? trim($values[$mapping[$field]] ?? '') : '';
        $comments = [];
        $raw = $get('comments');
        if ($raw !== '') {
            $parts = $separator === '' ? [$raw] : explode($separator, str_replace("\r\n", "\n", $raw));
            foreach ($parts as $part) {
                $comment = JiraCsvParser::parseComment($part);
                if ($comment !== null) {
                    $comments[] = $comment;
                }
            }
        }
        $labels = array_values(array_unique(array_filter(array_map(
            'trim',
            preg_split('/[,;|]/', $get('labels')) ?: []
        ), static fn (string $label): bool => $label !== '')));

        return [
            'key'            => mb_substr($get('key'), 0, 100),
            'summary'        => mb_substr($get('summary'), 0, 255),
            'description'    => $get('description'),
            'type'           => $get('type'),
            'status'         => $get('status'),
            'priority'       => $get('priority'),
            'project'        => '',
            'category'       => $get('category'),
            'group'          => $get('group'),
            'reporter'       => $get('reporter') !== '' ? $get('reporter') : $get('reporter_email'),
            'reporter_email' => $get('reporter_email'),
            'assignee'       => $get('assignee') !== '' ? $get('assignee') : $get('assignee_email'),
            'assignee_email' => $get('assignee_email'),
            'created'        => CsvReader::parseDate($get('created')),
            'resolved'       => CsvReader::parseDate($get('resolved')),
            'labels'         => $labels,
            'comments'       => $comments,
        ];
    }

    /**
     * Second fichier de commentaires (une ligne par commentaire, lié au ticket par l'identifiant
     * d'origine), colonnes reconnues d'après leur en-tête.
     *
     * @return array{comments: array<string, list<array{date: ?string, author: string, body: string}>>, count: int,
     *     columns_found: bool}
     */
    public static function parseCommentsFile(string $content): array
    {
        $table = CsvReader::read($content);
        $columns = self::match($table['header'], self::COMMENT_FILE_FIELDS);
        if (!isset($columns['key'], $columns['body'])) {
            return ['comments' => [], 'count' => 0, 'columns_found' => false];
        }
        $comments = [];
        $count = 0;
        foreach ($table['rows'] as $row) {
            $get = static fn (string $field): string => isset($columns[$field]) ? trim($row['values'][$columns[$field]] ?? '') : '';
            $key = $get('key');
            $body = $get('body');
            if ($key === '' || $body === '') {
                continue;
            }
            $comments[$key][] = ['date' => CsvReader::parseDate($get('date')), 'author' => $get('author'), 'body' => $body];
            $count++;
        }

        return ['comments' => $comments, 'count' => $count, 'columns_found' => true];
    }

    /**
     * Quelques valeurs de chaque colonne, pour choisir la correspondance à l'écran.
     *
     * @param list<array{line: int, values: list<string>}> $rows
     * @return list<string> position => exemples séparés par « ; »
     */
    public static function samples(array $header, array $rows, int $max = 3): array
    {
        $samples = [];
        foreach (array_keys($header) as $position) {
            $values = [];
            foreach ($rows as $row) {
                $value = trim($row['values'][$position] ?? '');
                if ($value !== '' && !in_array($value, $values, true)) {
                    $values[] = mb_strimwidth(str_replace(["\r", "\n"], ' ', $value), 0, 40, '...');
                }
                if (count($values) >= $max) {
                    break;
                }
            }
            $samples[] = implode(' ; ', $values);
        }

        return $samples;
    }
}
