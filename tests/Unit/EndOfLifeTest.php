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

use GlpiPlugin\Configurationglpiauto\Audit\Checks\EndOfLifeCheck;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\CycleMatcher;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeClient;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeMapping;
use PHPUnit\Framework\TestCase;

/**
 * Issue #467 : lecture des réponses endoflife.date, choix du cycle, correspondances et classement,
 * sans réseau ni base (cycles fictifs, date de référence fixe).
 */
final class EndOfLifeTest extends TestCase
{
    private const TODAY = '2026-06-01';

    /**
     * @return list<array{cycle: string, codename: string, eol: string, latest: string}>
     */
    private static function cycles(): array
    {
        return EndOfLifeClient::parse(['result' => ['releases' => [
            ['name' => '11-24h2-w', 'eolFrom' => '2026-10-13', 'isEol' => false],
            ['name' => '11-24h2-e', 'eolFrom' => '2027-10-12', 'isEol' => false],
            ['name' => '10-22h2', 'eolFrom' => '2025-10-14', 'isEol' => true],
            ['name' => '12', 'codename' => 'Bookworm', 'eolFrom' => '2028-06-10'],
            ['name' => '22.04', 'codename' => 'Jammy Jellyfish', 'eolFrom' => '2027-04-01'],
            ['name' => '8', 'isEol' => true],
            ['name' => '13', 'eolFrom' => null, 'isEol' => false],
        ]]]);
    }

    public function testParsesBothApiFormats(): void
    {
        $v1 = self::cycles();
        $this->assertSame('11-24h2-w', $v1[0]['cycle']);
        $this->assertSame('2026-10-13', $v1[0]['eol']);
        $this->assertSame(EndOfLifeClient::EOL_WITHOUT_DATE, $v1[5]['eol']);
        $this->assertSame('', $v1[6]['eol']);

        $legacy = EndOfLifeClient::parse([['cycle' => '7', 'eol' => '2024-06-30'], ['cycle' => '6', 'eol' => true]]);
        $this->assertSame(['7', '6'], array_column($legacy, 'cycle'));
        $this->assertSame(EndOfLifeClient::EOL_WITHOUT_DATE, $legacy[1]['eol']);

        $this->expectException(\RuntimeException::class);
        EndOfLifeClient::parse(['message' => 'not a product']);
    }

    public function testOnlyTheEndOfLifeApiIsCalled(): void
    {
        $called = [];
        $client = new EndOfLifeClient(static function (string $url) use (&$called): string {
            $called[] = $url;

            return (string) json_encode([['cycle' => '1', 'eol' => '2020-01-01']]);
        });
        $this->assertCount(1, $client->cycles('windows'));
        $this->assertSame(['https://endoflife.date/api/v1/products/windows/'], $called);

        try {
            $client->cycles('../../evil.example');
            $this->fail('identifiant de produit invalide accepté');
        } catch (\RuntimeException) {
            $this->assertCount(1, $called, 'aucun appel pour un produit invalide');
        }
    }

    public function testPicksTheCycle(): void
    {
        $cycles = self::cycles();
        $this->assertSame('10-22h2', CycleMatcher::match('Microsoft Windows 10 Pro', '22H2', $cycles)['cycle'] ?? null);
        $this->assertSame('11-24h2-w', CycleMatcher::match('Microsoft Windows 11 Pro', '24H2', $cycles)['cycle'] ?? null, 'variante dont la fin est la plus proche');
        $this->assertSame('12', CycleMatcher::match('Debian GNU/Linux', '12.5', $cycles)['cycle'] ?? null);
        $this->assertSame('12', CycleMatcher::match('Debian', 'bookworm', $cycles)['cycle'] ?? null);
        $this->assertSame('22.04', CycleMatcher::match('Ubuntu', '22.04.4 LTS', $cycles)['cycle'] ?? null);
        $this->assertSame('8', CycleMatcher::match('Model 8', '', $cycles)['cycle'] ?? null, 'numéro du nom de modèle');
        $this->assertSame('13', CycleMatcher::match('Anything', '', $cycles, '13')['cycle'] ?? null, 'cycle imposé');
        $this->assertNull(CycleMatcher::match('Unknown', '99.1', $cycles));
    }

