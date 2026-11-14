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

namespace GlpiPlugin\Configurationglpiauto\Import;

use CommonITILObject;
use ITILFollowup;
use ITILSolution;
use SolutionType;
use Ticket;
use User;
use UserEmail;

/**
 * Issue #345 : import de tickets d'un autre outil en tickets GLPI. Moteur commun à l'import Jira
 * (JiraImporter) et, depuis l'issue #430, à l'import générique d'un fichier CSV
 * (CsvTicketImporter).
 *
 * - Aperçu à blanc d'abord (preview()) : rien n'est écrit.
 * - Personnes rapprochées par adresse e-mail puis par « prénom nom » ; une personne inconnue est
 *   signalée, jamais créée.
 * - Idempotent : l'identifiant d'origine (aussi gardé comme identifiant externe du ticket,
 *   cherchable), propre à chaque source, évite les doublons ; un nouvel import met à jour le titre
 *   et le statut et n'ajoute que les nouveaux commentaires.
 * - Aucune notification pendant l'import ; dates de création et de résolution conservées.
 * - Import par lots (BATCH_SIZE tickets par requête) avec reprise : la position est gardée en
 *   session, un lot interrompu est simplement rejoué (l'identifiant d'origine évite les doublons) ;
 *   journal CSV téléchargeable à la fin.
 */
abstract class TicketImporter
{
    /** Correspondance identifiant d'origine (par source) => ticket GLPI. */
    public const TABLE = 'glpi_plugin_configurationglpiauto_jiraimports';

    /** Nom du gabarit de solution créé par SolutionLibraryBuilder (issue #346). */
    private const NOT_RETAINED_SOLUTION = 'Demande non retenue';

    /** Fichier téléversé gardé entre l'aperçu et l'import (dossier temporaire de GLPI). */
    protected const SESSION_KEY = '';

    /** Résultat et journal du dernier import terminé (gardés après forgetPending()). */
    protected const LAST_KEY = '';

    /** Début du nom des fichiers temporaires (import en attente, journal, pièces jointes). */
    protected const FILE_PREFIX = '';

    /** Tickets traités par requête : un lot court reste loin des délais d'exécution de PHP. */
    public const BATCH_SIZE = 25;

    /** Issue #345 : qualification d'un ticket en incident de sécurité (plugin grcmanager, optionnel). */
    private const GRC_QUALIFY_CLASS = 'GlpiPlugin\\Grcmanager\\SecurityIncidentTicket';
    private const GRC_INCIDENT_CLASS = 'PluginGrcmanagerSecurityIncident';

    /** Source des tickets, gardée avec l'identifiant d'origine (« jira », « csv:ancien outil »...). */
    abstract public function source(): string;

    /**
     * Tickets d'un fichier CSV en attente (forme de JiraCsvParser::parse()).
     *
     * @param array<string, mixed> $pending import en attente (session)
     * @return array{issues: list<array<string, mixed>>, errors: list<array{line: int, message: string}>}
     */
    abstract protected static function parseFile(string $content, array $pending): array;

    /**
     * Contenu HTML du ticket créé (texte d'origine échappé, puis mention de son origine).
     *
     * @param array<string, mixed> $issue
     */
    abstract protected function ticketContent(array $issue): string;

    /** Contenu HTML d'un suivi repris d'un commentaire (auteur nommé s'il n'a pas de compte). */
    abstract protected function commentContent(string $author, string $body): string;

    /** Texte de la solution « Demande non retenue ». */
    abstract protected function notRetainedText(): string;

    /** Commentaire d'une étiquette créée dans le plugin Tag. */
    abstract protected function labelComment(): string;

    /** Marque d'une pièce jointe dans le commentaire du document (évite de la rajouter au réimport). */
    abstract protected function attachmentMarker(string $key, string $file): string;

    /**
     * @param array<string, mixed> $extra données propres à la source, gardées avec l'import en attente
     */
    public static function storePending(string $content, int $entitiesId, array $extra = []): void
    {
        static::forgetPending();
        $path = GLPI_TMP_DIR . '/' . static::FILE_PREFIX . '-' . bin2hex(random_bytes(8)) . '.csv';
        file_put_contents($path, $content);
        $_SESSION[static::SESSION_KEY] = ['file' => $path, 'entities_id' => $entitiesId] + $extra;
    }

