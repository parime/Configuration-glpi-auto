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

namespace GlpiPlugin\Configurationglpiauto\Tests\Unit;

use GlpiPlugin\Configurationglpiauto\Jira\JiraCsvParser;
use GlpiPlugin\Configurationglpiauto\Jira\JiraMapping;
use PHPUnit\Framework\TestCase;

/**
 * Issue #345 : lecture d'exports CSV Jira fictifs et correspondances proposées par défaut.
 */
final class JiraCsvTest extends TestCase
{
    public function testEnglishExportWithRepeatedColumns(): void
    {
        $csv = "Summary,Issue key,Issue id,Issue Type,Status,Project name,Priority,Assignee,Reporter,Created,Resolved,"
            . "Description,Labels,Labels,Comment,Comment\n"
            . "Imprimante HS,DEMO-12,10012,Bug,Done,Support,High,Alex Martin,Camille Durand,24/Oct/26 3:15 PM,"
            . "25/Oct/26 9:00 AM,\"Ne s'allume plus, voyant rouge\",materiel,urgent,"
            . "\"25/Oct/26 8:00 AM;5b10ac;Je regarde ça; merci\",\"25/Oct/26 9:00 AM;5b10ac;Changé\"\n";
        $result = JiraCsvParser::parse($csv);

        $this->assertSame([], $result['errors']);
        $issue = $result['issues'][0];
        $this->assertSame('DEMO-12', $issue['key']);
        $this->assertSame('Bug', $issue['type']);
        $this->assertSame('2026-10-24 15:15:00', $issue['created']);
        $this->assertSame('2026-10-25 09:00:00', $issue['resolved']);
        $this->assertSame(['materiel', 'urgent'], $issue['labels']);
        $this->assertCount(2, $issue['comments']);
        $this->assertSame('Je regarde ça; merci', $issue['comments'][0]['body'], 'Les « ; » du texte sont gardés.');
        $this->assertSame('2026-10-25 08:00:00', $issue['comments'][0]['date']);
    }

    public function testFrenchExportWithSemicolonsAndFrenchMonths(): void
    {
        $csv = "\xEF\xBB\xBFRésumé;Clé de ticket;Type de ticket;État;Priorité;Responsable;Rapporteur;Création\n"
            . "Accès dossier compta;APP-3;Demande;À faire;Moyenne;;Lou Petit;03/févr./26 14:05\n"
            . ";APP-4;Demande;À faire;;;;\n";
        $result = JiraCsvParser::parse($csv);

        $this->assertSame([['line' => 3, 'message' => 'missing_key_or_summary']], $result['errors']);
        $this->assertSame('APP-3', $result['issues'][0]['key']);
        $this->assertSame('À faire', $result['issues'][0]['status']);
        $this->assertSame('2026-02-03 14:05:00', $result['issues'][0]['created']);
    }

    public function testMissingColumnsAreReported(): void
    {
        $this->assertSame(
            [['line' => 1, 'message' => 'missing_columns']],
            JiraCsvParser::parse("Status,Priority\nDone,High\n")['errors']
        );
    }

    public function testDefaultStatusMapping(): void
    {
        $this->assertSame(JiraMapping::STATUS_NEW, JiraMapping::defaultStatus('À faire'));
        $this->assertSame(JiraMapping::STATUS_ASSIGNED, JiraMapping::defaultStatus('En cours'));
        $this->assertSame(JiraMapping::STATUS_WAITING, JiraMapping::defaultStatus('Att. retour utilisateur / client'));
        $this->assertSame(JiraMapping::STATUS_CLOSED, JiraMapping::defaultStatus('Terminé'));
        $this->assertSame(JiraMapping::STATUS_CLOSED, JiraMapping::defaultStatus('Fini'));
        $this->assertSame(JiraMapping::STATUS_NOT_RETAINED, JiraMapping::defaultStatus('Non retenu'));
        $this->assertSame(JiraMapping::STATUS_NOT_RETAINED, JiraMapping::defaultStatus("Won't Do"));
        $this->assertSame(JiraMapping::STATUS_NEW, JiraMapping::defaultStatus('Statut maison'));
    }

    public function testTypePriorityAndOverrides(): void
    {
        $this->assertSame(JiraMapping::TYPE_INCIDENT, JiraMapping::defaultType('Bug'));
        $this->assertSame(JiraMapping::TYPE_INCIDENT, JiraMapping::defaultType('Incident'));
        $this->assertSame(JiraMapping::TYPE_REQUEST, JiraMapping::defaultType('Demande de service'));
        $this->assertSame(5, JiraMapping::defaultPriority('Highest'));
        $this->assertSame(4, JiraMapping::defaultPriority('Haute'));
        $this->assertSame(2, JiraMapping::defaultPriority('Low'));
        $this->assertSame(3, JiraMapping::defaultPriority(''));
        $this->assertSame(JiraMapping::STATUS_SOLVED, JiraMapping::normalizeStatus('5', 'Done'));
        $this->assertSame(JiraMapping::STATUS_CLOSED, JiraMapping::normalizeStatus('99', 'Done'), 'Valeur inconnue.');
        $this->assertSame(JiraMapping::TYPE_REQUEST, JiraMapping::normalizeType('2', 'Bug'));
    }

    public function testSummaryAndNameKeys(): void
    {
        $summary = JiraMapping::summarize([
            ['status' => 'Done', 'type' => 'Bug', 'project' => 'Support'],
            ['status' => 'Done', 'type' => 'Task', 'project' => 'Support'],
            ['status' => 'To Do', 'type' => 'Bug', 'project' => ''],
        ]);
        $this->assertSame(['Done' => 2, 'To Do' => 1], $summary['statuses']);
        $this->assertSame(['Support' => 2, '—' => 1], $summary['projects']);
        $this->assertSame(['camille durand', 'durand camille'], JiraMapping::nameKeys('Camille Durand'));
        $this->assertSame([], JiraMapping::nameKeys(' '));
    }
}
