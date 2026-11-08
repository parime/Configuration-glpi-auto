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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration;

use CommonITILActor;
use GlpiPlugin\Configurationglpiauto\Jira\JiraCsvParser;
use GlpiPlugin\Configurationglpiauto\Jira\JiraImporter;
use GlpiPlugin\Configurationglpiauto\Jira\JiraMapping;
use ITILFollowup;
use ITILSolution;
use PHPUnit\Framework\TestCase;
use Ticket;
use Ticket_User;
use User;
use UserEmail;

/**
 * Issue #345 : import d'un export Jira fictif : personnes reconnues, statuts, commentaires,
 * « non retenu », et réimport sans doublon.
 */
final class JiraImporterTest extends TestCase
{
    /** @var int[] */
    private array $users = [];

    private string $prefix;

    protected function setUp(): void
    {
        $this->prefix = 'PHPUNIT' . random_int(1000, 9999);
    }

    protected function tearDown(): void
    {
        global $DB;

        foreach ($DB->request(['FROM' => JiraImporter::TABLE, 'WHERE' => ['jira_key' => ['LIKE', $this->prefix . '-%']]]) as $row) {
            (new Ticket())->delete(['id' => $row['tickets_id']], true);
        }
        $DB->delete(JiraImporter::TABLE, ['jira_key' => ['LIKE', $this->prefix . '-%']]);
        foreach ($this->users as $id) {
            (new User())->delete(['id' => $id], true);
        }
    }

    private function user(string $first, string $last, ?string $email = null): int
    {
        $id = (int) (new User())->add([
            'name' => strtolower($first) . '.' . strtolower($last) . random_int(1000, 9999),
            'firstname' => $first,
            'realname' => $last,
        ]);
        if ($email !== null) {
            (new UserEmail())->add(['users_id' => $id, 'email' => $email]);
        }
        $this->users[] = $id;

        return $id;
    }

    private function csv(string $extraComment = ''): string
    {
        $p = $this->prefix;

        return "Summary,Issue key,Issue Type,Status,Priority,Assignee,Reporter,Reporter Email,Created,Resolved,"
            . "Description,Comment,Comment\n"
            . "Imprimante HS,$p-1,Bug,Done,High,Alex Phpunitimport,Compte Messagerie,camille.$p@exemple.invalid,"
            . "24/Oct/26 3:15 PM,25/Oct/26 9:00 AM,Voyant rouge,\"24/Oct/26 4:00 PM;Alex Phpunitimport;Je regarde\",$extraComment\n"
            . "Nouvel écran,$p-2,Demande,Non retenu,Low,,Inconnu Personne,,20/Oct/26 9:00 AM,,Pour le bureau 12,,\n"
            . "Accès compta,$p-3,Demande,Att. retour client,Medium,Alex Phpunitimport,,,22/Oct/26 9:00 AM,,,,\n";
    }

