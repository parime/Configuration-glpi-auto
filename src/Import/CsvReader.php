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

use DateTimeImmutable;

/**
 * Issue #430 : lecture commune des fichiers CSV importés (export Jira, export d'un autre outil
 * d'assistance, tableur), sans dépendance à GLPI (testée unitairement) : encodage UTF-8 ou
 * Windows-1252, séparateur détecté, dates aux formats Jira, français et ISO.
 */
final class CsvReader
{
    /** Séparateurs reconnus, le premier l'emporte à égalité. */
    private const DELIMITERS = [',', ';', "\t", '|'];

    private const MONTHS = [
        'janv' => 'Jan', 'janvier' => 'Jan', 'fevr' => 'Feb', 'fevrier' => 'Feb', 'mars' => 'Mar', 'avr' => 'Apr',
        'avril' => 'Apr', 'mai' => 'May', 'juin' => 'Jun', 'juil' => 'Jul', 'juillet' => 'Jul', 'aout' => 'Aug',
        'sept' => 'Sep', 'septembre' => 'Sep', 'oct' => 'Oct', 'octobre' => 'Oct', 'nov' => 'Nov',
        'novembre' => 'Nov', 'dec' => 'Dec', 'decembre' => 'Dec',
    ];

    /**
     * Formats de date essayés dans l'ordre : ceux des exports Jira d'abord (inchangés), puis les
     * formats français (jour en premier) et ISO des tableurs et des autres outils.
     */
    private const DATE_FORMATS = [
        'd/M/y g:i A', 'd/M/y h:i A', 'd/M/y H:i', 'd/M/Y g:i A', 'd/M/Y H:i', 'Y-m-d H:i', 'Y-m-d H:i:s',
        'd/m/Y H:i', 'd/m/Y', 'Y-m-d', 'Y-m-d\TH:i:s.vO', 'Y-m-d\TH:i:sP',
        'd/m/Y H:i:s', 'd/m/y H:i', 'd/m/y H:i:s', 'd/m/y', 'd.m.Y H:i', 'd.m.Y H:i:s', 'd.m.Y', 'd-m-Y H:i',
        'd-m-Y H:i:s', 'd-m-Y', 'Y/m/d H:i', 'Y/m/d H:i:s', 'Y/m/d', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i',
        'Y-m-d\TH:i:s.u', 'Y-m-d\TH:i:s.uP', 'Y-m-d H:i:s.u',
    ];

    /** Contenu en UTF-8, sans marque d'ordre des octets (un fichier de tableur est souvent en Windows-1252). */
    public static function toUtf8(string $content): string
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        return $content;
    }

    /** Séparateur le plus fréquent de la première ligne (hors guillemets). */
    public static function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\n") ?: '';
        $firstLine = (string) preg_replace('/"[^"]*"/', '', $firstLine);
        $best = ',';
        $bestCount = 0;
        foreach (self::DELIMITERS as $delimiter) {
            $count = substr_count($firstLine, $delimiter);
            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }

        return $best;
    }

    /**
     * En-tête et lignes d'un CSV (les lignes vides sont sautées ; `line` est le numéro de ligne du
     * fichier, pour les messages d'erreur).
     *
     * @return array{header: list<string>, rows: list<array{line: int, values: list<string>}>, delimiter: string}
     */
    public static function read(string $content, int $maxRows = PHP_INT_MAX): array
    {
        $content = self::toUtf8($content);
        $delimiter = self::detectDelimiter($content);
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return ['header' => [], 'rows' => [], 'delimiter' => $delimiter];
        }
        fwrite($handle, $content);
        rewind($handle);
        $header = fgetcsv($handle, null, $delimiter, '"', '');
        if (!is_array($header) || $header === [null]) {
            fclose($handle);

            return ['header' => [], 'rows' => [], 'delimiter' => $delimiter];
        }
        $rows = [];
        $line = 1;
        while (count($rows) < $maxRows && ($values = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $line++;
            if ($values === [null]) {
                continue;
            }
            $rows[] = ['line' => $line, 'values' => array_map(static fn ($value): string => (string) $value, $values)];
        }
        fclose($handle);

        return [
            'header'    => array_map(static fn ($label): string => trim((string) $label), $header),
            'rows'      => $rows,
            'delimiter' => $delimiter,
        ];
    }

    /**
     * Date au format Y-m-d H:i:s, ou null : exports Jira (« 24/Oct/26 3:15 PM », « 24/oct./26
     * 15:15 »), formats français (« 14/11/2026 09:30 », « 14.11.2026 ») et ISO (« 2026-11-14T09:30 »).
     */
    public static function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $normalized = (string) preg_replace_callback(
            '/(?<=\/)([[:alpha:]éèûô]+)\.?(?=\/)/u',
            static function (array $m): string {
                $month = self::slug($m[1]);
                return self::MONTHS[$month] ?? ucfirst($month);
            },
            $value
        );
        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $normalized);
            $warnings = DateTimeImmutable::getLastErrors();
            $clean = $warnings === false || ($warnings['warning_count'] === 0 && $warnings['error_count'] === 0);
            // « 14/11/26 » lu avec une année sur quatre chiffres donne l'an 26 : format suivant.
            if ($date !== false && $clean && (int) $date->format('Y') >= 1900) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    /** Texte comparable : minuscules, sans accents, mots séparés par une espace. */
    public static function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'ô' => 'o', 'ö' => 'o', 'î' => 'i', 'ï' => 'i', 'û' => 'u', 'ù' => 'u', 'ü' => 'u', 'ç' => 'c']);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }
}
