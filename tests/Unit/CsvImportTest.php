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

namespace GlpiPlugin\Configurationglpiauto\Tests\Unit;

use GlpiPlugin\Configurationglpiauto\Import\CsvColumns;
use GlpiPlugin\Configurationglpiauto\Import\CsvImportProfile;
use GlpiPlugin\Configurationglpiauto\Import\CsvReader;
use GlpiPlugin\Configurationglpiauto\Import\TicketMapping;
use PHPUnit\Framework\TestCase;

/**
 * Issue #430 : import générique de tickets depuis un fichier CSV fictif : colonnes proposées
 * d'après les en-têtes, encodages, séparateurs, dates françaises et ISO, commentaires, modèles.
 */
final class CsvImportTest extends TestCase
{
    /** Export fictif « d'un autre outil d'assistance » : en-têtes français, point-virgule. */
    private const FRENCH_EXPORT = "N° ticket;Objet;Description;État;Type de demande;Priorité;Catégorie;Demandeur;"
        . "E-mail du demandeur;Technicien;Groupe;Date d'ouverture;Date de clôture;Commentaires\n"
        . "1042;Imprimante HS;Voyant rouge au 2e étage;Clôturé;Incident;Haute;Matériel > Imprimante;Camille Durand;"
        . "camille.durand@exemple.invalid;Alex Martin;Support N1;14/11/2026 09:30;15/11/2026 17:05;"
        . "\"14/11/2026 10:00;Alex Martin;Je passe voir\n15/11/2026 17:00;Alex Martin;Toner changé\"\n"
        . "1043;Accès VPN;;En attente;Demande;Basse;Réseau;Inconnu Personne;;;;2026-11-13;;\n";

    public function testFrenchHeadersAreSuggested(): void
    {
        $table = CsvReader::read(self::FRENCH_EXPORT);
        $this->assertSame(';', $table['delimiter']);
        $mapping = CsvColumns::suggest($table['header']);

        $expected = [
            'key' => 0, 'summary' => 1, 'description' => 2, 'status' => 3, 'type' => 4, 'priority' => 5,
            'category' => 6, 'reporter' => 7, 'reporter_email' => 8, 'assignee' => 9, 'group' => 10,
            'created' => 11, 'resolved' => 12, 'comments' => 13,
        ];
        foreach ($expected as $field => $position) {
            $this->assertSame($position, $mapping[$field] ?? null, $field);
        }
        $this->assertSame([], CsvColumns::missingRequired($mapping));
    }

    public function testEnglishHeadersAndPrefixesAreSuggested(): void
    {
        $header = ['Ticket ID', 'Subject', 'Status of the request', 'Requester Email', 'Assigned to', 'Created at',
            'Tags', 'Unrelated column'];
        $mapping = CsvColumns::suggest($header);

        $this->assertSame(0, $mapping['key']);
        $this->assertSame(1, $mapping['summary']);
        $this->assertSame(2, $mapping['status'], 'En-tête qui commence par le nom du champ.');
        $this->assertSame(3, $mapping['reporter_email']);
        $this->assertSame(4, $mapping['assignee']);
        $this->assertSame(5, $mapping['created']);
        $this->assertSame(6, $mapping['labels']);
        $this->assertNotContains(7, $mapping, 'Colonne inconnue : non importée.');
    }

    public function testEachColumnServesOneFieldOnly(): void
    {
        $mapping = CsvColumns::suggest(['Date', 'Titre', 'Id']);
        $this->assertSame(0, $mapping['created']);
        $this->assertArrayNotHasKey('resolved', $mapping);
        $this->assertSame(count($mapping), count(array_unique($mapping)));
    }

