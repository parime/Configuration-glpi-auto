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

/**
 * Issue #452 : étape « Inventaire Docker » de l'assistant. Quand le plugin Inventaire Docker
 * (`dockerinventory`, 0.7.0 et plus) est actif, déclare ses connexions (Portainer, WUD, Harbor et,
 * à partir de 0.8.0, Kubernetes) sans passer par les écrans du plugin.
 *
 * Tout passe par la classe `Connection` du plugin, jamais par sa table :
 * - test : `prepareInputForAdd()` (mêmes contrôles et même chiffrement GLPIKey que le formulaire du
 *   plugin), puis `testConnection()` sur la connexion encore non enregistrée ; le résultat est
 *   rendu tel quel (un avertissement reste un avertissement) ;
 * - enregistrement : le même test d'abord, puis `add()` seulement s'il a réussi, même avec un
 *   avertissement. Une connexion de même type et de même adresse déjà déclarée n'est jamais
 *   modifiée : l'enregistrement est refusé avec un lien vers elle ;
 * - registres maîtrisés : `HarborSettings::save()`, seulement sur demande explicite.
 *
 * Les secrets (jeton, mot de passe) ne sont jamais stockés par ce plugin, ni renvoyés, ni
 * journalisés : ils vont du formulaire à `Connection`, qui les chiffre avec la clé de GLPI. Aucun
 * import n'est lancé : la page de la connexion le propose.
 *
 * Droit : celui du plugin pour créer une connexion (`Connection::canCreate()` et `can()`, qui
 * contrôle aussi l'entité), en plus du droit natif « Configuration » exigé par ajax/.
 */
final class DockerInventorySetup
{
    public const PLUGIN_KEY = 'dockerinventory';

    public const MIN_VERSION = '0.7.0';

    public const CONNECTION_CLASS = 'GlpiPlugin\\Dockerinventory\\Connection';

    public const SETTINGS_CLASS = 'GlpiPlugin\\Dockerinventory\\Harbor\\HarborSettings';

    public const TYPE_PORTAINER = 'portainer';
    public const TYPE_WUD = 'wud';
    public const TYPE_HARBOR = 'harbor';
    public const TYPE_KUBERNETES = 'kubernetes';

    /** Champs saisis dans l'étape, par type (le reste garde les valeurs par défaut du plugin). */
    public const FIELDS = [
        self::TYPE_PORTAINER  => ['name', 'url', 'api_key', 'allow_http'],
        self::TYPE_WUD        => ['name', 'url', 'username', 'api_key', 'allow_http'],
        self::TYPE_HARBOR     => ['name', 'url', 'registry_host', 'username', 'api_key', 'allow_http'],
        self::TYPE_KUBERNETES => ['name', 'url', 'api_key', 'ca_certificate'],
    ];

    /** Champs secrets : jamais renvoyés au navigateur. */
    public const SECRET_FIELDS = ['api_key'];

    public const LEVEL_OK = 'ok';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';

    private static ?bool $available = null;

    /** @var (callable(\CommonDBTM): mixed)|null */
    private $tester;

    /**
     * @param (callable(\CommonDBTM): mixed)|null $tester test d'une connexion non enregistrée ;
     *        par défaut `Connection::testConnection()` (remplacé dans les tests, sans réseau)
     */
    public function __construct(?callable $tester = null)
    {
        $this->tester = $tester;
    }

    /**
     * Plugin actif, en version 0.7.0 ou plus, et sa classe chargée.
     */
    public static function isAvailable(): bool
    {
        if (self::$available === null) {
            $version = class_exists('Plugin') && \Plugin::isPluginActive(self::PLUGIN_KEY) ? self::pluginVersion() : '';
            self::$available = $version !== '' && version_compare($version, self::MIN_VERSION, '>=')
                && class_exists(self::CONNECTION_CLASS);
        }

        return self::$available;
    }

    public static function pluginVersion(): string
    {
        $plugin = new \Plugin();
        if (!$plugin->getFromDBbyDir(self::PLUGIN_KEY)) {
            return '';
        }

        return (string) ($plugin->fields['version'] ?? '');
    }

