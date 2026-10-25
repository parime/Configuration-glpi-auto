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

namespace GlpiPlugin\Configurationglpiauto\Faq;

/**
 * Issue #321: end-user security good-practice articles, German translation of HygieneArticlesFr (same order).
 */
final class HygieneArticlesDe
{
    public const ARTICLES = [
        [
            'name' => 'Gute Praxis: ein sicheres Passwort wählen',
            'answer' => <<<'HTML'
                <p>Ein sicheres Passwort ist vor allem ein <strong>langes</strong> Passwort: Die Länge zählt mehr als komplizierte Sonderzeichen.</p>
                <h4>Die einfachste Methode: die Passphrase</h4>
                <p>Setzen Sie vier oder fünf Wörter zusammen, die nichts miteinander zu tun haben, zum Beispiel „Giraffe-Trommel-Gewitter-Zitrone“. Das ist leicht zu merken und sehr schwer zu erraten. Vermeiden Sie bekannte Zitate, Liedtexte und alles, was mit Ihnen zu tun hat (Vornamen, Geburtsdaten, Firmenname).</p>
                <h4>Die wichtigsten Regeln</h4>
                <ul>
                  <li>mindestens 14 Zeichen oder vier Wörter;</li>
                  <li>ein <strong>eigenes Passwort für jeden Dienst</strong>: Wird eine Website gehackt, bleiben Ihre anderen Konten geschützt;</li>
                  <li>verwenden Sie Ihr berufliches Passwort niemals auf einer privaten Website.</li>
                </ul>
                <h4>Der Passwort-Manager</h4>
                <p>Niemand kann sich Dutzende verschiedener Passwörter merken. Ein Passwort-Manager merkt sie sich für Sie, und Sie müssen sich nur noch ein einziges Hauptpasswort einprägen. Fragen Sie den Support, welcher Passwort-Manager im Unternehmen erlaubt ist.</p>
                HTML,
        ],
        [
            'name' => 'Gute Praxis: das eigene Passwort niemals weitergeben',
            'answer' => <<<'HTML'
                <p>Ihr Passwort ist persönlich: Es beweist, dass tatsächlich Sie selbst handeln. Alles, was mit Ihrem Konto getan wird, wird Ihnen zugerechnet.</p>
                <h4>Niemand braucht Ihr Passwort</h4>
                <ul>
                  <li><strong>Der IT-Support wird Sie niemals danach fragen</strong>, weder am Telefon noch per E-Mail oder Nachricht. Die Techniker haben ihre eigenen Werkzeuge, um tätig zu werden.</li>
                  <li>Wer im Kollegenkreis eine Datei oder einen Zugang braucht, muss diesen selbst beantragen: Überlassen Sie niemandem Ihre Sitzung.</li>
                  <li>Auch Ihre Führungskraft nicht: Eine Abwesenheit wird über eine Vertretung oder eine Ordnerfreigabe geregelt, nicht über ein weitergegebenes Passwort.</li>
                </ul>
                <h4>Wenn jemand Sie danach fragt</h4>
                <p>Dann handelt es sich wahrscheinlich um einen Betrugsversuch. Antworten Sie nicht und melden Sie es dem Support über das Portal („Einen Vorfall melden“).</p>
                <h4>Wenn Sie es versehentlich weitergegeben haben</h4>
                <p>Ändern Sie es sofort und informieren Sie den Support: Es ist kein Fehler, das zu melden, sondern genau das Richtige.</p>
                HTML,
        ],
        [
            'name' => 'Gute Praxis: die Sitzung sperren, sobald Sie Ihren Arbeitsplatz verlassen',
            'answer' => <<<'HTML'
                <p>Ein offen gelassener Computer, selbst für zwei Minuten, erlaubt es jedem, Ihre Nachrichten zu lesen, in Ihrem Namen eine E-Mail zu senden oder Dateien zu kopieren.</p>
                <h4>Die richtige Gewohnheit, jedes Mal, wenn Sie aufstehen</h4>
                <ul>
                  <li>Unter Windows: Drücken Sie <strong>Windows + L</strong>.</li>
                  <li>Auf dem Mac: Drücken Sie <strong>Ctrl + Cmd + Q</strong>.</li>
                </ul>
                <p>Ihre Arbeit bleibt geöffnet: Wenn Sie zurückkommen, geben Sie einfach Ihr Passwort erneut ein.</p>
                <h4>Außerdem</h4>
                <ul>
                  <li>Lassen Sie Ihren Laptop nicht unbeaufsichtigt im Zug, im Café oder im Besprechungsraum.</li>
                  <li>Räumen Sie vertrauliche Papierunterlagen weg, bevor Sie abends gehen.</li>
                  <li>Die automatische Sperre nach einigen Minuten ist ein Schutz, keine Schikane: Versuchen Sie nicht, sie abzuschalten.</li>
                </ul>
                HTML,
        ],
        [
            'name' => 'Gute Praxis: warum Sie keine Administratorrechte auf Ihrem Computer haben',
            'answer' => <<<'HTML'
                <p>Ihr Konto hat keine Administratorrechte auf Ihrem Computer. Das ist kein Misstrauen: Es ist das Prinzip der <strong>minimalen Rechte</strong>, einer der wirksamsten Schutzmechanismen.</p>
                <h4>Wozu das gut ist</h4>
                <p>Schadsoftware handelt mit den Rechten der Person, die sie geöffnet hat. Ohne Administratorrechte kann sie sich weder tief im System einnisten noch das Antivirenprogramm abschalten oder sich leicht verbreiten. So bleibt ein einfacher Fehlklick begrenzt.</p>
                <h4>Sie müssen eine Software installieren oder eine Einstellung ändern</h4>
                <ul>
                  <li>Stellen Sie die Anfrage über das Portal, im Servicekatalog („Einen Service anfragen“).</li>
                  <li>Geben Sie die Software oder die Einstellung an und warum Sie sie benötigen.</li>
                  <li>Der Support installiert sie für Sie oder gewährt Ihnen ein <strong>befristetes</strong> Recht, wenn es begründet ist.</li>
                </ul>
                <p>Installieren Sie niemals eine im Internet gefundene Software, um diese Einschränkung zu umgehen: Genau darauf haben es Angreifer abgesehen.</p>
                HTML,
        ],
        [
            'name' => 'Gute Praxis: die Mehr-Faktor-Authentifizierung',
            'answer' => <<<'HTML'
                <p>Die Mehr-Faktor-Authentifizierung ergänzt Ihr Passwort um einen zweiten Nachweis: einen Code auf Ihrem Telefon, eine Benachrichtigung, die Sie bestätigen, oder einen Sicherheitsschlüssel. Selbst wenn jemand Ihr Passwort stiehlt, kann er sich ohne dieses zweite Element nicht anmelden.</p>
                <h4>Die richtigen Gewohnheiten</h4>
                <ul>
                  <li>Bestätigen Sie <strong>niemals</strong> eine Anmeldeanfrage, die Sie nicht selbst ausgelöst haben, auch wenn sie sich wiederholt: Das ist ein Zeichen dafür, dass jemand Ihr Passwort kennt. Lehnen Sie ab und melden Sie es dann dem Support.</li>
                  <li>Geben Sie einen per SMS oder in der App erhaltenen Code niemals an andere weiter, auch nicht an jemanden, der sich als Support ausgibt.</li>
                </ul>
                <h4>Sie haben Ihr Telefon verloren oder gewechselt</h4>
                <p>Informieren Sie den Support <strong>noch am selben Tag</strong>, über das Portal oder telefonisch. Er deaktiviert das alte Gerät und hilft Ihnen, das neue zu registrieren. Warten Sie mit der Meldung nicht, bis Sie gesperrt sind.</p>
                HTML,
        ],
        [
            'name' => 'Gute Praxis: Phishing erkennen und melden',
            'answer' => <<<'HTML'
                <p>Phishing ist eine Nachricht, die einen vertrauenswürdigen Absender nachahmt, damit Sie auf einen Link klicken, einen Anhang öffnen oder ein Passwort preisgeben. Es ist das häufigste Einfallstor für Angriffe.</p>
                <h4>Anzeichen, die Sie stutzig machen sollten</h4>
                <ul>
                  <li>ein Gefühl von <strong>Dringlichkeit</strong>: „Ihr Konto wird heute gelöscht“, „Zahlung überfällig“;</li>
                  <li>eine Absenderadresse, die der echten ähnelt, aber nicht ganz gleich ist;</li>
                  <li>ein Link, dessen tatsächliche Adresse (sichtbar, wenn Sie mit der Maus darüberfahren, ohne zu klicken) nicht zur erwarteten Website passt;</li>
                  <li>eine ungewöhnliche Bitte: eine Bankverbindung ändern, Geschenkkarten kaufen, einen Code mitteilen;</li>
                  <li>ein unerwarteter Anhang, selbst von einem bekannten Kontakt.</li>
                </ul>
                <h4>Was tun</h4>
                <ul>
                  <li>Klicken Sie nicht und öffnen Sie den Anhang nicht.</li>
                  <li>Kontaktieren Sie im Zweifel den Absender auf anderem Weg (über seine gewohnte Nummer, nicht die aus der Nachricht).</li>
                  <li>Melden Sie die Nachricht dem Support über das Portal („Einen Vorfall melden“).</li>
                </ul>
                <p>Sie haben bereits geklickt oder Ihr Passwort eingegeben? Informieren Sie den Support <strong>sofort</strong>: Wenige Minuten können entscheidend sein.</p>
                HTML,
        ],
        [
            'name' => 'Gute Praxis: USB-Sticks und Wechseldatenträger',
            'answer' => <<<'HTML'
                <p>Ein USB-Stick kann Schadsoftware enthalten, die startet, sobald man ihn einsteckt. Außerdem geht er leicht verloren, samt den Daten darauf.</p>
                <h4>Die Regeln, die Sie befolgen sollten</h4>
                <ul>
                  <li>Stecken Sie <strong>niemals</strong> einen gefundenen oder unaufgefordert erhaltenen Stick ein: Geben Sie ihn beim Support ab.</li>
                  <li>Verwenden Sie nur Datenträger, die vom Unternehmen bereitgestellt oder erlaubt sind.</li>
                  <li>Kopieren Sie keine Unternehmensdaten auf einen privaten Stick.</li>
                  <li>Nutzen Sie zum Austausch von Dateien lieber die Freigabebereiche des Unternehmens als einen USB-Stick.</li>
                </ul>
                <h4>Wenn Sie einen Stick oder eine Festplatte verlieren</h4>
                <p>Melden Sie es sofort dem Support und geben Sie an, was darauf gespeichert war. Befanden sich darauf personenbezogene oder vertrauliche Daten, kann das Unternehmen verpflichtet sein, dies innerhalb kurzer Fristen zu melden.</p>
                HTML,
        ],
        [
            'name' => 'Gute Praxis: Updates nicht aufschieben',
            'answer' => <<<'HTML'
                <p>Updates schließen Sicherheitslücken, die Angreifer kennen und manchmal schon am Tag nach ihrer Veröffentlichung ausnutzen. Ein Computer, der nicht auf dem neuesten Stand ist, ist eine offene Tür.</p>
                <h4>Was wir von Ihnen erwarten</h4>
                <ul>
                  <li>Wenn Ihr Computer einen Neustart vorschlägt, um ein Update abzuschließen, führen Sie ihn so bald wie möglich durch, zum Beispiel in der Pause oder am Ende des Arbeitstages.</li>
                  <li>Schieben Sie ein Update nicht mehrere Tage hintereinander auf.</li>
                  <li>Halten Sie auch Ihr Diensthandy und dessen Apps auf dem neuesten Stand.</li>
                </ul>
                <h4>Wenn ein Update Probleme verursacht</h4>
                <p>Eine Software funktioniert nicht mehr oder der Computer ist nach dem Neustart sehr langsam? Melden Sie es dem Support über das Portal, statt selbst zu versuchen, das Update rückgängig zu machen.</p>
                HTML,
        ],
        [
            'name' => 'Gute Praxis: Homeoffice, öffentliches WLAN und VPN',
            'answer' => <<<'HTML'
                <p>Außerhalb der Firmenräume laufen Ihre Verbindungen über Netze, die das Unternehmen nicht kontrolliert. Einige einfache Regeln schützen sie.</p>
                <h4>Das VPN</h4>
                <p>Das VPN baut einen verschlüsselten Tunnel zwischen Ihrem Computer und dem Unternehmensnetz auf. <strong>Verbinden Sie es jedes Mal</strong>, bevor Sie auf Anwendungen und Dateien des Unternehmens zugreifen.</p>
                <h4>Öffentliche WLAN-Netze</h4>
                <ul>
                  <li>Am Bahnhof, im Hotel oder im Café kann ein offenes WLAN abgehört oder durch einen gefälschten Zugangspunkt nachgeahmt werden.</li>
                  <li>Nutzen Sie lieber den Hotspot Ihres Diensthandys.</li>
                  <li>Wenn Sie ein öffentliches WLAN nutzen, aktivieren Sie zuerst das VPN, bevor Sie irgendetwas anderes tun.</li>
                </ul>
                <h4>Zu Hause und unterwegs</h4>
                <ul>
                  <li>Lassen Sie keine Angehörigen Ihren Arbeitscomputer benutzen.</li>
                  <li>Achten Sie in Verkehrsmitteln und an öffentlichen Orten auf neugierige Blicke und Ohren: Einen Blickschutzfilter für den Bildschirm können Sie beim Support anfragen.</li>
                </ul>
                HTML,
        ],
        [
            'name' => 'Gute Praxis: wo Sie Ihre Dokumente speichern',
            'answer' => <<<'HTML'
                <p>Ein Dokument, das nur auf Ihrem Computer gespeichert ist, geht bei einem Defekt, einem Diebstahl oder einer Ransomware mit ihm verloren. Die Speicherbereiche des Unternehmens dagegen werden gesichert.</p>
                <h4>Wo speichern</h4>
                <ul>
                  <li>Ihre Arbeitsdokumente: in Ihrem persönlichen Bereich des Unternehmens oder in den freigegebenen Ordnern Ihrer Abteilung;</li>
                  <li>nicht auf dem Desktop und nicht im Ordner „Downloads“ Ihres Computers, die in der Regel nicht gesichert werden;</li>
                  <li>niemals bei einem privaten Speicherdienst (privates Online-Konto, private E-Mail).</li>
                </ul>
                <h4>Sie haben eine Datei versehentlich gelöscht oder überschrieben</h4>
                <p>Lag sie in einem gesicherten Bereich, kann der Support sie oft wiederherstellen. Stellen Sie die Anfrage schnell über das Portal und geben Sie den Dateinamen, ihren Speicherort und das Datum an, an dem sie noch korrekt war.</p>
                HTML,
        ],
        [
            'name' => 'Gute Praxis: personenbezogene Daten im Alltag (DSGVO)',
            'answer' => <<<'HTML'
                <p>Personenbezogene Daten sind alle Informationen, mit denen sich eine Person identifizieren lässt: Name, Adresse, Telefonnummer, Kundennummer, Foto, Gesundheits- oder Gehaltsdaten. Die DSGVO schreibt vor, sie zu schützen.</p>
                <h4>Im Alltag</h4>
                <ul>
                  <li>Greifen Sie nur auf die Daten zu, die Sie für Ihre Arbeit benötigen.</li>
                  <li>Senden Sie keine Kunden- oder Mitarbeiterdateien an eine private Adresse oder über einen nicht erlaubten Dienst.</li>
                  <li>Prüfen Sie die Empfänger, bevor Sie eine E-Mail mit personenbezogenen Daten senden.</li>
                  <li>Behalten Sie keine Kopien „für alle Fälle“: Löschen Sie, was nicht mehr benötigt wird.</li>
                  <li>Drucken Sie so wenig wie möglich und holen Sie Ihre Ausdrucke sofort ab.</li>
                </ul>
                <h4>Wenn ein Fehler passiert</h4>
                <p>Eine E-Mail an den falschen Empfänger, eine zu weit freigegebene Datei, ein verlorener Computer: Informieren Sie sofort den Support. Eine Datenpanne mit personenbezogenen Daten muss unter Umständen innerhalb von 72 Stunden der Aufsichtsbehörde gemeldet werden.</p>
                HTML,
        ],
        [
            'name' => 'Gute Praxis: was tun bei einem Sicherheitsvorfall',
            'answer' => <<<'HTML'
                <p>Ein Sicherheitsvorfall ist zum Beispiel: ein Computer, der ungewöhnlich langsam ist oder unbekannte Fenster anzeigt, Dateien, die nicht mehr lesbar sind, eine Nachricht mit Lösegeldforderung, eine in Ihrem Namen gesendete E-Mail, der Verlust oder Diebstahl eines Computers oder Telefons.</p>
                <h4>Die richtigen Gewohnheiten</h4>
                <ul>
                  <li><strong>Informieren Sie sofort den Support</strong>, über das Portal („Einen Vorfall melden“) oder telefonisch, wenn Sie Ihren Computer nicht mehr nutzen können.</li>
                  <li>Lösegeldforderung oder unlesbare Dateien: <strong>Ziehen Sie das Netzwerkkabel ab und schalten Sie das WLAN aus</strong>, aber schalten Sie den Computer nicht ohne Anweisung des Supports aus.</li>
                  <li>Zahlen Sie niemals ein Lösegeld und antworten Sie den Angreifern nicht.</li>
                  <li>Versuchen Sie nicht, selbst etwas zu reparieren, und löschen Sie nichts: Die Spuren helfen zu verstehen, was passiert ist.</li>
                  <li>Notieren Sie die Uhrzeit und was Sie gesehen haben; ein mit Ihrem Telefon aufgenommenes Foto des Bildschirms kann helfen.</li>
                </ul>
                <h4>Keine Scheu vor dem Melden</h4>
                <p>Jeder kann in eine Falle tappen. Entscheidend ist die Schnelligkeit: Ein innerhalb weniger Minuten gemeldeter Vorfall richtet viel weniger Schaden an als einer, der erst Tage später entdeckt wird.</p>
                HTML,
        ],
    ];
}
