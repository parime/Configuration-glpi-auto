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
 */
final class JiraApiClient
{
    public const PAGE_SIZE = 50;
    public const MAX_ISSUES = 5000;

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
        $headers = [
            'Accept'        => 'application/json',
            'Authorization' => self::authHeader($flavour, $user, $token),
        ];
        $url = $baseUrl . JiraApiMapper::searchPath($flavour);
        $issues = [];
        $errors = [];
        $startAt = 0;
        $nextToken = '';
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
            $nextToken = (string) ($page['nextPageToken'] ?? '');
            $more = $flavour === JiraApiMapper::FLAVOUR_CLOUD
                ? $nextToken !== '' && !($page['isLast'] ?? false)
                : $count > 0 && $startAt < (int) ($page['total'] ?? 0);
        } while ($more && count($issues) < self::MAX_ISSUES);

        return [
            'issues'    => array_slice($issues, 0, self::MAX_ISSUES),
            'errors'    => $errors,
            'truncated' => $more && count($issues) >= self::MAX_ISSUES,
        ];
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
