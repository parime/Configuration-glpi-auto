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
 * Issue #345, phase 2 : ticket renvoyé par l'API REST de Jira (Cloud v3 ou Data Center v2)
 * ramené à la même forme que JiraCsvParser, pour réutiliser l'aperçu et l'import existants.
 * Sans dépendance à GLPI (testé unitairement).
 */
final class JiraApiMapper
{
    public const FLAVOUR_CLOUD = 'cloud';
    public const FLAVOUR_DATACENTER = 'datacenter';

    /** Champs demandés à Jira : seulement ce que l'import utilise. */
    public const FIELDS = [
        'summary', 'description', 'issuetype', 'status', 'priority', 'project', 'reporter', 'assignee',
        'created', 'resolutiondate', 'labels', 'comment', 'attachment',
    ];

    /**
     * @param array<string, mixed> $apiIssue
     * @return array<string, mixed>|null null si le ticket n'a ni clé ni résumé
     */
    public static function fromApi(array $apiIssue): ?array
    {
        $fields = is_array($apiIssue['fields'] ?? null) ? $apiIssue['fields'] : [];
        $key = trim(self::text($apiIssue['key'] ?? ''));
        $summary = trim(self::text($fields['summary'] ?? ''));
        if ($key === '' || $summary === '') {
            return null;
        }
        $comments = [];
        foreach (self::listOf($fields['comment']['comments'] ?? []) as $comment) {
            $body = trim(self::richText($comment['body'] ?? ''));
            if ($body === '') {
                continue;
            }
            $comments[] = [
                'date'   => self::parseDate(self::text($comment['created'] ?? '')),
                'author' => self::text($comment['author']['displayName'] ?? ''),
                'body'   => $body,
            ];
        }
        $attachments = [];
        foreach (self::listOf($fields['attachment'] ?? []) as $attachment) {
            $id = self::text($attachment['id'] ?? '');
            $filename = self::safeFilename(self::text($attachment['filename'] ?? ''));
            $url = self::text($attachment['content'] ?? '');
            if ($id === '' || $filename === '' || $url === '') {
                continue;
            }
            $attachments[] = [
                'id'       => $id,
                'filename' => $filename,
                'size'     => is_numeric($attachment['size'] ?? null) ? (int) $attachment['size'] : 0,
                'url'      => $url,
            ];
        }
        $labels = [];
        foreach (self::listOf($fields['labels'] ?? []) as $label) {
            $label = trim(self::text($label));
            if ($label !== '' && !in_array($label, $labels, true)) {
                $labels[] = $label;
            }
        }

        return [
            'key'            => $key,
            'summary'        => $summary,
            'description'    => trim(self::richText($fields['description'] ?? '')),
            'type'           => self::text($fields['issuetype']['name'] ?? ''),
            'status'         => self::text($fields['status']['name'] ?? ''),
            'priority'       => self::text($fields['priority']['name'] ?? ''),
            'project'        => self::text($fields['project']['name'] ?? ''),
            'reporter'       => self::text($fields['reporter']['displayName'] ?? ''),
            'assignee'       => self::text($fields['assignee']['displayName'] ?? ''),
            'reporter_email' => self::text($fields['reporter']['emailAddress'] ?? ''),
            'assignee_email' => self::text($fields['assignee']['emailAddress'] ?? ''),
            'created'        => self::parseDate(self::text($fields['created'] ?? '')),
            'resolved'       => self::parseDate(self::text($fields['resolutiondate'] ?? '')),
            'labels'         => $labels,
            'comments'       => $comments,
            'attachments'    => $attachments,
        ];
    }

    /**
     * Texte d'un champ riche : chaîne (Data Center, API v2) ou document ADF (Cloud, API v3), dont
     * on garde le texte et les sauts de paragraphe.
     */
    public static function richText(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (!is_array($value)) {
            return '';
        }
        $type = (string) ($value['type'] ?? '');
        if ($type === 'text') {
            return self::text($value['text'] ?? '');
        }
        if ($type === 'hardBreak') {
            return "\n";
        }
        if (in_array($type, ['mention', 'emoji'], true)) {
            return self::text($value['attrs']['text'] ?? '');
        }
        $parts = array_map([self::class, 'richText'], self::listOf($value['content'] ?? []));
        $joined = implode('', $parts);
        $blocks = ['paragraph', 'heading', 'listItem', 'codeBlock', 'blockquote', 'rule'];

        return in_array($type, $blocks, true) ? $joined . "\n" : ($type === 'doc' ? rtrim($joined) : $joined);
    }

    /**
     * Date ISO 8601 de l'API (« 2026-01-15T10:00:00.000+0100 ») au format SQL, dans le fuseau du
     * serveur ; null si absente ou illisible.
     */
    public static function parseDate(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }
        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            return null;
        }

        return $date->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }

    /**
     * Adresse de la recherche selon la version de Jira : Cloud utilise /rest/api/3/search/jql
     * (pagination par jeton), Data Center /rest/api/2/search (pagination par position).
     */
    public static function searchPath(string $flavour): string
    {
        return $flavour === self::FLAVOUR_CLOUD ? '/rest/api/3/search/jql' : '/rest/api/2/search';
    }

    /**
     * Adresse de base acceptée : http(s) seulement, sans identifiants ni paramètres, HTTPS exigé
     * sauf autorisation explicite. Renvoie l'adresse nettoyée, ou null.
     */
    public static function normalizeBaseUrl(string $url, bool $allowHttp): ?string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, $allowHttp ? ['https', 'http'] : ['https'], true)) {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        return $scheme . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . rtrim($parts['path'] ?? '', '/');
    }

    /**
     * Nom de fichier sûr pour GLPI : sans dossier ni caractère de contrôle, 200 caractères au plus.
     */
    public static function safeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', '', $name));

        return $name === '.' || $name === '..' ? '' : mb_substr($name, 0, 200);
    }

    /**
     * Une pièce jointe ne se télécharge (avec le jeton) que sur l'adresse de Jira elle-même : même
     * schéma, même hôte, même port, sous le même chemin de base.
     */
    public static function isSameOrigin(string $url, string $baseUrl): bool
    {
        $u = parse_url($url);
        $b = parse_url($baseUrl);
        if (!is_array($u) || !is_array($b) || !isset($u['scheme'], $u['host'], $b['scheme'], $b['host'])) {
            return false;
        }
        $basePath = rtrim($b['path'] ?? '', '/') . '/';

        return strtolower($u['scheme']) === strtolower($b['scheme'])
            && strtolower($u['host']) === strtolower($b['host'])
            && ($u['port'] ?? null) === ($b['port'] ?? null)
            && !isset($u['user']) && !isset($u['pass'])
            && str_starts_with(($u['path'] ?? '/'), $basePath);
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<mixed>
     */
    private static function listOf(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
