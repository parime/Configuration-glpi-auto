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

namespace GlpiPlugin\Configurationglpiauto;

use Entity_KnowbaseItem;
use KnowbaseItem;
use KnowbaseItemTranslation;

/**
 * Turns on `kb_faq_enabled` into two real, ready-to-read `KnowbaseItem` FAQ articles (issues #143
 * and #144) — written for the *end user* browsing GLPI's self-service Helpdesk portal (the
 * `/Helpdesk` React app this plugin's other builders configure), not for the GLPI administrator
 * running this wizard. Deliberately zero plugin/product jargon and zero internal class/field
 * names in the article bodies themselves: a non-technical employee who has never used GLPI is the
 * target reader, confirmed against a real run of the portal (`/Helpdesk`, `/ServiceCatalog`,
 * `/front/helpdesk.faq.php` as the `post-only` demo profile) so the step-by-step wording matches
 * the actual menu labels ("Catalogue de services", "Signaler un incident", "Demander un service",
 * "Tickets en cours"/"Tickets résolus") rather than guessed ones.
 *
 * No static docs equivalent exists in this repo (`docs/TUTORIAL.md` is admin-facing, walking the
 * wizard itself, not the portal an end user lands on afterward) — this repo's own precedent for
 * *any* end-user-facing content is always to seed it as real GLPI data via a `Config`-gated
 * builder (`KnowbaseCategoryBuilder` for KB categories, `SolutionLibraryBuilder` for solution
 * templates...), never a markdown file nobody using the deployed instance would ever open. Seeding
 * real `KnowbaseItem` rows with `is_faq=1` means these two articles actually show up in the
 * instance's native FAQ (searchable/browsable from `/front/helpdesk.faq.php`) the moment the admin
 * runs this wizard, exactly like every other "content library" this plugin ships.
 *
 * Visibility: confirmed by reading GLPI 11.0.8's own `KnowbaseItem::getVisibilityCriteriaKB()`
 * that an FAQ row with *no* visibility link row at all is invisible to everyone except its own
 * author/a KB admin — `is_faq=1` alone is not enough, unlike what the admin-facing "add" screen
 * might suggest. An `Entity_KnowbaseItem` row scoped to entity 0 + `is_recursive=1` (same "root,
 * recursive" idiom every other builder in this plugin already uses for instance-wide visibility,
 * e.g. `KnowbaseCategoryBuilder`) is what actually makes the article visible from any entity.
 *
 * Translation: full 6-language translation of the article body (not just a short label), same
 * precedent as `SolutionLibraryBuilder`'s solution templates — the source string is long-form
 * prose, not a UI string, so it stays a plain PHP literal here rather than going through
 * `__()`/`locales/*.po` (confirmed against this repo's own locale-completeness CI check, which
 * only extracts `__()`/`_n()` calls). Stored via `KnowbaseItemTranslation` (GLPI's own per-language
 * KB content table, confirmed by reading `KnowbaseItem.php`/`KnowbaseItemTranslation.php` — a
 * completely different mechanism from `DropdownTranslation`, which only covers `CommonDropdown`
 * item names/content and does not apply to `KnowbaseItem`), not `Translations::applyContent()`
 * (scoped to `DropdownTranslation` by its own docblock).
 */