    /**
     * Import en attente (fichier, entité, données propres à la source), ou tableau vide.
     *
     * @return array<string, mixed>
     */
    public static function pending(): array
    {
        $pending = $_SESSION[static::SESSION_KEY] ?? null;

        return is_array($pending) ? $pending : [];
    }

    /**
     * Complète l'import en attente (correspondance des colonnes choisie après le téléversement...).
     *
     * @param array<string, mixed> $values
     */
    public static function updatePending(array $values): void
    {
        if (static::pending() !== []) {
            $_SESSION[static::SESSION_KEY] = $values + static::pending();
        }
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
        $dir = $_SESSION[static::SESSION_KEY]['attachments_dir'] ?? '';
        // Le dossier des pièces jointes vient d'être rempli pour cet import : il n'est pas effacé.
        unset($_SESSION[static::SESSION_KEY]['attachments_dir']);
        static::forgetPending();
        $path = GLPI_TMP_DIR . '/' . static::FILE_PREFIX . '-' . bin2hex(random_bytes(8)) . '.json';
        file_put_contents($path, (string) json_encode(['issues' => $issues, 'errors' => $errors]));
        $_SESSION[static::SESSION_KEY] = [
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
        $dir = GLPI_TMP_DIR . '/' . static::FILE_PREFIX . '-' . bin2hex(random_bytes(8)) . '-files';
        mkdir($dir, 0700);

        return $dir;
    }

    /** Dossier des pièces jointes de l'import en attente, s'il a été créé par newAttachmentsDir(). */
    public static function pendingAttachmentsDir(): string
    {
        $dir = (string) ($_SESSION[static::SESSION_KEY]['attachments_dir'] ?? '');

        return static::isOurAttachmentsDir($dir) ? $dir : '';
    }

    protected static function isOurAttachmentsDir(string $dir): bool
    {
        return $dir !== '' && preg_match('/^' . static::FILE_PREFIX . '-[0-9a-f]{16}-files$/', basename($dir)) === 1
            && is_dir($dir) && realpath(dirname($dir)) === realpath(GLPI_TMP_DIR);
    }

    /**
     * @return array{issues: list<array<string, mixed>>, errors: list<array{line: int, message: string}>}|null
     */
    public static function loadPending(): ?array
    {
        $file = (string) ($_SESSION[static::SESSION_KEY]['file'] ?? '');
        if (!static::isOurPendingFile($file)) {
            return null;
        }
        if (str_ends_with($file, '.json')) {
            $data = json_decode((string) file_get_contents($file), true);

            return is_array($data) && is_array($data['issues'] ?? null)
                ? ['issues' => array_values($data['issues']), 'errors' => array_values((array) ($data['errors'] ?? []))]
                : null;
        }

        return static::parseFile((string) file_get_contents($file), static::pending());
    }

    /**
     * Issue #345 : démarre un import par lots avec les correspondances choisies dans l'aperçu.
     *
     * @param array{status_map: array<string, mixed>, type_map: array<string, mixed>, import_labels: bool,
     *     project_map: array<string, array{entities_id: int, itilcategories_id: int}>, qualify_security: bool,
     *     value_maps?: array<string, array<string, int>>} $options
     */
    public static function startJob(array $options, int $total): void
    {
        static::forgetLast();
        $log = GLPI_TMP_DIR . '/' . static::FILE_PREFIX . '-' . bin2hex(random_bytes(8)) . '.log.csv';
        $handle = fopen($log, 'w');
        if ($handle !== false) {
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [__('Date', 'configurationglpiauto'), __('Message', 'configurationglpiauto')], ';', '"', '');
            fclose($handle);
        }
        $_SESSION[static::SESSION_KEY]['job'] = $options + [
            'cursor'  => 0,
            'total'   => $total,
            'log'     => $log,
            'started' => date('Y-m-d H:i:s'),
            'result'  => ['created' => 0, 'updated' => 0, 'attachments' => 0, 'labels' => 0, 'security' => 0, 'errors' => []],
        ];
    }

    /**
     * Import en cours (lots restants), ou null.
     *
     * @return array<string, mixed>|null
     */
    public static function job(): ?array
    {
        $job = $_SESSION[static::SESSION_KEY]['job'] ?? null;

        return is_array($job) ? $job : null;
    }

