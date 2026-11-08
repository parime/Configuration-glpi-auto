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

use CommonITILObject;
use GlpiPlugin\Configurationglpiauto\PluginTemplate;
use ITILFollowup;
use ITILSolution;
use SolutionType;
use Ticket;
use User;
use UserEmail;

/**
 * Issue #345 : import de tickets Jira (export CSV) en tickets GLPI.
 *
 * - Aperçu à blanc d'abord (JiraImporter::preview()) : rien n'est écrit.
 * - Personnes rapprochées par adresse e-mail puis par « prénom nom » ; une personne inconnue est
 *   signalée, jamais créée.
 * - Idempotent : la clé Jira (aussi gardée comme identifiant externe du ticket, cherchable) évite
 *   les doublons ; un nouvel import met à jour le titre et le statut et n'ajoute que les nouveaux
 *   commentaires.
 * - Aucune notification pendant l'import ; dates de création et de résolution conservées.
 */
final class JiraImporter
{
    public const TABLE = 'glpi_plugin_configurationglpiauto_jiraimports';

    /** Nom du gabarit de solution créé par SolutionLibraryBuilder (issue #346). */
    private const NOT_RETAINED_SOLUTION = 'Demande non retenue';

    /** Fichier téléversé gardé entre l'aperçu et l'import (dossier temporaire de GLPI). */
    private const SESSION_KEY = 'plugin_configurationglpiauto_jira_import';

    public static function storePending(string $content, int $entitiesId): void
    {
        self::forgetPending();
        $path = GLPI_TMP_DIR . '/jira-import-' . bin2hex(random_bytes(8)) . '.csv';
        file_put_contents($path, $content);
        $_SESSION[self::SESSION_KEY] = ['file' => $path, 'entities_id' => $entitiesId];
    }

    /**
     * Issue #345, phase 2 : tickets déjà lus par l'API Jira (forme de JiraCsvParser), gardés dans un
     * fichier temporaire comme l'export CSV ; le jeton d'API, lui, n'est jamais écrit.
     *
     * @param list<array<string, mixed>> $issues
     * @param list<array{line: int, message: string}> $errors
     */
    public static function storePendingIssues(array $issues, array $errors, int $entitiesId, string $attachmentsDir = ''): void
    {
        $dir = $_SESSION[self::SESSION_KEY]['attachments_dir'] ?? '';
        // Le dossier des pièces jointes vient d'être rempli pour cet import : il n'est pas effacé.
        unset($_SESSION[self::SESSION_KEY]['attachments_dir']);
        self::forgetPending();
        $path = GLPI_TMP_DIR . '/jira-import-' . bin2hex(random_bytes(8)) . '.json';
        file_put_contents($path, (string) json_encode(['issues' => $issues, 'errors' => $errors]));
        $_SESSION[self::SESSION_KEY] = [
            'file'            => $path,
            'entities_id'     => $entitiesId,
            'attachments_dir' => $attachmentsDir !== '' ? $attachmentsDir : $dir,
        ];
    }

    /**
     * Issue #345, phase 2 : dossier temporaire des pièces jointes téléchargées pendant la lecture par
     * l'API, effacé avec l'import en attente (forgetPending()).
     */
    public static function newAttachmentsDir(): string
    {
        $dir = GLPI_TMP_DIR . '/jira-import-' . bin2hex(random_bytes(8)) . '-files';
        mkdir($dir, 0700);

        return $dir;
    }

    /** Dossier des pièces jointes de l'import en attente, s'il a été créé par newAttachmentsDir(). */
    public static function pendingAttachmentsDir(): string
    {
        $dir = (string) ($_SESSION[self::SESSION_KEY]['attachments_dir'] ?? '');

        return self::isOurAttachmentsDir($dir) ? $dir : '';
    }

