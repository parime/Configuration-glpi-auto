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
use GlpiPlugin\Configurationglpiauto\Import\CsvColumns;
use GlpiPlugin\Configurationglpiauto\Import\CsvImportProfile;
use GlpiPlugin\Configurationglpiauto\Import\CsvReader;
use GlpiPlugin\Configurationglpiauto\Import\CsvTicketImporter;
use GlpiPlugin\Configurationglpiauto\Import\TicketImporter;
use GlpiPlugin\Configurationglpiauto\Import\TicketMapping;
use GlpiPlugin\Configurationglpiauto\Jira\JiraCsvParser;
use GlpiPlugin\Configurationglpiauto\Jira\JiraImporter;
use Group;
use Group_Ticket;
use ITILCategory;
use ITILFollowup;
use PHPUnit\Framework\TestCase;
use Ticket;
use Ticket_User;
use User;
use UserEmail;

/**
 * Issue #430 : import générique d'un fichier CSV fictif « d'un autre outil d'assistance » :
 * correspondance des colonnes et des valeurs, réimport sans doublon, identifiants propres à chaque
 * source (pas de collision avec une clé Jira), commentaires d'un second fichier, pièces jointes
 * d'une archive, lots avec reprise, modèles enregistrés.
 */
final class CsvTicketImporterTest extends TestCase
{
    /** @var list<array{class-string<\CommonDBTM>, int}> */
    private array $created = [];

    private string $prefix;

    private string $source;

    protected function setUp(): void
    {
        $this->prefix = 'PHPUNITCSV' . random_int(1000, 9999);
        $this->source = 'Outil fictif ' . $this->prefix;
        \Session::changeActiveEntities(0, true);
    }

    protected function tearDown(): void
    {
        global $DB;

        CsvTicketImporter::forgetPending();
        CsvTicketImporter::forgetLast();
        foreach ([CsvTicketImporter::sourceKey($this->source), JiraImporter::SOURCE] as $source) {
            $where = ['source' => $source, 'jira_key' => ['LIKE', $this->prefix . '%']];
            foreach ($DB->request(['FROM' => TicketImporter::TABLE, 'WHERE' => $where]) as $row) {
                (new Ticket())->delete(['id' => $row['tickets_id']], true);
            }
            $DB->delete(TicketImporter::TABLE, $where);
        }
        foreach (array_reverse($this->created) as [$class, $id]) {
            (new $class())->delete(['id' => $id], true);
        }
        foreach (CsvImportProfile::all() as $profile) {
            if (str_contains($profile['name'], $this->prefix)) {
                CsvImportProfile::delete($profile['id']);
            }
        }
    }

    /**
     * @param class-string<\CommonDBTM> $class
     * @param array<string, mixed> $input
     */
    private function add(string $class, array $input): int
    {
        $id = (int) (new $class())->add($input);
        $this->assertGreaterThan(0, $id, $class);
        $this->created[] = [$class, $id];

        return $id;
    }

    /** Export fictif : en-têtes français, point-virgule, Windows-1252 comme un « CSV » d'Excel. */
    private function csv(string $extraComment = ''): string
    {
        $p = $this->prefix;
        $csv = "N° ticket;Objet;Description;État;Type de demande;Priorité;Catégorie;Demandeur;E-mail du demandeur;"
            . "Technicien;Groupe;Date d'ouverture;Date de clôture;Commentaires\n"
            . "{$p}-1;Imprimante en panne;Voyant rouge;Clôturé;Incident;Urgente;Imprimantes $p;Compte Messagerie;"
            . "camille.$p@exemple.invalid;Alex Phpunitcsv;Support $p;14/11/2026 09:30;15/11/2026 17:05;"
            . "\"14/11/2026 10:00;Alex Phpunitcsv;Je passe voir$extraComment\"\n"
            . "{$p}-2;Accès VPN;;En attente;Demande;Basse;Inconnue $p;Inconnu Personne;;;;2026-11-13;;\n";

        return (string) mb_convert_encoding($csv, 'Windows-1252', 'UTF-8');
    }

