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

use ITILFollowupTemplate;

/**
 * Turns on `followup_library_enabled` into a general-purpose `ITILFollowupTemplate` library
 * (Configuration > Intitulés > Assistance > "Gabarits de suivis") — ready-to-use "keep the
 * requester informed" messages any technician can pick when adding a followup to any ticket.
 *
 * Distinct from `WaitReasonBuilder`'s own `ITILFollowupTemplate` rows (Sprint 24): those exist
 * only to auto-attach to a specific `PendingReason` when a ticket is put on hold, named after that
 * reason. These are named after the *communication moment* instead (requesting info, notifying of
 * a delay...) so they read sensibly when picked manually mid-ticket, independent of any pending
 * status — deliberately different names from `WaitReasonBuilder`'s so the two libraries never
 * collide even though they cover overlapping situations.
 */
class FollowupLibraryBuilder
{
    /**
     * Twig prefix (GLPI's own `Glpi\ContentTemplates\TemplateManager`, sandboxed, available on
     * `ITILFollowupTemplate`/`SolutionTemplate`/`TaskTemplate` since 10.0 — confirmed via
     * `AbstractITILChildTemplate::getRenderedContent()`) resolving to a real "Bonjour <nom>,"
     * when the ticket/change/problem has at least one requester, falling back to the generic
     * "Bonjour," otherwise. `itemtype` picks the right root node — these templates are shared
     * across Ticket/Change/Problem (`AbstractITILChildTemplate`'s three children), each exposing
     * its data under a *different* root variable name (`ticket`/`change`/`problem`), so a template
     * can't hardcode `ticket.requesters` without breaking on the other two. `.fullname` (GLPI's
     * `User::getFriendlyName()`) rather than `.firstname` — falls back to the login when
     * firstname/realname aren't set, `.firstname` alone would silently render blank on those
     * accounts (confirmed empirically: the seed `glpi` account has neither set).
     */
    private const GREETING = "{% set requesters = itemtype == 'Change' ? change.requesters.users : (itemtype == 'Problem' ? problem.requesters.users : ticket.requesters.users) %}{% if requesters|length > 0 %}Bonjour {{ requesters|first.fullname }},{% else %}Bonjour,{% endif %}";

    // Same Twig structure as GREETING (see its own docblock for the itemtype-branching reasoning),
    // only the literal greeting word translated — everything else is Twig syntax/variable names,
    // language-neutral by construction. Content translation (Sprint 37) works the same way as the
    // wizard's own UI strings: a `DropdownTranslation` row per language on the `content` field,
    // read by `AbstractITILChildTemplate::getRenderedContent()` — confirmed in core source — before
    // Twig ever runs, so the *stored* template differs per language, not just the rendered output.
    private const GREETING_EN = "{% set requesters = itemtype == 'Change' ? change.requesters.users : (itemtype == 'Problem' ? problem.requesters.users : ticket.requesters.users) %}{% if requesters|length > 0 %}Hello {{ requesters|first.fullname }},{% else %}Hello,{% endif %}";

    private const GREETING_DE = "{% set requesters = itemtype == 'Change' ? change.requesters.users : (itemtype == 'Problem' ? problem.requesters.users : ticket.requesters.users) %}{% if requesters|length > 0 %}Hallo {{ requesters|first.fullname }},{% else %}Hallo,{% endif %}";

    private const GREETING_IT = "{% set requesters = itemtype == 'Change' ? change.requesters.users : (itemtype == 'Problem' ? problem.requesters.users : ticket.requesters.users) %}{% if requesters|length > 0 %}Buongiorno {{ requesters|first.fullname }},{% else %}Buongiorno,{% endif %}";

    private const GREETING_ES = "{% set requesters = itemtype == 'Change' ? change.requesters.users : (itemtype == 'Problem' ? problem.requesters.users : ticket.requesters.users) %}{% if requesters|length > 0 %}Hola {{ requesters|first.fullname }},{% else %}Hola,{% endif %}";