    private static function isOurAttachmentsDir(string $dir): bool
    {
        return $dir !== '' && preg_match('/^jira-import-[0-9a-f]{16}-files$/', basename($dir)) === 1
            && is_dir($dir) && realpath(dirname($dir)) === realpath(GLPI_TMP_DIR);
    }

    /**
     * @return array{issues: list<array<string, mixed>>, errors: list<array{line: int, message: string}>}|null
     */
    public static function loadPending(): ?array
    {
        $file = (string) ($_SESSION[self::SESSION_KEY]['file'] ?? '');
        if ($file === '' || !is_file($file)) {
            return null;
        }
        if (str_ends_with($file, '.json')) {
            $data = json_decode((string) file_get_contents($file), true);

            return is_array($data) && is_array($data['issues'] ?? null)
                ? ['issues' => array_values($data['issues']), 'errors' => array_values((array) ($data['errors'] ?? []))]
                : null;
        }

        return JiraCsvParser::parse((string) file_get_contents($file));
    }

    public static function pendingEntity(): int
    {
        return (int) ($_SESSION[self::SESSION_KEY]['entities_id'] ?? -1);
    }

    public static function forgetPending(): void
    {
        $file = (string) ($_SESSION[self::SESSION_KEY]['file'] ?? '');
        // Seul un fichier créé par storePending() (dossier temporaire de GLPI, nom imposé) est supprimé.
        $isOurs = preg_match('/^jira-import-[0-9a-f]{16}\.(csv|json)$/', basename($file)) === 1
            && realpath(dirname($file)) === realpath(GLPI_TMP_DIR);
        if ($isOurs && is_file($file)) {
            @unlink($file); // nosemgrep: php.lang.security.unlink-use.unlink-use
        }
        $dir = (string) ($_SESSION[self::SESSION_KEY]['attachments_dir'] ?? '');
        if (self::isOurAttachmentsDir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $attachment) {
                if (is_file($attachment)) {
                    @unlink($attachment); // nosemgrep: php.lang.security.unlink-use.unlink-use
                }
            }
            @rmdir($dir);
        }
        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Issue #345, phase 2 : le plugin Tag (étiquettes) est-il installé et actif ?
     */
    public static function tagsAvailable(): bool
    {
        return \Plugin::isPluginActive('tag') && class_exists('PluginTagTag') && class_exists('PluginTagTagItem');
    }

    /**
     * @param list<array<string, mixed>> $issues
     * @return array<string, int> étiquette => nombre de tickets, par ordre alphabétique
     */
    public static function countLabels(array $issues): array
    {
        $labels = [];
        foreach ($issues as $issue) {
            foreach ((array) ($issue['labels'] ?? []) as $label) {
                $label = trim((string) $label);
                if ($label !== '') {
                    $labels[$label] = ($labels[$label] ?? 0) + 1;
                }
            }
        }
        ksort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        return $labels;
    }

    /**
     * Formulaires actifs du catalogue (GLPI 11+) dont le nom contient le sujet, trois au plus.
     * Lecture seule : rien n'est créé.
     *
     * @param list<array{word: string, count: int, examples: list<string>}> $subjects
     * @return list<array{word: string, count: int, examples: list<string>, forms: list<array{name: string, url: string}>}>
     */
    public static function withMatchingForms(array $subjects): array
    {
        global $DB;

        $forms = [];
        if (class_exists(\Glpi\Form\Form::class) && $DB->tableExists(\Glpi\Form\Form::getTable())) {
            $table = \Glpi\Form\Form::getTable();
            foreach (
                $DB->request([
                    'SELECT' => ['id', 'name'],
                    'FROM'   => $table,
                    'WHERE'  => ['is_active' => 1, 'is_deleted' => 0, 'is_draft' => 0]
                        + getEntitiesRestrictCriteria($table, '', '', true),
                ]) as $row
            ) {
                $forms[] = [
                    'name' => (string) $row['name'],
                    'slug' => ' ' . JiraCsvParser::slug((string) $row['name']) . ' ',
                    'url'  => \Glpi\Form\Form::getFormURLWithID((int) $row['id']),
                ];
            }
        }
        foreach ($subjects as $i => $subject) {
            $matches = [];
            foreach ($forms as $form) {
                // Mot entier ou début de mot (« imprimante » trouve « imprimantes »).
                if (str_contains($form['slug'], ' ' . $subject['word']) && count($matches) < 3) {
                    $matches[] = ['name' => $form['name'], 'url' => $form['url']];
                }
            }
            $subjects[$i]['forms'] = $matches;
        }

        return $subjects;
    }

