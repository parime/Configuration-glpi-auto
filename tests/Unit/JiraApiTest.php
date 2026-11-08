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
use PHPUnit\Framework\TestCase;

/**
 * Issue #345, phase 2 : tickets fictifs renvoyés par l'API Jira (Cloud v3 et Data Center v2),
 * sans réseau (transport injecté).
 */
final class JiraApiTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function cloudIssue(string $key): array
    {
        return [
            'key'    => $key,
            'fields' => [
                'summary'     => 'Imprimante du 2e étage hors ligne',
                'description' => ['type' => 'doc', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Plus rien ne sort.']]],
                    ['type' => 'paragraph', 'content' => [
                        ['type' => 'text', 'text' => 'Voir avec '],
                        ['type' => 'mention', 'attrs' => ['text' => '@Camille']],
                    ]],
                ]],
                'issuetype'      => ['name' => 'Incident'],
                'status'         => ['name' => 'En cours'],
                'priority'       => ['name' => 'High'],
                'project'        => ['name' => 'Support infrastructure'],
                'reporter'       => ['displayName' => 'Alex Martin', 'emailAddress' => 'alex.martin@exemple.test'],
                'assignee'       => null,
                'created'        => '2026-01-15T10:00:00.000+0000',
                'resolutiondate' => null,
                'labels'         => ['imprimante', 'imprimante', 'etage-2'],
                'comment'        => ['comments' => [
                    [
                        'created' => '2026-01-15T11:30:00.000+0000',
                        'author'  => ['displayName' => 'Camille Durand'],
                        'body'    => ['type' => 'doc', 'content' => [
                            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Bourrage papier.']]],
                        ]],
                    ],
                    ['created' => '2026-01-15T12:00:00.000+0000', 'author' => [], 'body' => ['type' => 'doc']],
                ]],
            ],
        ];
    }

    public function testCloudIssueIsMappedToTheCsvShapeWithReadableRichText(): void
    {
        date_default_timezone_set('UTC');
        $issue = JiraApiMapper::fromApi(self::cloudIssue('SUP-1'));
        $this->assertNotNull($issue);
        $this->assertSame('SUP-1', $issue['key']);
        $this->assertSame("Plus rien ne sort.\nVoir avec @Camille", $issue['description']);
        $this->assertSame('Incident', $issue['type']);
        $this->assertSame('alex.martin@exemple.test', $issue['reporter_email']);
        $this->assertSame('', $issue['assignee'], 'Ticket non attribué.');
        $this->assertSame('2026-01-15 10:00:00', $issue['created']);
        $this->assertNull($issue['resolved']);
        $this->assertSame(['imprimante', 'etage-2'], $issue['labels']);
        $this->assertSame(
            [['date' => '2026-01-15 11:30:00', 'author' => 'Camille Durand', 'body' => 'Bourrage papier.']],
            $issue['comments'],
            'Le commentaire vide est ignoré.'
        );
    }

    public function testDataCenterTextFieldsAndIncompleteIssues(): void
    {
        $issue = JiraApiMapper::fromApi(['key' => 'APP-7', 'fields' => [
            'summary' => 'Erreur 500 sur la facturation', 'description' => "Ligne 1\nLigne 2",
            'comment' => ['comments' => [['created' => 'pas une date', 'author' => ['displayName' => 'X'], 'body' => 'Vu.']]],
        ]]);
        $this->assertNotNull($issue);
        $this->assertSame("Ligne 1\nLigne 2", $issue['description']);
        $this->assertNull($issue['comments'][0]['date'], 'Date illisible : absente, sans erreur.');
        $this->assertNull(JiraApiMapper::fromApi(['key' => 'APP-8', 'fields' => ['summary' => '']]));
        $this->assertNull(JiraApiMapper::fromApi(['fields' => ['summary' => 'Sans clé']]));
    }

    public function testOnlyCleanHttpsAddressesAreAcceptedUnlessHttpIsAllowed(): void
    {
        $this->assertSame('https://exemple.atlassian.net', JiraApiMapper::normalizeBaseUrl(' https://exemple.atlassian.net/ ', false));
        $this->assertSame('https://jira.exemple.test:8443/jira', JiraApiMapper::normalizeBaseUrl('https://jira.exemple.test:8443/jira/', false));
        $this->assertNull(JiraApiMapper::normalizeBaseUrl('http://jira.exemple.test', false));
        $this->assertSame('http://jira.exemple.test', JiraApiMapper::normalizeBaseUrl('http://jira.exemple.test', true));
        $this->assertNull(JiraApiMapper::normalizeBaseUrl('https://user:pass@jira.exemple.test', false));
        $this->assertNull(JiraApiMapper::normalizeBaseUrl('https://jira.exemple.test/?x=1', false));
        $this->assertNull(JiraApiMapper::normalizeBaseUrl('file:///etc/passwd', true));
        $this->assertNull(JiraApiMapper::normalizeBaseUrl('jira.exemple.test', false));
    }

    public function testDataCenterPagesAreReadByPositionWithABearerToken(): void
    {
        $calls = [];
        $client = new JiraApiClient(static function (string $url, array $query, array $headers) use (&$calls): string {
            $calls[] = [$url, $query['startAt'] ?? null, $headers['Authorization']];
            $start = (int) $query['startAt'];
            $issues = $start === 0 ? [self::cloudIssue('A-1'), self::cloudIssue('A-2')] : [self::cloudIssue('A-3')];

            return (string) json_encode(['issues' => $issues, 'total' => 3, 'startAt' => $start]);
        });
        $result = $client->fetch('https://jira.exemple.test', JiraApiMapper::FLAVOUR_DATACENTER, '', 'pat-123', 'project = A');

        $this->assertSame(['A-1', 'A-2', 'A-3'], array_column($result['issues'], 'key'));
        $this->assertSame([], $result['errors']);
        $this->assertFalse($result['truncated']);
        $this->assertSame('https://jira.exemple.test/rest/api/2/search', $calls[0][0]);
        $this->assertSame([0, 2], array_column($calls, 1));
        $this->assertSame('Bearer pat-123', $calls[0][2]);
    }

    public function testCloudPagesFollowTheNextPageTokenWithBasicAuth(): void
    {
        $calls = [];
        $client = new JiraApiClient(static function (string $url, array $query, array $headers) use (&$calls): string {
            $calls[] = [$url, $query['nextPageToken'] ?? '', $headers['Authorization']];

            return ($query['nextPageToken'] ?? '') === ''
                ? (string) json_encode(['issues' => [self::cloudIssue('C-1')], 'nextPageToken' => 'p2', 'isLast' => false])
                : (string) json_encode(['issues' => [self::cloudIssue('C-2')], 'isLast' => true]);
        });
        $result = $client->fetch('https://exemple.atlassian.net', JiraApiMapper::FLAVOUR_CLOUD, 'a@exemple.test', 'tok', 'project = C');

        $this->assertSame(['C-1', 'C-2'], array_column($result['issues'], 'key'));
        $this->assertSame('https://exemple.atlassian.net/rest/api/3/search/jql', $calls[0][0]);
        $this->assertSame(['', 'p2'], array_column($calls, 1));
        $this->assertSame('Basic ' . base64_encode('a@exemple.test:tok'), $calls[0][2]);
    }

    public function testAttachmentsAreMappedWithSafeNamesAndOnlyFetchedFromJiraItself(): void
    {
        $issue = JiraApiMapper::fromApi(['key' => 'SUP-9', 'fields' => [
            'summary'    => 'Capture jointe',
            'attachment' => [
                ['id' => '10001', 'filename' => 'capture.png', 'size' => 1200, 'content' => 'https://j.test/secure/attachment/10001/capture.png'],
                ['id' => '10002', 'filename' => '../../etc/passwd', 'size' => 10, 'content' => 'https://j.test/secure/attachment/10002/x'],
                ['id' => '10003', 'filename' => 'ailleurs.pdf', 'size' => 10, 'content' => 'https://autre.test/secure/attachment/10003/a.pdf'],
                ['id' => '10004', 'filename' => 'enorme.iso', 'size' => 50 * 1024 * 1024, 'content' => 'https://j.test/secure/attachment/10004/e.iso'],
                ['id' => '', 'filename' => 'sans-id.txt', 'size' => 1, 'content' => 'https://j.test/x'],
            ],
        ]]);
        $this->assertNotNull($issue);
        $this->assertSame(['capture.png', 'passwd', 'ailleurs.pdf', 'enorme.iso'], array_column($issue['attachments'], 'filename'));

        $this->assertTrue(JiraApiMapper::isSameOrigin('https://j.test/secure/a', 'https://j.test'));
        $this->assertTrue(JiraApiMapper::isSameOrigin('https://j.test/jira/secure/a', 'https://j.test/jira'));
        $this->assertFalse(JiraApiMapper::isSameOrigin('https://j.test/autre/secure/a', 'https://j.test/jira'));
        $this->assertFalse(JiraApiMapper::isSameOrigin('https://j.test.evil/secure/a', 'https://j.test'));
        $this->assertFalse(JiraApiMapper::isSameOrigin('http://j.test/secure/a', 'https://j.test'), 'Pas de rétrogradation en HTTP.');
        $this->assertFalse(JiraApiMapper::isSameOrigin('https://j.test:8443/secure/a', 'https://j.test'));

        $dir = sys_get_temp_dir() . '/jira-att-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $asked = [];
        $auth = [];
        $client = new JiraApiClient(static function (string $url, array $query, array $headers) use (&$asked, &$auth): string {
            $asked[] = $url;
            $auth[] = $headers['Authorization'];

            return 'contenu de ' . basename($url);
        });
        $issues = [$issue];
        $report = $client->downloadAttachments($issues, 'https://j.test', 'datacenter', '', 'pat', $dir);

        $this->assertSame(2, $report['downloaded'], 'capture.png et passwd (renommé) seulement.');
        $this->assertSame(['SUP-9 / ailleurs.pdf', 'SUP-9 / enorme.iso'], $report['skipped']);
        $this->assertSame(
            ['https://j.test/secure/attachment/10001/capture.png', 'https://j.test/secure/attachment/10002/x'],
            $asked,
            'Le jeton ne part jamais vers un autre hôte, ni pour un fichier trop gros.'
        );
        $this->assertSame(['Bearer pat', 'Bearer pat'], $auth);
        $this->assertSame('10001', $issues[0]['attachments'][0]['file']);
        $this->assertArrayNotHasKey('file', $issues[0]['attachments'][2]);
        $this->assertSame('contenu de capture.png', file_get_contents($dir . '/10001'));
        array_map('unlink', glob($dir . '/*') ?: []);
        rmdir($dir);
    }

    public function testErrorsAreNamedWithoutEverExposingTheToken(): void
    {
        $failing = static fn (string $status): JiraApiClient => new JiraApiClient(
            static function () use ($status): string {
                throw new \RuntimeException('HTTP ' . $status . ' returned for token secret-tok');
            }
        );
        foreach (['401' => 'api_unauthorized', '403' => 'api_unauthorized', '404' => 'api_not_found', '400' => 'api_bad_query', '0' => 'api_unreachable'] as $status => $code) {
            $result = $failing((string) $status)->fetch('https://j.test', JiraApiMapper::FLAVOUR_DATACENTER, '', 'secret-tok', 'x');
            $this->assertSame([['line' => 0, 'message' => $code]], $result['errors'], (string) $status);
            $this->assertStringNotContainsString('secret-tok', (string) json_encode($result));
        }
        $blocked = new JiraApiClient(static function (): string {
            throw new \RuntimeException('Host "jira.lan" is blocked for "http://jira.lan/rest/api/2/search".');
        });
        $this->assertSame(
            'api_private_network',
            $blocked->fetch('http://jira.lan', 'datacenter', '', 't', 'x')['errors'][0]['message'],
            'GLPI 12 refuse le réseau privé : message dédié.'
        );
        $notJira = new JiraApiClient(static fn (): string => '<html>login</html>');
        $this->assertSame('api_unexpected', $notJira->fetch('https://j.test', 'datacenter', '', 't', 'x')['errors'][0]['message']);
    }
}