class FaqBuilder
{
    private const ARTICLES = [
        [
            'name' => 'Comment utiliser le catalogue de services pour faire une demande',
            'answer' => <<<'HTML'
                <p>Le <strong>catalogue de services</strong> est la vitrine du portail d'assistance&nbsp;: une liste de tout ce que vous pouvez demander (un accès, du matériel, une information RH, une réservation de salle...) sans avoir à écrire vous-même un ticket depuis zéro. Vous choisissez ce dont vous avez besoin dans une liste, vous répondez à quelques questions simples, et un ticket est créé automatiquement pour vous, déjà correctement classé.</p>
                <h4>1. Ouvrir le catalogue de services</h4>
                <p>Connectez-vous au portail GLPI avec votre compte habituel. Dans le menu en haut de la page, cliquez sur <strong>« Catalogue de services »</strong>.</p>
                <h4>2. Trouver le bon service</h4>
                <p>Le catalogue est organisé par thème, sous forme de vignettes&nbsp;: « IT &amp; SI », « Ressources Humaines », « Bâtiment &amp; Moyens Généraux »... Cliquez sur le thème qui correspond à votre besoin pour voir la liste des services disponibles, ou utilisez directement la barre de recherche en haut de la page si vous savez déjà ce que vous cherchez (par exemple&nbsp;: « clavier », « accès VPN », « congé »).</p>
                <h4>3. Remplir le formulaire</h4>
                <p>Chaque service ouvre un petit formulaire à remplir. Les champs marqués d'une étoile rouge (*) sont obligatoires, les autres sont facultatifs. Certains formulaires vous posent une question supplémentaire uniquement si votre réponse précédente le justifie&nbsp;: par exemple, un formulaire de demande de congé ne vous demandera un justificatif que si vous indiquez qu'il s'agit d'un arrêt maladie. Ne soyez pas surpris si le formulaire change légèrement sous vos yeux, c'est normal&nbsp;: il s'adapte à ce que vous avez déjà répondu pour ne vous poser que les questions utiles.</p>
                <p><em>Exemple&nbsp;:</em> pour une demande de licence logicielle, on vous demandera simplement le nom du logiciel concerné et le nombre de licences souhaité.</p>
                <h4>4. Envoyer la demande</h4>
                <p>Une fois le formulaire complété, cliquez sur le bouton d'envoi en bas de page. Un ticket est alors créé automatiquement, avec un titre et une catégorie déjà remplis pour vous&nbsp;: vous n'avez rien d'autre à faire.</p>
                <h4>5. Suivre votre demande</h4>
                <p>Pour voir où en est votre demande, cliquez sur « Tickets » dans le menu du haut. Vous y retrouvez toutes vos demandes en cours et déjà traitées. Vous recevrez aussi un e-mail à chaque étape importante (prise en charge, réponse du support, clôture).</p>
                HTML,
            'translations' => [
                'en_GB' => [
                    'name' => 'How to use the service catalog to make a request',
                    'answer' => <<<'HTML'
                        <p>The <strong>service catalog</strong> is the front page of the support portal: a list of everything you can request (an access, some equipment, HR information, a room booking...) without having to write a ticket from scratch yourself. You pick what you need from a list, answer a few simple questions, and a ticket is created for you automatically, already correctly categorised.</p>
                        <h4>1. Open the service catalog</h4>
                        <p>Log in to the GLPI portal with your usual account. In the menu at the top of the page, click <strong>"Service catalog"</strong>.</p>
                        <h4>2. Find the right service</h4>
                        <p>The catalog is organised by topic, shown as tiles: "IT &amp; Information Systems", "Human Resources", "Facilities &amp; General Services"... Click the topic that matches your need to see the list of available services, or use the search bar at the top of the page directly if you already know what you are looking for (for example: "keyboard", "VPN access", "leave").</p>
                        <h4>3. Fill in the form</h4>
                        <p>Each service opens a short form to fill in. Fields marked with a red star (*) are required, the others are optional. Some forms ask you an extra question only if your previous answer calls for it: for example, a leave request form will only ask for supporting documents if you say it is sick leave. Do not be surprised if the form changes slightly as you go, that is normal: it adapts to what you have already answered so it only asks the questions that matter.</p>
                        <p><em>Example:</em> for a software licence request, you will simply be asked for the name of the software and the number of licences needed.</p>
                        <h4>4. Send the request</h4>
                        <p>Once the form is complete, click the submit button at the bottom of the page. A ticket is then created automatically, with a title and a category already filled in for you: there is nothing else to do.</p>
                        <h4>5. Track your request</h4>
                        <p>To see how your request is progressing, click "Tickets" in the top menu. You will find all your ongoing and already handled requests there. You will also receive an email at every important step (taken in charge, reply from support, closure).</p>
                        HTML,
                ],
                'de_DE' => [
                    'name' => 'So nutzen Sie den Servicekatalog, um eine Anfrage zu stellen',
                    'answer' => <<<'HTML'
                        <p>Der <strong>Servicekatalog</strong> ist die Startseite des Support-Portals: eine Liste von allem, was Sie anfragen können (einen Zugang, Ausrüstung, eine Information der Personalabteilung, eine Raumbuchung...), ohne selbst ein Ticket von Grund auf schreiben zu müssen. Sie wählen aus einer Liste aus, was Sie brauchen, beantworten ein paar einfache Fragen, und ein Ticket wird automatisch für Sie erstellt, bereits richtig eingeordnet.</p>
                        <h4>1. Den Servicekatalog öffnen</h4>
                        <p>Melden Sie sich mit Ihrem gewohnten Konto im GLPI-Portal an. Klicken Sie im Menü oben auf der Seite auf <strong>„Servicekatalog“</strong>.</p>
                        <h4>2. Den richtigen Service finden</h4>
                        <p>Der Katalog ist nach Themen als Kacheln geordnet: „IT &amp; Informationssysteme“, „Personalwesen“, „Gebäude &amp; Allgemeine Dienste“... Klicken Sie auf das Thema, das zu Ihrem Anliegen passt, um die verfügbaren Services zu sehen, oder nutzen Sie direkt die Suchleiste oben auf der Seite, wenn Sie bereits wissen, wonach Sie suchen (zum Beispiel: „Tastatur“, „VPN-Zugang“, „Urlaub“).</p>
                        <h4>3. Das Formular ausfüllen</h4>
                        <p>Jeder Service öffnet ein kurzes Formular. Mit einem roten Sternchen (*) markierte Felder sind Pflichtfelder, die anderen sind optional. Manche Formulare stellen Ihnen eine zusätzliche Frage nur dann, wenn Ihre vorherige Antwort dies erfordert: zum Beispiel fragt ein Urlaubsantrag nur dann nach einem Nachweis, wenn Sie angeben, dass es sich um eine Krankschreibung handelt. Wundern Sie sich nicht, wenn sich das Formular dabei leicht verändert, das ist normal: Es passt sich an das an, was Sie bereits beantwortet haben, damit nur die relevanten Fragen gestellt werden.</p>
                        <p><em>Beispiel:</em> Bei einer Anfrage für eine Softwarelizenz werden Sie einfach nach dem Namen der Software und der gewünschten Anzahl an Lizenzen gefragt.</p>
                        <h4>4. Die Anfrage senden</h4>
                        <p>Sobald das Formular ausgefüllt ist, klicken Sie unten auf der Seite auf die Sende-Schaltfläche. Ein Ticket wird dann automatisch erstellt, mit einem Titel und einer Kategorie, die bereits für Sie ausgefüllt sind: Sie müssen nichts weiter tun.</p>
                        <h4>5. Ihre Anfrage verfolgen</h4>
                        <p>Um zu sehen, wie weit Ihre Anfrage ist, klicken Sie im oberen Menü auf „Tickets“. Dort finden Sie alle Ihre laufenden und bereits bearbeiteten Anfragen. Sie erhalten außerdem bei jedem wichtigen Schritt eine E-Mail (Übernahme, Antwort des Supports, Abschluss).</p>
                        HTML,
                ],
                'it_IT' => [
                    'name' => "Come usare il catalogo dei servizi per fare una richiesta",
                    'answer' => <<<'HTML'
                        <p>Il <strong>catalogo dei servizi</strong> è la vetrina del portale di assistenza: un elenco di tutto ciò che potete richiedere (un accesso, del materiale, un'informazione delle Risorse Umane, la prenotazione di una sala...) senza dover scrivere voi stessi un ticket da zero. Scegliete ciò di cui avete bisogno da un elenco, rispondete a qualche semplice domanda e un ticket viene creato automaticamente per voi, già correttamente classificato.</p>
                        <h4>1. Aprire il catalogo dei servizi</h4>
                        <p>Accedete al portale GLPI con il vostro account abituale. Nel menu in alto nella pagina, fate clic su <strong>«Catalogo dei servizi»</strong>.</p>
                        <h4>2. Trovare il servizio giusto</h4>
                        <p>Il catalogo è organizzato per argomento, sotto forma di riquadri: «IT e Sistemi Informativi», «Risorse Umane», «Edificio e Servizi Generali»... Fate clic sull'argomento che corrisponde alla vostra esigenza per vedere l'elenco dei servizi disponibili, oppure usate direttamente la barra di ricerca in alto nella pagina se sapete già cosa state cercando (ad esempio: «tastiera», «accesso VPN», «ferie»).</p>
                        <h4>3. Compilare il modulo</h4>
                        <p>Ogni servizio apre un breve modulo da compilare. I campi contrassegnati da un asterisco rosso (*) sono obbligatori, gli altri sono facoltativi. Alcuni moduli pongono una domanda aggiuntiva solo se la vostra risposta precedente lo giustifica: ad esempio, un modulo di richiesta ferie chiederà un giustificativo solo se indicate che si tratta di un'assenza per malattia. Non stupitevi se il modulo cambia leggermente man mano: è normale, si adatta a quanto avete già risposto per porvi solo le domande utili.</p>
                        <p><em>Esempio:</em> per una richiesta di licenza software, vi verrà semplicemente chiesto il nome del software interessato e il numero di licenze desiderato.</p>
                        <h4>4. Inviare la richiesta</h4>
                        <p>Una volta completato il modulo, fate clic sul pulsante di invio in fondo alla pagina. Un ticket viene quindi creato automaticamente, con un titolo e una categoria già compilati per voi: non dovete fare altro.</p>
                        <h4>5. Seguire la vostra richiesta</h4>
                        <p>Per vedere a che punto è la vostra richiesta, fate clic su «Ticket» nel menu in alto. Vi ritroverete tutte le vostre richieste in corso e già gestite. Riceverete anche un'e-mail a ogni passaggio importante (presa in carico, risposta del supporto, chiusura).</p>
                        HTML,
                ],
                'es_ES' => [
                    'name' => 'Cómo usar el catálogo de servicios para hacer una solicitud',
                    'answer' => <<<'HTML'
                        <p>El <strong>catálogo de servicios</strong> es el escaparate del portal de asistencia: una lista de todo lo que puede solicitar (un acceso, material, información de Recursos Humanos, la reserva de una sala...) sin tener que escribir usted mismo un ticket desde cero. Elige lo que necesita de una lista, responde a algunas preguntas sencillas, y se crea automáticamente un ticket para usted, ya correctamente clasificado.</p>
                        <h4>1. Abrir el catálogo de servicios</h4>
                        <p>Inicie sesión en el portal de GLPI con su cuenta habitual. En el menú de la parte superior de la página, haga clic en <strong>«Catálogo de servicios»</strong>.</p>
                        <h4>2. Encontrar el servicio adecuado</h4>
                        <p>El catálogo está organizado por temas, en forma de tarjetas: «TI y Sistemas de Información», «Recursos Humanos», «Edificio y Servicios Generales»... Haga clic en el tema que corresponda a su necesidad para ver la lista de servicios disponibles, o utilice directamente la barra de búsqueda en la parte superior de la página si ya sabe lo que busca (por ejemplo: «teclado», «acceso VPN», «vacaciones»).</p>
                        <h4>3. Rellenar el formulario</h4>
                        <p>Cada servicio abre un breve formulario para rellenar. Los campos marcados con un asterisco rojo (*) son obligatorios, los demás son opcionales. Algunos formularios le hacen una pregunta adicional solo si su respuesta anterior lo justifica: por ejemplo, un formulario de solicitud de vacaciones solo pedirá un justificante si indica que se trata de una baja médica. No se sorprenda si el formulario cambia ligeramente mientras lo rellena, es normal: se adapta a lo que ya ha respondido para plantearle solo las preguntas útiles.</p>
                        <p><em>Ejemplo:</em> para una solicitud de licencia de software, simplemente se le pedirá el nombre del software en cuestión y el número de licencias deseado.</p>
                        <h4>4. Enviar la solicitud</h4>
                        <p>Una vez completado el formulario, haga clic en el botón de envío al final de la página. Se crea entonces un ticket automáticamente, con un título y una categoría ya rellenados para usted: no tiene que hacer nada más.</p>
                        <h4>5. Seguir su solicitud</h4>
                        <p>Para ver en qué punto está su solicitud, haga clic en «Tickets» en el menú superior. Allí encontrará todas sus solicitudes en curso y ya tramitadas. También recibirá un correo electrónico en cada etapa importante (asignación, respuesta del soporte, cierre).</p>
                        HTML,
                ],
                'pt_BR' => [
                    'name' => 'Como usar o catálogo de serviços para fazer uma solicitação',
                    'answer' => <<<'HTML'
                        <p>O <strong>catálogo de serviços</strong> é a vitrine do portal de atendimento: uma lista de tudo o que você pode solicitar (um acesso, um equipamento, uma informação de RH, a reserva de uma sala...) sem precisar escrever um chamado do zero. Você escolhe o que precisa em uma lista, responde a algumas perguntas simples, e um chamado é criado automaticamente para você, já corretamente classificado.</p>
                        <h4>1. Abrir o catálogo de serviços</h4>
                        <p>Faça login no portal do GLPI com sua conta habitual. No menu no topo da página, clique em <strong>"Catálogo de serviços"</strong>.</p>
                        <h4>2. Encontrar o serviço certo</h4>
                        <p>O catálogo é organizado por tema, em forma de blocos: "TI e Sistemas de Informação", "Recursos Humanos", "Predial e Serviços Gerais"... Clique no tema que corresponde à sua necessidade para ver a lista de serviços disponíveis, ou use diretamente a barra de busca no topo da página se já souber o que está procurando (por exemplo: "teclado", "acesso VPN", "férias").</p>
                        <h4>3. Preencher o formulário</h4>
                        <p>Cada serviço abre um pequeno formulário para preencher. Os campos marcados com um asterisco vermelho (*) são obrigatórios, os demais são opcionais. Alguns formulários fazem uma pergunta adicional apenas se sua resposta anterior justificar: por exemplo, um formulário de solicitação de férias só vai pedir um atestado se você indicar que se trata de uma licença médica. Não se surpreenda se o formulário mudar um pouco à sua frente, isso é normal: ele se adapta ao que você já respondeu para fazer apenas as perguntas necessárias.</p>
                        <p><em>Exemplo:</em> para uma solicitação de licença de software, será perguntado apenas o nome do software desejado e a quantidade de licenças.</p>
                        <h4>4. Enviar a solicitação</h4>
                        <p>Depois de preencher o formulário, clique no botão de envio no final da página. Um chamado é então criado automaticamente, com um título e uma categoria já preenchidos para você: não é preciso fazer mais nada.</p>
                        <h4>5. Acompanhar sua solicitação</h4>
                        <p>Para ver como está o andamento da sua solicitação, clique em "Chamados" no menu superior. Lá você encontra todas as suas solicitações em andamento e já tratadas. Você também receberá um e-mail a cada etapa importante (atendimento iniciado, resposta do suporte, encerramento).</p>
                        HTML,
                ],
            ],
        ],
        [
            'name' => "Signaler un incident ou faire une demande : quelle différence et comment s'y prendre",
            'answer' => <<<'HTML'
                <p>Dans GLPI, chaque ticket appartient à l'un de ces deux types&nbsp;:</p>
                <ul>
                <li><strong>Un incident</strong>&nbsp;: quelque chose qui fonctionnait avant et qui ne fonctionne plus. Exemple&nbsp;: «&nbsp;mon écran ne s'allume plus&nbsp;», «&nbsp;je n'arrive plus à me connecter à mon compte&nbsp;», «&nbsp;l'imprimante du 2<sup>e</sup> étage est en panne&nbsp;».</li>
                <li><strong>Une demande</strong>&nbsp;: quelque chose dont vous avez besoin, mais qui n'est pas une panne. Exemple&nbsp;: «&nbsp;j'ai besoin d'un nouveau clavier&nbsp;», «&nbsp;je voudrais accéder à ce dossier partagé&nbsp;», «&nbsp;je pars en congé la semaine prochaine&nbsp;».</li>
                </ul>
                <p>En résumé&nbsp;: si quelque chose est cassé, c'est un incident. Si vous voulez quelque chose de nouveau, c'est une demande. Rassurez-vous, si vous vous trompez, le support pourra toujours reclasser votre ticket&nbsp;: l'essentiel est de décrire clairement votre besoin.</p>
                <h4>1. Ouvrir le portail d'assistance</h4>
                <p>Connectez-vous à GLPI avec votre compte habituel. Vous arrivez sur la page d'accueil du portail, qui vous demande «&nbsp;Besoin d'aide&nbsp;? Une question&nbsp;?&nbsp;».</p>
                <h4>2. Choisir le bon point d'entrée</h4>
                <p>Deux vignettes permettent de commencer&nbsp;:</p>
                <ul>
                <li><strong>«&nbsp;Signaler un incident&nbsp;»</strong> si quelque chose ne fonctionne pas&nbsp;;</li>
                <li><strong>«&nbsp;Demander un service&nbsp;»</strong> (ou le <strong>catalogue de services</strong>) si vous avez besoin de quelque chose de nouveau.</li>
                </ul>
                <h4>3. Choisir la bonne catégorie</h4>
                <p>On vous demandera de préciser le domaine concerné (informatique, bâtiment, ressources humaines...). Choisissez celui qui correspond le mieux à votre problème&nbsp;: cela permet à votre demande d'arriver directement à la bonne équipe, sans détour.</p>
                <h4>4. Écrire une description claire</h4>
                <p>Prenez quelques secondes pour donner des informations utiles, cela permet au support de résoudre votre problème beaucoup plus vite&nbsp;:</p>
                <ul>
                <li>le nom ou le numéro de l'appareil concerné, si vous le connaissez (il est souvent écrit sur une étiquette)&nbsp;;</li>
                <li>depuis quand le problème existe («&nbsp;depuis ce matin&nbsp;», «&nbsp;depuis hier après-midi&nbsp;»)&nbsp;;</li>
                <li>ce que vous avez déjà essayé, s'il y a lieu&nbsp;;</li>
                <li>une capture d'écran si un message d'erreur s'affiche&nbsp;: elle en dit souvent plus qu'une longue explication.</li>
                </ul>
                <p>Une description comme «&nbsp;ça ne marche pas&nbsp;» oblige le support à revenir vers vous pour en savoir plus, ce qui retarde la résolution. Une description comme «&nbsp;mon écran externe ne s'allume plus depuis ce matin, le câble est bien branché&nbsp;» permet d'agir tout de suite.</p>
                <h4>5. Envoyer et suivre votre ticket</h4>
                <p>Une fois votre ticket envoyé, vous pouvez suivre son avancement à tout moment depuis le menu «&nbsp;Tickets&nbsp;»&nbsp;: onglet «&nbsp;Tickets en cours&nbsp;» pour les demandes non terminées, «&nbsp;Tickets résolus&nbsp;» pour l'historique. Vous recevrez aussi un e-mail à chaque mise à jour importante (prise en charge par un technicien, demande de précision, résolution). Vous pouvez répondre directement sur le ticket si vous avez une information complémentaire à ajouter.</p>
                HTML,
            'translations' => [
                'en_GB' => [
                    'name' => 'Reporting an issue or making a request: the difference, and how to do it',
                    'answer' => <<<'HTML'
                        <p>In GLPI, every ticket belongs to one of these two types:</p>
                        <ul>
                        <li><strong>An incident</strong>: something that used to work and no longer does. Example: "my screen will not turn on", "I can no longer log into my account", "the printer on the 2nd floor is broken".</li>
                        <li><strong>A request</strong>: something you need, but which is not a breakdown. Example: "I need a new keyboard", "I would like access to this shared folder", "I am going on leave next week".</li>
                        </ul>
                        <p>In short: if something is broken, it is an incident. If you want something new, it is a request. Do not worry if you get it wrong, support can always reclassify your ticket: what matters most is describing your need clearly.</p>
                        <h4>1. Open the support portal</h4>
                        <p>Log in to GLPI with your usual account. You land on the portal's home page, which asks you "How can we help you?".</p>
                        <h4>2. Choose the right starting point</h4>
                        <p>Two tiles let you get started:</p>
                        <ul>
                        <li><strong>"Report an issue"</strong> if something is not working;</li>
                        <li><strong>"Request a service"</strong> (or the <strong>service catalog</strong>) if you need something new.</li>
                        </ul>
                        <h4>3. Choose the right category</h4>
                        <p>You will be asked to specify the area concerned (IT, facilities, human resources...). Pick the one that best matches your problem: this lets your request go straight to the right team, with no detour.</p>
                        <h4>4. Write a clear description</h4>
                        <p>Take a few seconds to give useful information, it lets support solve your problem much faster:</p>
                        <ul>
                        <li>the name or number of the device concerned, if you know it (it is often written on a label);</li>
                        <li>since when the problem has existed ("since this morning", "since yesterday afternoon");</li>
                        <li>what you have already tried, if anything;</li>
                        <li>a screenshot if an error message is shown: it often says more than a long explanation.</li>
                        </ul>
                        <p>A description such as "it does not work" forces support to come back to you for more details, which delays the resolution. A description such as "my external screen has not turned on since this morning, the cable is properly plugged in" allows action to be taken straight away.</p>
                        <h4>5. Send and track your ticket</h4>
                        <p>Once your ticket is sent, you can track its progress at any time from the "Tickets" menu: the "Ongoing tickets" tab for requests still in progress, "Solved tickets" for the history. You will also receive an email at every important update (taken in charge by a technician, request for more details, resolution). You can reply directly on the ticket if you have extra information to add.</p>
                        HTML,
                ],
                'de_DE' => [
                    'name' => 'Einen Vorfall melden oder eine Anfrage stellen: der Unterschied und die Vorgehensweise',
                    'answer' => <<<'HTML'
                        <p>In GLPI gehört jedes Ticket zu einem dieser beiden Typen:</p>
                        <ul>
                        <li><strong>Ein Vorfall (Störung)</strong>: etwas, das vorher funktioniert hat und jetzt nicht mehr funktioniert. Beispiel: „mein Bildschirm lässt sich nicht mehr einschalten“, „ich kann mich nicht mehr in mein Konto einloggen“, „der Drucker im 2. Stock ist defekt“.</li>
                        <li><strong>Eine Anfrage</strong>: etwas, das Sie brauchen, aber keine Störung ist. Beispiel: „ich brauche eine neue Tastatur“, „ich möchte Zugriff auf diesen freigegebenen Ordner“, „ich gehe nächste Woche in den Urlaub“.</li>
                        </ul>
                        <p>Kurz gesagt: Wenn etwas kaputt ist, ist es ein Vorfall. Wenn Sie etwas Neues möchten, ist es eine Anfrage. Keine Sorge, falls Sie sich irren, der Support kann Ihr Ticket jederzeit neu einordnen: Wichtig ist vor allem, dass Sie Ihr Anliegen klar beschreiben.</p>
                        <h4>1. Das Support-Portal öffnen</h4>
                        <p>Melden Sie sich mit Ihrem gewohnten Konto bei GLPI an. Sie gelangen auf die Startseite des Portals, die Sie fragt: „Wie können wir Ihnen helfen?“.</p>
                        <h4>2. Den richtigen Einstiegspunkt wählen</h4>
                        <p>Zwei Kacheln ermöglichen den Einstieg:</p>
                        <ul>
                        <li><strong>„Einen Vorfall melden“</strong>, wenn etwas nicht funktioniert;</li>
                        <li><strong>„Einen Service anfragen“</strong> (oder der <strong>Servicekatalog</strong>), wenn Sie etwas Neues brauchen.</li>
                        </ul>
                        <h4>3. Die richtige Kategorie wählen</h4>
                        <p>Sie werden gebeten, den betroffenen Bereich anzugeben (IT, Gebäude, Personalwesen...). Wählen Sie den Bereich, der am besten zu Ihrem Problem passt: So gelangt Ihre Anfrage direkt und ohne Umweg an das richtige Team.</p>
                        <h4>4. Eine klare Beschreibung schreiben</h4>
                        <p>Nehmen Sie sich ein paar Sekunden Zeit, um nützliche Informationen zu geben, das ermöglicht dem Support, Ihr Problem viel schneller zu lösen:</p>
                        <ul>
                        <li>den Namen oder die Nummer des betroffenen Geräts, falls bekannt (oft auf einem Etikett vermerkt);</li>
                        <li>seit wann das Problem besteht („seit heute Morgen“, „seit gestern Nachmittag“);</li>
                        <li>was Sie gegebenenfalls bereits versucht haben;</li>
                        <li>einen Screenshot, falls eine Fehlermeldung angezeigt wird: er sagt oft mehr aus als eine lange Erklärung.</li>
                        </ul>
                        <p>Eine Beschreibung wie „es funktioniert nicht“ zwingt den Support, bei Ihnen nachzufragen, was die Lösung verzögert. Eine Beschreibung wie „mein externer Bildschirm lässt sich seit heute Morgen nicht mehr einschalten, das Kabel ist richtig eingesteckt“ ermöglicht es, sofort zu handeln.</p>
                        <h4>5. Ihr Ticket senden und verfolgen</h4>
                        <p>Sobald Ihr Ticket gesendet ist, können Sie seinen Fortschritt jederzeit über das Menü „Tickets“ verfolgen: Reiter „Laufende Tickets“ für noch offene Anfragen, „Gelöste Tickets“ für den Verlauf. Sie erhalten außerdem bei jeder wichtigen Aktualisierung eine E-Mail (Übernahme durch einen Techniker, Rückfrage, Lösung). Sie können direkt im Ticket antworten, wenn Sie eine zusätzliche Information hinzufügen möchten.</p>
                        HTML,
                ],
                'it_IT' => [
                    'name' => 'Segnalare un incidente o fare una richiesta: la differenza e come procedere',
                    'answer' => <<<'HTML'
                        <p>In GLPI, ogni ticket appartiene a uno di questi due tipi:</p>
                        <ul>
                        <li><strong>Un incidente</strong>: qualcosa che prima funzionava e ora non funziona più. Esempio: «il mio schermo non si accende più», «non riesco più ad accedere al mio account», «la stampante del 2° piano è guasta».</li>
                        <li><strong>Una richiesta</strong>: qualcosa di cui avete bisogno, ma che non è un guasto. Esempio: «ho bisogno di una nuova tastiera», «vorrei accedere a questa cartella condivisa», «la prossima settimana vado in ferie».</li>
                        </ul>
                        <p>In sintesi: se qualcosa è rotto, è un incidente. Se volete qualcosa di nuovo, è una richiesta. Non preoccupatevi se sbagliate, il supporto potrà sempre riclassificare il vostro ticket: l'importante è descrivere chiaramente la vostra esigenza.</p>
                        <h4>1. Aprire il portale di assistenza</h4>
                        <p>Accedete a GLPI con il vostro account abituale. Arriverete sulla pagina iniziale del portale, che vi chiede «Come possiamo aiutarvi?».</p>
                        <h4>2. Scegliere il punto di partenza giusto</h4>
                        <p>Due riquadri permettono di iniziare:</p>
                        <ul>
                        <li><strong>«Segnalare un incidente»</strong> se qualcosa non funziona;</li>
                        <li><strong>«Richiedere un servizio»</strong> (o il <strong>catalogo dei servizi</strong>) se avete bisogno di qualcosa di nuovo.</li>
                        </ul>
                        <h4>3. Scegliere la categoria giusta</h4>
                        <p>Vi verrà chiesto di precisare l'ambito interessato (informatica, edificio, risorse umane...). Scegliete quello che corrisponde meglio al vostro problema: questo permette alla vostra richiesta di arrivare direttamente al team giusto, senza intermediari.</p>
                        <h4>4. Scrivere una descrizione chiara</h4>
                        <p>Dedicate qualche secondo a fornire informazioni utili, questo permette al supporto di risolvere il vostro problema molto più velocemente:</p>
                        <ul>
                        <li>il nome o il numero del dispositivo interessato, se lo conoscete (spesso è scritto su un'etichetta);</li>
                        <li>da quando esiste il problema («da questa mattina», «da ieri pomeriggio»);</li>
                        <li>cosa avete già provato, se del caso;</li>
                        <li>uno screenshot se compare un messaggio di errore: spesso dice più di una lunga spiegazione.</li>
                        </ul>
                        <p>Una descrizione come «non funziona» obbliga il supporto a ricontattarvi per saperne di più, il che ritarda la risoluzione. Una descrizione come «il mio schermo esterno non si accende più da questa mattina, il cavo è collegato correttamente» permette di agire subito.</p>
                        <h4>5. Inviare e seguire il vostro ticket</h4>
                        <p>Una volta inviato il ticket, potete seguirne l'avanzamento in qualsiasi momento dal menu «Ticket»: scheda «Ticket in corso» per le richieste non ancora concluse, «Ticket risolti» per lo storico. Riceverete anche un'e-mail a ogni aggiornamento importante (presa in carico da un tecnico, richiesta di chiarimenti, risoluzione). Potete rispondere direttamente sul ticket se avete un'informazione aggiuntiva da fornire.</p>
                        HTML,
                ],
                'es_ES' => [
                    'name' => 'Notificar una incidencia o hacer una solicitud: la diferencia y cómo hacerlo',
                    'answer' => <<<'HTML'
                        <p>En GLPI, cada ticket pertenece a uno de estos dos tipos:</p>
                        <ul>
                        <li><strong>Una incidencia</strong>: algo que antes funcionaba y que ya no funciona. Ejemplo: «mi pantalla ya no se enciende», «ya no puedo iniciar sesión en mi cuenta», «la impresora de la 2.ª planta está averiada».</li>
                        <li><strong>Una solicitud</strong>: algo que necesita, pero que no es una avería. Ejemplo: «necesito un teclado nuevo», «me gustaría acceder a esta carpeta compartida», «me voy de vacaciones la semana que viene».</li>
                        </ul>
                        <p>En resumen: si algo está roto, es una incidencia. Si quiere algo nuevo, es una solicitud. No se preocupe si se equivoca, el soporte siempre podrá reclasificar su ticket: lo esencial es describir claramente su necesidad.</p>
                        <h4>1. Abrir el portal de asistencia</h4>
                        <p>Inicie sesión en GLPI con su cuenta habitual. Llegará a la página de inicio del portal, que le pregunta «¿En qué podemos ayudarle?».</p>
                        <h4>2. Elegir el punto de partida adecuado</h4>
                        <p>Dos tarjetas le permiten empezar:</p>
                        <ul>
                        <li><strong>«Notificar una incidencia»</strong> si algo no funciona;</li>
                        <li><strong>«Solicitar un servicio»</strong> (o el <strong>catálogo de servicios</strong>) si necesita algo nuevo.</li>
                        </ul>
                        <h4>3. Elegir la categoría adecuada</h4>
                        <p>Se le pedirá que precise el ámbito afectado (informática, edificio, recursos humanos...). Elija el que mejor corresponda a su problema: esto permite que su solicitud llegue directamente al equipo adecuado, sin rodeos.</p>
                        <h4>4. Escribir una descripción clara</h4>
                        <p>Dedique unos segundos a dar información útil, esto permite al soporte resolver su problema mucho más rápido:</p>
                        <ul>
                        <li>el nombre o el número del equipo afectado, si lo conoce (a menudo está escrito en una etiqueta);</li>
                        <li>desde cuándo existe el problema («desde esta mañana», «desde ayer por la tarde»);</li>
                        <li>lo que ya ha probado, si procede;</li>
                        <li>una captura de pantalla si aparece un mensaje de error: a menudo dice más que una larga explicación.</li>
                        </ul>
                        <p>Una descripción como «no funciona» obliga al soporte a volver a contactarle para saber más, lo que retrasa la resolución. Una descripción como «mi pantalla externa no se enciende desde esta mañana, el cable está bien conectado» permite actuar de inmediato.</p>
                        <h4>5. Enviar y seguir su ticket</h4>
                        <p>Una vez enviado su ticket, puede seguir su progreso en cualquier momento desde el menú «Tickets»: pestaña «Tickets en curso» para las solicitudes aún no finalizadas, «Tickets resueltos» para el historial. También recibirá un correo electrónico en cada actualización importante (asignación a un técnico, solicitud de más información, resolución). Puede responder directamente en el ticket si tiene información adicional que añadir.</p>
                        HTML,
                ],
                'pt_BR' => [
                    'name' => 'Reportar um incidente ou fazer uma solicitação: qual a diferença e como proceder',
                    'answer' => <<<'HTML'
                        <p>No GLPI, cada chamado pertence a um destes dois tipos:</p>
                        <ul>
                        <li><strong>Um incidente</strong>: algo que funcionava antes e não funciona mais. Exemplo: "minha tela não liga mais", "não consigo mais entrar na minha conta", "a impressora do 2º andar está com defeito".</li>
                        <li><strong>Uma solicitação</strong>: algo que você precisa, mas que não é um problema de funcionamento. Exemplo: "preciso de um teclado novo", "gostaria de ter acesso a esta pasta compartilhada", "vou tirar férias na próxima semana".</li>
                        </ul>
                        <p>Resumindo: se algo está quebrado, é um incidente. Se você quer algo novo, é uma solicitação. Fique tranquilo, se você errar, o suporte sempre pode reclassificar seu chamado: o essencial é descrever claramente o que você precisa.</p>
                        <h4>1. Abrir o portal de atendimento</h4>
                        <p>Faça login no GLPI com sua conta habitual. Você chega à página inicial do portal, que pergunta "Precisa de ajuda? Alguma dúvida?".</p>
                        <h4>2. Escolher o ponto de partida certo</h4>
                        <p>Dois blocos permitem começar:</p>
                        <ul>
                        <li><strong>"Reportar um incidente"</strong> se algo não está funcionando;</li>
                        <li><strong>"Solicitar um serviço"</strong> (ou o <strong>catálogo de serviços</strong>) se você precisa de algo novo.</li>
                        </ul>
                        <h4>3. Escolher a categoria certa</h4>
                        <p>Será solicitado que você especifique a área envolvida (informática, predial, recursos humanos...). Escolha a que melhor corresponde ao seu problema: isso permite que sua solicitação chegue diretamente à equipe certa, sem desvios.</p>
                        <h4>4. Escrever uma descrição clara</h4>
                        <p>Reserve alguns segundos para dar informações úteis, isso permite que o suporte resolva seu problema muito mais rápido:</p>
                        <ul>
                        <li>o nome ou número do equipamento envolvido, se você souber (geralmente está escrito em uma etiqueta);</li>
                        <li>desde quando o problema existe ("desde esta manhã", "desde ontem à tarde");</li>
                        <li>o que você já tentou, se for o caso;</li>
                        <li>uma captura de tela se aparecer uma mensagem de erro: ela costuma dizer mais do que uma explicação longa.</li>
                        </ul>
                        <p>Uma descrição como "não funciona" obriga o suporte a entrar em contato com você para saber mais, o que atrasa a resolução. Uma descrição como "minha tela externa não liga mais desde esta manhã, o cabo está bem conectado" permite agir imediatamente.</p>
                        <h4>5. Enviar e acompanhar seu chamado</h4>
                        <p>Depois de enviar seu chamado, você pode acompanhar seu andamento a qualquer momento pelo menu "Chamados": aba "Chamados em andamento" para as solicitações ainda não concluídas, "Chamados resolvidos" para o histórico. Você também receberá um e-mail a cada atualização importante (atendimento assumido por um técnico, pedido de mais informações, resolução). Você pode responder diretamente no chamado se tiver alguma informação adicional a acrescentar.</p>
                        HTML,
                ],
            ],
        ],
    ];

    /**
     * @return int Number of FAQ articles created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['kb_faq_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::ARTICLES as $article) {
            $id = $this->getOrCreate($article['name'], $article['answer']);
            $this->ensureVisibleToEveryEntity($id);
            $this->applyTranslations($id, $article['translations']);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, answer: string}>
     */
    public static function getArticlesPreview(): array
    {
        return array_map(
            static fn (array $article): array => ['name' => $article['name'], 'answer' => $article['answer']],
            self::ARTICLES
        );
    }

    private function getOrCreate(string $name, string $answer): int
    {
        $item = new KnowbaseItem();
        if ($item->getFromDBByCrit(['name' => $name])) {
            return (int) $item->getID();
        }

        return (int) $item->add([
            'name' => $name,
            'answer' => $answer,
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_faq' => 1,
            'users_id' => 0,
        ]);
    }

    /**
     * See this class's own docblock for why this is required: `is_faq=1` alone does not make an
     * article visible to anyone but its author/a KB admin in GLPI 11.0.8.
     */
    private function ensureVisibleToEveryEntity(int $knowbaseitemsId): void
    {
        $link = new Entity_KnowbaseItem();
        $crit = ['knowbaseitems_id' => $knowbaseitemsId, 'entities_id' => 0];
        if (!$link->getFromDBByCrit($crit)) {
            $link->add($crit + ['is_recursive' => 1]);
        }
    }

    /**
     * @param array<string, array{name: string, answer: string}> $translations
     */
    private function applyTranslations(int $knowbaseitemsId, array $translations): void
    {
        foreach ($translations as $language => $fields) {
            $translation = new KnowbaseItemTranslation();
            $crit = ['knowbaseitems_id' => $knowbaseitemsId, 'language' => $language];
            if (!$translation->getFromDBByCrit($crit)) {
                $translation->add($crit + ['name' => $fields['name'], 'answer' => $fields['answer'], 'users_id' => 0]);
            } elseif ($translation->fields['name'] !== $fields['name'] || $translation->fields['answer'] !== $fields['answer']) {
                // Same add-only pitfall already fixed on Translations::applyIcon()/applyContent()
                // (see that class's own comments) — re-running the wizard after this content
                // itself changes must update the existing row, not silently leave stale text.
                $translation->update($crit + ['id' => (int) $translation->getID(), 'name' => $fields['name'], 'answer' => $fields['answer']]);
            }
        }
    }
}