    public function testEachJiraProjectGetsItsEntityAndCategoryOnlyWhenAllowed(): void
    {
        $p = $this->prefix;
        $root = 0;
        $child = (int) (new \Entity())->add(['name' => 'PHPUnit projet ' . $p, 'entities_id' => $root]);
        $other = (int) (new \Entity())->add(['name' => 'PHPUnit autre ' . $p, 'entities_id' => $root]);
        $category = (int) (new \ITILCategory())->add(['name' => 'PHPUnit cat ' . $p, 'entities_id' => $child, 'is_recursive' => 0]);
        $foreign = (int) (new \ITILCategory())->add(['name' => 'PHPUnit cat autre ' . $p, 'entities_id' => $other, 'is_recursive' => 0]);
        \Session::changeActiveEntities($root, true);
        $issues = [
            ['key' => "$p-1", 'project' => 'Support infra'],
            ['key' => "$p-2", 'project' => 'Appli [métier] "RH"'],
            ['key' => "$p-3", 'project' => ''],
        ];
        $infra = JiraMapping::projectKey('Support infra');
        $rh = JiraMapping::projectKey('Appli [métier] "RH"');

        $map = JiraImporter::projectMap($issues, [$infra => $child, $rh => $child], [$infra => $category, $rh => $foreign], $root);

        $this->assertSame(['entities_id' => $child, 'itilcategories_id' => $category], $map['Support infra']);
        $this->assertSame(
            ['entities_id' => $child, 'itilcategories_id' => 0],
            $map['Appli [métier] "RH"'],
            'Catégorie d\'une autre entité : ignorée.'
        );
        $this->assertSame(['entities_id' => $root, 'itilcategories_id' => 0], $map['-'], 'Sans réglage : entité de l\'import.');

        // Entité hors des entités actives de l'utilisateur : celle de l'import à la place.
        \Session::changeActiveEntities($other, false);
        $this->assertSame($other, JiraImporter::projectMap($issues, [$infra => $child], [], $other)['Support infra']['entities_id']);
        \Session::changeActiveEntities($root, true);

        (new \ITILCategory())->delete(['id' => $category], true);
        (new \ITILCategory())->delete(['id' => $foreign], true);
        (new \Entity())->delete(['id' => $child], true);
        (new \Entity())->delete(['id' => $other], true);
    }

    public function testPreviewThenImportThenReimportWithoutDuplicates(): void
    {
        $alex = $this->user('Alex', 'Phpunitimport');
        $camille = $this->user('Camille', 'Phpunitimport', 'camille.' . $this->prefix . '@exemple.invalid');
        $issues = JiraCsvParser::parse($this->csv())['issues'];
        $importer = new JiraImporter();

        $preview = $importer->preview($issues);
        $this->assertSame(3, $preview['total']);
        $this->assertSame(0, $preview['already_imported']);
        $this->assertSame($alex, $preview['people_found']['Alex Phpunitimport'], 'Reconnu par son nom.');
        $this->assertSame($camille, $preview['people_found']['Compte Messagerie'], 'Reconnu par son e-mail.');
        $this->assertSame(['Inconnu Personne'], $preview['people_missing'], 'Inconnu signalé, jamais créé.');
        $this->assertSame(JiraMapping::STATUS_NOT_RETAINED, $preview['statuses']['Non retenu']);

        $result = $importer->import($issues, $preview['statuses'], $preview['types'], 0);
        $this->assertSame(3, $result['created']);
        $this->assertSame([], $result['errors']);

        $first = new Ticket();
        $first->getFromDB($importer->findTicket($this->prefix . '-1'));
        $this->assertSame('2026-10-24 15:15:00', $first->fields['date'], 'Date de création conservée.');
        $this->assertSame(Ticket::CLOSED, (int) $first->fields['status']);
        $this->assertSame('2026-10-25 09:00:00', $first->fields['closedate']);
        $this->assertSame(Ticket::INCIDENT_TYPE, (int) $first->fields['type']);
        $this->assertSame($this->prefix . '-1', $first->fields['externalid']);
        $this->assertSame($camille, $this->actor($first->getID(), CommonITILActor::REQUESTER), 'Reconnu par son e-mail.');
        $this->assertSame($alex, $this->actor($first->getID(), CommonITILActor::ASSIGN));
        $followups = (new ITILFollowup())->find(['itemtype' => Ticket::class, 'items_id' => $first->getID()]);
        $this->assertCount(1, $followups);
        $this->assertSame('2026-10-24 16:00:00', current($followups)['date']);
        $this->assertSame('2026-10-24 16:00:00', current($followups)['date_creation'], 'Date affichée dans le fil.');
        $this->assertSame('2026-10-24 15:15:00', $first->fields['date_creation']);

        $second = new Ticket();
        $second->getFromDB($importer->findTicket($this->prefix . '-2'));
        $this->assertSame(Ticket::CLOSED, (int) $second->fields['status']);
        $this->assertSame(1, $this->notRetainedTraces($second->getID()), 'Solution, ou suivi si GLPI la refuse.');
        $third = new Ticket();
        $third->getFromDB($importer->findTicket($this->prefix . '-3'));
        $this->assertSame(Ticket::WAITING, (int) $third->fields['status']);

        // Réimport avec un commentaire de plus : mise à jour, aucun doublon.
        $again = JiraCsvParser::parse($this->csv('"26/Oct/26 10:00 AM;Alex Phpunitimport;Remplacée"'))['issues'];
        $this->assertSame(3, (new JiraImporter())->preview($again)['already_imported']);
        $result = (new JiraImporter())->import($again, $preview['statuses'], $preview['types'], 0);
        $this->assertSame(0, $result['created']);
        $this->assertSame(3, $result['updated']);
        $this->assertSame(1, countElementsInTable(Ticket::getTable(), ['externalid' => $this->prefix . '-1']));
        $this->assertCount(2, (new ITILFollowup())->find(['itemtype' => Ticket::class, 'items_id' => $first->getID()]));
        $this->assertSame(1, $this->notRetainedTraces($second->getID()), 'Pas de second motif au réimport.');
    }

