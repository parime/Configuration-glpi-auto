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

namespace GlpiPlugin\Configurationglpiauto;

use Entity;
use Group;
use ITILCategory;
use MailCollector;
use Rule;
use RuleAction;
use RuleCollection;
use RuleCriteria;
use RuleMailCollector;
use RuleMailCollectorCollection;
use RuleTicket;
use Session;

/**
 * Issue #429 : réception des tickets par e-mail. Crée les collecteurs de courriels natifs de GLPI
 * (Configuration > Collecteurs), les règles des collecteurs (Administration > Règles > Règles
 * d'affectation d'un ticket ouvert via un collecteur de courriels) et les règles métier des tickets
 * qui classent les tickets d'une boîte (catégorie, groupe).
 *
 * - Un collecteur par adresse, nommé par l'adresse elle-même : GLPI met le nom du collecteur dans
 *   sa liste noire d'expéditeurs (`MailCollector::buildTicket()`), ce qui évite qu'une boîte crée
 *   des tickets avec ses propres messages. Retrouvé par ce nom à chaque exécution : jamais de
 *   doublon. Créé désactivé sauf choix explicite (« Activer la relève ») ; un collecteur existant
 *   n'est jamais désactivé par l'assistant (l'administrateur a pu l'activer depuis GLPI).
 * - Le mot de passe n'est jamais enregistré par le plugin (ni configuration, ni profil, ni
 *   historique, ni export) : lu dans le formulaire et transmis tel quel à GLPI, qui le chiffre
 *   (`MailCollector::prepareInput()`, clé GLPIKey). Vide : le mot de passe déjà enregistré reste.
 * - Entité : règle de collecteur « Collecteur de courriels est <boîte> » placée avant la règle
 *   « Root » de GLPI. Catégorie et groupe : règle métier des tickets (critère « Collecteur de
 *   courriels », à la création), car les règles de collecteur ne savent affecter que l'entité.
 * - Réponses automatiques : les règles natives de GLPI (« GLPI notifications », « Auto-Reply
 *   Auto-Submitted », et sur option « X-Auto-Response-Suppress », inactive par défaut car Outlook
 *   pose aussi cet en-tête sur des messages ordinaires) sont activées, et deux règles complètent :
 *   sujets de messages d'absence, erreurs de distribution (mailer-daemon, postmaster). Option :
 *   refus des expéditeurs hors des domaines autorisés. Refus toujours sans réponse, pour ne jamais
 *   entretenir une boucle de messages.
 *
 * Les règles de l'assistant sont reconnues par leur identifiant (`uuid`), jamais par leur nom :
 * l'administrateur peut les renommer, les déplacer ou les désactiver ensuite dans GLPI.
 */
final class MailCollectorBuilder
{
    public const MAX_MAILBOXES = 20;

    public const UUID_PREFIX = 'configurationglpiauto-mail-';

    /** Règles natives de GLPI activées par l'option « refuser les réponses automatiques ». */
    private const NATIVE_AUTOREPLY_RULES = [
        'glpi_rule_mail_collector_glpi_notifications' => 'GLPI notifications',
        'glpi_rule_mail_collector_auto_reply_auto_submitted' => 'Auto-Reply Auto-Submitted',
    ];

    private const NATIVE_X_AUTO_RESPONSE_SUPPRESS_RULE = [
        'glpi_rule_mail_collector_x_auto_response_suppress' => 'X-Auto-Response-Suppress',
    ];

    /**
     * Sujets des messages d'absence dans les langues du plugin : uniquement des formules propres
     * aux réponses automatiques (jamais « absent » seul, qui ouvrirait le refus d'un vrai message
     * « Absent demain, mon poste ne démarre plus »).
     */
    public const OUT_OF_OFFICE_SUBJECT_REGEX = '/^\s*(r[ée]ponse automatique|message d\'absence|absent\(e\) du bureau|out of office|automatic reply|auto[ -]?reply|autoreply|abwesenheitsnotiz|automatische antwort|risposta automatica|fuori sede|respuesta autom[áa]tica|fuera de la oficina|resposta autom[áa]tica|odpowied[źz] automatyczna)/iu';