    /**
     * Issue #345 : correspondance projet Jira => entité et catégorie, lue dans le formulaire de
     * l'aperçu (clés JiraMapping::projectKey()). Une entité hors des entités de l'utilisateur ou une
     * catégorie invisible depuis l'entité choisie sont ignorées (valeurs de l'import par défaut).
     *
     * @param list<array<string, mixed>> $issues
     * @param array<mixed> $entities projectKey => entities_id
     * @param array<mixed> $categories projectKey => itilcategories_id
     * @return array<string, array{entities_id: int, itilcategories_id: int}>
     */
    public static function projectMap(array $issues, array $entities, array $categories, int $defaultEntity): array
    {
        $map = [];
        foreach (array_keys(JiraMapping::summarize($issues)['projects']) as $project) {
            $project = (string) $project;
            $key = JiraMapping::projectKey($project);
            $entity = (int) ($entities[$key] ?? $defaultEntity);
            if (!\Session::haveAccessToEntity($entity)) {
                $entity = $defaultEntity;
            }
            $categoryId = (int) ($categories[$key] ?? 0);
            if ($categoryId > 0) {
                $category = new \ITILCategory();
                $visible = $category->getFromDB($categoryId)
                    && in_array((int) $category->fields['entities_id'], getAncestorsOf('glpi_entities', $entity) + [$entity => $entity], true)
                    && ((int) $category->fields['entities_id'] === $entity || (bool) $category->fields['is_recursive']);
                $categoryId = $visible ? $categoryId : 0;
            }
            $map[$project] = ['entities_id' => $entity, 'itilcategories_id' => $categoryId];
        }

        return $map;
    }

    /** @var array<string, int> cache étiquette => identifiant du plugin Tag */
    private array $tags = [];

    /** @var array<string, int> cache nom/e-mail => utilisateur */
    private array $users = [];

    /**
     * @param list<array<string, mixed>> $issues
     * @return array{summary: array<string, array<string, int>>, statuses: array<string, int|string>,
     *     types: array<string, int>, people_found: array<string, int>, people_missing: list<string>,
     *     already_imported: int, total: int, attachments: int, attachments_skipped: int,
     *     labels: array<string, int>, tags_available: bool,
     *     subjects: list<array{word: string, count: int, examples: list<string>, forms: list<array{name: string, url: string}>}>}
     */
    public function preview(array $issues): array
    {
        $summary = JiraMapping::summarize($issues);
        $statuses = [];
        foreach (array_keys($summary['statuses']) as $status) {
            $statuses[(string) $status] = JiraMapping::defaultStatus((string) $status);
        }
        $types = [];
        foreach (array_keys($summary['types']) as $type) {
            $types[(string) $type] = JiraMapping::defaultType((string) $type);
        }
        $found = [];
        $missing = [];
        foreach ($issues as $issue) {
            foreach ([['reporter', 'reporter_email'], ['assignee', 'assignee_email']] as [$name, $email]) {
                $label = (string) $issue[$name];
                if ($label === '' || isset($found[$label]) || in_array($label, $missing, true)) {
                    continue;
                }
                $usersId = $this->resolveUser($label, (string) ($issue[$email] ?? ''));
                if ($usersId > 0) {
                    $found[$label] = $usersId;
                } else {
                    $missing[] = $label;
                }
            }
        }
        sort($missing);

        return [
            'summary'          => $summary,
            'statuses'         => $statuses,
            'types'            => $types,
            'people_found'     => $found,
            'people_missing'   => $missing,
            'already_imported' => count(array_filter(
                $issues,
                fn (array $issue): bool => $this->findTicket((string) $issue['key']) > 0
            )),
            'total'            => count($issues),
            // Issue #345, phase 2 : pièces jointes lues par l'API (téléchargées ou écartées).
            'attachments'         => $this->countAttachments($issues, true),
            'attachments_skipped' => $this->countAttachments($issues, false),
            // Issue #345, phase 2 : étiquettes Jira, importables comme étiquettes du plugin Tag.
            'labels'              => self::countLabels($issues),
            'tags_available'      => self::tagsAvailable(),
            // Issue #345, bonus : sujets fréquents et formulaires du catalogue qui y répondent.
            'subjects'            => self::withMatchingForms(JiraMapping::frequentSubjects($issues)),
        ];
    }