    /**
     * @return array{issues: list<array<string, mixed>>, errors: list<array{line: int, message: string}>}
     */
    private function parse(string $csv, string $separator = "\n"): array
    {
        return CsvColumns::parse($csv, CsvColumns::suggest(CsvReader::read($csv)['header']), $separator);
    }

    private function actor(int $ticketsId, int $type): int
    {
        $row = current((new Ticket_User())->find(['tickets_id' => $ticketsId, 'type' => $type]));

        return (int) ($row['users_id'] ?? 0);
    }

    public function testPreviewThenImportThenReimportWithoutDuplicates(): void
    {
        $p = $this->prefix;
        $alex = $this->add(User::class, ['name' => 'alex.phpunitcsv' . random_int(1000, 9999), 'firstname' => 'Alex', 'realname' => 'Phpunitcsv']);
        $camille = $this->add(User::class, ['name' => 'camille.phpunitcsv' . random_int(1000, 9999), 'firstname' => 'Camille', 'realname' => 'Phpunitcsv']);
        (new UserEmail())->add(['users_id' => $camille, 'email' => "camille.$p@exemple.invalid"]);
        $category = $this->add(ITILCategory::class, ['name' => "Imprimantes $p", 'entities_id' => 0, 'is_recursive' => 1]);
        $group = $this->add(Group::class, ['name' => "Support $p", 'entities_id' => 0, 'is_recursive' => 1, 'is_assign' => 1]);

        $parsed = $this->parse($this->csv());
        $this->assertSame([], $parsed['errors']);
        $importer = new CsvTicketImporter($this->source);
        $preview = $importer->preview($parsed['issues']);
        $this->assertSame(2, $preview['total']);
        $this->assertSame(0, $preview['already_imported']);
        $this->assertSame($alex, $preview['people_found']['Alex Phpunitcsv']);
        $this->assertSame($camille, $preview['people_found']['Compte Messagerie'], 'Reconnu par son e-mail.');
        $this->assertSame(['Inconnu Personne'], $preview['people_missing']);
        $values = $importer->previewValues($parsed['issues'], 0);
        $this->assertSame($category, $values['categories']["Imprimantes $p"]['default'], 'Catégorie du même nom proposée.');
        $this->assertSame(0, $values['categories']["Inconnue $p"]['default']);
        $this->assertSame(5, $values['priorities']['Urgente']['default']);
        $this->assertSame([], $values['groups_missing']);
        $this->assertSame($group, $values['groups_found']["Support $p"]);

        // Correspondances choisies à l'écran : « Basse » devient « Très basse ».
        $result = $importer->import(
            $parsed['issues'],
            $preview['statuses'],
            $preview['types'],
            0,
            false,
            [],
            false,
            ['priority' => ['Basse' => 1], 'category' => ["Imprimantes $p" => $category]]
        );
        $this->assertSame(2, $result['created']);
        $this->assertSame([], $result['errors']);

        $first = new Ticket();
        $this->assertTrue($first->getFromDB($importer->findTicket("$p-1")));
        $this->assertSame('Imprimante en panne', $first->fields['name']);
        $this->assertSame('2026-11-14 09:30:00', $first->fields['date'], 'Date d\'ouverture conservée (format français).');
        $this->assertSame(Ticket::CLOSED, (int) $first->fields['status']);
        $this->assertSame('2026-11-15 17:05:00', $first->fields['closedate']);
        $this->assertSame(Ticket::INCIDENT_TYPE, (int) $first->fields['type']);
        // Urgence reprise du fichier ; la priorité peut être recalculée par GLPI (matrice, règles de l'instance).
        $this->assertSame(5, (int) $first->fields['urgency']);
        $this->assertSame($category, (int) $first->fields['itilcategories_id']);
        $this->assertSame("$p-1", $first->fields['externalid']);
        $this->assertSame($camille, $this->actor($first->getID(), CommonITILActor::REQUESTER));
        $this->assertSame($alex, $this->actor($first->getID(), CommonITILActor::ASSIGN));
        $this->assertSame(1, countElementsInTable(Group_Ticket::getTable(), ['tickets_id' => $first->getID(), 'groups_id' => $group, 'type' => CommonITILActor::ASSIGN]));
        $followups = (new ITILFollowup())->find(['itemtype' => Ticket::class, 'items_id' => $first->getID()]);
        $this->assertCount(1, $followups);
        $this->assertSame('2026-11-14 10:00:00', current($followups)['date']);
        $this->assertSame($alex, (int) current($followups)['users_id']);

        $second = new Ticket();
        $this->assertTrue($second->getFromDB($importer->findTicket("$p-2")));
        $this->assertSame(Ticket::WAITING, (int) $second->fields['status']);
        $this->assertSame(1, (int) $second->fields['urgency'], 'Priorité choisie à l\'écran.');
        $this->assertSame(0, (int) $second->fields['itilcategories_id'], 'Catégorie inconnue : aucune.');
        $this->assertSame('2026-11-13 00:00:00', $second->fields['date'], 'Date ISO.');

        // Réimport avec un commentaire de plus (sans date) : mise à jour, aucun doublon.
        $again = $this->parse($this->csv("\nRemplacée"));
        $importer = new CsvTicketImporter($this->source);
        $this->assertSame(2, $importer->preview($again['issues'])['already_imported']);
        $result = $importer->import($again['issues'], $preview['statuses'], $preview['types'], 0);
        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['updated']);
        $this->assertSame(1, countElementsInTable(Ticket::getTable(), ['externalid' => "$p-1"]));
        $this->assertCount(2, (new ITILFollowup())->find(['itemtype' => Ticket::class, 'items_id' => $first->getID()]));
        // Troisième passage identique : rien n'est ajouté, même le commentaire sans date.
        (new CsvTicketImporter($this->source))->import($again['issues'], $preview['statuses'], $preview['types'], 0);
        $this->assertCount(2, (new ITILFollowup())->find(['itemtype' => Ticket::class, 'items_id' => $first->getID()]));
    }

    public function testSameIdentifierInAnotherSourceOrInJiraIsAnotherTicket(): void
    {
        $p = $this->prefix;
        $csv = "id;titre\n$p-1;Depuis le tableur\n";
        $issues = $this->parse($csv)['issues'];
        $jira = JiraCsvParser::parse("Summary,Issue key\nDepuis Jira,$p-1\n")['issues'];

        $this->assertSame(1, (new JiraImporter())->import($jira, [], [], 0)['created']);
        $importer = new CsvTicketImporter($this->source);
        $this->assertSame(0, $importer->preview($issues)['already_imported'], 'La clé Jira ne compte pas pour le CSV.');
        $this->assertSame(1, $importer->import($issues, [], [], 0)['created']);
        $this->assertNotSame((new JiraImporter())->findTicket("$p-1"), $importer->findTicket("$p-1"));
        // Même outil, nom écrit autrement : même source, donc mise à jour.
        $same = new CsvTicketImporter(mb_strtoupper($this->source));
        $this->assertSame($importer->source(), $same->source());
        $this->assertSame(1, $same->import($issues, [], [], 0)['updated']);
        $this->assertSame(2, countElementsInTable(Ticket::getTable(), ['externalid' => "$p-1"]));
    }

    public function testBatchImportWithCommentsFileAttachmentsAndResume(): void
    {
        $p = $this->prefix;
        $count = CsvTicketImporter::BATCH_SIZE + 3;
        $csv = "Référence,Sujet,Statut,Créé le\n";
        for ($i = 1; $i <= $count; $i++) {
            $csv .= "$p-$i,Demande $i,Ouvert,14/11/2026 08:00\n";
        }
        CsvTicketImporter::storePending($csv, 0, ['source_name' => $this->source]);
        CsvTicketImporter::storeComments("Identifiant;Date;Auteur;Commentaire\n$p-2;14/11/2026 09:00;Personne Externe;Rappel\n");
        $zipPath = GLPI_TMP_DIR . '/phpunit-' . $p . '.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString("pieces/$p-3/note.txt", 'Contenu fictif');
        $zip->addFromString('sans-dossier.txt', 'Ignoré');
        $zip->close();
        $stored = CsvTicketImporter::storeAttachments($zipPath);
        unlink($zipPath);
        $this->assertSame(['count' => 1, 'skipped' => 1], $stored);

        $table = CsvTicketImporter::pendingTable();
        $this->assertNotNull($table);
        $this->assertSame(1, CsvTicketImporter::pendingComments()['count']);
        CsvTicketImporter::updatePending(['mapping' => CsvColumns::suggest($table['header']), 'separator' => 'none']);
        $issues = CsvTicketImporter::loadPending()['issues'] ?? [];
        $this->assertCount($count, $issues);
        $this->assertSame(TicketMapping::STATUS_NEW, (new CsvTicketImporter())->preview($issues)['statuses']['Ouvert']);
        $this->assertSame(1, (new CsvTicketImporter())->preview($issues)['attachments']);

        CsvTicketImporter::startJob(
            ['status_map' => [], 'type_map' => [], 'import_labels' => false, 'project_map' => [], 'qualify_security' => false],
            $count
        );
        $this->assertFalse((new CsvTicketImporter())->runNextBatch());
        // Interruption simulée : le lot est rejoué sans doublon.
        $_SESSION['plugin_configurationglpiauto_csv_import']['job']['cursor'] = CsvTicketImporter::BATCH_SIZE - 2;
        $this->assertTrue((new CsvTicketImporter())->runNextBatch());
        $last = CsvTicketImporter::last();
        $this->assertNotNull($last);
        $this->assertSame($count, $last['result']['created']);
        $this->assertSame(2, $last['result']['updated']);
        $this->assertSame([], $last['result']['errors']);
        $this->assertSame(1, $last['result']['attachments']);
        $this->assertCount($count + 2, CsvTicketImporter::logLines());
        $this->assertNull(CsvTicketImporter::loadPending(), 'Fichiers en attente effacés à la fin.');
        $this->assertNull(JiraImporter::job(), 'L\'import Jira n\'est pas touché.');

        $importer = new CsvTicketImporter($this->source);
        $this->assertCount(1, (new ITILFollowup())->find(['itemtype' => Ticket::class, 'items_id' => $importer->findTicket("$p-2")]));
        $this->assertSame(1, countElementsInTable(\Document_Item::getTable(), [
            'itemtype' => Ticket::class,
            'items_id' => $importer->findTicket("$p-3"),
        ]));
        foreach ((new \Document_Item())->find(['itemtype' => Ticket::class, 'items_id' => $importer->findTicket("$p-3")]) as $link) {
            $this->created[] = [\Document::class, (int) $link['documents_id']];
        }
    }

    public function testProfilesAreSavedExportedAndImported(): void
    {
        $name = 'Modèle ' . $this->prefix;
        $settings = ['source_name' => $this->source, 'columns' => ['key' => 'Réf.', 'summary' => 'Sujet'], 'separator' => 'pipe'];
        $id = CsvImportProfile::save($name, $settings);
        $this->assertGreaterThan(0, $id);
        $this->assertSame($id, CsvImportProfile::save($name, $settings + ['separator_custom' => 'x']), 'Même nom : remplacé.');
        $this->assertSame('x', CsvImportProfile::get($id)['settings']['separator_custom'] ?? null);
        $this->assertSame(0, CsvImportProfile::save('  ', $settings));

        $json = (string) CsvImportProfile::export($id);
        CsvImportProfile::delete($id);
        $this->assertNull(CsvImportProfile::get($id));
        $imported = CsvImportProfile::parseExport($json);
        $this->assertNotNull($imported);
        $newId = CsvImportProfile::save($imported['name'], $imported['settings']);
        $this->assertSame(['key' => 'Réf.', 'summary' => 'Sujet'], CsvImportProfile::get($newId)['settings']['columns'] ?? null);
    }
}
