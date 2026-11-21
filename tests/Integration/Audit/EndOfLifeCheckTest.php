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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration\Audit;

use Computer;
use ComputerModel;
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\EndOfLifeCheck;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeCache;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeClient;
use GlpiPlugin\Configurationglpiauto\Config;
use Item_OperatingSystem;
use Item_Ticket;
use OperatingSystem;
use OperatingSystemVersion;
use PHPUnit\Framework\TestCase;
use Ticket;

/**
 * Issue #467 : contrôle de fin de support sur une vraie base, dates fictives en cache (aucun appel
 * réseau). Désactivé par défaut ; lecture seule ; un seul ticket, ouvert sur demande.
 */
final class EndOfLifeCheckTest extends TestCase
{
    private string $suffix;

    protected function setUp(): void
    {
        $this->suffix = (string) mt_rand(100000, 999999);
    }

    protected function tearDown(): void
    {
        $config = Config::getConfig();
        $config->update(['id' => $config->getID(), 'audit_eol_enabled' => 0, 'audit_eol_mapping' => '']);
    }

    private function configure(bool $enabled, string $mapping): void
    {
        $config = Config::getConfig();
        $config->update(['id' => $config->getID(), 'audit_eol_enabled' => $enabled ? 1 : 0, 'audit_eol_days' => 180, 'audit_eol_mapping' => $mapping]);
    }

    private function computerWithOs(string $os, string $version, string $model = ''): int
    {
        $fields = ['name' => 'PHPUNIT-EOL-' . $this->suffix . '-' . mt_rand(), 'entities_id' => 0];
        if ($model !== '') {
            $fields['computermodels_id'] = (int) (new ComputerModel())->add(['name' => $model]);
        }
        $id = (int) (new Computer())->add($fields);
        (new Item_OperatingSystem())->add([
            'itemtype'                   => Computer::class,
            'items_id'                   => $id,
            'operatingsystems_id'        => (int) (new OperatingSystem())->add(['name' => $os]),
            'operatingsystemversions_id' => (int) (new OperatingSystemVersion())->add(['name' => $version]),
            'entities_id'                => 0,
        ]);

        return $id;
    }

    public function testDisabledByDefaultAndReadsOnlyTheCache(): void
    {
        $os = 'PHPUnit OS ' . $this->suffix;
        $product = 'phpunit-' . $this->suffix;
        $computer = $this->computerWithOs($os, '1.2');
        EndOfLifeCache::store($product, EndOfLifeClient::parse([
            ['cycle' => '1', 'eol' => date('Y-m-d', strtotime('-10 days'))],
            ['cycle' => '2', 'eol' => date('Y-m-d', strtotime('+400 days'))],
        ]));

        $this->configure(false, "$os = $product");
        $this->assertSame([], (new EndOfLifeCheck())->analyze(), 'facultatif : rien tant que le contrôle n\'est pas activé');

        $this->configure(true, "$os = $product");
        $findings = (new EndOfLifeCheck())->analyze();
        $this->assertSame(AuditFinding::SEVERITY_WARNING, $findings[0]->severity);
        $this->assertTrue($findings[0]->fixable);
        $this->assertContains($computer, array_map(static fn (array $f): int => (int) $f['row']['id'], EndOfLifeCheck::evaluate()['found']));

        $before = new Computer();
        $before->getFromDB($computer);
        (new EndOfLifeCheck())->fix();
        (new EndOfLifeCheck())->fix();
        global $DB;
        $tickets = (int) $DB->request([
            'COUNT'      => 'c',
            'FROM'       => Ticket::getTable(),
            'INNER JOIN' => [Item_Ticket::getTable() => ['FKEY' => [Item_Ticket::getTable() => 'tickets_id', Ticket::getTable() => 'id']]],
            'WHERE'      => [
                Item_Ticket::getTable() . '.itemtype' => Computer::class,
                Item_Ticket::getTable() . '.items_id' => $computer,
                Ticket::getTable() . '.name'          => ['LIKE', EndOfLifeCheck::TICKET_PREFIX . '%'],
            ],
        ])->current()['c'];
        $this->assertSame(1, $tickets, 'un seul ticket même après deux clics');
        $after = new Computer();
        $after->getFromDB($computer);
        foreach (['states_id', 'users_id', 'locations_id', 'is_deleted', 'computermodels_id'] as $field) {
            $this->assertSame($before->fields[$field], $after->fields[$field], "$field inchangé");
        }
    }

    public function testModelsAndGapsAreReported(): void
    {
        $model = 'PHPUnit Model 8 ' . $this->suffix;
        $this->computerWithOs('PHPUnit Other OS ' . $this->suffix, '1', $model);
        $product = 'phpunit-model-' . $this->suffix;
        EndOfLifeCache::store($product, EndOfLifeClient::parse([['cycle' => '8', 'eol' => true]]));

        $this->configure(true, "$model = $product\nPHPUnit Other OS {$this->suffix} = phpunit-absent-{$this->suffix}");
        $result = EndOfLifeCheck::evaluate();

        $this->assertContains($model, array_column($result['found'], 'name'), 'modèle hors support sans date');
        $this->assertContains('phpunit-absent-' . $this->suffix, $result['missing'], 'produit non téléchargé signalé');
        $this->assertCount(2, (new EndOfLifeCheck())->analyze());
    }

    public function testRefreshKeepsTheCacheOnFailure(): void
    {
        $product = 'phpunit-refresh-' . $this->suffix;
        $ok = new EndOfLifeClient(static fn (string $url): string => (string) json_encode([['cycle' => '3', 'eol' => '2030-01-01']]));
        $this->assertSame([], EndOfLifeCache::refresh([$product], $ok));
        $down = new EndOfLifeClient(static function (string $url): string {
            throw new \RuntimeException('unreachable');
        });
        $this->assertSame([$product], EndOfLifeCache::refresh([$product], $down));
        $this->assertSame('3', EndOfLifeCache::all()[$product]['cycles'][0]['cycle'] ?? null);

        $this->assertSame(1, EndOfLifeCache::import($product, (string) json_encode(['result' => ['releases' => [['name' => '4', 'eolFrom' => '2031-01-01']]]])));
        $this->expectException(\RuntimeException::class);
        EndOfLifeCache::import($product, 'not json');
    }
}