    /**
     * @param list<array<string, mixed>> $issues
     */
    private function countAttachments(array $issues, bool $downloaded): int
    {
        $count = 0;
        foreach ($issues as $issue) {
            foreach ((array) ($issue['attachments'] ?? []) as $attachment) {
                $count += ((string) ($attachment['file'] ?? '') !== '') === $downloaded ? 1 : 0;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $issues
     * @param array<string, mixed> $statusMap statut Jira => statut GLPI (ou STATUS_NOT_RETAINED)
     * @param array<string, mixed> $typeMap   type Jira => type de ticket GLPI
     * @return array{created: int, updated: int, attachments: int, labels: int, errors: list<string>, log: list<string>}
     */
    public function import(
        array $issues,
        array $statusMap,
        array $typeMap,
        int $entitiesId,
        bool $importLabels = false,
        array $projectMap = []
    ): array {
        global $CFG_GLPI;

        $result = ['created' => 0, 'updated' => 0, 'attachments' => 0, 'labels' => 0, 'errors' => [], 'log' => []];
        $importLabels = $importLabels && self::tagsAvailable();
        $attachmentsDir = self::pendingAttachmentsDir();
        // Aucune notification pendant l'import (en mémoire seulement, rétabli à la fin).
        $notifications = $CFG_GLPI['use_notifications'] ?? 0;
        $CFG_GLPI['use_notifications'] = 0;
        try {
            foreach ($issues as $issue) {
                $key = (string) $issue['key'];
                $status = JiraMapping::normalizeStatus($statusMap[(string) $issue['status']] ?? null, (string) $issue['status']);
                $type = JiraMapping::normalizeType($typeMap[(string) $issue['type']] ?? null, (string) $issue['type']);
                $ticketsId = $this->findTicket($key);
                if ($ticketsId > 0) {
                    $this->update($ticketsId, $issue, $status);
                    $result['attachments'] += $this->addAttachments($ticketsId, $issue, $attachmentsDir);
                    $result['labels'] += $importLabels ? $this->addLabels($ticketsId, $issue, $entitiesId, $result) : 0;
                    $result['updated']++;
                    $result['log'][] = sprintf(__('%1$s : ticket %2$d mis à jour', 'configurationglpiauto'), $key, $ticketsId);
                    continue;
                }
                // Issue #345 : entité et catégorie propres au projet Jira, sinon celles de l'import.
                $target = $projectMap[(string) $issue['project']] ?? [];
                $ticketsId = $this->create(
                    $issue,
                    $status,
                    $type,
                    (int) ($target['entities_id'] ?? $entitiesId),
                    (int) ($target['itilcategories_id'] ?? 0)
                );
                if ($ticketsId <= 0) {
                    $result['errors'][] = sprintf(__('%s : création du ticket impossible', 'configurationglpiauto'), $key);
                    continue;
                }
                $result['created']++;
                $result['attachments'] += $this->addAttachments($ticketsId, $issue, $attachmentsDir);
                $result['labels'] += $importLabels ? $this->addLabels($ticketsId, $issue, $entitiesId, $result) : 0;
                $result['log'][] = sprintf(__('%1$s : ticket %2$d créé', 'configurationglpiauto'), $key, $ticketsId);
            }
        } finally {
            $CFG_GLPI['use_notifications'] = $notifications;
        }

        return $result;
    }

    public function findTicket(string $key): int
    {
        global $DB;

        $row = $DB->request(['SELECT' => ['tickets_id'], 'FROM' => self::TABLE, 'WHERE' => ['jira_key' => $key]])->current();
        if ($row === null) {
            return 0;
        }
        $ticket = new Ticket();

        return $ticket->getFromDB((int) $row['tickets_id']) ? (int) $row['tickets_id'] : 0;
    }

    /**
     * Compte GLPI actif d'une personne Jira : adresse e-mail d'abord, puis « prénom nom » (dans
     * les deux ordres) ou identifiant, seulement s'il n'y a qu'un seul compte correspondant.
     */
    public function resolveUser(string $displayName, string $email = ''): int
    {
        global $DB;

        $cacheKey = mb_strtolower($displayName . '|' . $email);
        if (isset($this->users[$cacheKey])) {
            return $this->users[$cacheKey];
        }
        $usersId = 0;
        $email = trim($email !== '' ? $email : (filter_var($displayName, FILTER_VALIDATE_EMAIL) ? $displayName : ''));
        if ($email !== '') {
            // Recherche directe (User::getOrImportByEmail() peut interroger l'annuaire) : un seul
            // compte actif portant cette adresse.
            $ids = array_column(iterator_to_array($DB->request([
                'SELECT'     => ['glpi_useremails.users_id'],
                'FROM'       => UserEmail::getTable(),
                'INNER JOIN' => [
                    User::getTable() => ['ON' => [UserEmail::getTable() => 'users_id', User::getTable() => 'id']],
                ],
                'WHERE'      => [
                    'glpi_useremails.email' => $email,
                    'glpi_users.is_active'  => 1,
                    'glpi_users.is_deleted' => 0,
                ],
            ]), false), 'users_id');
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $usersId = count($ids) === 1 ? $ids[0] : 0;
        }
        if ($usersId <= 0 && trim($displayName) !== '') {
            $keys = JiraMapping::nameKeys($displayName);
            $matches = [];
            foreach ($DB->request([
                'SELECT' => ['id', 'name', 'firstname', 'realname'],
                'FROM'   => User::getTable(),
                'WHERE'  => ['is_active' => 1, 'is_deleted' => 0],
            ]) as $row) {
                $candidates = [
                    JiraCsvParser::slug((string) $row['name']),
                    JiraCsvParser::slug($row['firstname'] . ' ' . $row['realname']),
                ];
                if (array_intersect($keys, $candidates) !== []) {
                    $matches[] = (int) $row['id'];
                }
            }
            $usersId = count($matches) === 1 ? $matches[0] : 0;
        }

        return $this->users[$cacheKey] = $usersId;
    }

    /**
     * @param array<string, mixed> $issue
     */
    private function create(array $issue, int|string $status, int $type, int $entitiesId, int $categoryId = 0): int
    {
        global $DB;

        $key = (string) $issue['key'];
        $content = PluginTemplate::render('jira/ticket_content.html.twig', [
            'text'    => trim((string) $issue['description']) !== ''
                ? (string) $issue['description']
                : (string) $issue['summary'],
            'key'     => $key,
            'project' => (string) $issue['project'],
            'labels'  => $issue['labels'],
        ]);
        $priority = JiraMapping::defaultPriority((string) $issue['priority']);
        $input = [
            'name'          => (string) $issue['summary'],
            'content'       => $content,
            'type'          => $type,
            'entities_id'   => $entitiesId,
            'urgency'       => min(5, max(1, $priority)),
            'impact'        => 3,
            'priority'      => $priority,
            'externalid'    => $key,
            '_disablenotif' => true,
        ];
        if ($categoryId > 0) {
            $input['itilcategories_id'] = $categoryId;
        }
        if ($issue['created'] !== null) {
            // Date d'ouverture et date affichée dans le fil du ticket : celles de Jira.
            $input['date'] = $issue['created'];
            $input['date_creation'] = $issue['created'];
        }
        $requester = $this->resolveUser((string) $issue['reporter'], (string) ($issue['reporter_email'] ?? ''));
        if ($requester > 0) {
            $input['_users_id_requester'] = $requester;
        }
        $assignee = $this->resolveUser((string) $issue['assignee'], (string) ($issue['assignee_email'] ?? ''));
        if ($assignee > 0) {
            $input['_users_id_assign'] = $assignee;
        }
        $ticket = new Ticket();
        $ticketsId = (int) $ticket->add($input);
        if ($ticketsId <= 0) {
            return 0;
        }
        // Le ticket importé auparavant a pu être supprimé dans GLPI : sa correspondance, restée en
        // place, bloquait la nouvelle (jira_key est unique) et faisait échouer tout le réimport.
        $DB->delete(self::TABLE, ['jira_key' => $key]);
        $DB->insert(self::TABLE, [
            'jira_key'      => $key,
            'tickets_id'    => $ticketsId,
            'date_creation' => date('Y-m-d H:i:s'),
        ]);
        $this->addComments($ticketsId, $issue, null);
        $this->applyStatus($ticketsId, $issue, $status);

        return $ticketsId;
    }

    /**
     * @param array<string, mixed> $issue
     */
    private function update(int $ticketsId, array $issue, int|string $status): void
    {
        global $DB;

        $mapping = $DB->request(['FROM' => self::TABLE, 'WHERE' => ['tickets_id' => $ticketsId]])->current();
        $DB->update(Ticket::getTable(), ['name' => (string) $issue['summary']], ['id' => $ticketsId]);
        $this->addComments($ticketsId, $issue, $mapping['last_comment_date'] ?? null);
        $this->applyStatus($ticketsId, $issue, $status);
        $DB->update(self::TABLE, ['date_mod' => date('Y-m-d H:i:s')], ['tickets_id' => $ticketsId]);
    }

    /**
     * Commentaires Jira => suivis, avec leur date d'origine ; à la mise à jour, seuls les
     * commentaires plus récents que le dernier importé sont ajoutés.
     *
     * @param array<string, mixed> $issue
     */
    /**
     * Issue #345, phase 2 : pièces jointes téléchargées par l'API, ajoutées au ticket comme documents
     * GLPI (Document::add(), qui applique les extensions autorisées et le stockage de GLPI). Une
     * marque dans le commentaire du document évite de la rajouter au réimport.
     *
     * @param array<string, mixed> $issue
     */
    private function addAttachments(int $ticketsId, array $issue, string $dir): int
    {
        global $DB;

        if ($dir === '') {
            return 0;
        }
        $ticket = new \Ticket();
        if (!$ticket->getFromDB($ticketsId)) {
            return 0;
        }
        $added = 0;
        foreach ((array) ($issue['attachments'] ?? []) as $attachment) {
            $file = (string) ($attachment['file'] ?? '');
            if ($file === '' || preg_match('/^[0-9A-Za-z_-]{1,64}$/', $file) !== 1 || !is_file($dir . '/' . $file)) {
                continue;
            }
            $marker = sprintf('Jira : %s / %s', (string) $issue['key'], $file);
            $exists = $DB->request([
                'COUNT'      => 'cpt',
                'FROM'       => \Document::getTable(),
                'INNER JOIN' => [
                    \Document_Item::getTable() => ['FKEY' => [
                        \Document::getTable()      => 'id',
                        \Document_Item::getTable() => 'documents_id',
                    ]],
                ],
                'WHERE'      => [
                    \Document::getTable() . '.comment'       => $marker,
                    \Document_Item::getTable() . '.itemtype' => \Ticket::class,
                    \Document_Item::getTable() . '.items_id' => $ticketsId,
                ],
            ])->current();
            if ((int) ($exists['cpt'] ?? 0) > 0) {
                continue;
            }
            $filename = JiraApiMapper::safeFilename((string) $attachment['filename']);
            $prefix = bin2hex(random_bytes(6)) . '_';
            if ($filename === '' || !copy($dir . '/' . $file, GLPI_TMP_DIR . '/' . $prefix . $filename)) {
                continue;
            }
            $documentsId = (new \Document())->add([
                'name'                    => $filename,
                'entities_id'             => (int) $ticket->fields['entities_id'],
                'is_recursive'            => 0,
                'comment'                 => $marker,
                'itemtype'                => \Ticket::class,
                'items_id'                => $ticketsId,
                '_filename'               => [$prefix . $filename],
                '_prefix_filename'        => [$prefix],
                '_only_if_upload_succeed' => 1,
            ]);
            // Extension refusée par GLPI : le document n'est pas créé et la copie est retirée.
            if (is_file(GLPI_TMP_DIR . '/' . $prefix . $filename)) {
                @unlink(GLPI_TMP_DIR . '/' . $prefix . $filename); // nosemgrep: php.lang.security.unlink-use.unlink-use
            }
            if ($documentsId !== false && (int) $documentsId > 0) {
                $added++;
            }
        }

        return $added;
    }

    /**
     * Issue #345, phase 2 : étiquettes Jira posées sur le ticket avec le plugin Tag. Une étiquette
     * active du même nom, visible depuis l'entité de l'import, est réutilisée ; sinon elle est créée
     * dans cette entité (récursive). Un lien déjà présent n'est pas recréé.
     *
     * @param array<string, mixed> $issue
     * @param array{errors: list<string>} $result
     */
    private function addLabels(int $ticketsId, array $issue, int $entitiesId, array &$result): int
    {
        global $DB;

        // Classes du plugin Tag (optionnel), obtenues par GLPI : false si le plugin manque.
        $tagModel = getItemForItemtype('PluginTagTag');
        $linkModel = getItemForItemtype('PluginTagTagItem');
        if (!$tagModel instanceof \CommonDBTM || !$linkModel instanceof \CommonDBTM) {
            return 0;
        }
        $added = 0;
        foreach ((array) ($issue['labels'] ?? []) as $label) {
            $label = mb_substr(trim((string) $label), 0, 255);
            if ($label === '') {
                continue;
            }
            if (!isset($this->tags[$label])) {
                $table = $tagModel::getTable();
                $found = $DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => $table,
                    'WHERE'  => ['name' => $label, 'is_active' => 1]
                        + getEntitiesRestrictCriteria($table, '', $entitiesId, true),
                    'LIMIT'  => 1,
                ])->current();
                $tagId = (int) ($found['id'] ?? 0);
                if ($tagId === 0) {
                    $tagId = (int) (clone $tagModel)->add([
                        'name'         => $label,
                        'entities_id'  => $entitiesId,
                        'is_recursive' => 1,
                        'is_active'    => 1,
                        'comment'      => __('Étiquette importée de Jira', 'configurationglpiauto'),
                    ]);
                }
                $this->tags[$label] = $tagId;
            }
            $tagId = $this->tags[$label];
            if ($tagId <= 0) {
                $result['errors'][] = sprintf(__('%1$s : étiquette « %2$s » non créée', 'configurationglpiauto'), (string) $issue['key'], $label);
                continue;
            }
            $link = ['plugin_tag_tags_id' => $tagId, 'itemtype' => \Ticket::class, 'items_id' => $ticketsId];
            if (countElementsInTable($linkModel::getTable(), $link) > 0) {
                continue;
            }
            if ((clone $linkModel)->add($link) !== false) {
                $added++;
            }
        }

        return $added;
    }

    private function addComments(int $ticketsId, array $issue, ?string $after): void
    {
        global $DB;

        $last = $after;
        foreach ($issue['comments'] as $comment) {
            $date = $comment['date'] ?? null;
            if ($after !== null && ($date === null || $date <= $after)) {
                continue;
            }
            $author = (string) $comment['author'];
            $usersId = $author !== '' ? $this->resolveUser($author) : 0;
            // Auteur nommé seulement quand il n'a pas de compte GLPI.
            $body = PluginTemplate::render('jira/comment.html.twig', [
                'author' => $usersId <= 0 ? $author : '',
                'body'   => (string) $comment['body'],
            ]);
            $input = [
                'itemtype'      => Ticket::class,
                'items_id'      => $ticketsId,
                'content'       => $body,
                'users_id'      => $usersId,
                '_disablenotif' => true,
            ];
            if ($date !== null) {
                $input['date'] = $date;
                $input['date_creation'] = $date;
            }
            (new ITILFollowup())->add($input);
            if ($date !== null && ($last === null || $date > $last)) {
                $last = $date;
            }
        }
        if ($last !== null) {
            $DB->update(self::TABLE, ['last_comment_date' => $last], ['tickets_id' => $ticketsId]);
        }
    }

    /**
     * Statut posé directement (le ticket importé reflète l'état Jira, sans rejouer le cycle de
     * vie GLPI ni notifier) ; « Non retenu » ajoute la solution « Demande non retenue ».
     *
     * @param array<string, mixed> $issue
     */
    private function applyStatus(int $ticketsId, array $issue, int|string $status): void
    {
        global $DB;

        $resolved = $issue['resolved'] ?? null;
        $closingDate = $resolved ?? ($issue['created'] ?? date('Y-m-d H:i:s'));
        if ($status === JiraMapping::STATUS_NOT_RETAINED) {
            $type = new SolutionType();
            $solution = [
                'itemtype'      => Ticket::class,
                'items_id'      => $ticketsId,
                'content'       => htmlescape(__('Demande non retenue (importé de Jira).', 'configurationglpiauto')),
                '_disablenotif' => true,
            ];
            if ($type->getFromDBByCrit(['name' => self::NOT_RETAINED_SOLUTION])) {
                $solution['solutiontypes_id'] = $type->getID();
            }
            $hasSolution = countElementsInTable(
                ITILSolution::getTable(),
                ['itemtype' => Ticket::class, 'items_id' => $ticketsId]
            ) > 0;
            // GLPI peut refuser la solution (champs obligatoires du gabarit, ex. catégorie) : le motif
            // reste alors tracé par un suivi, et le ticket est clos comme dans Jira.
            if (!$hasSolution && !(new ITILSolution())->add($solution)) {
                $hasFollowup = countElementsInTable(ITILFollowup::getTable(), [
                    'itemtype' => Ticket::class,
                    'items_id' => $ticketsId,
                    'content'  => $solution['content'],
                ]) > 0;
                if (!$hasFollowup) {
                    (new ITILFollowup())->add([
                        'itemtype'      => Ticket::class,
                        'items_id'      => $ticketsId,
                        'content'       => $solution['content'],
                        '_disablenotif' => true,
                    ]);
                }
            }
            $status = JiraMapping::STATUS_CLOSED;
        }
        $fields = ['status' => (int) $status];
        if (in_array($status, [CommonITILObject::SOLVED, CommonITILObject::CLOSED], true)) {
            $fields['solvedate'] = $closingDate;
        }
        if ($status === CommonITILObject::CLOSED) {
            $fields['closedate'] = $closingDate;
        }
        $DB->update(Ticket::getTable(), $fields, ['id' => $ticketsId]);
    }
}