    public const BOUNCE_FROM_REGEX = '/^(mailer-daemon|postmaster)@/i';

    public const BOUNCE_SUBJECT_REGEX = '/^\s*(undeliverable|undelivered mail|delivery status notification \(failure\)|mail delivery failed|returned mail|non remis|message non remis|unzustellbar|non recapitabile|no se puede entregar|n[ãa]o entregue)/iu';

    /** @var list<string> */
    private array $unresolved = [];

    /**
     * Normalise la liste des boîtes saisie à l'assistant (POST ou JSON d'un profil importé).
     * Jamais de mot de passe : la clé `passwd` d'une ligne est ignorée ici.
     *
     * @param mixed $raw
     * @return list<array{address: string, server: string, protocol: string, security: string, port: int, validate_cert: bool, folder: string, login: string, accepted_folder: string, refused_folder: string, filesize_max_mb: int, activate: bool, entity: string, category: string, group: string}>
     */
    public static function sanitizeMailboxes($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }

        $clean = [];
        $seen = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $address = mb_strtolower(trim((string) ($row['address'] ?? '')));
            if ($address === '' || filter_var($address, FILTER_VALIDATE_EMAIL) === false || isset($seen[$address])) {
                continue;
            }
            $server = mb_strtolower(trim((string) ($row['server'] ?? '')));
            if (!preg_match('/^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?$/', $server)) {
                $server = '';
            }
            $protocol = ($row['protocol'] ?? 'imap') === 'pop' ? 'pop' : 'imap';
            $security = in_array($row['security'] ?? 'ssl', ['ssl', 'tls', 'none'], true) ? (string) $row['security'] : 'ssl';
            $port = (int) ($row['port'] ?? 0);
            if ($port < 1 || $port > 65535) {
                $port = self::defaultPort($protocol, $security);
            }
            $size = (int) ($row['filesize_max_mb'] ?? 10);

