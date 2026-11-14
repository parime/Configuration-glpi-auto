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

use GlpiPlugin\Configurationglpiauto\Jira\JiraApiMapper;
use GlpiPlugin\Configurationglpiauto\PluginTemplate;
use ITILCategory;

/**
 * Issue #430 : import générique de tickets depuis un fichier CSV (autre outil d'assistance,
 * tableur), avec le moteur de l'import Jira (TicketImporter) : aperçu à blanc, correspondance des
 * valeurs, lots avec reprise, journal, réimport sans doublon, aucune notification.
 *
 * L'identifiant d'origine est rangé sous la source « csv:<nom de l'outil> » : deux outils (ou un
 * outil et Jira) peuvent avoir le même identifiant sans se confondre.
 */
final class CsvTicketImporter extends TicketImporter
{
    protected const SESSION_KEY = 'plugin_configurationglpiauto_csv_import';

    protected const LAST_KEY = 'plugin_configurationglpiauto_csv_import_last';

    protected const FILE_PREFIX = 'csv-import';

    /** Nom de l'outil d'origine proposé par défaut. */
    public const DEFAULT_SOURCE_NAME = 'CSV';

    /** Pièces jointes d'une archive ZIP : mêmes limites que l'import Jira par l'API. */
    public const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;
    public const MAX_ATTACHMENTS_BYTES = 200 * 1024 * 1024;
    public const MAX_ATTACHMENTS = 2000;

    private string $sourceName;

    /** @var array<string, int> cache valeur|entité => catégorie proposée */
    private array $categories = [];

    public function __construct(?string $sourceName = null)
    {
        $name = trim($sourceName ?? (string) (self::pending()['source_name'] ?? ''));
        $this->sourceName = $name !== '' ? $name : self::DEFAULT_SOURCE_NAME;
    }

    public function sourceName(): string
    {
        return $this->sourceName;
    }

    public function source(): string
    {
        return self::sourceKey($this->sourceName);
    }

    /** Clé de source enregistrée avec chaque identifiant d'origine (sans casse ni accents). */
    public static function sourceKey(string $sourceName): string
    {
        $slug = CsvReader::slug($sourceName);

        return 'csv:' . mb_substr($slug !== '' ? $slug : CsvReader::slug(self::DEFAULT_SOURCE_NAME), 0, 200);
    }

    protected static function parseFile(string $content, array $pending): array
    {
        $mapping = array_map('intval', (array) ($pending['mapping'] ?? []));
        $separator = CsvColumns::separator((string) ($pending['separator'] ?? 'none'), (string) ($pending['separator_custom'] ?? ''));
        $parsed = CsvColumns::parse($content, $mapping, $separator, self::pendingComments()['comments']);
        // Pièces jointes de l'archive, par identifiant d'origine.
        $index = (array) ($pending['attachments_index'] ?? []);
        foreach ($parsed['issues'] as $i => $issue) {
            $parsed['issues'][$i]['attachments'] = array_values((array) ($index[(string) $issue['key']] ?? []));
        }

        return $parsed;
    }

    /**
     * En-tête et premières lignes du fichier en attente, pour l'étape de correspondance des colonnes.
     *
     * @return array{header: list<string>, rows: list<array{line: int, values: list<string>}>, delimiter: string}|null
     */
    public static function pendingTable(int $maxRows = 5): ?array
    {
        $file = (string) (self::pending()['file'] ?? '');
        if (!self::isOurPendingFile($file)) {
            return null;
        }
        $table = CsvReader::read((string) file_get_contents($file), $maxRows);

        return $table['header'] === [] ? null : $table;
    }

    /**
     * Commentaires du second fichier de l'import en attente.
     *
     * @return array{comments: array<string, list<array{date: ?string, author: string, body: string}>>, count: int,
     *     columns_found: bool}
     */
    public static function pendingComments(): array
    {
        $file = (string) (self::pending()['comments_file'] ?? '');
        if (!self::isOurPendingFile($file)) {
            return ['comments' => [], 'count' => 0, 'columns_found' => true];
        }

        return CsvColumns::parseCommentsFile((string) file_get_contents($file));
    }

