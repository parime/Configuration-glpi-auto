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
 * ------------------------------------------------------------------------- * SPDX-License-Identifier: GPL-3.0-or-later

 */

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration\Audit;

use Config;
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\MailCollectorCheck;
use MailCollector;
use PHPUnit\Framework\TestCase;
use Rule;
use RuleMailCollector;

/**
 * Issue #429 : contrôles d'audit de la réception par e-mail. Les collecteurs de test sont actifs
 * mais pointent vers un serveur fictif et ne sont jamais relevés ; ils sont supprimés, et la
 * configuration et les règles natives restaurées, en fin de test. Les constats sont repérés par
 * leurs liens (adresses), jamais par leur texte traduit.
 */
final class MailCollectorCheckTest extends TestCase
{
    /** @var list<int> */
    private array $collectorIds = [];

    /** @var array<int, int> */
    private array $ruleStates = [];

    private string $adminEmail = '';

    protected function setUp(): void
    {
        foreach ((new Rule())->find(['sub_type' => RuleMailCollector::class]) as $row) {
            $this->ruleStates[(int) $row['id']] = (int) $row['is_active'];
        }
        $this->adminEmail = (string) (Config::getConfigurationValues('core', ['admin_email'])['admin_email'] ?? '');
    }

    protected function tearDown(): void
    {
        foreach ($this->collectorIds as $id) {
            (new MailCollector())->delete(['id' => $id], true);
        }
        foreach ($this->ruleStates as $id => $isActive) {
            $rule = new Rule();
            if ($rule->getFromDB($id) && (int) $rule->fields['is_active'] !== $isActive) {
                $rule->update(['id' => $id, 'is_active' => $isActive]);
            }
        }
        Config::setConfigurationValues('core', ['admin_email' => $this->adminEmail]);
    }

    private function collector(string $address, int $errors = 0): int
    {
        $id = (int) (new MailCollector())->add([
            'name' => $address,
            'mail_server' => 'imap.exemple.test',
            'server_port' => 993,
            'server_type' => '/imap',
            'server_ssl' => '/ssl',
            'server_cert' => '/validate-cert',
            'login' => $address,
            'is_active' => 1,
        ]);
        $this->collectorIds[] = $id;
        if ($errors > 0) {
            (new MailCollector())->update(['id' => $id, 'errors' => $errors]);
        }

        return $id;
    }

    /**
     * @return list<AuditFinding> constats qui citent l'adresse
     */
    private static function findingsFor(string $address): array
    {
        return array_values(array_filter(
            (new MailCollectorCheck())->analyze(),
            static fn (AuditFinding $f): bool => array_key_exists($address, $f->links)
        ));
    }

    public function testAHealthyCollectorRaisesNothing(): void
    {
        $address = 'phpunit-' . mt_rand(100000, 999999) . '@exemple.test';
        $this->collector($address);

        $this->assertSame([], self::findingsFor($address));
    }

    public function testACollectorInErrorIsReported(): void
    {
        $address = 'phpunit-' . mt_rand(100000, 999999) . '@exemple.test';
        $this->collector($address, 3);

        $findings = self::findingsFor($address);
        $this->assertCount(1, $findings);
        $this->assertSame(AuditFinding::SEVERITY_WARNING, $findings[0]->severity);
        $this->assertSame('mail_collectors', $findings[0]->checkKey);
    }

    public function testACollectorThatIsAlsoAGlpiSenderAddressIsALoop(): void
    {
        $address = 'phpunit-' . mt_rand(100000, 999999) . '@exemple.test';
        Config::setConfigurationValues('core', ['admin_email' => $address]);
        $this->collector($address);

        $findings = self::findingsFor($address);
        $this->assertCount(1, $findings);
        $this->assertSame(AuditFinding::SEVERITY_CRITICAL, $findings[0]->severity);
    }

    public function testWithoutAnyActiveEntityRuleEveryMessageWouldBeRefused(): void
    {
        $address = 'phpunit-' . mt_rand(100000, 999999) . '@exemple.test';
        $this->collector($address);
        foreach ($this->ruleStates as $id => $isActive) {
            if ($isActive) {
                (new Rule())->update(['id' => $id, 'is_active' => 0]);
            }
        }

        $severities = array_map(static fn (AuditFinding $f): string => $f->severity, self::findingsFor($address));
        $this->assertContains(AuditFinding::SEVERITY_CRITICAL, $severities, 'collecteur sans règle');
        // Les règles natives de refus sont aussi désactivées : risque de boucle signalé.
        $this->assertNotEmpty(array_filter((new MailCollectorCheck())->analyze(), static fn (AuditFinding $f): bool => $f->links === []));
    }

    public function testNoActiveCollectorMeansNothingToCheck(): void
    {
        $address = 'phpunit-' . mt_rand(100000, 999999) . '@exemple.test';
        $id = $this->collector($address, 5);
        (new MailCollector())->update(['id' => $id, 'is_active' => 0]);

        $this->assertSame([], self::findingsFor($address));
    }
}