            $seen[$address] = true;
            $clean[] = [
                'address' => $address,
                'server' => $server,
                'protocol' => $protocol,
                'security' => $security,
                'port' => $port,
                'validate_cert' => !empty($row['validate_cert']),
                // POP n'a qu'une boîte de réception : ni dossier relevé, ni dossiers de classement.
                'folder' => $protocol === 'imap' ? self::cleanText($row['folder'] ?? 'INBOX', 'INBOX') : '',
                'login' => self::cleanText($row['login'] ?? '', $address),
                'accepted_folder' => $protocol === 'imap' ? self::cleanText($row['accepted_folder'] ?? '', '') : '',
                'refused_folder' => $protocol === 'imap' ? self::cleanText($row['refused_folder'] ?? '', '') : '',
                'filesize_max_mb' => max(0, min(99, $size)),
                'activate' => !empty($row['activate']),
                'entity' => self::cleanText($row['entity'] ?? '', ''),
                'category' => self::cleanText($row['category'] ?? '', ''),
                'group' => self::cleanText($row['group'] ?? '', ''),
            ];
            if (count($clean) >= self::MAX_MAILBOXES) {
                break;
            }
        }

        return $clean;
    }

    /**
     * Domaines autorisés saisis librement (« exemple.fr, exemple.com ») : seuls les noms de
     * domaine valides sont gardés, en minuscules, sans doublon.
     *
     * @return list<string>
     */
    public static function parseDomains(string $value): array
    {
        $domains = [];
        foreach (preg_split('/[\s,;]+/', mb_strtolower($value)) ?: [] as $domain) {
            $domain = ltrim($domain, '@');
            if (preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
                $domains[] = $domain;
            }
        }

        return array_values(array_unique($domains));
    }

    public static function defaultPort(string $protocol, string $security): int
    {
        if ($protocol === 'pop') {
            return $security === 'ssl' ? 995 : 110;
        }

        return $security === 'ssl' ? 993 : 143;
    }

    private static function cleanText(mixed $value, string $default): string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $value) ?? '');

        return mb_substr($value !== '' ? $value : $default, 0, 255);
    }

    /**
     * Collecteurs déjà présents dans GLPI, pour l'étape de l'assistant (test de connexion) : jamais
     * le mot de passe, seulement s'il est renseigné.
     *
     * @return list<array{id: int, name: string, is_active: bool, has_password: bool, errors: int, url: string}>
     */
    public static function getExistingCollectors(): array
    {
        $list = [];
        foreach ((new MailCollector())->find([], ['name ASC'], 50) as $row) {
            $list[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'is_active' => !empty($row['is_active']),
                'has_password' => !empty($row['passwd']),
                'errors' => (int) $row['errors'],
                'url' => MailCollector::getFormURLWithID((int) $row['id']),
            ];
        }

        return $list;
    }

    /**
     * Noms proposés dans les champs entité, catégorie et groupe de l'étape (listes de suggestions,
     * la saisie reste libre : une catégorie créée par cette même exécution n'existe pas encore).
     *
     * @return array{entities: list<string>, categories: list<string>, groups: list<string>}
     */
    public static function getSuggestions(): array
    {
        $names = static function (string $itemtype, array $where = []): array {
            global $DB;
            $list = [];
            foreach ($DB->request(['SELECT' => ['id', 'completename'], 'FROM' => $itemtype::getTable(), 'WHERE' => $where, 'ORDER' => 'completename ASC', 'LIMIT' => 300]) as $row) {
                if ($itemtype !== Entity::class || Session::haveAccessToEntity((int) $row['id'])) {
                    $list[] = (string) $row['completename'];
                }
            }

            return $list;
        };

        return [
            'entities' => $names(Entity::class),
            'categories' => $names(ITILCategory::class),
            'groups' => $names(Group::class, ['is_assign' => 1]),
        ];
    }

    /** @return list<string> Noms d'entité, de catégorie ou de groupe saisis mais introuvables. */
    public function getUnresolved(): array
    {
        return array_values(array_unique($this->unresolved));
    }

    /**
     * @param array<string, string> $passwordsByAddress adresse (minuscules) => mot de passe saisi,
     *        lu dans le formulaire et jamais enregistré par le plugin
     * @return array{created: int, updated: int, activated: int, rules: int}
     */
    public function build(Config $config, array $passwordsByAddress, bool $canManageCollectorRules, bool $canManageTicketRules): array
    {
        $result = ['created' => 0, 'updated' => 0, 'activated' => 0, 'rules' => 0];
        if (empty($config->fields['mail_collectors_enabled'])) {
            return $result;
        }

        foreach (self::sanitizeMailboxes($config->fields['mail_collectors'] ?? '[]') as $mailbox) {
            if ($mailbox['server'] === '') {
                continue;
            }
            [$collectorId, $created, $activated] = $this->ensureCollector($mailbox, $passwordsByAddress[$mailbox['address']] ?? '');
            if ($collectorId <= 0) {
                continue;
            }
            $result[$created ? 'created' : 'updated']++;
            $result['activated'] += $activated ? 1 : 0;

            if ($canManageCollectorRules) {
                $result['rules'] += $this->ensureEntityRule($collectorId, $mailbox);
            }
            if ($canManageTicketRules) {
                $result['rules'] += $this->ensureTicketRule($collectorId, $mailbox);
            }
        }

        if ($canManageCollectorRules) {
            $result['rules'] += $this->applyRejectionRules($config);
        }

        return $result;
    }

    /**
     * @param array{address: string, server: string, protocol: string, security: string, port: int, validate_cert: bool, folder: string, login: string, accepted_folder: string, refused_folder: string, filesize_max_mb: int, activate: bool, entity: string, category: string, group: string} $mailbox
     * @return array{0: int, 1: bool, 2: bool} identifiant, créé ?, activé par cette exécution ?
     */
    public function ensureCollector(array $mailbox, string $password = ''): array
    {
        $input = [
            'name' => $mailbox['address'],
            'mail_server' => $mailbox['server'],
            'server_port' => $mailbox['port'],
            'server_type' => '/' . $mailbox['protocol'],
            'server_ssl' => $mailbox['security'] === 'ssl' ? '/ssl' : '',
            'server_tls' => $mailbox['security'] === 'tls' ? '/tls' : '',
            'server_cert' => $mailbox['validate_cert'] ? '/validate-cert' : '/novalidate-cert',
            'server_rsh' => '',
            'server_secure' => '',
            'server_debug' => '',
            'server_mailbox' => $mailbox['folder'],
            'login' => $mailbox['login'],
            'accepted' => $mailbox['accepted_folder'],
            'refused' => $mailbox['refused_folder'],
            'filesize_max' => $mailbox['filesize_max_mb'] * 1048576,
        ];
        if ($password !== '') {
            $input['passwd'] = $password;
        }

        $collector = new MailCollector();
        if (Lookup::loadFirst($collector, ['name' => $mailbox['address']])) {
            $wasActive = !empty($collector->fields['is_active']);
            if ($mailbox['activate']) {
                $input['is_active'] = 1;
            }
            $collector->update(['id' => $collector->getID()] + $input);

            return [(int) $collector->getID(), false, $mailbox['activate'] && !$wasActive];
        }

        $id = (int) $collector->add($input + [
            'is_active' => $mailbox['activate'] ? 1 : 0,
            'comment' => __('Créé par l\'assistant Configuration GLPI Auto.', 'configurationglpiauto'),
        ]);

        return [$id, $id > 0, $id > 0 && $mailbox['activate']];
    }

    /**
     * Règle de collecteur « entité » de la boîte : créée, mise à jour, ou supprimée quand aucune
     * entité n'est plus choisie (règle de l'assistant uniquement, reconnue par son uuid).
     *
     * @param array{address: string, entity: string} $mailbox
     */
    public function ensureEntityRule(int $collectorId, array $mailbox): int
    {
        $uuid = self::UUID_PREFIX . 'entity-' . $collectorId;
        $entityId = $mailbox['entity'] !== '' ? $this->resolveEntity($mailbox['entity']) : null;
        if ($entityId === null) {
            $this->deleteRule($uuid);

            return 0;
        }

        $rulesId = $this->ensureRule(
            $uuid,
            RuleMailCollector::class,
            sprintf('Collecteur %s : entité', $mailbox['address']),
            Rule::AND_MATCHING,
            [['mailcollector', Rule::PATTERN_IS, (string) $collectorId]],
            [['assign', 'entities_id', (string) $entityId]]
        );

        return $rulesId > 0 ? 1 : 0;
    }

    /**
     * Règle métier des tickets « catégorie et groupe » de la boîte (à la création du ticket).
     *
     * @param array{address: string, category: string, group: string} $mailbox
     */
    public function ensureTicketRule(int $collectorId, array $mailbox): int
    {
        $uuid = self::UUID_PREFIX . 'ticket-' . $collectorId;
        $actions = [];
        if ($mailbox['category'] !== '' && ($categoryId = $this->resolveCategory($mailbox['category'])) !== null) {
            $actions[] = ['assign', 'itilcategories_id', (string) $categoryId];
        }
        if ($mailbox['group'] !== '' && ($groupId = $this->resolveGroup($mailbox['group'])) !== null) {
            $actions[] = ['assign', '_groups_id_assign', (string) $groupId];
        }
        if ($actions === []) {
            $this->deleteRule($uuid);

            return 0;
        }

        $rulesId = $this->ensureRule(
            $uuid,
            RuleTicket::class,
            sprintf('Collecteur %s : catégorie et groupe', $mailbox['address']),
            Rule::AND_MATCHING,
            [['_mailgate', Rule::PATTERN_IS, (string) $collectorId]],
            $actions,
            ['condition' => RuleTicket::ONADD]
        );

        return $rulesId > 0 ? 1 : 0;
    }

    /**
     * Refus des réponses automatiques, des erreurs de distribution et, sur option, des expéditeurs
     * hors domaines autorisés. Une option décochée ne désactive rien : seule la règle des domaines,
     * propre à l'assistant, est retirée quand la liste est vidée.
     */
    public function applyRejectionRules(Config $config): int
    {
        $count = 0;
        if (!empty($config->fields['mail_reject_auto_replies'])) {
            $count += $this->activateNativeRules(self::NATIVE_AUTOREPLY_RULES);
            $count += $this->ensureRule(
                self::UUID_PREFIX . 'out-of-office',
                RuleMailCollector::class,
                'Collecteurs : refuser les messages d\'absence',
                Rule::AND_MATCHING,
                [['subject', Rule::REGEX_MATCH, self::OUT_OF_OFFICE_SUBJECT_REGEX]],
                [['assign', '_refuse_email_no_response', '1']]
            ) > 0 ? 1 : 0;
            $count += $this->ensureRule(
                self::UUID_PREFIX . 'bounce',
                RuleMailCollector::class,
                'Collecteurs : refuser les erreurs de distribution',
                Rule::OR_MATCHING,
                [
                    ['from', Rule::REGEX_MATCH, self::BOUNCE_FROM_REGEX],
                    ['subject', Rule::REGEX_MATCH, self::BOUNCE_SUBJECT_REGEX],
                ],
                [['assign', '_refuse_email_no_response', '1']]
            ) > 0 ? 1 : 0;
        }
        if (!empty($config->fields['mail_reject_x_auto_response_suppress'])) {
            $count += $this->activateNativeRules(self::NATIVE_X_AUTO_RESPONSE_SUPPRESS_RULE);
        }

        $domains = self::parseDomains((string) ($config->fields['mail_allowed_domains'] ?? ''));
        $uuid = self::UUID_PREFIX . 'allowed-domains';
        if ($domains === []) {
            $this->deleteRule($uuid);
        } else {
            $count += $this->ensureRule(
                $uuid,
                RuleMailCollector::class,
                'Collecteurs : refuser les expéditeurs hors domaines autorisés',
                Rule::AND_MATCHING,
                [['from', Rule::REGEX_NOT_MATCH, self::allowedDomainsRegex($domains)]],
                [['assign', '_refuse_email_no_response', '1']]
            ) > 0 ? 1 : 0;
        }

        return $count;
    }

    /**
     * @param list<string> $domains
     */
    public static function allowedDomainsRegex(array $domains): string
    {
        $quoted = array_map(static fn (string $d): string => preg_quote($d, '/'), $domains);

        return '/@([a-z0-9-]+\.)*(' . implode('|', $quoted) . ')$/i';
    }

    /**
     * @param array<string, string> $rules uuid => nom (repli si l'uuid n'existe pas dans cette version de GLPI)
     */
    private function activateNativeRules(array $rules): int
    {
        $count = 0;
        foreach ($rules as $uuid => $name) {
            $rule = new Rule();
            if (
                !Lookup::loadFirst($rule, ['sub_type' => RuleMailCollector::class, 'uuid' => $uuid])
                && !Lookup::loadFirst($rule, ['sub_type' => RuleMailCollector::class, 'name' => $name])
            ) {
                continue;
            }
            if (empty($rule->fields['is_active'])) {
                $rule->update(['id' => $rule->getID(), 'is_active' => 1]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Crée ou remet à jour une règle de l'assistant : critères et actions remplacés à chaque
     * exécution ; nom, rang et état actif laissés tels que l'administrateur les a réglés ensuite.
     *
     * @param list<array{0: string, 1: int, 2: string}> $criteria
     * @param list<array{0: string, 1: string, 2: string}> $actions
     * @param array<string, mixed> $extra
     */
    private function ensureRule(string $uuid, string $subType, string $name, string $match, array $criteria, array $actions, array $extra = []): int
    {
        $rule = new Rule();
        if (Lookup::loadFirst($rule, ['uuid' => $uuid, 'sub_type' => $subType])) {
            $rulesId = (int) $rule->getID();
            foreach ((new RuleCriteria())->find(['rules_id' => $rulesId]) as $row) {
                (new RuleCriteria())->delete(['id' => (int) $row['id']], true);
            }
            foreach ((new RuleAction())->find(['rules_id' => $rulesId]) as $row) {
                (new RuleAction())->delete(['id' => (int) $row['id']], true);
            }
            $rule->update(['id' => $rulesId, 'match' => $match]);
            $isNew = false;
        } else {
            $rulesId = (int) $rule->add([
                'name' => $name,
                'uuid' => $uuid,
                'sub_type' => $subType,
                'match' => $match,
                'is_active' => 1,
                'entities_id' => 0,
                'is_recursive' => 1,
                'description' => __('Règle créée par l\'assistant Configuration GLPI Auto (réception des tickets par e-mail).', 'configurationglpiauto'),
            ] + $extra);
            if ($rulesId <= 0) {
                return 0;
            }
            $isNew = true;
        }

        foreach ($criteria as [$criterion, $condition, $pattern]) {
            (new RuleCriteria())->add(['rules_id' => $rulesId, 'criteria' => $criterion, 'condition' => $condition, 'pattern' => $pattern]);
        }
        foreach ($actions as [$actionType, $field, $value]) {
            (new RuleAction())->add(['rules_id' => $rulesId, 'action_type' => $actionType, 'field' => $field, 'value' => $value]);
        }

        if ($isNew && $subType === RuleMailCollector::class) {
            $this->moveBeforeFirstEntityRule($rulesId);
        }

        return $rulesId;
    }

    private function deleteRule(string $uuid): void
    {
        $rule = new Rule();
        if (Lookup::loadFirst($rule, ['uuid' => $uuid])) {
            $rule->delete(['id' => $rule->getID()], true);
        }
    }

    /**
     * GLPI s'arrête à la première règle de collecteur qui correspond : une règle de l'assistant est
     * placée juste avant la première règle qui affecte une entité (« Root » sur une instance neuve),
     * donc après les refus natifs de GLPI, et les refus de l'assistant avant toute affectation.
     */
    private function moveBeforeFirstEntityRule(int $rulesId): void
    {
        global $DB;

        $first = $DB->request([
            'SELECT' => ['r.id'],
            'FROM' => Rule::getTable() . ' AS r',
            'INNER JOIN' => [
                RuleAction::getTable() . ' AS a' => ['ON' => ['a' => 'rules_id', 'r' => 'id']],
            ],
            'WHERE' => [
                'r.sub_type' => RuleMailCollector::class,
                'NOT' => ['r.id' => $rulesId],
                'a.field' => ['entities_id', '_affect_entity_by_domain', '_affect_entity_by_tag', '_affect_entity_by_user_entity'],
            ],
            'ORDER' => 'r.ranking ASC',
            'LIMIT' => 1,
        ])->current();

        $own = new Rule();
        if ($first === null || !$own->getFromDB($rulesId)) {
            return;
        }
        $reference = new Rule();
        if ($reference->getFromDB((int) $first['id']) && (int) $own->fields['ranking'] > (int) $reference->fields['ranking']) {
            (new RuleMailCollectorCollection())->moveRule($rulesId, (int) $first['id'], RuleCollection::MOVE_BEFORE);
        }
    }

    private function resolveEntity(string $name): ?int
    {
        $entity = new Entity();
        if (
            (Lookup::loadFirst($entity, ['completename' => $name]) || Lookup::loadFirst($entity, ['name' => $name]))
            && Session::haveAccessToEntity((int) $entity->getID())
        ) {
            return (int) $entity->getID();
        }
        $this->unresolved[] = $name;

        return null;
    }

    private function resolveCategory(string $name): ?int
    {
        $category = new ITILCategory();
        if (Lookup::loadFirst($category, ['completename' => $name]) || Lookup::loadFirst($category, ['name' => $name])) {
            return (int) $category->getID();
        }
        $this->unresolved[] = $name;

        return null;
    }

    private function resolveGroup(string $name): ?int
    {
        $group = new Group();
        if (Lookup::loadFirst($group, ['completename' => $name]) || Lookup::loadFirst($group, ['name' => $name])) {
            return (int) $group->getID();
        }
        $this->unresolved[] = $name;

        return null;
    }
}
