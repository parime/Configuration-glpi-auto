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
     * @return array{issues: list<array<string, mixed>>, errors: list<array{line: int, message: string}>}|null
     */
    public static function loadPending(): ?array
    {
        $file = (string) ($_SESSION[self::SESSION_KEY]['file'] ?? '');
        if ($file === '' || !is_file($file)) {
            return null;
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
        $isOurs = preg_match('/^jira-import-[0-9a-f]{16}\.csv$/', basename($file)) === 1
            && realpath(dirname($file)) === realpath(GLPI_TMP_DIR);
        if ($isOurs && is_file($file)) {
            @unlink($file); // nosemgrep: php.lang.security.unlink-use.unlink-use
        }
        unset($_SESSION[self::SESSION_KEY]);
    }

    /** @var array<string, int> cache nom/e-mail => utilisateur */
    private array $users = [];

    /**
     * @param list<array<string, mixed>> $issues
     * @return array{summary: array<string, array<string, int>>, statuses: array<string, int|string>,
     *     types: array<string, int>, people_found: array<string, int>, people_missing: list<string>,
     *     already_imported: int, total: int}
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
        ];
    }

    /**
     * @param list<array<string, mixed>> $issues
     * @param array<string, mixed> $statusMap statut Jira => statut GLPI (ou STATUS_NOT_RETAINED)
     * @param array<string, mixed> $typeMap   type Jira => type de ticket GLPI
     * @return array{created: int, updated: int, errors: list<string>, log: list<string>}
     */
    public function import(array $issues, array $statusMap, array $typeMap, int $entitiesId): array
    {
        global $CFG_GLPI;

        $result = ['created' => 0, 'updated' => 0, 'errors' => [], 'log' => []];
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
                    $result['updated']++;
                    $result['log'][] = sprintf(__('%1$s : ticket %2$d mis à jour', 'configurationglpiauto'), $key, $ticketsId);
                    continue;
                }
                $ticketsId = $this->create($issue, $status, $type, $entitiesId);
                if ($ticketsId <= 0) {
                    $result['errors'][] = sprintf(__('%s : création du ticket impossible', 'configurationglpiauto'), $key);
                    continue;
                }
                $result['created']++;
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
    private function create(array $issue, int|string $status, int $type, int $entitiesId): int
    {
        global $DB;

        $key = (string) $issue['key'];
        $content = trim((string) $issue['description']) !== ''
            ? nl2br(htmlescape((string) $issue['description']))
            : htmlescape((string) $issue['summary']);
        $content .= '<p><em>' . htmlescape(sprintf(__('Importé de Jira : %s', 'configurationglpiauto'), $key));
        if ((string) $issue['project'] !== '') {
            $content .= ' — ' . htmlescape((string) $issue['project']);
        }
        if ($issue['labels'] !== []) {
            $content .= ' — ' . htmlescape(implode(', ', $issue['labels']));
        }
        $content .= '</em></p>';
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
            $body = nl2br(htmlescape((string) $comment['body']));
            if ($usersId <= 0 && $author !== '') {
                $body = '<p><em>' . htmlescape(sprintf(__('Auteur Jira : %s', 'configurationglpiauto'), $author))
                    . '</em></p>' . $body;
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
