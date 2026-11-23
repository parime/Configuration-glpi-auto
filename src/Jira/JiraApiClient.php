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
 * Issue #345, phase 2 : lecture des tickets par l'API REST de Jira, en lecture seule. Le jeton
 * n'est jamais enregistré : il sert à cette lecture puis est oublié. Pagination bornée
 * (MAX_ISSUES) ; une erreur arrête la lecture et est rendue lisible, sans jeton dans le message.
 *
 * Le transport est injecté (testable sans réseau) ; par défaut Compat\Http::get(), qui passe par
 * le client HTTP de GLPI (proxy) et ne suit pas les redirections.
 *
 * Issue #503 : la requête est toujours bornée (JiraQuery) ; Jira Cloud se lit par
 * GET /rest/api/3/search/jql et se pagine par `nextPageToken`, absent de la dernière page
 * (`isLast` à vrai). Documentation officielle :
 * - https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-search/#api-rest-api-3-search-jql-get
 * - https://dac-static.atlassian.com/cloud/jira/platform/swagger-v3.v3.json (schéma SearchAndReconcileResults)
 *   (l'ancienne recherche /rest/api/3/search y est marquée « Currently being removed »)
 * Data Center garde /rest/api/2/search et la pagination par position (startAt, total) :
 * https://docs.atlassian.com/software/jira/docs/api/REST/9.17.0/ (ressource api/2/search)
 */
final class JiraApiClient
{
    public const PAGE_SIZE = 50;
    public const MAX_ISSUES = 5000;
    public const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;
    public const MAX_TOTAL_BYTES = 200 * 1024 * 1024;
    /** Nom de client générique, sans information sur l'instance ni sur la personne. */
    public const USER_AGENT = 'glpi-jira-import';

    /** @var callable(string, array<string, scalar>, array<string, string>): string */
    private $transport;

    /**
     * @param (callable(string, array<string, scalar>, array<string, string>): string)|null $transport
     */
    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport ?? static fn (string $url, array $query, array $headers): string
            => \GlpiPlugin\Configurationglpiauto\Compat\Http::get($url, [
                'query'            => $query,
                'headers'          => $headers,
                'timeout'          => 30,
                'follow_redirects' => false,
                'context'          => JiraConnection::class,
            ]);
    }

    /**
     * En-tête d'authentification : Cloud = adresse e-mail et jeton d'API (Basic), Data Center =
     * jeton d'accès personnel (Bearer).
     */
    public static function authHeader(string $flavour, string $user, string $token): string
    {
        return $flavour === JiraApiMapper::FLAVOUR_CLOUD
            ? 'Basic ' . base64_encode($user . ':' . $token)
            : 'Bearer ' . $token;
    }

    /**
     * @return array{issues: list<array<string, mixed>>, errors: list<array{line: int, message: string}>,
     *     truncated: bool}
     */
    public function fetch(string $baseUrl, string $flavour, string $user, string $token, string $jql): array
    {
        if (!JiraQuery::isBounded($jql)) {
            return ['issues' => [], 'errors' => [['line' => 0, 'message' => 'api_unbounded_query']], 'truncated' => false];
        }
        $headers = [
            'Accept'        => 'application/json',
            'Authorization' => self::authHeader($flavour, $user, $token),
            'User-Agent'    => self::USER_AGENT,
        ];
        $url = $baseUrl . JiraApiMapper::searchPath($flavour);
        $issues = [];
        $errors = [];
        $startAt = 0;
        $nextToken = '';
        $seenTokens = [];
        $more = false;
        do {
            $query = [
                'jql'        => $jql,
                'maxResults' => self::PAGE_SIZE,
                'fields'     => implode(',', JiraApiMapper::FIELDS),
            ];
            if ($flavour === JiraApiMapper::FLAVOUR_CLOUD) {
                if ($nextToken !== '') {
                    $query['nextPageToken'] = $nextToken;
                }
            } else {
                $query['startAt'] = $startAt;
            }
            try {
                $page = json_decode(($this->transport)($url, $query, $headers), true);
            } catch (\Throwable $e) {
                $errors[] = ['line' => 0, 'message' => self::describe($e)];
                break;
            }
            if (!is_array($page) || !is_array($page['issues'] ?? null)) {
                $errors[] = ['line' => 0, 'message' => 'api_unexpected'];
                break;
            }
            foreach ($page['issues'] as $apiIssue) {
                $issue = is_array($apiIssue) ? JiraApiMapper::fromApi($apiIssue) : null;
                if ($issue === null) {
                    $errors[] = ['line' => 0, 'message' => 'missing_key_or_summary'];
                    continue;
                }
                $issues[] = $issue;
            }
            $count = count($page['issues']);
            $startAt += $count;
            $nextToken = is_string($page['nextPageToken'] ?? null) ? $page['nextPageToken'] : '';
            if ($flavour === JiraApiMapper::FLAVOUR_CLOUD) {
                // Dernière page : pas de jeton et isLast à vrai. Un jeton déjà vu arrêterait
                // sinon la lecture dans une boucle sans fin.
                $more = $nextToken !== '' && ($page['isLast'] ?? false) !== true && !isset($seenTokens[$nextToken]);
                $seenTokens[$nextToken] = true;
            } else {
                $more = $count > 0 && $startAt < (int) ($page['total'] ?? 0);
            }
        } while ($more && count($issues) < self::MAX_ISSUES);

        return [
            'issues'    => array_slice($issues, 0, self::MAX_ISSUES),
            'errors'    => $errors,
            'truncated' => $more && count($issues) >= self::MAX_ISSUES,
        ];
    }

    /**
     * Pièces jointes, téléchargées pendant la lecture (le jeton n'est plus disponible ensuite) dans
     * `$dir`, sous le nom de leur identifiant Jira. Seulement depuis l'adresse de Jira elle-même
     * (le jeton n'est jamais envoyé ailleurs), MAX_ATTACHMENT_BYTES par fichier et
     * MAX_TOTAL_BYTES en tout. Chaque pièce téléchargée reçoit `file` ; les autres sont signalées.
     *
     * @param list<array<string, mixed>> $issues modifié : `attachments[*].file`
     * @return array{downloaded: int, skipped: list<string>}
     */
    public function downloadAttachments(
        array &$issues,
        string $baseUrl,
        string $flavour,
        string $user,
        string $token,
        string $dir
    ): array {
        $headers = ['Authorization' => self::authHeader($flavour, $user, $token), 'User-Agent' => self::USER_AGENT];
        $downloaded = 0;
        $total = 0;
        $skipped = [];
        foreach ($issues as $i => $issue) {
            foreach ((array) ($issue['attachments'] ?? []) as $a => $attachment) {
                $label = $issue['key'] . ' / ' . $attachment['filename'];
                $id = (string) $attachment['id'];
                if (preg_match('/^[0-9A-Za-z_-]{1,64}$/', $id) !== 1
                    || !JiraApiMapper::isSameOrigin((string) $attachment['url'], $baseUrl)) {
                    $skipped[] = $label;
                    continue;
                }
                if ((int) $attachment['size'] > self::MAX_ATTACHMENT_BYTES
                    || $total + (int) $attachment['size'] > self::MAX_TOTAL_BYTES) {
                    $skipped[] = $label;
                    continue;
                }
                try {
                    $content = ($this->transport)((string) $attachment['url'], [], $headers);
                } catch (\Throwable $e) {
                    $skipped[] = $label;
                    continue;
                }
                if (strlen($content) > self::MAX_ATTACHMENT_BYTES || $total + strlen($content) > self::MAX_TOTAL_BYTES) {
                    $skipped[] = $label;
                    continue;
                }
                file_put_contents($dir . '/' . $id, $content);
                $total += strlen($content);
                $issues[$i]['attachments'][$a]['file'] = $id;
                $downloaded++;
            }
        }

        return ['downloaded' => $downloaded, 'skipped' => $skipped];
    }

    /** Message d'erreur affichable : code HTTP reconnu, jamais le jeton. */
    private static function describe(\Throwable $e): string
    {
        $message = $e->getMessage();
        // GLPI 12 : appel vers un réseau privé refusé (voir JiraConnection).
        if (str_contains($message, 'is blocked for')) {
            return 'api_private_network';
        }
        if (preg_match('/\b(401|403)\b/', $message) === 1) {
            return 'api_unauthorized';
        }
        if (preg_match('/\b404\b/', $message) === 1) {
            return 'api_not_found';
        }
        if (preg_match('/\b400\b/', $message) === 1) {
            return 'api_bad_query';
        }

        return 'api_unreachable';
    }
}