    /** Fichier de commentaires gardé avec l'import en attente (nom imposé, dossier temporaire de GLPI). */
    public static function storeComments(string $content): void
    {
        $path = GLPI_TMP_DIR . '/' . self::FILE_PREFIX . '-' . bin2hex(random_bytes(8)) . '.comments.csv';
        file_put_contents($path, CsvReader::toUtf8($content));
        self::updatePending(['comments_file' => $path]);
    }

    /**
     * Pièces jointes d'une archive ZIP : chaque fichier est rangé dans un dossier qui porte
     * l'identifiant d'origine du ticket (« 1042/capture.png »). Les fichiers sont copiés, sous un
     * nom neutre, dans un dossier temporaire effacé avec l'import en attente ; rien n'est exécuté ni
     * décompressé ailleurs. Un fichier trop gros ou au-delà des limites est écarté.
     *
     * @return array{count: int, skipped: int}
     */
    public static function storeAttachments(string $zipPath): array
    {
        $result = ['count' => 0, 'skipped' => 0];
        if (!class_exists(\ZipArchive::class)) {
            return $result;
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::RDONLY) !== true) {
            return $result;
        }
        $dir = self::newAttachmentsDir();
        $index = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            // Archive créée sous Windows sans UTF-8 : noms en page de codes DOS.
            if (!mb_check_encoding($name, 'UTF-8')) {
                $name = (string) mb_convert_encoding($name, 'UTF-8', 'CP850');
            }
            if ($name === '' || str_ends_with($name, '/') || str_starts_with(basename($name), '.') || str_contains($name, '__MACOSX')) {
                continue;
            }
            $key = basename(dirname($name));
            $filename = JiraApiMapper::safeFilename(basename($name));
            if ($key === '' || $key === '.' || $filename === '' || $result['count'] >= self::MAX_ATTACHMENTS) {
                $result['skipped']++;
                continue;
            }
            // Nom stable (identifiant d'origine + nom du fichier) : le réimport ne double pas la pièce jointe.
            $file = substr(hash('sha256', $key . '/' . $filename), 0, 32);
            $written = self::copyEntry($zip, $i, $dir . '/' . $file, self::MAX_ATTACHMENTS_BYTES - $total);
            if ($written === null) {
                $result['skipped']++;
                continue;
            }
            $total += $written;
            $index[$key][] = ['file' => $file, 'filename' => $filename];
            $result['count']++;
        }
        $zip->close();
        self::updatePending(['attachments_dir' => $dir, 'attachments_index' => $index]);

        return $result;
    }

    /** Copie d'un fichier de l'archive, arrêtée au-delà de la taille permise (null : écarté). */
    private static function copyEntry(\ZipArchive $zip, int $index, string $target, int $budget): ?int
    {
        $stream = $zip->getStream((string) $zip->getNameIndex($index));
        $out = fopen($target, 'wb');
        if ($stream === false || $out === false) {
            return null;
        }
        $limit = min(self::MAX_ATTACHMENT_BYTES, $budget);
        $size = 0;
        while (!feof($stream)) {
            $chunk = (string) fread($stream, 65536);
            $size += strlen($chunk);
            if ($size > $limit) {
                break;
            }
            fwrite($out, $chunk);
        }
        fclose($stream);
        fclose($out);
        if ($size > $limit || $size === 0) {
            @unlink($target); // nosemgrep: php.lang.security.unlink-use.unlink-use

            return null;
        }

        return $size;
    }

    /**
     * Issue #430 : valeurs propres à l'import CSV pour l'aperçu : priorités et catégories du fichier
     * (avec la correspondance proposée), groupes de techniciens reconnus ou non.
     *
     * @param list<array<string, mixed>> $issues
     * @return array{priorities: array<string, array{count: int, default: int}>,
     *     categories: array<string, array{count: int, default: int}>, groups_found: array<string, int>,
     *     groups_missing: list<string>}
     */
    public function previewValues(array $issues, int $entitiesId): array
    {
        $priorities = [];
        $categories = [];
        $groupsFound = [];
        $groupsMissing = [];
        foreach ($issues as $issue) {
            $priority = (string) $issue['priority'];
            if ($priority !== '') {
                $priorities[$priority] ??= ['count' => 0, 'default' => TicketMapping::defaultPriority($priority)];
                $priorities[$priority]['count']++;
            }
            $category = (string) ($issue['category'] ?? '');
            if ($category !== '') {
                $categories[$category] ??= ['count' => 0, 'default' => $this->suggestCategory($category, $entitiesId)];
                $categories[$category]['count']++;
            }
            $group = (string) ($issue['group'] ?? '');
            if ($group !== '' && !isset($groupsFound[$group]) && !in_array($group, $groupsMissing, true)) {
                $groupsId = $this->resolveGroup($group, $entitiesId);
                if ($groupsId > 0) {
                    $groupsFound[$group] = $groupsId;
                } else {
                    $groupsMissing[] = $group;
                }
            }
        }
        sort($groupsMissing);
        ksort($categories, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'priorities'     => $priorities,
            'categories'     => $categories,
            'groups_found'   => $groupsFound,
            'groups_missing' => $groupsMissing,
        ];
    }

    /**
     * Catégorie GLPI du même nom (ou nom complet, « Parent > Enfant »), visible depuis l'entité,
     * seulement s'il n'y en a qu'une.
     */
    public function suggestCategory(string $value, int $entitiesId): int
    {
        global $DB;

        $slug = CsvReader::slug($value);
        $cacheKey = $slug . '|' . $entitiesId;
        if ($slug === '') {
            return 0;
        }
        if (isset($this->categories[$cacheKey])) {
            return $this->categories[$cacheKey];
        }
        $table = ITILCategory::getTable();
        $matches = [];
        foreach (
            $DB->request([
                'SELECT' => ['id', 'name', 'completename'],
                'FROM'   => $table,
                'WHERE'  => getEntitiesRestrictCriteria($table, '', $entitiesId, true),
            ]) as $row
        ) {
            if (in_array($slug, [CsvReader::slug((string) $row['name']), CsvReader::slug((string) $row['completename'])], true)) {
                $matches[] = (int) $row['id'];
            }
        }

        return $this->categories[$cacheKey] = count($matches) === 1 ? $matches[0] : 0;
    }

    /**
     * Correspondance de valeurs lue dans le formulaire de l'aperçu (clés TicketMapping::projectKey()
     * de chaque valeur, qui peut contenir n'importe quel caractère) => valeur d'origine => choix.
     *
     * @param list<string> $values valeurs d'origine
     * @param array<mixed> $posted
     * @return array<string, mixed>
     */
    public static function valueMap(array $values, array $posted): array
    {
        $map = [];
        foreach ($values as $value) {
            $key = TicketMapping::projectKey((string) $value);
            if (isset($posted[$key]) && is_scalar($posted[$key])) {
                $map[(string) $value] = $posted[$key];
            }
        }

        return $map;
    }

    /**
     * Catégories choisies dans l'aperçu, seulement si elles sont visibles depuis l'entité de l'import.
     *
     * @param list<string> $values
     * @param array<mixed> $posted
     * @return array<string, int>
     */
    public static function categoryMap(array $values, array $posted, int $entitiesId): array
    {
        $map = [];
        foreach (self::valueMap($values, $posted) as $value => $categoryId) {
            $categoryId = (int) $categoryId;
            if (self::categoryVisible($categoryId, $entitiesId)) {
                $map[$value] = $categoryId;
            }
        }

        return $map;
    }

    protected function ticketContent(array $issue): string
    {
        return PluginTemplate::render('csvimport/ticket_content.html.twig', [
            'text'     => trim((string) $issue['description']) !== ''
                ? (string) $issue['description']
                : (string) $issue['summary'],
            'key'      => (string) $issue['key'],
            'source'   => $this->sourceName,
            'labels'   => $issue['labels'],
        ]);
    }

    protected function commentContent(string $author, string $body): string
    {
        return PluginTemplate::render('csvimport/comment.html.twig', ['author' => $author, 'body' => $body]);
    }

    protected function notRetainedText(): string
    {
        return sprintf(__('Demande non retenue (importé de %s).', 'configurationglpiauto'), $this->sourceName);
    }

    protected function labelComment(): string
    {
        return sprintf(__('Étiquette importée de %s', 'configurationglpiauto'), $this->sourceName);
    }

    protected function attachmentMarker(string $key, string $file): string
    {
        return sprintf('%s : %s / %s', $this->sourceName, $key, $file);
    }
}