    public function testParseWithMappingFrenchExport(): void
    {
        $table = CsvReader::read(self::FRENCH_EXPORT);
        $parsed = CsvColumns::parse(self::FRENCH_EXPORT, CsvColumns::suggest($table['header']), "\n");

        $this->assertSame([], $parsed['errors']);
        $this->assertCount(2, $parsed['issues']);
        $first = $parsed['issues'][0];
        $this->assertSame('1042', $first['key']);
        $this->assertSame('Imprimante HS', $first['summary']);
        $this->assertSame('Clôturé', $first['status']);
        $this->assertSame('Matériel > Imprimante', $first['category']);
        $this->assertSame('Support N1', $first['group']);
        $this->assertSame('camille.durand@exemple.invalid', $first['reporter_email']);
        $this->assertSame('2026-11-14 09:30:00', $first['created']);
        $this->assertSame('2026-11-15 17:05:00', $first['resolved']);
        $this->assertCount(2, $first['comments'], 'Un commentaire par ligne de la cellule.');
        $this->assertSame(['date' => '2026-11-14 10:00:00', 'author' => 'Alex Martin', 'body' => 'Je passe voir'], $first['comments'][0]);
        $second = $parsed['issues'][1];
        $this->assertSame('2026-11-13 00:00:00', $second['created'], 'Date ISO sans heure.');
        $this->assertNull($second['resolved']);
        $this->assertSame([], $second['comments']);

        $this->assertSame(TicketMapping::STATUS_CLOSED, TicketMapping::defaultStatus($first['status']));
        $this->assertSame(TicketMapping::STATUS_WAITING, TicketMapping::defaultStatus($second['status']));
        $this->assertSame(TicketMapping::TYPE_INCIDENT, TicketMapping::defaultType($first['type']));
        $this->assertSame(4, TicketMapping::defaultPriority($first['priority']));
        $this->assertSame(2, TicketMapping::normalizePriority('9', 'Basse'), 'Valeur hors 1 à 5 : priorité proposée.');
        $this->assertSame(5, TicketMapping::normalizePriority('5', 'Basse'));
    }

    public function testWindows1252CommaAndTabSeparatedFiles(): void
    {
        $latin = mb_convert_encoding("Numéro,Intitulé,Créé le\n7,Écran cassé,14.11.2026 08:15\n", 'Windows-1252', 'UTF-8');
        $this->assertFalse(mb_check_encoding($latin, 'UTF-8'));
        $table = CsvReader::read($latin);
        $this->assertSame(',', $table['delimiter']);
        $parsed = CsvColumns::parse($latin, CsvColumns::suggest($table['header']));
        $this->assertSame('Écran cassé', $parsed['issues'][0]['summary']);
        $this->assertSame('2026-11-14 08:15:00', $parsed['issues'][0]['created']);

        $tabs = "\xEF\xBB\xBFid\ttitle\tcreated\n8\tSouris\t2026-11-14T08:15:00\n";
        $table = CsvReader::read($tabs);
        $this->assertSame("\t", $table['delimiter']);
        $this->assertSame('id', $table['header'][0], 'Marque d\'ordre des octets retirée.');
        $parsed = CsvColumns::parse($tabs, CsvColumns::suggest($table['header']));
        $this->assertSame('2026-11-14 08:15:00', $parsed['issues'][0]['created']);
    }

    public function testFrenchAndIsoDates(): void
    {
        $cases = [
            '14/11/2026 09:30'          => '2026-11-14 09:30:00',
            '14/11/2026 09:30:15'       => '2026-11-14 09:30:15',
            '14/11/26 09:30'            => '2026-11-14 09:30:00',
            '14/11/26'                  => '2026-11-14 00:00:00',
            '14-11-2026'                => '2026-11-14 00:00:00',
            '2026/11/14 09:30'          => '2026-11-14 09:30:00',
            '2026-11-14T09:30'          => '2026-11-14 09:30:00',
            '2026-11-14T09:30:00+01:00' => '2026-11-14 09:30:00',
            '24/Oct/26 3:15 PM'         => '2026-10-24 15:15:00',
        ];
        foreach ($cases as $value => $expected) {
            $this->assertSame($expected, CsvReader::parseDate($value), $value);
        }
        $this->assertNull(CsvReader::parseDate('demain'));
        $this->assertNull(CsvReader::parseDate('31/02/2026'), 'Date impossible.');
    }

    public function testMissingRequiredColumnsDuplicateKeysAndEmptyLines(): void
    {
        $csv = "id;titre;étiquettes\n1;Premier;réseau, urgent\n\n1;Doublon;\n;Sans identifiant;\n2;Second;a|b;c\n";
        $parsed = CsvColumns::parse($csv, ['key' => 0, 'summary' => 1, 'labels' => 2]);
        $this->assertCount(2, $parsed['issues']);
        $this->assertSame(['réseau', 'urgent'], $parsed['issues'][0]['labels']);
        $messages = array_column($parsed['errors'], 'message', 'line');
        $this->assertSame(['duplicate_key', 'missing_key_or_summary'], array_values($messages));
        $this->assertSame([4, 5], array_keys($messages), 'Numéro de ligne du fichier, ligne vide comprise.');

        $this->assertSame('missing_columns', CsvColumns::parse($csv, ['key' => 0])['errors'][0]['message']);
        $this->assertSame(['summary'], CsvColumns::missingRequired(['key' => 0]));
        $this->assertSame('empty', CsvColumns::parse('', ['key' => 0, 'summary' => 1])['errors'][0]['message']);
    }

