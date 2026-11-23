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

namespace GlpiPlugin\Configurationglpiauto\Tests\Unit;

use GlpiPlugin\Configurationglpiauto\Jira\JiraApiClient;
use GlpiPlugin\Configurationglpiauto\Jira\JiraApiMapper;
use GlpiPlugin\Configurationglpiauto\Jira\JiraQuery;
use PHPUnit\Framework\TestCase;

/**
 * Issue #503 : requête JQL bornée et pagination par `nextPageToken`, contre un faux Jira Cloud
 * bâti sur la documentation officielle de GET /rest/api/3/search/jql :
 * - https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-search/#api-rest-api-3-search-jql-get
 * - https://dac-static.atlassian.com/cloud/jira/platform/swagger-v3.v3.json
 *
 * `fixtures/jira/search-jql-get-200.json` est la réponse d'exemple « 200 » de cette spécification,
 * recopiée telle quelle. Elle n'a pas de champ `summary` : le faux serveur en ajoute un (champ que
 * le client demande dans `fields`), c'est la seule donnée ajoutée.
 */
final class JiraQueryTest extends TestCase
{
    public function testTheQueryIsAlwaysBoundedByProjectAndSortedStably(): void
    {
        $this->assertSame(
            'project in ("SUP") ORDER BY created ASC, key ASC',
            JiraQuery::build('sup')
        );
        $this->assertSame(
            'project in ("SUP", "OPS_2") AND updated >= "2026-01-31" AND (status != Done) ORDER BY created ASC, key ASC',
            JiraQuery::build(' SUP, ops_2;SUP ', '2026-01-31', 'status != Done ORDER BY priority DESC')
        );
        $this->assertSame(['A', 'B'], JiraQuery::projectKeys("A\nB"));
    }

    public function testInvalidProjectsDatesOrFiltersAreRefused(): void
    {
        $this->assertNull(JiraQuery::build(''), 'Sans projet, la requête ne serait pas bornée.');
        $this->assertNull(JiraQuery::build('SUP" OR project != "X'), 'Pas d\'injection par la clé.');
        $this->assertNull(JiraQuery::build('1SUP'));
        $this->assertNull(JiraQuery::build(implode(',', array_map(static fn (int $i): string => 'P' . $i, range(1, 21)))));
        $this->assertNull(JiraQuery::build('SUP', '2026-02-30'));
        $this->assertNull(JiraQuery::build('SUP', '31/01/2026'));
        $this->assertNull(JiraQuery::build('SUP', '', 'status = Done) OR (project != SUP'), 'Le filtre ne sort pas de ses parenthèses.');
        $this->assertNull(JiraQuery::build('SUP', '', 'summary ~ "x'));
        $this->assertNotNull(JiraQuery::build('SUP', '', 'summary ~ "a ) b"'), 'Parenthèse entre guillemets acceptée.');
    }

    public function testOnlyBoundedQueriesAreSent(): void
    {
        // Exemples de la documentation : « order by key desc » (non bornée),
        // « assignee = currentUser() order by key » (bornée).
        $this->assertFalse(JiraQuery::isBounded('order by key desc'));
        $this->assertFalse(JiraQuery::isBounded('   '));
        $this->assertTrue(JiraQuery::isBounded('assignee = currentUser() order by key'));

        $calls = 0;
        $client = new JiraApiClient(static function () use (&$calls): string {
            $calls++;

            return '{}';
        });
        $result = $client->fetch('https://exemple.atlassian.net', JiraApiMapper::FLAVOUR_CLOUD, 'a@exemple.test', 't', 'order by key desc');
        $this->assertSame([['line' => 0, 'message' => 'api_unbounded_query']], $result['errors']);
        $this->assertSame(0, $calls, 'Aucun appel à Jira pour une requête non bornée.');
    }