    /**
     * Un ticket importé puis supprimé dans GLPI : le réimport recrée le ticket. Sa correspondance
     * restée en place (jira_key unique) faisait échouer tout l'import.
     */
    public function testReimportAfterTheImportedTicketWasDeleted(): void
    {
        $issues = JiraCsvParser::parse($this->csv())['issues'];
        $importer = new JiraImporter();
        $preview = $importer->preview($issues);
        $importer->import($issues, $preview['statuses'], $preview['types'], 0);
        $deleted = $importer->findTicket($this->prefix . '-1');
        (new Ticket())->delete(['id' => $deleted], true);

        $again = new JiraImporter();
        $this->assertSame(2, $again->preview($issues)['already_imported']);
        $result = $again->import($issues, $preview['statuses'], $preview['types'], 0);
        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(2, $result['updated']);
        $recreated = $again->findTicket($this->prefix . '-1');
        $this->assertGreaterThan(0, $recreated);
        $this->assertNotSame($deleted, $recreated);
    }

    /**
     * Issue #345 : import par lots. Un lot interrompu est rejoué sans doublon, le journal CSV garde
     * chaque ticket, et le résultat cumulé est conservé une fois l'import fini.
     */
    public function testBatchImportResumesWithoutDuplicatesAndKeepsALog(): void
    {
        $count = JiraImporter::BATCH_SIZE + 5;
        $csv = "Summary,Issue key,Issue Type,Status,Created,Labels\n";
        for ($i = 1; $i <= $count; $i++) {
            $csv .= "Demande $i,{$this->prefix}-$i,Demande,To Do,20/Oct/26 9:00 AM," . ($i === 2 ? 'Sécurité' : 'divers') . "\n";
        }
        JiraImporter::storePending($csv, 0);
        $issues = JiraImporter::loadPending()['issues'] ?? [];
        $this->assertCount($count, $issues);
        $this->assertSame(1, (new JiraImporter())->preview($issues)['security_count']);
        $options = [
            'status_map' => [], 'type_map' => [], 'import_labels' => false, 'project_map' => [],
            // Sans grcmanager actif, la case est ignorée : aucune erreur.
            'qualify_security' => true,
        ];
        JiraImporter::startJob($options, $count);

        $this->assertFalse((new JiraImporter())->runNextBatch(), 'Un lot reste à importer.');
        $this->assertSame(JiraImporter::BATCH_SIZE, JiraImporter::job()['cursor']);
        $this->assertSame(JiraImporter::BATCH_SIZE, JiraImporter::job()['result']['created']);

        // Interruption simulée pendant le second lot : la position revient en arrière, le lot est rejoué.
        $_SESSION['plugin_configurationglpiauto_jira_import']['job']['cursor'] = JiraImporter::BATCH_SIZE - 3;
        $this->assertTrue((new JiraImporter())->runNextBatch(), 'Import terminé.');
        $this->assertNull(JiraImporter::job());
        $last = JiraImporter::last();
        $this->assertNotNull($last);
        $this->assertSame($count, $last['result']['created'] + $last['result']['updated'] - 3);
        $this->assertSame(3, $last['result']['updated'], 'Les tickets rejoués sont mis à jour, pas recréés.');
        $this->assertSame([], $last['result']['errors']);
        for ($i = 1; $i <= $count; $i++) {
            $this->assertSame(1, countElementsInTable(Ticket::getTable(), ['externalid' => "{$this->prefix}-$i"]));
        }

        // Avec grcmanager actif (instance de développement), le ticket « Sécurité » est relié à un
        // incident de sécurité ; sans lui (CI), la case est sans effet.
        $security = JiraImporter::securityAvailable() ? 1 : 0;
        $this->assertSame($security, $last['result']['security']);
        if ($security === 1) {
            $incidents = (array) call_user_func(
                ['GlpiPlugin\\Grcmanager\\SecurityIncidentTicket', 'getIncidentIdsForTicket'],
                (new JiraImporter())->findTicket("{$this->prefix}-2")
            );
            $this->assertCount(1, $incidents);
            $incident = getItemForItemtype('PluginGrcmanagerSecurityIncident');
            $this->assertNotFalse($incident);
            $incident->delete(['id' => $incidents[0]], true);
        }

        $log = JiraImporter::logFile();
        $this->assertNotSame('', $log);
        $this->assertCount($count + 3 + $security, JiraImporter::logLines(), 'Une ligne par ticket traité, rejeu compris.');
        $this->assertStringStartsWith("\xEF\xBB\xBF", (string) file_get_contents($log), 'CSV lisible par un tableur.');
        $this->assertNull(JiraImporter::loadPending(), 'Fichier en attente effacé à la fin.');

        JiraImporter::forgetLast();
        $this->assertFalse(is_file($log));
        $this->assertNull(JiraImporter::last());
    }