    /**
     * Traite le lot suivant. Le lot entier est rejoué s'il a été interrompu : l'identifiant
     * d'origine fait d'un ticket déjà créé une mise à jour. À la fin, le résultat et le journal sont gardés, l'import en
     * attente est effacé.
     *
     * @return bool true quand tout est importé
     */
    public function runNextBatch(): bool
    {
        $job = static::job();
        $parsed = static::loadPending();
        if ($job === null || $parsed === null) {
            return true;
        }
        $cursor = (int) $job['cursor'];
        $batch = array_slice($parsed['issues'], $cursor, self::BATCH_SIZE);
        $result = $this->import(
            $batch,
            (array) $job['status_map'],
            (array) $job['type_map'],
            static::pendingEntity(),
            (bool) $job['import_labels'],
            (array) $job['project_map'],
            (bool) $job['qualify_security'],
            (array) ($job['value_maps'] ?? [])
        );
        static::appendLog((string) $job['log'], array_merge($result['log'], $result['errors']));
        foreach (['created', 'updated', 'attachments', 'labels', 'security'] as $counter) {
            $job['result'][$counter] += $result[$counter];
        }
        $job['result']['errors'] = array_merge($job['result']['errors'], $result['errors']);
        $job['cursor'] = min((int) $job['total'], $cursor + self::BATCH_SIZE);
        $_SESSION[static::SESSION_KEY]['job'] = $job;
        if ($job['cursor'] < (int) $job['total']) {
            return false;
        }
        $_SESSION[static::LAST_KEY] = ['result' => $job['result'], 'log' => $job['log'], 'total' => $job['total']];
        unset($_SESSION[static::SESSION_KEY]['job']);
        static::forgetPending();

        return true;
    }

    /**
     * Dernier import terminé : compteurs, erreurs, fichier du journal.
     *
     * @return array{result: array<string, mixed>, log: string, total: int}|null
     */
    public static function last(): ?array
    {
        $last = $_SESSION[static::LAST_KEY] ?? null;

        return is_array($last) ? $last : null;
    }

    /** Journal CSV d'un import (en cours ou terminé), s'il a été créé par startJob(). */
    public static function logFile(): string
    {
        $file = (string) (static::job()['log'] ?? static::last()['log'] ?? '');

        return static::isOurLogFile($file) ? $file : '';
    }

    /** Lignes du journal, pour l'affichage du résultat. */
    public static function logLines(int $max = 500): array
    {
        $file = static::logFile();
        if ($file === '') {
            return [];
        }
        $lines = [];
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return [];
        }
        fgetcsv($handle, null, ';', '"', '');
        while (count($lines) < $max && ($row = fgetcsv($handle, null, ';', '"', '')) !== false) {
            $lines[] = implode('  ', array_map('strval', $row));
        }
        fclose($handle);

