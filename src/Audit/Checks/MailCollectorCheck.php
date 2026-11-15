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

namespace GlpiPlugin\Configurationglpiauto\Audit\Checks;

use Config;
use GlpiPlugin\Configurationglpiauto\Audit\AuditCheckInterface;
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use MailCollector;
use Rule;
use RuleAction;
use RuleMailCollector;

/**
 * Issue #429 : contrôles de la réception des tickets par e-mail, sur les seuls collecteurs ACTIFS
 * (un collecteur désactivé ne relève rien) :
 * - collecteur en erreur (compteur `errors` de GLPI, remis à zéro à la première connexion réussie) ;
 * - collecteur sans règle : aucune règle de collecteur active n'affecte d'entité, donc GLPI refuse
 *   chaque message (« aucune règle ne correspond ») ;
 * - boucle : une boîte relevée est aussi une adresse d'envoi de GLPI (ses notifications
 *   reviendraient en tickets), ou une règle native de refus des messages automatiques est désactivée.
 *
 * Lecture seule, pas de correction automatique : les règles relèvent du droit
 * « rule_mailcollector », pas du droit de configuration exigé par l'écran d'audit.
 */
final class MailCollectorCheck implements AuditCheckInterface
{
    private const ENTITY_ACTION_FIELDS = ['entities_id', '_affect_entity_by_domain', '_affect_entity_by_tag', '_affect_entity_by_user_entity'];

    private const SENDER_CONFIG_KEYS = ['admin_email', 'admin_email_noreply', 'admin_reply', 'from_email', 'noreply_email', 'replyto_email', 'smtp_sender'];

    /** uuid => nom (repli) des règles natives qui refusent les messages automatiques. */
    private const NATIVE_LOOP_RULES = [
        'glpi_rule_mail_collector_glpi_notifications' => 'GLPI notifications',
        'glpi_rule_mail_collector_auto_reply_auto_submitted' => 'Auto-Reply Auto-Submitted',
    ];

    public function getKey(): string
    {
        return 'mail_collectors';
    }

