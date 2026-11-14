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

use GlpiPlugin\Configurationglpiauto\Import\TicketImporter;
use GlpiPlugin\Configurationglpiauto\PluginTemplate;

/**
 * Issue #345 : import de tickets Jira (export CSV ou API REST) en tickets GLPI. Le moteur (aperçu,
 * lots avec reprise, journal, réimport sans doublon, pièces jointes, étiquettes, incidents de
 * sécurité) est commun à tous les imports de tickets depuis l'issue #430 (TicketImporter) ; seuls
 * la lecture de l'export Jira et les textes propres à Jira sont ici.
 */
final class JiraImporter extends TicketImporter
{
    /** Source des correspondances clé Jira => ticket (valeur des imports antérieurs à l'issue #430). */
    public const SOURCE = 'jira';

    protected const SESSION_KEY = 'plugin_configurationglpiauto_jira_import';

    protected const LAST_KEY = 'plugin_configurationglpiauto_jira_import_last';

    protected const FILE_PREFIX = 'jira-import';

    public function source(): string
    {
        return self::SOURCE;
    }

    protected static function parseFile(string $content, array $pending): array
    {
        return JiraCsvParser::parse($content);
    }

    protected function ticketContent(array $issue): string
    {
        return PluginTemplate::render('jira/ticket_content.html.twig', [
            'text'    => trim((string) $issue['description']) !== ''
                ? (string) $issue['description']
                : (string) $issue['summary'],
            'key'     => (string) $issue['key'],
            'project' => (string) $issue['project'],
            'labels'  => $issue['labels'],
        ]);
    }

    protected function commentContent(string $author, string $body): string
    {
        return PluginTemplate::render('jira/comment.html.twig', ['author' => $author, 'body' => $body]);
    }

    protected function notRetainedText(): string
    {
        return __('Demande non retenue (importé de Jira).', 'configurationglpiauto');
    }

    protected function labelComment(): string
    {
        return __('Étiquette importée de Jira', 'configurationglpiauto');
    }

    protected function attachmentMarker(string $key, string $file): string
    {
        return sprintf('Jira : %s / %s', $key, $file);
    }
}