    public function testCommentSeparatorsAndSecondFile(): void
    {
        $csv = "id,titre,suivis\n5,Badge,\"Premier | Second |  \"\n";
        $this->assertCount(2, CsvColumns::parse($csv, ['key' => 0, 'summary' => 1, 'comments' => 2], '|')['issues'][0]['comments']);
        $this->assertCount(1, CsvColumns::parse($csv, ['key' => 0, 'summary' => 1, 'comments' => 2])['issues'][0]['comments']);
        $this->assertSame("\n\n", CsvColumns::separator('blankline'));
        $this->assertSame('###', CsvColumns::separator('custom', '###'));
        $this->assertSame('', CsvColumns::separator('inconnu'));

        $file = "Identifiant;Date;Auteur;Commentaire\n5;14/11/2026 10:00;Alex Martin;Badge prêt\n5;;;Remis\n6;;;Autre ticket\n;;;Orphelin\n";
        $comments = CsvColumns::parseCommentsFile($file);
        $this->assertTrue($comments['columns_found']);
        $this->assertSame(3, $comments['count']);
        $parsed = CsvColumns::parse($csv, ['key' => 0, 'summary' => 1], '', $comments['comments']);
        $this->assertSame(
            [['date' => '2026-11-14 10:00:00', 'author' => 'Alex Martin', 'body' => 'Badge prêt'], ['date' => null, 'author' => '', 'body' => 'Remis']],
            $parsed['issues'][0]['comments']
        );
        $this->assertFalse(CsvColumns::parseCommentsFile("a;b\n1;2\n")['columns_found']);
    }

    public function testMappingFromScreenAndProfiles(): void
    {
        $this->assertSame(['key' => 0, 'summary' => 2], CsvColumns::normalize(
            ['key' => '0', 'summary' => '2', 'status' => '', 'type' => '9', 'inconnu' => '1', 'priority' => '-1'],
            3
        ));

        $header = ['Réf.', 'Sujet', 'Statut'];
        $labels = CsvColumns::toLabels(['key' => 0, 'summary' => 1], $header);
        $this->assertSame(['key' => 'Réf.', 'summary' => 'Sujet'], $labels);
        // Même outil, colonnes dans un autre ordre et autre casse : le modèle suit les en-têtes.
        $this->assertSame(['key' => 2, 'summary' => 0], CsvColumns::fromProfile(['SUJET', 'Statut', 'ref'], $labels));

        $settings = CsvImportProfile::normalize([
            'source_name' => ' Ancien outil ', 'columns' => $labels + ['pirate' => 'x', 'status' => ''],
            'separator' => 'pipe',
        ]);
        $this->assertSame(['source_name' => 'Ancien outil', 'columns' => $labels, 'separator' => 'pipe', 'separator_custom' => ''], $settings);
        $this->assertSame('none', CsvImportProfile::normalize(['separator' => 'rm -rf'])['separator']);

        $json = (string) json_encode(['format' => CsvImportProfile::FORMAT, 'version' => 1, 'name' => 'Ancien outil'] + $settings);
        $this->assertSame(['name' => 'Ancien outil', 'settings' => $settings], CsvImportProfile::parseExport($json));
        $this->assertNull(CsvImportProfile::parseExport('{"name":"x","columns":{"key":"id"}}'), 'Pas un modèle exporté.');
        $this->assertNull(CsvImportProfile::parseExport('pas du JSON'));
    }

    public function testSamplesForTheColumnsScreen(): void
    {
        $table = CsvReader::read(self::FRENCH_EXPORT);
        $samples = CsvColumns::samples($table['header'], $table['rows']);
        $this->assertSame('1042 ; 1043', $samples[0]);
        $this->assertStringNotContainsString("\n", $samples[13], 'Cellule sur plusieurs lignes : une seule ligne.');
        $this->assertSame('Voyant rouge au 2e étage', $samples[2], 'Cellule vide ignorée.');
        $this->assertCount(count($table['header']), $samples);
    }
}