        return $lines;
    }

    /**
     * @param list<string> $messages
     */
    protected static function appendLog(string $file, array $messages): void
    {
        if (!static::isOurLogFile($file) || $messages === []) {
            return;
        }
        $handle = fopen($file, 'a');
        if ($handle === false) {
            return;
        }
        foreach ($messages as $message) {
            fputcsv($handle, [date('Y-m-d H:i:s'), $message], ';', '"', '');
        }
        fclose($handle);
    }

    protected static function isOurLogFile(string $file): bool
    {
        return preg_match('/^' . static::FILE_PREFIX . '-[0-9a-f]{16}\.log\.csv$/', basename($file)) === 1
            && is_file($file) && realpath(dirname($file)) === realpath(GLPI_TMP_DIR);
    }

    /**
     * Arrête un import en cours : les tickets déjà importés restent, le résultat partiel et le
     * journal sont gardés comme pour un import terminé.
     */
    public static function abandonJob(): void
    {
        $job = static::job();
        if ($job !== null) {
            static::appendLog((string) $job['log'], [sprintf(
                __('Import arrêté par l\'utilisateur après %1$d ticket(s) sur %2$d', 'configurationglpiauto'),
                (int) $job['cursor'],
                (int) $job['total']
            )]);
            $_SESSION[static::LAST_KEY] = [
                'result'  => $job['result'] + ['stopped_at' => (int) $job['cursor']],
                'log'     => $job['log'],
                'total'   => $job['total'],
            ];
            unset($_SESSION[static::SESSION_KEY]['job']);
        }
        static::forgetPending();
    }

    /** Oublie le dernier import terminé et efface son journal. */
    public static function forgetLast(): void
    {
        $file = (string) (static::last()['log'] ?? '');
        if (static::isOurLogFile($file)) {
            @unlink($file); // nosemgrep: php.lang.security.unlink-use.unlink-use
        }
        unset($_SESSION[static::LAST_KEY]);
    }

    /** Seul un fichier créé par storePending() (dossier temporaire de GLPI, nom imposé) est lu ou supprimé. */
    protected static function isOurPendingFile(string $file): bool
    {
        return preg_match('/^' . static::FILE_PREFIX . '-[0-9a-f]{16}(\.comments)?\.(csv|json)$/', basename($file)) === 1
            && is_file($file) && realpath(dirname($file)) === realpath(GLPI_TMP_DIR);
    }

    public static function pendingEntity(): int
    {
        return (int) ($_SESSION[static::SESSION_KEY]['entities_id'] ?? -1);
    }

    public static function forgetPending(): void
    {
        // Fichier en attente et, pour l'import CSV, fichier des commentaires (issue #430).
        foreach (['file', 'comments_file'] as $field) {
            $file = (string) ($_SESSION[static::SESSION_KEY][$field] ?? '');
            if (static::isOurPendingFile($file)) {
                @unlink($file); // nosemgrep: php.lang.security.unlink-use.unlink-use
            }
        }
        $dir = (string) ($_SESSION[static::SESSION_KEY]['attachments_dir'] ?? '');
        if (static::isOurAttachmentsDir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $attachment) {
                if (is_file($attachment)) {
                    @unlink($attachment); // nosemgrep: php.lang.security.unlink-use.unlink-use
                }
            }
            @rmdir($dir);
        }
        unset($_SESSION[static::SESSION_KEY]);
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
                    'slug' => ' ' . CsvReader::slug((string) $row['name']) . ' ',
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
     * l'aperçu (clés TicketMapping::projectKey()). Une entité hors des entités de l'utilisateur ou une
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
        foreach (array_keys(TicketMapping::summarize($issues)['projects']) as $project) {
            $project = (string) $project;
            $key = TicketMapping::projectKey($project);
            $entity = (int) ($entities[$key] ?? $defaultEntity);
            if (!\Session::haveAccessToEntity($entity)) {
                $entity = $defaultEntity;
            }
            $categoryId = (int) ($categories[$key] ?? 0);
            $categoryId = self::categoryVisible($categoryId, $entity) ? $categoryId : 0;
            $map[$project] = ['entities_id' => $entity, 'itilcategories_id' => $categoryId];
        }

        return $map;
    }

    /** Catégorie de tickets existante et visible depuis l'entité (la sienne, ou un parent récursif). */
    public static function categoryVisible(int $categoryId, int $entity): bool
    {
        $category = new \ITILCategory();

        return $categoryId > 0 && $category->getFromDB($categoryId)
            && in_array((int) $category->fields['entities_id'], getAncestorsOf('glpi_entities', $entity) + [$entity => $entity], true)
            && ((int) $category->fields['entities_id'] === $entity || (bool) $category->fields['is_recursive']);
    }

    /** @var array<string, int> cache étiquette => identifiant du plugin Tag */
    private array $tags = [];

    /** @var array<string, int> cache nom/e-mail => utilisateur */
    private array $users = [];

    /** @var array<string, int> cache nom|entité => groupe */
    private array $groups = [];

    /**
     * @param list<array<string, mixed>> $issues
     * @return array{summary: array<string, array<string, int>>, statuses: array<string, int|string>,
     *     types: array<string, int>, people_found: array<string, int>, people_missing: list<string>,
     *     already_imported: int, total: int, attachments: int, attachments_skipped: int,
     *     labels: array<string, int>, tags_available: bool, security_count: int, security_available: bool,
     *     subjects: list<array{word: string, count: int, examples: list<string>, forms: list<array{name: string, url: string}>}>}
     */
    public function preview(array $issues): array
    {
        $summary = TicketMapping::summarize($issues);
        $statuses = [];
        foreach (array_keys($summary['statuses']) as $status) {
            $statuses[(string) $status] = TicketMapping::defaultStatus((string) $status);
        }
        $types = [];
        foreach (array_keys($summary['types']) as $type) {
            $types[(string) $type] = TicketMapping::defaultType((string) $type);
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
            // Issue #345 : tickets étiquetés « Sécurité », qualifiables en incidents de grcmanager.
            'security_count'      => TicketMapping::countSecurityIssues($issues),
            'security_available'  => self::securityAvailable(),
            // Issue #345, bonus : sujets fréquents et formulaires du catalogue qui y répondent.
            'subjects'            => self::withMatchingForms(TicketMapping::frequentSubjects($issues)),
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
     * @param array<string, array<string, mixed>> $valueMaps issue #430 : autres correspondances de
     *     valeurs choisies dans l'aperçu, « priority » (valeur => 1 à 5) et « category » (valeur =>
     *     catégorie GLPI) ; une valeur absente garde le comportement par défaut
     * @return array{created: int, updated: int, attachments: int, labels: int, security: int, errors: list<string>,
     *     log: list<string>}
     */
    public function import(
        array $issues,
        array $statusMap,
        array $typeMap,
        int $entitiesId,
        bool $importLabels = false,
        array $projectMap = [],
        bool $qualifySecurity = false,
        array $valueMaps = []
    ): array {
        global $CFG_GLPI;

        $result = [
            'created' => 0, 'updated' => 0, 'attachments' => 0, 'labels' => 0, 'security' => 0,
            'errors'  => [], 'log' => [],
        ];
        $importLabels = $importLabels && self::tagsAvailable();
        $qualifySecurity = $qualifySecurity && self::securityAvailable();
        $attachmentsDir = static::pendingAttachmentsDir();
        // Aucune notification pendant l'import (en mémoire seulement, rétabli à la fin).
        $notifications = $CFG_GLPI['use_notifications'] ?? 0;
        $CFG_GLPI['use_notifications'] = 0;
        try {
            foreach ($issues as $issue) {
                $key = (string) $issue['key'];
                $status = TicketMapping::normalizeStatus($statusMap[(string) $issue['status']] ?? null, (string) $issue['status']);
                $type = TicketMapping::normalizeType($typeMap[(string) $issue['type']] ?? null, (string) $issue['type']);
                $ticketsId = $this->findTicket($key);
                if ($ticketsId > 0) {
                    $this->update($ticketsId, $issue, $status);
                    $result['attachments'] += $this->addAttachments($ticketsId, $issue, $attachmentsDir);
                    $result['labels'] += $importLabels ? $this->addLabels($ticketsId, $issue, $entitiesId, $result) : 0;
                    $result['updated']++;
                    $result['log'][] = sprintf(__('%1$s : ticket %2$d mis à jour', 'configurationglpiauto'), $key, $ticketsId);
                    $this->qualifyIfSecurity($qualifySecurity, $ticketsId, $issue, $result);
                    continue;
                }
                // Issue #345 : entité et catégorie propres au projet Jira, sinon celles de l'import.
                $target = $projectMap[(string) ($issue['project'] ?? '')] ?? [];
                $entity = (int) ($target['entities_id'] ?? $entitiesId);
                // Issue #430 : catégorie choisie pour la valeur de la colonne « Catégorie ».
                $categoryId = (int) ($valueMaps['category'][(string) ($issue['category'] ?? '')] ?? 0);
                $categoryId = $categoryId > 0 && self::categoryVisible($categoryId, $entity)
                    ? $categoryId : (int) ($target['itilcategories_id'] ?? 0);
                $ticketsId = $this->create(
                    $issue,
                    $status,
                    $type,
                    $entity,
                    $categoryId,
                    TicketMapping::normalizePriority(
                        $valueMaps['priority'][(string) $issue['priority']] ?? null,
                        (string) $issue['priority']
                    )
                );
                if ($ticketsId <= 0) {
                    $result['errors'][] = sprintf(__('%s : création du ticket impossible', 'configurationglpiauto'), $key);
                    continue;
                }
                $result['created']++;
                $result['attachments'] += $this->addAttachments($ticketsId, $issue, $attachmentsDir);
                $result['labels'] += $importLabels ? $this->addLabels($ticketsId, $issue, $entitiesId, $result) : 0;
                $result['log'][] = sprintf(__('%1$s : ticket %2$d créé', 'configurationglpiauto'), $key, $ticketsId);
                $this->qualifyIfSecurity($qualifySecurity, $ticketsId, $issue, $result);
            }
        } finally {
            $CFG_GLPI['use_notifications'] = $notifications;
        }

        return $result;
    }

    /**
     * Issue #345 : grcmanager est-il actif, avec la qualification d'un ticket en incident de
     * sécurité, et l'utilisateur peut-il créer un incident ? Classes appelées par leur nom : le
     * plugin grcmanager est optionnel.
     */
    public static function securityAvailable(): bool
    {
        $canCreate = [self::GRC_INCIDENT_CLASS, 'canCreate'];

        return \Plugin::isPluginActive('grcmanager')
            && is_callable([self::GRC_QUALIFY_CLASS, 'qualify'])
            && is_callable($canCreate)
            && (bool) call_user_func($canCreate);
    }

    /**
     * Ticket étiqueté « Sécurité » : qualifié en incident de sécurité de grcmanager, par la même
     * fonction que l'action groupée de grcmanager (un ticket déjà qualifié garde son incident).
     *
     * @param array<string, mixed> $issue
     * @param array{security: int, errors: list<string>, log: list<string>} $result
     */
    private function qualifyIfSecurity(bool $enabled, int $ticketsId, array $issue, array &$result): void
    {
        $qualify = [self::GRC_QUALIFY_CLASS, 'qualify'];
        $ticket = new Ticket();
        if (!$enabled || !TicketMapping::hasSecurityLabel($issue) || !is_callable($qualify) || !$ticket->getFromDB($ticketsId)) {
            return;
        }
        $incidentsId = (int) call_user_func($qualify, $ticket);
        $key = (string) $issue['key'];
        if ($incidentsId <= 0) {
            $result['errors'][] = sprintf(__('%s : incident de sécurité non créé', 'configurationglpiauto'), $key);

            return;
        }
        $result['security']++;
        $result['log'][] = sprintf(
            __('%1$s : ticket %2$d relié à l\'incident de sécurité %3$d', 'configurationglpiauto'),
            $key,
            $ticketsId,
            $incidentsId
        );
    }

    public function findTicket(string $key): int
    {
        global $DB;

        $row = $DB->request([
            'SELECT' => ['tickets_id'],
            'FROM'   => self::TABLE,
            'WHERE'  => ['source' => $this->source(), 'jira_key' => $key],
        ])->current();
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
            $keys = TicketMapping::nameKeys($displayName);
            $matches = [];
            foreach ($DB->request([
                'SELECT' => ['id', 'name', 'firstname', 'realname'],
                'FROM'   => User::getTable(),
                'WHERE'  => ['is_active' => 1, 'is_deleted' => 0],
            ]) as $row) {
                $candidates = [
                    CsvReader::slug((string) $row['name']),
                    CsvReader::slug($row['firstname'] . ' ' . $row['realname']),
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
     * Issue #430 : groupe de techniciens GLPI d'un nom (nom ou nom complet, sans casse ni accents),
     * visible depuis l'entité et autorisé en attribution, seulement s'il n'y en a qu'un.
     */
    public function resolveGroup(string $name, int $entitiesId): int
    {
        global $DB;

        $slug = CsvReader::slug($name);
        $cacheKey = $slug . '|' . $entitiesId;
        if ($slug === '') {
            return 0;
        }
        if (isset($this->groups[$cacheKey])) {
            return $this->groups[$cacheKey];
        }
        $table = \Group::getTable();
        $matches = [];
        foreach (
            $DB->request([
                'SELECT' => ['id', 'name', 'completename'],
                'FROM'   => $table,
                'WHERE'  => ['is_assign' => 1] + getEntitiesRestrictCriteria($table, '', $entitiesId, true),
            ]) as $row
        ) {
            if (in_array($slug, [CsvReader::slug((string) $row['name']), CsvReader::slug((string) $row['completename'])], true)) {
                $matches[] = (int) $row['id'];
            }
        }

        return $this->groups[$cacheKey] = count($matches) === 1 ? $matches[0] : 0;
    }

    /**
     * @param array<string, mixed> $issue
     */
    private function create(array $issue, int|string $status, int $type, int $entitiesId, int $categoryId = 0, ?int $priority = null): int
    {
        global $DB;

        $key = (string) $issue['key'];
        $content = $this->ticketContent($issue);
        $priority ??= TicketMapping::defaultPriority((string) $issue['priority']);
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
            // Date d'ouverture et date affichée dans le fil du ticket : celles d'origine.
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
        // Issue #430 : groupe de techniciens (colonne « Groupe » de l'import CSV).
        $group = $this->resolveGroup((string) ($issue['group'] ?? ''), $entitiesId);
        if ($group > 0) {
            $input['_groups_id_assign'] = $group;
        }
        $ticket = new Ticket();
        $ticketsId = (int) $ticket->add($input);
        if ($ticketsId <= 0) {
            return 0;
        }
        // Le ticket importé auparavant a pu être supprimé dans GLPI : sa correspondance, restée en
        // place, bloquait la nouvelle (source et jira_key sont uniques) et faisait échouer tout le
        // réimport.
        $DB->delete(self::TABLE, ['source' => $this->source(), 'jira_key' => $key]);
        $DB->insert(self::TABLE, [
            'source'        => $this->source(),
            'jira_key'      => $key,
            'tickets_id'    => $ticketsId,
            'date_creation' => date('Y-m-d H:i:s'),
        ]);
        $this->addComments($ticketsId, $issue, null, false);
        $this->applyStatus($ticketsId, $issue, $status);

        return $ticketsId;
    }

    /**
     * @param array<string, mixed> $issue
     */
    private function update(int $ticketsId, array $issue, int|string $status): void
    {
        global $DB;

        $where = ['source' => $this->source(), 'tickets_id' => $ticketsId];
        $mapping = $DB->request(['FROM' => self::TABLE, 'WHERE' => $where])->current();
        $DB->update(Ticket::getTable(), ['name' => (string) $issue['summary']], ['id' => $ticketsId]);
        $this->addComments($ticketsId, $issue, $mapping['last_comment_date'] ?? null, true);
        $this->applyStatus($ticketsId, $issue, $status);
        $DB->update(self::TABLE, ['date_mod' => date('Y-m-d H:i:s')], $where);
    }

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
            $marker = $this->attachmentMarker((string) $issue['key'], $file);
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
            $filename = \GlpiPlugin\Configurationglpiauto\Jira\JiraApiMapper::safeFilename((string) $attachment['filename']);
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
                        'comment'      => $this->labelComment(),
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

    /**
     * Commentaires d'origine => suivis, avec leur date d'origine ; à la mise à jour, seuls les
     * commentaires plus récents que le dernier importé sont ajoutés, et jamais un suivi déjà
     * présent à l'identique (commentaires sans date d'un fichier CSV, issue #430).
     *
     * @param array<string, mixed> $issue
     */
    private function addComments(int $ticketsId, array $issue, ?string $after, bool $isUpdate): void
    {
        global $DB;

        $last = $after;
        foreach ((array) ($issue['comments'] ?? []) as $comment) {
            $date = $comment['date'] ?? null;
            if ($after !== null && ($date === null || $date <= $after)) {
                continue;
            }
            $author = (string) $comment['author'];
            $usersId = $author !== '' ? $this->resolveUser($author) : 0;
            // Auteur nommé seulement quand il n'a pas de compte GLPI.
            $body = $this->commentContent($usersId <= 0 ? $author : '', (string) $comment['body']);
            $known = $isUpdate && countElementsInTable(ITILFollowup::getTable(), [
                'itemtype' => Ticket::class,
                'items_id' => $ticketsId,
                'content'  => $body,
            ]) > 0;
            if ($known) {
                continue;
            }
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
            $DB->update(self::TABLE, ['last_comment_date' => $last], ['source' => $this->source(), 'tickets_id' => $ticketsId]);
        }
    }

    /**
     * Statut posé directement (le ticket importé reflète l'état d'origine, sans rejouer le cycle de
     * vie GLPI ni notifier) ; « Non retenu » ajoute la solution « Demande non retenue ».
     *
     * @param array<string, mixed> $issue
     */
    private function applyStatus(int $ticketsId, array $issue, int|string $status): void
    {
        global $DB;

        $resolved = $issue['resolved'] ?? null;
        $closingDate = $resolved ?? ($issue['created'] ?? date('Y-m-d H:i:s'));
        if ($status === TicketMapping::STATUS_NOT_RETAINED) {
            $type = new SolutionType();
            $solution = [
                'itemtype'      => Ticket::class,
                'items_id'      => $ticketsId,
                'content'       => htmlescape($this->notRetainedText()),
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
            // reste alors tracé par un suivi, et le ticket est clos comme à l'origine.
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
            $status = TicketMapping::STATUS_CLOSED;
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
