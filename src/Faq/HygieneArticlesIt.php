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
 * Issue #321: end-user security good-practice articles, Italian translation of HygieneArticlesFr (same order).
 */
final class HygieneArticlesIt
{
    public const ARTICLES = [
        [
            'name' => 'Buone pratiche: scegliere una password robusta',
            'answer' => <<<'HTML'
                <p>Una password robusta è prima di tutto una password <strong>lunga</strong>: la lunghezza conta più dei caratteri complicati.</p>
                <h4>Il metodo più semplice: la passphrase</h4>
                <p>Unisca quattro o cinque parole senza alcun legame tra loro, ad esempio «giraffa-tamburo-temporale-limone». È facile da ricordare e molto difficile da indovinare. Eviti frasi celebri, testi di canzoni e tutto ciò che La riguarda (nomi, date di nascita, nome dell'azienda).</p>
                <h4>Le regole da ricordare</h4>
                <ul>
                  <li>almeno 14 caratteri, oppure quattro parole;</li>
                  <li>una password <strong>diversa per ogni servizio</strong>: se un sito viene violato, gli altri Suoi account restano protetti;</li>
                  <li>non riutilizzi mai la Sua password di lavoro su un sito personale.</li>
                </ul>
                <h4>Il gestore di password</h4>
                <p>Nessuno riesce a ricordare decine di password diverse. Un gestore di password le ricorda al posto Suo: Le resta da memorizzare una sola password principale. Chieda al supporto quale gestore è autorizzato in azienda.</p>
                HTML,
        ],
        [
            'name' => 'Buone pratiche: non condividere mai la propria password',
            'answer' => <<<'HTML'
                <p>La Sua password è personale: dimostra che è proprio Lei ad agire. Tutto ciò che viene fatto con il Suo account Le viene attribuito.</p>
                <h4>Nessuno ha bisogno della Sua password</h4>
                <ul>
                  <li><strong>Il supporto informatico non gliela chiederà mai</strong>, né per telefono, né per e-mail, né tramite messaggio. I tecnici dispongono di strumenti propri per intervenire.</li>
                  <li>Un collega che ha bisogno di un file o di un accesso deve richiederlo per sé: non gli presti la Sua sessione.</li>
                  <li>Nemmeno il Suo responsabile: un'assenza si gestisce con una delega o una condivisione di cartella, non comunicando una password.</li>
                </ul>
                <h4>Se qualcuno gliela chiede</h4>
                <p>Si tratta probabilmente di un tentativo di truffa. Non risponda e lo segnali al supporto dal portale («Segnalare un incidente»).</p>
                <h4>Se l'ha comunicata per errore</h4>
                <p>La cambi immediatamente e avvisi il supporto: segnalarlo non è una colpa, è il riflesso giusto.</p>
                HTML,
        ],
        [
            'name' => 'Buone pratiche: bloccare la sessione appena si lascia la postazione',
            'answer' => <<<'HTML'
                <p>Un computer lasciato aperto, anche solo per due minuti, permette a chiunque di leggere i Suoi messaggi, inviare un'e-mail a Suo nome o copiare dei file.</p>
                <h4>Il riflesso giusto, ogni volta che si alza</h4>
                <ul>
                  <li>Su Windows: prema i tasti <strong>Windows + L</strong>.</li>
                  <li>Su Mac: prema <strong>Ctrl + Cmd + Q</strong>.</li>
                </ul>
                <p>Il Suo lavoro resta aperto: al ritorno basta digitare di nuovo la password.</p>
                <h4>Inoltre</h4>
                <ul>
                  <li>Non lasci il portatile incustodito in treno, al bar o in una sala riunioni.</li>
                  <li>Metta via i documenti cartacei riservati prima di andare via la sera.</li>
                  <li>Il blocco automatico dopo qualche minuto è una protezione, non un fastidio: non cerchi di disattivarlo.</li>
                </ul>
                HTML,
        ],
        [
            'name' => 'Buone pratiche: perché non è amministratore del Suo computer',
            'answer' => <<<'HTML'
                <p>Il Suo account non ha i diritti di amministrazione del computer. Non è una mancanza di fiducia: è il principio del <strong>privilegio minimo</strong>, una delle protezioni più efficaci.</p>
                <h4>A cosa serve</h4>
                <p>Un software malevolo agisce con i diritti della persona che lo ha aperto. Senza diritti di amministrazione, non può installarsi in profondità, né disattivare l'antivirus, né diffondersi facilmente. Un semplice clic sbagliato resta così limitato.</p>
                <h4>Deve installare un software o modificare un'impostazione</h4>
                <ul>
                  <li>Faccia la richiesta dal portale, nel catalogo dei servizi («Richiedere un servizio»).</li>
                  <li>Indichi il software o l'impostazione e il motivo per cui Le serve.</li>
                  <li>Il supporto lo installa per Lei, oppure Le concede un diritto <strong>temporaneo</strong> se giustificato.</li>
                </ul>
                <p>Non installi mai un software trovato su Internet per aggirare questo limite: è proprio ciò che cercano gli attaccanti.</p>
                HTML,
        ],
        [
            'name' => 'Buone pratiche: l\'autenticazione a più fattori',
            'answer' => <<<'HTML'
                <p>L'autenticazione a più fattori aggiunge una seconda prova alla Sua password: un codice ricevuto sul telefono, una notifica da approvare o una chiave di sicurezza. Anche se qualcuno Le ruba la password, non può accedere senza questo secondo elemento.</p>
                <h4>I riflessi giusti</h4>
                <ul>
                  <li>Non approvi <strong>mai</strong> una richiesta di accesso che non ha avviato Lei stesso, anche se si ripete: è il segno che qualcuno conosce la Sua password. La rifiuti, poi lo segnali al supporto.</li>
                  <li>Non comunichi mai a nessuno un codice ricevuto via SMS o tramite l'applicazione, nemmeno a chi si presenta come il supporto.</li>
                </ul>
                <h4>Ha perso o cambiato telefono</h4>
                <p>Avvisi il supporto <strong>il giorno stesso</strong>, dal portale o per telefono. Disattiverà il vecchio dispositivo e La aiuterà a registrare quello nuovo. Non aspetti di restare bloccato per segnalarlo.</p>
                HTML,
        ],
        [
            'name' => 'Buone pratiche: riconoscere un tentativo di phishing e segnalarlo',
            'answer' => <<<'HTML'
                <p>Il phishing è un messaggio che imita un mittente di fiducia per indurLa a cliccare su un link, aprire un allegato o fornire una password. È la principale porta d'ingresso degli attacchi.</p>
                <h4>I segnali che devono metterLa in allerta</h4>
                <ul>
                  <li>un senso di <strong>urgenza</strong>: «il Suo account sarà eliminato oggi», «pagamento in ritardo»;</li>
                  <li>un indirizzo del mittente che somiglia a quello vero, ma non del tutto;</li>
                  <li>un link il cui indirizzo reale (visibile passando il mouse sopra il link senza cliccare) non corrisponde al sito atteso;</li>
                  <li>una richiesta insolita: cambiare le coordinate bancarie (IBAN), acquistare carte regalo, comunicare un codice;</li>
                  <li>un allegato inatteso, anche da un contatto conosciuto.</li>
                </ul>
                <h4>Cosa fare</h4>
                <ul>
                  <li>Non clicchi e non apra l'allegato.</li>
                  <li>In caso di dubbio, contatti il mittente con un altro mezzo (il suo numero abituale, non quello indicato nel messaggio).</li>
                  <li>Segnali il messaggio al supporto dal portale («Segnalare un incidente»).</li>
                </ul>
                <p>Ha già cliccato o inserito la Sua password? Avvisi il supporto <strong>immediatamente</strong>: pochi minuti possono fare la differenza.</p>
                HTML,
        ],
        [
            'name' => 'Buone pratiche: chiavette USB e supporti rimovibili',
            'answer' => <<<'HTML'
                <p>Una chiavetta USB può contenere un software malevolo che si avvia non appena viene collegata. Inoltre si perde facilmente, insieme ai dati che contiene.</p>
                <h4>Le regole da seguire</h4>
                <ul>
                  <li>Non colleghi <strong>mai</strong> una chiavetta trovata o ricevuta senza averla richiesta: la consegni al supporto.</li>
                  <li>Utilizzi solo i supporti forniti o autorizzati dall'azienda.</li>
                  <li>Non copi dati aziendali su una chiavetta personale.</li>
                  <li>Per scambiare file, preferisca gli spazi di condivisione aziendali alla chiavetta USB.</li>
                </ul>
                <h4>Se perde una chiavetta o un disco</h4>
                <p>Lo segnali subito al supporto, precisando che cosa conteneva. Se vi si trovavano dati personali o riservati, l'azienda potrebbe avere obblighi di notifica entro tempi brevi.</p>
                HTML,
        ],
        [
            'name' => 'Buone pratiche: non rimandare gli aggiornamenti',
            'answer' => <<<'HTML'
                <p>Gli aggiornamenti correggono falle di sicurezza che gli attaccanti conoscono e talvolta sfruttano già il giorno dopo la loro pubblicazione. Un computer non aggiornato è una porta aperta.</p>
                <h4>Cosa Le chiediamo</h4>
                <ul>
                  <li>Quando il computer propone di riavviarsi per completare un aggiornamento, lo faccia appena possibile, ad esempio durante la pausa o a fine giornata.</li>
                  <li>Non rimandi un aggiornamento per più giorni di seguito.</li>
                  <li>Aggiorni anche il Suo telefono di lavoro e le sue applicazioni.</li>
                </ul>
                <h4>Se un aggiornamento crea problemi</h4>
                <p>Un software non funziona più, il computer è molto lento dopo il riavvio? Lo segnali al supporto dal portale invece di cercare di tornare indietro da solo.</p>
                HTML,
        ],
        [
            'name' => 'Buone pratiche: telelavoro, Wi-Fi pubblico e VPN',
            'answer' => <<<'HTML'
                <p>Fuori dagli uffici, le Sue comunicazioni passano attraverso reti che l'azienda non controlla. Alcune semplici regole le proteggono.</p>
                <h4>La VPN</h4>
                <p>La VPN crea un tunnel cifrato tra il Suo computer e la rete aziendale. <strong>La attivi sempre</strong> prima di accedere alle applicazioni e ai file dell'azienda.</p>
                <h4>Le reti Wi-Fi pubbliche</h4>
                <ul>
                  <li>In stazione, in albergo o al bar, un Wi-Fi aperto può essere intercettato o imitato da un falso punto di accesso.</li>
                  <li>Preferisca la condivisione della connessione del Suo telefono di lavoro.</li>
                  <li>Se usa un Wi-Fi pubblico, attivi la VPN prima di ogni altra cosa.</li>
                </ul>
                <h4>A casa e in trasferta</h4>
                <ul>
                  <li>Non lasci che un familiare o un conoscente usi il Suo computer di lavoro.</li>
                  <li>Attenzione a sguardi e orecchie indiscrete sui mezzi di trasporto e nei luoghi pubblici: può richiedere al supporto un filtro privacy per lo schermo.</li>
                </ul>
                HTML,
        ],
        [
            'name' => 'Buone pratiche: dove salvare i propri documenti',
            'answer' => <<<'HTML'
                <p>Un documento salvato solo sul Suo computer scompare insieme a esso in caso di guasto, furto o ransomware. Gli spazi aziendali, invece, sono sottoposti a backup.</p>
                <h4>Dove salvare</h4>
                <ul>
                  <li>i Suoi documenti di lavoro: nel Suo spazio personale aziendale o nelle cartelle condivise del Suo reparto;</li>
                  <li>non sul desktop né nella cartella «Download» del computer, che in genere non sono sottoposti a backup;</li>
                  <li>mai su un servizio di archiviazione personale (account online privato, posta elettronica personale).</li>
                </ul>
                <h4>Ha eliminato o sovrascritto un file per errore</h4>
                <p>Se si trovava in uno spazio sottoposto a backup, spesso il supporto può ripristinarlo. Faccia la richiesta rapidamente dal portale indicando il nome del file, la sua posizione e la data in cui era ancora corretto.</p>
                HTML,
        ],
        [
            'name' => 'Buone pratiche: i dati personali nel lavoro quotidiano (GDPR)',
            'answer' => <<<'HTML'
                <p>I dati personali sono tutte le informazioni che permettono di identificare una persona: nome, indirizzo, telefono, codice cliente, foto, dati sanitari o retributivi. Il GDPR impone di proteggerli.</p>
                <h4>Nel quotidiano</h4>
                <ul>
                  <li>Acceda solo ai dati di cui ha bisogno per il Suo lavoro.</li>
                  <li>Non invii file di clienti o dipendenti a un indirizzo personale, né tramite un servizio non autorizzato.</li>
                  <li>Verifichi i destinatari prima di inviare un'e-mail che contiene dati personali.</li>
                  <li>Non conservi copie «per sicurezza»: elimini ciò che non serve più.</li>
                  <li>Stampi il meno possibile e ritiri subito le Sue stampe.</li>
                </ul>
                <h4>In caso di errore</h4>
                <p>Un'e-mail inviata al destinatario sbagliato, un file condiviso troppo ampiamente, un computer smarrito: avvisi immediatamente il supporto. Una violazione di dati personali deve talvolta essere notificata all'autorità di controllo entro 72 ore.</p>
                HTML,
        ],
        [
            'name' => 'Buone pratiche: cosa fare in caso di incidente di sicurezza',
            'answer' => <<<'HTML'
                <p>Un incidente di sicurezza è, ad esempio: un computer insolitamente lento o che mostra finestre sconosciute, file diventati illeggibili, un messaggio che chiede un riscatto, un'e-mail inviata a Suo nome, la perdita o il furto di un computer o di un telefono.</p>
                <h4>I riflessi giusti</h4>
                <ul>
                  <li><strong>Avvisi immediatamente il supporto</strong>, dal portale («Segnalare un incidente») o per telefono se non può più usare il Suo computer.</li>
                  <li>Messaggio di riscatto o file illeggibili: <strong>scolleghi il cavo di rete e disattivi il Wi-Fi</strong>, ma non spenga il computer senza indicazioni del supporto.</li>
                  <li>Non paghi mai un riscatto e non risponda agli attaccanti.</li>
                  <li>Non cerchi di riparare da solo e non elimini nulla: le tracce aiutano a capire cosa è successo.</li>
                  <li>Annoti l'ora e ciò che ha visto; una foto dello schermo scattata con il telefono può essere utile.</li>
                </ul>
                <h4>Nessuna vergogna nel segnalare</h4>
                <p>Chiunque può cadere in una trappola. Ciò che conta è la rapidità: un incidente segnalato in pochi minuti causa molti meno danni di un incidente scoperto giorni dopo.</p>
                HTML,
        ],
    ];
}