    /**
     * L'utilisateur courant a-t-il le droit du plugin pour créer une connexion ?
     */
    public static function canCreate(): bool
    {
        if (!self::isAvailable()) {
            return false;
        }
        $class = self::CONNECTION_CLASS;

        return (bool) $class::canCreate();
    }

    /**
     * Types proposés : Kubernetes à partir de dockerinventory 0.8.0 seulement.
     *
     * @return list<string>
     */
    public static function types(): array
    {
        $types = [self::TYPE_PORTAINER, self::TYPE_WUD, self::TYPE_HARBOR];
        if (defined(self::CONNECTION_CLASS . '::TYPE_KUBERNETES')) {
            $types[] = self::TYPE_KUBERNETES;
        }

        return $types;
    }

    /**
     * Pure : saisie de l'étape réduite aux champs de ce type, prête pour `Connection`.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function buildInput(string $type, array $raw, int $entityId = 0): array
    {
        $input = ['type' => $type, 'entities_id' => $entityId, 'is_active' => 1];
        foreach (self::FIELDS[$type] ?? [] as $field) {
            if ($field === 'allow_http') {
                $input[$field] = empty($raw[$field]) ? 0 : 1;
                continue;
            }
            $value = $raw[$field] ?? '';
            $input[$field] = is_scalar($value) ? (string) $value : '';
            if (!in_array($field, self::SECRET_FIELDS, true) && $field !== 'ca_certificate') {
                $input[$field] = trim($input[$field]);
            }
        }
        if (($input['name'] ?? '') === '') {
            $input['name'] = self::defaultName($type, (string) ($input['url'] ?? ''));
        }
        $input['name'] = mb_substr((string) $input['name'], 0, 255);

        return $input;
    }

    /**
     * Pure : « Portainer - portainer.exemple.test » quand aucun nom n'est saisi.
     */
    public static function defaultName(string $type, string $url): string
    {
        $label = self::typeLabels()[$type] ?? $type;
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $label . ' - ' . $host : $label;
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_PORTAINER  => 'Portainer',
            self::TYPE_WUD        => "What's Up Docker (WUD)",
            self::TYPE_HARBOR     => 'Harbor',
            self::TYPE_KUBERNETES => 'Kubernetes',
        ];
    }

    /**
     * Pure : niveau d'affichage d'un résultat de `Connection::testConnection()`.
     *
     * @param array<string, mixed> $result
     */
    public static function level(array $result): string
    {
        if (empty($result['ok'])) {
            return self::LEVEL_ERROR;
        }

        return empty($result['warning']) ? self::LEVEL_OK : self::LEVEL_WARNING;
    }

    /**
     * Teste la saisie sans rien enregistrer.
     *
     * @param array<string, mixed> $raw
     * @return array{level: string, message: string}
     */
    public function test(string $type, array $raw): array
    {
        $refusal = $this->refusal($type);
        if ($refusal !== null) {
            return $refusal;
        }
        $input = self::buildInput($type, $raw, self::activeEntity());
        $connection = $this->newConnection();
        $prepared = $this->prepare($connection, $input);
        if (is_string($prepared)) {
            return ['level' => self::LEVEL_ERROR, 'message' => $prepared];
        }

        return $this->runTest($connection, $prepared);
    }

    /**
     * Teste la saisie, puis crée la connexion si le test a réussi (même avec un avertissement).
     *
     * @param array<string, mixed> $raw
     * @return array{level: string, message: string, saved?: bool, id?: int, url?: string}
     */
    public function save(string $type, array $raw): array
    {
        $refusal = $this->refusal($type);
        if ($refusal !== null) {
            return $refusal;
        }
        $input = self::buildInput($type, $raw, self::activeEntity());
        $connection = $this->newConnection();
        if (!$connection->can(-1, CREATE, $input)) {
            return ['level' => self::LEVEL_ERROR, 'message' => self::rightMessage()];
        }
        $prepared = $this->prepare($connection, $input);
        if (is_string($prepared)) {
            return ['level' => self::LEVEL_ERROR, 'message' => $prepared];
        }
        $existing = self::findExisting($type, (string) ($prepared['url'] ?? ''));
        if ($existing !== null) {
            return [
                'level'   => self::LEVEL_ERROR,
                'message' => sprintf(
                    __('Une connexion existe déjà pour cette adresse (« %s ») : elle n\'est pas modifiée. Ouvrez-la dans Inventaire Docker pour la changer.', 'configurationglpiauto'),
                    $existing['name']
                ),
                'url'     => $existing['url'],
            ];
        }
        $result = $this->runTest($connection, $prepared);
        if ($result['level'] === self::LEVEL_ERROR) {
            return [
                'level'   => self::LEVEL_ERROR,
                'message' => sprintf(
                    __('Connexion non enregistrée, le test a échoué : %s', 'configurationglpiauto'),
                    $result['message']
                ),
            ];
        }

        $creator = $this->newConnection();
        $messages = self::sessionMessages();
        // Saisie d'origine (et non $prepared) : add() refait les mêmes contrôles et chiffre la clé.
        $id = $creator->add($input + ['_no_message' => true]);
        $errors = self::restoreSessionMessages($messages);
        if (!$id) {
            return [
                'level'   => self::LEVEL_ERROR,
                'message' => $errors !== '' ? $errors : __('La connexion n\'a pas pu être enregistrée.', 'configurationglpiauto'),
            ];
        }
        $class = self::CONNECTION_CLASS;

        return [
            'level'   => $result['level'],
            'message' => __('Connexion enregistrée.', 'configurationglpiauto') . ' ' . $result['message'],
            'saved'   => true,
            'id'      => (int) $id,
            'url'     => (string) $class::getFormURLWithID((int) $id),
        ];
    }

    /**
     * Registres maîtrisés (Harbor) : texte enregistré dans Inventaire Docker, un hôte par ligne.
     */
    public static function managedRegistries(): string
    {
        $class = self::SETTINGS_CLASS;
        if (!self::isAvailable() || !class_exists($class)) {
            return '';
        }

        return (string) $class::text();
    }

    /**
     * Enregistre les registres maîtrisés, sur demande explicite seulement.
     *
     * @return array{level: string, message: string}
     */
    public function saveManagedRegistries(string $text): array
    {
        $class = self::SETTINGS_CLASS;
        if (!self::canCreate() || !class_exists($class)) {
            return ['level' => self::LEVEL_ERROR, 'message' => self::rightMessage()];
        }
        $hosts = (array) $class::save($text);

        return [
            'level'   => self::LEVEL_OK,
            'message' => $hosts === []
                ? __('Registres maîtrisés : ceux des connexions Harbor (aucun hôte saisi).', 'configurationglpiauto')
                : sprintf(__('Registres maîtrisés enregistrés : %s', 'configurationglpiauto'), implode(', ', array_map('strval', $hosts))),
        ];
    }

    /**
     * Connexions déjà déclarées (nom, type, lien), pour l'étape.
     *
     * @return list<array{name: string, type: string, url: string}>
     */
    public static function existingConnections(): array
    {
        if (!self::canCreate()) {
            return [];
        }
        $class = self::CONNECTION_CLASS;
        $labels = self::typeLabels();
        $list = [];
        foreach ((new $class())->find([], ['type', 'name']) as $row) {
            $list[] = [
                'name' => (string) $row['name'],
                'type' => $labels[(string) $row['type']] ?? (string) $row['type'],
                'url'  => (string) $class::getFormURLWithID((int) $row['id']),
            ];
        }

        return $list;
    }

    /**
     * Données de l'étape de l'assistant ; null (étape masquée) si le plugin n'est pas actif.
     *
     * @return array{can_create: bool, types: list<string>, existing: list<array{name: string, type: string, url: string}>, plugin_url: string, managed_registries: string}|null
     */
    public static function viewData(): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }
        $canCreate = \Session::haveRight('config', UPDATE) && self::canCreate();

        return [
            'can_create'         => $canCreate,
            'types'              => self::types(),
            'existing'           => $canCreate ? self::existingConnections() : [],
            'plugin_url'         => self::pluginPageUrl(),
            'managed_registries' => $canCreate ? self::managedRegistries() : '',
        ];
    }

    /**
     * Page des connexions du plugin, où lancer le premier import.
     */
    public static function pluginPageUrl(): string
    {
        if (!self::isAvailable()) {
            return '';
        }
        $class = self::CONNECTION_CLASS;

        return (string) $class::getSearchURL();
    }

    /**
     * Connexion de même type et de même adresse, quelle que soit son entité.
     *
     * @return array{name: string, url: string}|null
     */
    public static function findExisting(string $type, string $url): ?array
    {
        $class = self::CONNECTION_CLASS;
        if ($url === '' || !class_exists($class)) {
            return null;
        }
        foreach ((new $class())->find(['type' => $type, 'url' => $url], [], 1) as $row) {
            return [
                'name' => (string) $row['name'],
                'url'  => (string) $class::getFormURLWithID((int) $row['id']),
            ];
        }

        return null;
    }

    /**
     * @return array{level: string, message: string}|null
     */
    private function refusal(string $type): ?array
    {
        if (!self::canCreate()) {
            return ['level' => self::LEVEL_ERROR, 'message' => self::rightMessage()];
        }
        if (!in_array($type, self::types(), true)) {
            return ['level' => self::LEVEL_ERROR, 'message' => __('Type de connexion inconnu.', 'configurationglpiauto')];
        }

        return null;
    }

    private static function rightMessage(): string
    {
        return __('Vous n\'avez pas le droit de créer une connexion dans Inventaire Docker.', 'configurationglpiauto');
    }

    private static function activeEntity(): int
    {
        return (int) ($_SESSION['glpiactive_entity'] ?? 0);
    }

    private function newConnection(): \CommonDBTM
    {
        $class = self::CONNECTION_CLASS;
        $connection = new $class();
        assert($connection instanceof \CommonDBTM);

        return $connection;
    }

    /**
     * Contrôles et chiffrement du plugin ; ses messages d'erreur sont rendus, pas laissés en session.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>|string saisie préparée (clé chiffrée), ou message d'erreur
     */
    private function prepare(\CommonDBTM $connection, array $input): array|string
    {
        $messages = self::sessionMessages();
        $prepared = $connection->prepareInputForAdd($input);
        $errors = self::restoreSessionMessages($messages);
        if (!is_array($prepared)) {
            return $errors !== '' ? $errors : __('Saisie refusée par Inventaire Docker.', 'configurationglpiauto');
        }

        return $prepared;
    }

    /**
     * @param array<string, mixed> $prepared
     * @return array{level: string, message: string}
     */
    private function runTest(\CommonDBTM $connection, array $prepared): array
    {
        $connection->fields = $prepared;
        $test = $this->tester !== null
            ? fn () => ($this->tester)($connection)
            : [$connection, 'testConnection'];
        if (!is_callable($test)) {
            return ['level' => self::LEVEL_ERROR, 'message' => __('Test de connexion indisponible.', 'configurationglpiauto')];
        }
        try {
            $result = $test();
        } catch (\Throwable) {
            // Jamais le texte de l'exception : il peut contenir l'adresse et le compte saisis.
            return ['level' => self::LEVEL_ERROR, 'message' => __('Le test de connexion a échoué.', 'configurationglpiauto')];
        }
        $result = is_array($result) ? $result : [];

        return ['level' => self::level($result), 'message' => (string) ($result['message'] ?? '')];
    }

    /**
     * @return array<mixed>
     */
    private static function sessionMessages(): array
    {
        return (array) ($_SESSION['MESSAGE_AFTER_REDIRECT'] ?? []);
    }

    /**
     * Rétablit les messages de session d'avant l'appel au plugin et rend ceux qu'il a ajoutés.
     *
     * @param array<mixed> $before
     */
    private static function restoreSessionMessages(array $before): string
    {
        $added = [];
        foreach ((array) ($_SESSION['MESSAGE_AFTER_REDIRECT'] ?? []) as $level => $messages) {
            $previous = (array) ($before[$level] ?? []);
            foreach (array_slice((array) $messages, count($previous)) as $message) {
                $added[] = html_entity_decode(strip_tags((string) $message), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        if ($before === []) {
            unset($_SESSION['MESSAGE_AFTER_REDIRECT']);
        } else {
            $_SESSION['MESSAGE_AFTER_REDIRECT'] = $before;
        }

        return implode(' ', $added);
    }
}
