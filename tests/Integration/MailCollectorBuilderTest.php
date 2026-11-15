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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration;

use Entity;
use GLPIKey;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\MailCollectorBuilder;
use Group;
use ITILCategory;
use MailCollector;
use PHPUnit\Framework\TestCase;
use Rule;
use RuleAction;
use RuleCriteria;
use RuleMailCollector;
use RuleMailCollectorCollection;
use RuleTicket;

/**
 * Issue #429 : collecteurs et règles réellement créés dans GLPI, sans boîte réelle (serveurs
 * fictifs `*.exemple.test`, jamais contactés : aucune relève n'est lancée). Tout ce qui est créé
 * est supprimé en fin de test, et l'état des règles natives de GLPI est restauré.
 */
final class MailCollectorBuilderTest extends TestCase
{
    /** @var list<int> */
    private array $collectorIds = [];

    /** @var array<int, int> rules_id => is_active d'origine des règles natives */
    private array $nativeRuleStates = [];

    /** @var list<string> uuid des règles globales de l'assistant absentes avant le test */
    private array $globalRulesToPurge = [];

    /** @var list<array{0: class-string<\CommonDBTM>, 1: int}> */
    private array $fixtures = [];

    protected function setUp(): void
    {
        foreach ((new Rule())->find(['sub_type' => RuleMailCollector::class]) as $row) {
            $this->nativeRuleStates[(int) $row['id']] = (int) $row['is_active'];
        }
        foreach (['out-of-office', 'bounce', 'allowed-domains'] as $suffix) {
            if (countElementsInTable(Rule::getTable(), ['uuid' => MailCollectorBuilder::UUID_PREFIX . $suffix]) === 0) {
                $this->globalRulesToPurge[] = MailCollectorBuilder::UUID_PREFIX . $suffix;
            }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->collectorIds as $id) {
            foreach (['entity', 'ticket'] as $kind) {
                foreach ((new Rule())->find(['uuid' => MailCollectorBuilder::UUID_PREFIX . $kind . '-' . $id]) as $row) {
                    (new Rule())->delete(['id' => (int) $row['id']], true);
                }
            }
            (new MailCollector())->delete(['id' => $id], true);
        }
        foreach ($this->globalRulesToPurge as $uuid) {
            foreach ((new Rule())->find(['uuid' => $uuid]) as $row) {
                (new Rule())->delete(['id' => (int) $row['id']], true);
            }
        }
        foreach ($this->nativeRuleStates as $id => $isActive) {
            $rule = new Rule();
            if ($rule->getFromDB($id) && (int) $rule->fields['is_active'] !== $isActive) {
                $rule->update(['id' => $id, 'is_active' => $isActive]);
            }
        }
        foreach (array_reverse($this->fixtures) as [$itemtype, $id]) {
            (new $itemtype())->delete(['id' => $id], true);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function mailbox(array $overrides = []): array
    {
        $row = MailCollectorBuilder::sanitizeMailboxes([$overrides + [
            'address' => 'phpunit-' . mt_rand(100000, 999999) . '@exemple.test',
            'server' => 'imap.exemple.test',
        ]]);

        return $row[0];
    }

    /**
     * @param array<string, mixed> $mailbox
     * @return array{0: int, 1: bool, 2: bool}
     */
    private function ensureCollector(MailCollectorBuilder $builder, array $mailbox, string $password = ''): array
    {
        $result = $builder->ensureCollector($mailbox, $password);
        if ($result[0] > 0 && !in_array($result[0], $this->collectorIds, true)) {
            $this->collectorIds[] = $result[0];
        }

        return $result;
    }

    /**
     * Même entrée que la relève (MailCollector::buildTicket()).
     *
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private static function processMail(int $collectorId, array $headers): array
    {
        return (new RuleMailCollectorCollection())->processAllRules([], [], [
            'mailcollector' => $collectorId,
            'headers' => $headers + ['subject' => 'Imprimante en panne', 'from' => 'utilisateur@exemple.test'],
            '_users_id_requester' => 0,
        ]);
    }

    public function testTheCollectorIsCreatedDisabledWithAnEncryptedPasswordAndNeverDuplicated(): void
    {
        $builder = new MailCollectorBuilder();
        $mailbox = $this->mailbox(['accepted_folder' => 'Traites', 'refused_folder' => 'Refuses', 'filesize_max_mb' => 5]);

        [$id, $created] = $this->ensureCollector($builder, $mailbox, 'mot-de-passe-fictif');
        $this->assertGreaterThan(0, $id);
        $this->assertTrue($created);

        $collector = new MailCollector();
        $collector->getFromDB($id);
        $this->assertSame(0, (int) $collector->fields['is_active'], 'désactivé sans choix explicite');
        $this->assertSame('{imap.exemple.test:993/imap/ssl/validate-cert}INBOX', $collector->fields['host']);
        $this->assertSame($mailbox['address'], $collector->fields['login']);
        $this->assertSame('Traites', $collector->fields['accepted']);
        $this->assertSame(5 * 1048576, (int) $collector->fields['filesize_max']);
        $this->assertNotSame('mot-de-passe-fictif', $collector->fields['passwd'], 'chiffré par GLPI');
        $this->assertSame('mot-de-passe-fictif', (new GLPIKey())->decrypt($collector->fields['passwd']));

        // Réexécution sans mot de passe : même collecteur, mot de passe conservé, réglage mis à jour.
        [$sameId, $createdAgain] = $this->ensureCollector($builder, ['port' => 1993] + $mailbox);
        $this->assertSame($id, $sameId);
        $this->assertFalse($createdAgain);
        $this->assertSame(1, countElementsInTable(MailCollector::getTable(), ['name' => $mailbox['address']]));
        $collector->getFromDB($id);
        $this->assertSame('mot-de-passe-fictif', (new GLPIKey())->decrypt($collector->fields['passwd']));
        $this->assertStringContainsString(':1993/', $collector->fields['host']);
    }

    public function testActivationIsExplicitAndAnActiveCollectorIsNeverDisabled(): void
    {
        $builder = new MailCollectorBuilder();
        $mailbox = $this->mailbox(['protocol' => 'pop', 'security' => 'none']);
        [$id] = $this->ensureCollector($builder, $mailbox);
        $collector = new MailCollector();
        $collector->getFromDB($id);
        $this->assertSame(0, (int) $collector->fields['is_active']);
        $this->assertSame('{imap.exemple.test:110/pop/validate-cert}', $collector->fields['host'], 'POP3 : pas de dossier');

        [, , $activated] = $this->ensureCollector($builder, ['activate' => true] + $mailbox);
        $this->assertTrue($activated);
        $collector->getFromDB($id);
        $this->assertSame(1, (int) $collector->fields['is_active']);

        $this->ensureCollector($builder, ['activate' => false] + $mailbox);
        $collector->getFromDB($id);
        $this->assertSame(1, (int) $collector->fields['is_active'], 'jamais désactivé par l\'assistant');
    }

    public function testTheMailboxEntityRuleRoutesItsMessagesWithoutDuplicates(): void
    {
        $entityName = 'PHPUnit Support ' . mt_rand(100000, 999999);
        $entityId = (int) (new Entity())->add(['name' => $entityName, 'entities_id' => 0]);
        $this->fixtures[] = [Entity::class, $entityId];

        $builder = new MailCollectorBuilder();
        $mailbox = $this->mailbox(['entity' => $entityName]);
        [$id] = $this->ensureCollector($builder, $mailbox);
        [$otherId] = $this->ensureCollector($builder, $this->mailbox());

        $results = [$builder->ensureEntityRule($id, $mailbox), $builder->ensureEntityRule($id, $mailbox)];
        $this->assertSame([1, 1], $results, 'créée puis mise à jour');
        $uuid = MailCollectorBuilder::UUID_PREFIX . 'entity-' . $id;
        $this->assertSame(1, countElementsInTable(Rule::getTable(), ['uuid' => $uuid]));
        $rule = new Rule();
        $rule->getFromDBByCrit(['uuid' => $uuid]);
        $this->assertSame(1, countElementsInTable(RuleCriteria::getTable(), ['rules_id' => $rule->getID()]));

        $this->assertSame($entityId, (int) (self::processMail($id, [])['entities_id'] ?? -1));
        $this->assertNotSame($entityId, (int) (self::processMail($otherId, [])['entities_id'] ?? -1), 'une autre boîte n\'est pas concernée');

        // Placée avant la règle générale « Root » de GLPI, qui l'aurait sinon masquée.
        $root = new Rule();
        if ($root->getFromDBByCrit(['sub_type' => RuleMailCollector::class, 'uuid' => 'glpi_rule_mail_collector_root'])) {
            $this->assertLessThan((int) $root->fields['ranking'], (int) $rule->fields['ranking']);
        }

        // Entité retirée de l'assistant : la règle de l'assistant disparaît.
        $this->assertSame(0, $builder->ensureEntityRule($id, ['entity' => ''] + $mailbox));
        $this->assertSame(0, countElementsInTable(Rule::getTable(), ['uuid' => $uuid]));
    }

    public function testTheTicketRuleSetsCategoryAndGroupAndReportsUnknownNames(): void
    {
        $suffix = (string) mt_rand(100000, 999999);
        $categoryId = (int) (new ITILCategory())->add(['name' => 'PHPUnit Sécurité ' . $suffix, 'entities_id' => 0, 'is_recursive' => 1]);
        $this->fixtures[] = [ITILCategory::class, $categoryId];
        $groupId = (int) (new Group())->add(['name' => 'PHPUnit Support N1 ' . $suffix, 'entities_id' => 0, 'is_recursive' => 1]);
        $this->fixtures[] = [Group::class, $groupId];

        $builder = new MailCollectorBuilder();
        $mailbox = $this->mailbox(['category' => 'PHPUnit Sécurité ' . $suffix, 'group' => 'PHPUnit Support N1 ' . $suffix]);
        [$id] = $this->ensureCollector($builder, $mailbox);

        $this->assertSame(1, $builder->ensureTicketRule($id, $mailbox));
        $rule = new Rule();
        $this->assertTrue($rule->getFromDBByCrit(['uuid' => MailCollectorBuilder::UUID_PREFIX . 'ticket-' . $id]));
        $this->assertSame(RuleTicket::class, $rule->fields['sub_type']);
        $this->assertSame(RuleTicket::ONADD, (int) $rule->fields['condition']);
        $criteria = array_values((new RuleCriteria())->find(['rules_id' => $rule->getID()]));
        $this->assertSame('_mailgate', $criteria[0]['criteria']);
        $this->assertSame((string) $id, (string) $criteria[0]['pattern']);
        $actions = array_column(array_values((new RuleAction())->find(['rules_id' => $rule->getID()])), 'value', 'field');
        $this->assertSame((string) $categoryId, (string) $actions['itilcategories_id']);
        $this->assertSame((string) $groupId, (string) $actions['_groups_id_assign']);
        $this->assertSame([], $builder->getUnresolved());

        // Nom introuvable : signalé, et la règle (devenue sans action) est retirée.
        $this->assertSame(0, $builder->ensureTicketRule($id, ['category' => 'PHPUnit catégorie inexistante', 'group' => ''] + $mailbox));
        $this->assertSame(['PHPUnit catégorie inexistante'], $builder->getUnresolved());
        $this->assertSame(0, countElementsInTable(Rule::getTable(), ['uuid' => MailCollectorBuilder::UUID_PREFIX . 'ticket-' . $id]));
    }

    public function testAutoRepliesBouncesAndForeignDomainsAreRejectedWithoutResponse(): void
    {
        $builder = new MailCollectorBuilder();
        [$id] = $this->ensureCollector($builder, $this->mailbox());

        $config = new Config();
        $config->fields = ['mail_reject_auto_replies' => 1, 'mail_reject_x_auto_response_suppress' => 0, 'mail_allowed_domains' => 'exemple.test'];
        $builder->applyRejectionRules($config);
        $builder->applyRejectionRules($config);

        foreach (['out-of-office', 'bounce', 'allowed-domains'] as $suffix) {
            $this->assertSame(1, countElementsInTable(Rule::getTable(), ['uuid' => MailCollectorBuilder::UUID_PREFIX . $suffix]), $suffix);
        }
        $native = new Rule();
        if ($native->getFromDBByCrit(['uuid' => 'glpi_rule_mail_collector_auto_reply_auto_submitted'])) {
            $this->assertSame(1, (int) $native->fields['is_active']);
        }

        $this->assertArrayHasKey('_refuse_email_no_response', self::processMail($id, ['subject' => 'Réponse automatique : Imprimante en panne']));
        $this->assertArrayHasKey('_refuse_email_no_response', self::processMail($id, ['from' => 'MAILER-DAEMON@exemple.test']));
        $this->assertArrayHasKey('_refuse_email_no_response', self::processMail($id, ['auto-submitted' => 'auto-replied']));
        $this->assertArrayHasKey('_refuse_email_no_response', self::processMail($id, ['from' => 'quelquun@autre-domaine.test']));
        $this->assertArrayNotHasKey('_refuse_email_no_response', self::processMail($id, []), 'un message ordinaire passe');
        $this->assertArrayNotHasKey('_refuse_email_with_response', self::processMail($id, ['from' => 'quelquun@autre-domaine.test']), 'jamais de réponse : pas de boucle');

        // Liste des domaines vidée : la règle des domaines de l'assistant est retirée.
        $config->fields['mail_allowed_domains'] = '';
        $builder->applyRejectionRules($config);
        $this->assertSame(0, countElementsInTable(Rule::getTable(), ['uuid' => MailCollectorBuilder::UUID_PREFIX . 'allowed-domains']));
        $this->assertArrayNotHasKey('_refuse_email_no_response', self::processMail($id, ['from' => 'quelquun@autre-domaine.test']));
    }

    public function testTheFullBuildIsOptInAndIdempotent(): void
    {
        $config = new Config();
        $config->fields = [
            'mail_collectors_enabled' => 0,
            'mail_collectors' => (string) json_encode([$this->mailbox(), $this->mailbox()]),
            'mail_reject_auto_replies' => 0,
            'mail_reject_x_auto_response_suppress' => 0,
            'mail_allowed_domains' => '',
        ];
        $this->assertSame(['created' => 0, 'updated' => 0, 'activated' => 0, 'rules' => 0], (new MailCollectorBuilder())->build($config, [], true, true));

        $config->fields['mail_collectors_enabled'] = 1;
        $first = (new MailCollectorBuilder())->build($config, [], true, true);
        foreach (json_decode($config->fields['mail_collectors'], true) as $mailbox) {
            $collector = new MailCollector();
            if ($collector->getFromDBByCrit(['name' => $mailbox['address']])) {
                $this->collectorIds[] = (int) $collector->getID();
            }
        }
        $this->assertSame(2, $first['created']);
        $this->assertCount(2, $this->collectorIds);

        $second = (new MailCollectorBuilder())->build($config, [], true, true);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['updated']);
    }

    public function testTheConfigurationNeverStoresTheMailboxPassword(): void
    {
        $input = (new Config())->prepareInputForUpdate([
            'mail_collectors' => [['address' => 'support@exemple.test', 'server' => 'imap.exemple.test', 'passwd' => 'MotDePasseFictif429']],
            'mail_collectors_form' => '1',
            'mail_allowed_domains' => 'exemple.test, pas un domaine',
        ]);
        $this->assertStringNotContainsString('MotDePasseFictif429', (string) json_encode($input));
        $this->assertSame('support@exemple.test', json_decode($input['mail_collectors'], true)[0]['address']);
        $this->assertArrayNotHasKey('mail_collectors_form', $input);
        $this->assertSame('exemple.test', $input['mail_allowed_domains']);

        // Toutes les boîtes retirées de l'étape : la liste enregistrée est bien vidée.
        $this->assertSame('[]', (new Config())->prepareInputForUpdate(['mail_collectors_form' => '1'])['mail_collectors']);
    }
}