    public function getDomain(): string
    {
        return __('Réception des tickets par e-mail', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        $collectors = (new MailCollector())->find(['is_active' => 1], ['name ASC']);
        if ($collectors === []) {
            return [];
        }

        $findings = [];

        $inError = array_filter($collectors, static fn (array $row): bool => (int) $row['errors'] > 0);
        if ($inError !== []) {
            $findings[] = new AuditFinding(
                checkKey: $this->getKey(),
                domain: $this->getDomain(),
                severity: AuditFinding::SEVERITY_WARNING,
                title: sprintf(__('%d collecteur(s) de courriels en erreur', 'configurationglpiauto'), count($inError)),
                description: __('GLPI n\'a pas pu se connecter à ces boîtes lors des dernières relèves : les messages reçus n\'y deviennent plus des tickets.', 'configurationglpiauto'),
                recommendation: __('Ouvrez le collecteur, vérifiez le serveur, le port, l\'identifiant et le mot de passe, puis cliquez sur « Récupérer les courriels maintenant ».', 'configurationglpiauto'),
                links: self::links($inError)
            );
        }

        if (!$this->hasActiveEntityRule()) {
            $findings[] = new AuditFinding(
                checkKey: $this->getKey(),
                domain: $this->getDomain(),
                severity: AuditFinding::SEVERITY_CRITICAL,
                title: __('Collecteurs de courriels sans règle', 'configurationglpiauto'),
                description: __('Aucune règle de collecteur active n\'affecte d\'entité : chaque message relevé est refusé, aucun ticket n\'est créé.', 'configurationglpiauto'),
                recommendation: __('Réactivez la règle « Root » de GLPI ou créez une règle d\'entité par boîte (étape Réception des tickets par e-mail de l\'assistant).', 'configurationglpiauto'),
                links: self::links($collectors)
            );
        }

        $senders = $this->glpiSenderAddresses();
        $looping = array_filter(
            $collectors,
            static fn (array $row): bool => isset($senders[mb_strtolower(trim((string) $row['name']))])
                || isset($senders[mb_strtolower(trim((string) $row['login']))])
        );
        if ($looping !== []) {
            $findings[] = new AuditFinding(
                checkKey: $this->getKey(),
                domain: $this->getDomain(),
                severity: AuditFinding::SEVERITY_CRITICAL,
                title: __('Boucle de messages : une boîte relevée est aussi une adresse d\'envoi de GLPI', 'configurationglpiauto'),
                description: __('Les notifications envoyées par GLPI arrivent dans une boîte qu\'il relève lui-même : elles risquent de revenir en tickets, en boucle.', 'configurationglpiauto'),
                recommendation: __('Utilisez une adresse d\'expédition distincte (par exemple ne-pas-repondre@) dans Configuration > Notifications > Configuration des suivis par courriels.', 'configurationglpiauto'),
                links: self::links($looping)
            );
        }

        $disabledRules = $this->disabledNativeLoopRules();
        if ($disabledRules !== []) {
            $findings[] = new AuditFinding(
                checkKey: $this->getKey(),
                domain: $this->getDomain(),
                severity: AuditFinding::SEVERITY_WARNING,
                title: __('Risque de boucle : refus des messages automatiques désactivé', 'configurationglpiauto'),
                description: sprintf(
                    __('Règle(s) de collecteur native(s) de GLPI désactivée(s) : %s. Les messages d\'absence et les notifications de GLPI peuvent alors créer des tickets en boucle.', 'configurationglpiauto'),
                    implode(', ', $disabledRules)
                ),
                recommendation: __('Réactivez ces règles dans Administration > Règles > Règles d\'affectation d\'un ticket ouvert via un collecteur de courriels.', 'configurationglpiauto')
            );
        }

        return $findings;
    }

    public function canFix(): bool
    {
        return false;
    }

    public function fix(): void
    {
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private static function links(array $rows): array
    {
        $links = [];
        foreach ($rows as $row) {
            $links[(string) $row['name']] = MailCollector::getFormURLWithID((int) $row['id']);
        }

        return $links;
    }

    private function hasActiveEntityRule(): bool
    {
        global $DB;

        return $DB->request([
            'SELECT' => ['r.id'],
            'FROM' => Rule::getTable() . ' AS r',
            'INNER JOIN' => [
                RuleAction::getTable() . ' AS a' => ['ON' => ['a' => 'rules_id', 'r' => 'id']],
            ],
            'WHERE' => [
                'r.sub_type' => RuleMailCollector::class,
                'r.is_active' => 1,
                'a.field' => self::ENTITY_ACTION_FIELDS,
            ],
            'LIMIT' => 1,
        ])->count() > 0;
    }

    /** @return array<string, true> adresses d'envoi de GLPI, en minuscules */
    private function glpiSenderAddresses(): array
    {
        $addresses = [];
        foreach (Config::getConfigurationValues('core', self::SENDER_CONFIG_KEYS) as $value) {
            $value = mb_strtolower(trim((string) $value));
            if ($value !== '') {
                $addresses[$value] = true;
            }
        }

        return $addresses;
    }

    /** @return list<string> */
    private function disabledNativeLoopRules(): array
    {
        global $DB;

        $disabled = [];
        foreach (self::NATIVE_LOOP_RULES as $uuid => $name) {
            $row = $DB->request([
                'SELECT' => ['name', 'is_active'],
                'FROM' => Rule::getTable(),
                'WHERE' => ['sub_type' => RuleMailCollector::class, 'OR' => ['uuid' => $uuid, 'name' => $name]],
                'ORDER' => 'id ASC',
                'LIMIT' => 1,
            ])->current();
            if ($row !== null && empty($row['is_active'])) {
                $disabled[] = (string) $row['name'];
            }
        }

        return $disabled;
    }
}