    private const TEMPLATES = [
        [
            'name' => 'Relance — informations complémentaires demandées',
            'icon' => '❓',
            'content' => self::GREETING . "\n\nNous avons besoin d'informations complémentaires pour avancer sur votre demande :\n- \n\nMerci de nous répondre dans les meilleurs délais.\n\nCordialement,",
            'translations' => [
                'en_GB' => self::GREETING_EN . "\n\nWe need additional information to move forward with your request:\n- \n\nPlease reply as soon as possible.\n\nKind regards,",
                'de_DE' => self::GREETING_DE . "\n\nWir benötigen weitere Informationen, um mit Ihrer Anfrage fortzufahren:\n- \n\nBitte antworten Sie so schnell wie möglich.\n\nMit freundlichen Grüßen,",
                'it_IT' => self::GREETING_IT . "\n\nAbbiamo bisogno di ulteriori informazioni per procedere con la vostra richiesta:\n- \n\nVi preghiamo di rispondere il prima possibile.\n\nCordiali saluti,",
                'es_ES' => self::GREETING_ES . "\n\nNecesitamos información adicional para avanzar con su solicitud:\n- \n\nLe rogamos que responda lo antes posible.\n\nAtentamente,",
            ],
        ],
        [
            'name' => 'Notification — commande ou livraison en cours',
            'icon' => '📦',
            'content' => self::GREETING . "\n\nVotre demande est en cours de traitement. Nous attendons la livraison du matériel/logiciel nécessaire et vous tiendrons informé dès réception.\n\nCordialement,",
            'translations' => [
                'en_GB' => self::GREETING_EN . "\n\nYour request is being processed. We are awaiting delivery of the necessary hardware/software and will keep you informed upon receipt.\n\nKind regards,",
                'de_DE' => self::GREETING_DE . "\n\nIhre Anfrage wird bearbeitet. Wir warten auf die Lieferung der benötigten Hardware/Software und halten Sie nach Erhalt auf dem Laufenden.\n\nMit freundlichen Grüßen,",
                'it_IT' => self::GREETING_IT . "\n\nLa vostra richiesta è in corso di elaborazione. Siamo in attesa della consegna dell'hardware/software necessario e vi terremo informati al ricevimento.\n\nCordiali saluti,",
                'es_ES' => self::GREETING_ES . "\n\nSu solicitud está siendo procesada. Estamos a la espera de la entrega del hardware/software necesario y le mantendremos informado en cuanto lo recibamos.\n\nAtentamente,",
            ],
        ],
        [
            'name' => 'Notification — escalade fournisseur',
            'icon' => '🪜',
            'content' => self::GREETING . "\n\nVotre ticket a été transmis à notre fournisseur/éditeur pour analyse. Nous reviendrons vers vous dès que nous aurons un retour.\n\nCordialement,",
            'translations' => [
                'en_GB' => self::GREETING_EN . "\n\nYour ticket has been forwarded to our supplier/vendor for analysis. We will get back to you as soon as we receive a response.\n\nKind regards,",
                'de_DE' => self::GREETING_DE . "\n\nIhr Ticket wurde zur Analyse an unseren Lieferanten/Hersteller weitergeleitet. Wir melden uns bei Ihnen, sobald wir eine Rückmeldung erhalten.\n\nMit freundlichen Grüßen,",
                'it_IT' => self::GREETING_IT . "\n\nIl vostro ticket è stato inoltrato al nostro fornitore/editore per l'analisi. Vi ricontatteremo non appena avremo una risposta.\n\nCordiali saluti,",
                'es_ES' => self::GREETING_ES . "\n\nSu ticket ha sido remitido a nuestro proveedor/editor para su análisis. Nos pondremos en contacto con usted en cuanto tengamos una respuesta.\n\nAtentamente,",
            ],
        ],
        [
            'name' => 'Notification — intervention planifiée',
            'icon' => '🗓️',
            'content' => self::GREETING . "\n\nUne intervention est planifiée pour résoudre votre demande. Merci de vous assurer de votre disponibilité à la date convenue.\n\nCordialement,",
            'translations' => [
                'en_GB' => self::GREETING_EN . "\n\nAn intervention has been scheduled to resolve your request. Please make sure you are available on the agreed date.\n\nKind regards,",
                'de_DE' => self::GREETING_DE . "\n\nEin Einsatz zur Lösung Ihrer Anfrage ist geplant. Bitte stellen Sie sicher, dass Sie am vereinbarten Termin verfügbar sind.\n\nMit freundlichen Grüßen,",
                'it_IT' => self::GREETING_IT . "\n\nÈ stato programmato un intervento per risolvere la vostra richiesta. Vi preghiamo di assicurarvi di essere disponibili alla data concordata.\n\nCordiali saluti,",
                'es_ES' => self::GREETING_ES . "\n\nSe ha programado una intervención para resolver su solicitud. Le rogamos que se asegure de estar disponible en la fecha acordada.\n\nAtentamente,",
            ],
        ],
        [
            'name' => 'Notification — validation en cours',
            'icon' => '✅',
            'content' => self::GREETING . "\n\nVotre demande nécessite une validation avant de pouvoir être traitée. Nous vous informerons dès qu'elle aura été obtenue.\n\nCordialement,",
            'translations' => [
                'en_GB' => self::GREETING_EN . "\n\nYour request requires approval before it can be processed. We will inform you as soon as it has been obtained.\n\nKind regards,",
                'de_DE' => self::GREETING_DE . "\n\nIhre Anfrage erfordert eine Freigabe, bevor sie bearbeitet werden kann. Wir informieren Sie, sobald diese vorliegt.\n\nMit freundlichen Grüßen,",
                'it_IT' => self::GREETING_IT . "\n\nLa vostra richiesta necessita di un'approvazione prima di poter essere elaborata. Vi informeremo non appena sarà stata ottenuta.\n\nCordiali saluti,",
                'es_ES' => self::GREETING_ES . "\n\nSu solicitud requiere una aprobación antes de poder ser tramitada. Le informaremos en cuanto la hayamos obtenido.\n\nAtentamente,",
            ],
        ],
    ];

    /**
     * @return int Number of followup templates created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['followup_library_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['followup_library_icons_enabled']);
        $count = 0;
        foreach (self::TEMPLATES as $template) {
            $templateId = $this->getOrCreateTemplate($template['name'], $template['content']);
            if ($withIcons) {
                Translations::applyIcon(ITILFollowupTemplate::class, $templateId, $template['name'], $template['icon']);
            }
            Translations::applyContent(ITILFollowupTemplate::class, $templateId, $template['translations']);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, icon: string, content: string, translations: array<string, string>}>
     */
    public static function getLibraryPreview(): array
    {
        return self::TEMPLATES;
    }

    private function getOrCreateTemplate(string $name, string $content): int
    {
        $item = new ITILFollowupTemplate();
        if ($item->getFromDBByCrit(['name' => $name])) {
            return (int) $item->getID();
        }

        return (int) $item->add([
            'name' => $name,
            'content' => $content,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
    }
}