    public function testCloudSearchIsPaginatedByNextPageTokenAgainstTheOfficialExample(): void
    {
        $server = self::fakeCloud(120);
        $client = new JiraApiClient($server['transport']);
        $jql = (string) JiraQuery::build('ED', '2026-01-01');
        $result = $client->fetch('https://exemple.atlassian.net', JiraApiMapper::FLAVOUR_CLOUD, 'a@exemple.test', 'tok', $jql);

        $this->assertSame([], $result['errors']);
        $this->assertFalse($result['truncated']);
        $this->assertCount(120, $result['issues']);
        $this->assertSame('ED-1', $result['issues'][0]['key']);
        $this->assertSame('ED-120', $result['issues'][119]['key']);
        // Champs de l'exemple officiel repris par l'import.
        $this->assertSame('Example', $result['issues'][0]['project']);
        $this->assertSame('Main order flow broken', $result['issues'][0]['description']);
        $this->assertSame('debuglog.txt', $result['issues'][0]['attachments'][0]['filename']);
        $this->assertSame('10001', $result['issues'][0]['attachments'][0]['id']);

        $requests = $server['requests']();
        $this->assertCount(3, $requests, '50 + 50 + 20 tickets.');
        $this->assertArrayNotHasKey('nextPageToken', $requests[0]['query'], 'Première page sans jeton.');
        $this->assertSame(['page-2', 'page-3'], [$requests[1]['query']['nextPageToken'], $requests[2]['query']['nextPageToken']]);
        foreach ($requests as $request) {
            $this->assertSame('https://exemple.atlassian.net/rest/api/3/search/jql', $request['url']);
            $this->assertSame($jql, $request['query']['jql']);
            $this->assertSame('glpi-jira-import', $request['headers']['User-Agent']);
            $this->assertStringContainsString('summary', (string) $request['query']['fields']);
        }
    }

    public function testTheVerbatimExampleWithoutSummaryIsReportedNotImported(): void
    {
        $example = (string) file_get_contents(__DIR__ . '/fixtures/jira/search-jql-get-200.json');
        $client = new JiraApiClient(static fn (): string => $example);
        $result = $client->fetch('https://your-domain.atlassian.net', JiraApiMapper::FLAVOUR_CLOUD, 'a@exemple.test', 't', 'project = ED');

        $this->assertSame([], $result['issues']);
        $this->assertSame([['line' => 0, 'message' => 'missing_key_or_summary']], $result['errors']);
    }

    public function testTheFakeServerRejectsAnUnboundedQueryLikeJiraCloud(): void
    {
        $server = self::fakeCloud(3);
        $this->expectExceptionMessage('400');
        ($server['transport'])('https://exemple.atlassian.net/rest/api/3/search/jql', ['jql' => 'order by key desc'], []);
    }

    public function testARepeatedTokenStopsTheReading(): void
    {
        $calls = 0;
        $client = new JiraApiClient(static function () use (&$calls): string {
            $calls++;

            return (string) json_encode([
                'isLast'        => false,
                'nextPageToken' => 'toujours-le-meme',
                'issues'        => [['key' => 'ED-' . $calls, 'fields' => ['summary' => 'Ticket ' . $calls]]],
            ]);
        });
        $result = $client->fetch('https://exemple.atlassian.net', JiraApiMapper::FLAVOUR_CLOUD, 'a@exemple.test', 't', 'project = ED');

        $this->assertSame(2, $calls, 'Le jeton revu à la deuxième page arrête la lecture.');
        $this->assertCount(2, $result['issues']);
    }

    /**
     * Faux Jira Cloud : refuse une requête non bornée (400), sert `$total` tickets par pages de
     * `maxResults` (50 par défaut d'après la documentation), sans `nextPageToken` et avec
     * `isLast` à vrai sur la dernière page.
     *
     * @return array{transport: callable(string, array<string, scalar>, array<string, string>): string,
     *     requests: callable(): list<array{url: string, query: array<string, scalar>, headers: array<string, string>}>}
     */
    private static function fakeCloud(int $total): array
    {
        $example = json_decode((string) file_get_contents(__DIR__ . '/fixtures/jira/search-jql-get-200.json'), true);
        $template = $example['issues'][0];
        $requests = [];
        $transport = static function (string $url, array $query, array $headers) use (&$requests, $template, $total): string {
            $requests[] = ['url' => $url, 'query' => $query, 'headers' => $headers];
            if (!str_ends_with($url, '/rest/api/3/search/jql') || !JiraQuery::isBounded((string) ($query['jql'] ?? ''))) {
                throw new \RuntimeException('HTTP 400 returned for "' . $url . '".');
            }
            $size = min(5000, (int) ($query['maxResults'] ?? 50));
            $page = isset($query['nextPageToken']) ? (int) substr((string) $query['nextPageToken'], 5) : 1;
            $start = ($page - 1) * $size;
            $issues = [];
            for ($n = $start + 1; $n <= min($total, $start + $size); $n++) {
                $issue = $template;
                $issue['id'] = (string) (10000 + $n);
                $issue['key'] = 'ED-' . $n;
                $issue['fields']['summary'] = 'Ticket fictif ' . $n;
                $issues[] = $issue;
            }
            $isLast = $start + $size >= $total;
            $body = ['isLast' => $isLast, 'issues' => $issues];
            if (!$isLast) {
                $body['nextPageToken'] = 'page-' . ($page + 1);
            }

            return (string) json_encode($body);
        };

        return ['transport' => $transport, 'requests' => static function () use (&$requests): array {
            return $requests;
        }];
    }
}