    public function testStatus(): void
    {
        $cycles = self::cycles();
        $this->assertSame(CycleMatcher::STATUS_EOL, CycleMatcher::status($cycles[2], self::TODAY, 180)['status']);
        $this->assertSame(CycleMatcher::STATUS_SOON, CycleMatcher::status($cycles[0], self::TODAY, 180)['status']);
        $this->assertSame(CycleMatcher::STATUS_SUPPORTED, CycleMatcher::status($cycles[0], self::TODAY, 30)['status']);
        $this->assertSame(CycleMatcher::STATUS_EOL, CycleMatcher::status($cycles[5], self::TODAY, 180)['status'], 'hors support sans date');
        $this->assertSame(CycleMatcher::STATUS_SUPPORTED, CycleMatcher::status($cycles[6], self::TODAY, 180)['status'], 'pas de fin annoncée');
        $this->assertSame(CycleMatcher::STATUS_UNKNOWN, CycleMatcher::status(null, self::TODAY, 180)['status']);
    }

    public function testMappingIsExplicitAndSuggestionsOnlyAddMissingNames(): void
    {
        $map = EndOfLifeMapping::parse("# comment\nMicrosoft  Windows 10 Pro = windows\nPhone X = iphone 12\nbroken line\nBad = ../x\n");
        $this->assertSame(['microsoft windows 10 pro', 'phone x'], array_keys($map));
        $this->assertSame(['name' => 'Phone X', 'product' => 'iphone', 'cycle' => '12'], $map['phone x']);
        $this->assertSame("Microsoft Windows 10 Pro = windows\nPhone X = iphone 12", EndOfLifeMapping::normalize("Microsoft  Windows 10 Pro = windows\nPhone X = iphone 12\nBad = ../x"));
        $this->assertSame(['windows', 'iphone'], EndOfLifeMapping::products("A = windows\nB = windows 11\nC = iphone"));

        $this->assertSame('debian', EndOfLifeMapping::suggest('Debian GNU/Linux 12 (bookworm)'));
        $this->assertSame('windows-server', EndOfLifeMapping::suggest('Microsoft Windows Server 2019 Standard'));
        $this->assertSame('', EndOfLifeMapping::suggest('Custom OS'));
        $this->assertSame(
            "Ubuntu = custom\nDebian GNU/Linux = debian",
            EndOfLifeMapping::withSuggestions('Ubuntu = custom', ['Ubuntu', 'Debian GNU/Linux', 'Custom OS']),
            'une correspondance saisie n\'est jamais remplacée'
        );
    }

    public function testClassifyReportsProblemsAndGaps(): void
    {
        $source = static fn (int $id, string $name, string $version, string $kind = EndOfLifeCheck::KIND_OS): array => [
            'itemtype' => 'Computer', 'row' => ['id' => $id, 'name' => 'PC-' . $id], 'kind' => $kind, 'name' => $name, 'version' => $version,
        ];
        $sources = [
            $source(1, 'Microsoft Windows 10 Pro', '22H2'),
            $source(2, 'Microsoft Windows 11 Pro', '24H2'),
            $source(3, 'Debian GNU/Linux', '12.5'),
            $source(4, 'Ubuntu', '18.04'),
            $source(5, 'Rocky Linux', '9'),
            $source(6, 'Not mapped', '1'),
            $source(7, 'Model 8', '', EndOfLifeCheck::KIND_MODEL),
        ];
        $mapping = EndOfLifeMapping::parse("Microsoft Windows 10 Pro = windows\nMicrosoft Windows 11 Pro = windows\nDebian GNU/Linux = debian\nUbuntu = debian\nRocky Linux = rocky-linux\nModel 8 = iphone");
        $cache = [
            'windows' => ['cycles' => self::cycles(), 'date' => '2026-05-30 10:00:00'],
            'debian'  => ['cycles' => self::cycles(), 'date' => '2026-01-01 10:00:00'],
            'iphone'  => ['cycles' => self::cycles(), 'date' => '2026-05-30 10:00:00'],
        ];

        $result = EndOfLifeCheck::classify($sources, $mapping, $cache, self::TODAY, 180);

        $this->assertSame([7, 1, 2], array_map(static fn (array $f): int => $f['row']['id'], $result['found']), 'hors support d\'abord, puis bientôt');
        $this->assertSame([CycleMatcher::STATUS_EOL, CycleMatcher::STATUS_EOL, CycleMatcher::STATUS_SOON], array_column($result['found'], 'status'));
        $this->assertSame(['rocky-linux'], $result['missing']);
        $this->assertSame(['Ubuntu 18.04'], $result['unmatched']);
        $this->assertSame(['debian'], $result['stale']);
    }
}
