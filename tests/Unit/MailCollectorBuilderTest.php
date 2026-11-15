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

use GlpiPlugin\Configurationglpiauto\Blueprint\BlueprintSerializer;
use GlpiPlugin\Configurationglpiauto\MailCollectorBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Issue #429 : normalisation des boîtes saisies, domaines autorisés et expressions des règles de
 * refus (fonctions pures, sans GLPI). Serveurs et adresses fictifs uniquement.
 */
final class MailCollectorBuilderTest extends TestCase
{
    public function testMailboxesAreNormalizedAndThePasswordIsNeverKept(): void
    {
        $clean = MailCollectorBuilder::sanitizeMailboxes([
            ['address' => ' Support@Exemple.TEST ', 'server' => 'IMAP.exemple.test', 'passwd' => 'MotDePasseFictif429', 'protocol' => 'imap', 'security' => 'ssl', 'port' => '', 'activate' => '0'],
            ['address' => 'support@exemple.test', 'server' => 'imap.exemple.test'],
            ['address' => 'pas-une-adresse', 'server' => 'imap.exemple.test'],
            ['address' => 'securite@exemple.test', 'server' => 'pop.exemple.test; rm -rf', 'protocol' => 'pop', 'security' => 'none', 'folder' => 'Archives', 'filesize_max_mb' => 500, 'activate' => '1'],
            'pas un tableau',
        ]);

        $this->assertCount(2, $clean, 'doublon, adresse invalide et ligne malformée ignorés');
        $this->assertSame('support@exemple.test', $clean[0]['address']);
        $this->assertSame('imap.exemple.test', $clean[0]['server']);
        $this->assertSame(993, $clean[0]['port']);
        $this->assertSame('INBOX', $clean[0]['folder']);
        $this->assertSame('support@exemple.test', $clean[0]['login'], 'identifiant par défaut : l\'adresse');
        $this->assertFalse($clean[0]['activate']);
        $this->assertArrayNotHasKey('passwd', $clean[0]);
        $this->assertStringNotContainsString('MotDePasseFictif429', (string) json_encode($clean));

        $this->assertSame('', $clean[1]['server'], 'nom de serveur invalide refusé');
        $this->assertSame(110, $clean[1]['port']);
        $this->assertSame('', $clean[1]['folder'], 'POP3 : pas de dossier');
        $this->assertSame(99, $clean[1]['filesize_max_mb']);
        $this->assertTrue($clean[1]['activate']);
    }

    public function testAJsonStringFromAnImportedProfileIsAccepted(): void
    {
        $json = (string) json_encode([['address' => 'facturation@exemple.test', 'server' => 'imap.exemple.test', 'security' => 'tls']]);

        $clean = MailCollectorBuilder::sanitizeMailboxes($json);

        $this->assertSame(143, $clean[0]['port']);
        $this->assertSame([], MailCollectorBuilder::sanitizeMailboxes('{pas du json'));
    }

    public function testTheListIsCapped(): void
    {
        $rows = [];
        for ($i = 0; $i < 30; $i++) {
            $rows[] = ['address' => 'boite' . $i . '@exemple.test', 'server' => 'imap.exemple.test'];
        }

        $this->assertCount(MailCollectorBuilder::MAX_MAILBOXES, MailCollectorBuilder::sanitizeMailboxes($rows));
    }

    public function testAllowedDomainsAreParsedAndMatchedIncludingSubdomains(): void
    {
        $domains = MailCollectorBuilder::parseDomains('exemple.test, @Filiale.Exemple.test ; pas_un_domaine exemple.test');
        $this->assertSame(['exemple.test', 'filiale.exemple.test'], $domains);

        $regex = MailCollectorBuilder::allowedDomainsRegex(['exemple.test']);
        $this->assertSame(1, preg_match($regex, 'utilisateur@exemple.test'));
        $this->assertSame(1, preg_match($regex, 'utilisateur@lyon.exemple.test'));
        $this->assertSame(0, preg_match($regex, 'utilisateur@exemple.test.autre.test'));
        $this->assertSame(0, preg_match($regex, 'utilisateur@autreexemple.test'));
    }

    public function testOutOfOfficeAndBounceExpressionsDoNotCatchOrdinaryMessages(): void
    {
        foreach (['Réponse automatique : Imprimante', 'Out of Office: printer', 'Automatic reply: VPN', 'Abwesenheitsnotiz: Drucker', 'Risposta automatica: stampante'] as $subject) {
            $this->assertSame(1, preg_match(MailCollectorBuilder::OUT_OF_OFFICE_SUBJECT_REGEX, $subject), $subject);
        }
        foreach (['Absent demain, mon poste ne démarre plus', 'RE: réponse automatique du serveur à corriger', 'Imprimante en panne'] as $subject) {
            $this->assertSame(0, preg_match(MailCollectorBuilder::OUT_OF_OFFICE_SUBJECT_REGEX, $subject), $subject);
        }

        $this->assertSame(1, preg_match(MailCollectorBuilder::BOUNCE_FROM_REGEX, 'MAILER-DAEMON@exemple.test'));
        $this->assertSame(1, preg_match(MailCollectorBuilder::BOUNCE_FROM_REGEX, 'postmaster@exemple.test'));
        $this->assertSame(0, preg_match(MailCollectorBuilder::BOUNCE_FROM_REGEX, 'utilisateur@exemple.test'));
        $this->assertSame(1, preg_match(MailCollectorBuilder::BOUNCE_SUBJECT_REGEX, 'Undeliverable: Imprimante'));
        $this->assertSame(0, preg_match(MailCollectorBuilder::BOUNCE_SUBJECT_REGEX, 'Imprimante non remise en service'));
    }

    public function testMailboxesRoundTripThroughAProfileExportWithoutPassword(): void
    {
        $stored = (string) json_encode(MailCollectorBuilder::sanitizeMailboxes([
            ['address' => 'support@exemple.test', 'server' => 'imap.exemple.test', 'passwd' => 'MotDePasseFictif429', 'entity' => 'Siège'],
        ]));

        $export = BlueprintSerializer::export(['mail_collectors' => $stored, 'mail_collectors_enabled' => 1], 'Profil fictif', '0.0.0');
        $this->assertIsArray($export['config']['mail_collectors'], 'exporté en JSON imbriqué, lisible');
        $this->assertStringNotContainsString('MotDePasseFictif429', (string) json_encode($export));

        $imported = BlueprintSerializer::import($export, ['mail_collectors' => '[]', 'mail_collectors_enabled' => 0]);
        $this->assertSame($stored, $imported['mail_collectors']);
        $this->assertSame(1, $imported['mail_collectors_enabled']);
    }
}