    public function testStoppingAnImportKeepsTheImportedTicketsAndThePartialLog(): void
    {
        $count = JiraImporter::BATCH_SIZE + 2;
        $csv = "Summary,Issue key,Issue Type,Status,Created\n";
        for ($i = 1; $i <= $count; $i++) {
            $csv .= "Demande $i,{$this->prefix}-$i,Demande,To Do,20/Oct/26 9:00 AM\n";
        }
        JiraImporter::storePending($csv, 0);
        JiraImporter::startJob(
            ['status_map' => [], 'type_map' => [], 'import_labels' => false, 'project_map' => [], 'qualify_security' => false],
            $count
        );
        (new JiraImporter())->runNextBatch();
        JiraImporter::abandonJob();

        $this->assertNull(JiraImporter::job());
        $this->assertNull(JiraImporter::loadPending());
        $last = JiraImporter::last();
        $this->assertSame(JiraImporter::BATCH_SIZE, $last['result']['stopped_at']);
        $this->assertSame(JiraImporter::BATCH_SIZE, $last['result']['created']);
        $this->assertCount(JiraImporter::BATCH_SIZE + 1, JiraImporter::logLines(), 'Tickets importés et arrêt.');
        JiraImporter::forgetLast();
    }

    /**
     * Solution « Demande non retenue », ou suivi équivalent quand GLPI refuse la solution
     * (catégorie obligatoire sur certaines instances).
     */
    private function notRetainedTraces(int $ticketsId): int
    {
        $criteria = ['itemtype' => Ticket::class, 'items_id' => $ticketsId];
        $followups = array_filter(
            (new ITILFollowup())->find($criteria),
            static fn (array $row): bool => str_contains((string) $row['content'], 'Jira')
                && !str_contains((string) $row['content'], 'Auteur')
        );

        return countElementsInTable(ITILSolution::getTable(), $criteria) + count($followups);
    }

    private function actor(int $ticketsId, int $type): int
    {
        $link = new Ticket_User();
        $rows = $link->find(['tickets_id' => $ticketsId, 'type' => $type]);

        return (int) (current($rows)['users_id'] ?? 0);
    }
}
