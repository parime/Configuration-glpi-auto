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

use SolutionTemplate;
use SolutionType;

/**
 * Turns on `solution_library_enabled` into a real `SolutionType`/`SolutionTemplate` library
 * (Configuration > Intitulés > Assistance > "Types de solutions" / "Gabarits de solution"). GLPI
 * ships neither by default. Types and templates are built together — a template with no type to
 * belong to (or vice versa) isn't useful on its own, same reasoning `ServiceCatalogBuilder`
 * applies to categories+forms.
 *
 * Modeled on a real production GLPI export's own 5-type closure taxonomy (generalized — dropped
 * anything specific to that org's own incident-response tooling), cross-checked against standard
 * ITSM closure-code practice (ServiceNow/ITIL: resolution vs. workaround vs. informational vs.
 * duplicate all being distinct, auditable closure categories rather than one bucket): a resolution
 * always falls into "the user was helped" (no technical fix), "something was actually fixed",
 * "access/accounts changed", "a security incident was handled", or "no technical action was
 * needed" — and which of those is even *selectable* differs by ITIL object type (a security
 * closure makes sense on an Incident or a Change, not on a Request; access management doesn't
 * apply to a Change). `SolutionType`'s own `is_incident`/`is_request`/`is_problem`/`is_change`
 * flags encode exactly that per-type visibility.
 *
 * Distinct from `WaitReasonBuilder`'s `SolutionTemplate` rows (Sprint 24): those are single,
 * narrowly-scoped templates auto-linked to a specific `PendingReason`; this is a general-purpose
 * library any technician can pick from `SolutionTemplate`'s own dropdown when closing any ticket.
 * Idempotent by name, so the two never collide even if a name were to coincide.
 */
class SolutionLibraryBuilder
{
    /**
     * Same itemtype-aware Twig prefix as `FollowupLibraryBuilder::GREETING` (see there for the
     * full reasoning) — duplicated rather than shared, consistent with this codebase's existing
     * "small local duplication over a cross-builder helper" convention.
     */
    private const GREETING = "{% set requesters = itemtype == 'Change' ? change.requesters.users : (itemtype == 'Problem' ? problem.requesters.users : ticket.requesters.users) %}{% if requesters|length > 0 %}Bonjour {{ requesters|first.fullname }},{% else %}Bonjour,{% endif %}";

    // Same GREETING_EN/_DE/_IT/_ES pattern as FollowupLibraryBuilder (see there for the full
    // reasoning) — duplicated rather than shared, consistent with this codebase's convention.
    private const GREETING_EN = "{% set requesters = itemtype == 'Change' ? change.requesters.users : (itemtype == 'Problem' ? problem.requesters.users : ticket.requesters.users) %}{% if requesters|length > 0 %}Hello {{ requesters|first.fullname }},{% else %}Hello,{% endif %}";

    private const GREETING_DE = "{% set requesters = itemtype == 'Change' ? change.requesters.users : (itemtype == 'Problem' ? problem.requesters.users : ticket.requesters.users) %}{% if requesters|length > 0 %}Hallo {{ requesters|first.fullname }},{% else %}Hallo,{% endif %}";

    private const GREETING_IT = "{% set requesters = itemtype == 'Change' ? change.requesters.users : (itemtype == 'Problem' ? problem.requesters.users : ticket.requesters.users) %}{% if requesters|length > 0 %}Buongiorno {{ requesters|first.fullname }},{% else %}Buongiorno,{% endif %}";

    private const GREETING_ES = "{% set requesters = itemtype == 'Change' ? change.requesters.users : (itemtype == 'Problem' ? problem.requesters.users : ticket.requesters.users) %}{% if requesters|length > 0 %}Hola {{ requesters|first.fullname }},{% else %}Hola,{% endif %}";

    /**
     * `solvedate` is defined on every `CommonITILObjectParameters` child alike (Ticket/Change/
     * Problem), same itemtype-branching reasoning as GREETING above. Real bug caught while adding
     * this: the two "Sécurité" templates already referenced `{{ ticket.solvedate }}` directly
     * (added in an earlier sprint) despite `SolutionType::is_change=1` making them selectable on a
     * Change — where `ticket` is undefined and Twig's `date` filter treats a null/undefined input
     * as "now", silently showing today's date instead of the real resolution date. Fixed alongside
     * the new GREETING rollout rather than left as a latent bug.
     */
    private const SOLVE_DATE = "{% set solve_date = itemtype == 'Change' ? change.solvedate : (itemtype == 'Problem' ? problem.solvedate : ticket.solvedate) %}{{ solve_date | date('d/m/Y H:i') }}";

    private const TYPES = [
        [
            'name' => 'Assistance / Support utilisateur',
            'icon' => '🙋',
            'comment' => 'Aide apportée à l\'utilisateur : explications, guidage, prise en main, formation — sans intervention technique sur un système.',
            'is_incident' => 1, 'is_request' => 1, 'is_problem' => 1, 'is_change' => 0,
            'templates' => [
                [
                    'name' => 'Accompagnement utilisateur réalisé',
                    'icon' => '🙋',
                    'content' => self::GREETING . "\n\nNous avons accompagné l'utilisateur pas à pas pour résoudre sa demande.\n\nAction réalisée : \n\nCordialement,",
                    'translations' => [
                        'en_GB' => self::GREETING_EN . "\n\nWe guided the user step by step to resolve their request.\n\nAction taken: \n\nKind regards,",
                        'de_DE' => self::GREETING_DE . "\n\nWir haben den Benutzer Schritt für Schritt bei der Lösung seiner Anfrage begleitet.\n\nDurchgeführte Maßnahme: \n\nMit freundlichen Grüßen,",
                        'it_IT' => self::GREETING_IT . "\n\nAbbiamo accompagnato l'utente passo dopo passo per risolvere la sua richiesta.\n\nAzione svolta: \n\nCordiali saluti,",
                        'es_ES' => self::GREETING_ES . "\n\nHemos acompañado al usuario paso a paso para resolver su solicitud.\n\nAcción realizada: \n\nAtentamente,",
                    ],
                ],
                [
                    'name' => 'Formation dispensée',
                    'icon' => '🎓',
                    'content' => self::GREETING . "\n\nUne session de formation/sensibilisation a été dispensée à l'utilisateur sur l'outil concerné.\n\nPoints abordés : \n\nCordialement,",
                    'translations' => [
                        'en_GB' => self::GREETING_EN . "\n\nA training/awareness session was provided to the user on the tool concerned.\n\nTopics covered: \n\nKind regards,",
                        'de_DE' => self::GREETING_DE . "\n\nDem Benutzer wurde eine Schulungs-/Sensibilisierungssitzung zum betreffenden Tool angeboten.\n\nBehandelte Themen: \n\nMit freundlichen Grüßen,",
                        'it_IT' => self::GREETING_IT . "\n\nÈ stata erogata all'utente una sessione di formazione/sensibilizzazione sullo strumento in questione.\n\nArgomenti trattati: \n\nCordiali saluti,",
                        'es_ES' => self::GREETING_ES . "\n\nSe ha impartido al usuario una sesión de formación/sensibilización sobre la herramienta en cuestión.\n\nTemas tratados: \n\nAtentamente,",
                    ],
                ],
            ],
        ],
        [
            'name' => 'Résolution technique',
            'icon' => '🔧',
            'comment' => 'Correction matérielle (équipement, périphérique), logicielle (bug, mise à jour) ou de configuration système/réseau/application.',
            'is_incident' => 1, 'is_request' => 1, 'is_problem' => 1, 'is_change' => 1,
            'templates' => [
                [
                    'name' => 'Résolution technique appliquée',
                    'icon' => '🔧',
                    'content' => self::GREETING . "\n\nLe problème a été identifié et corrigé.\n\nCause : \nAction réalisée : \n\nCordialement,",
                    'translations' => [
                        'en_GB' => self::GREETING_EN . "\n\nThe issue has been identified and fixed.\n\nCause: \nAction taken: \n\nKind regards,",
                        'de_DE' => self::GREETING_DE . "\n\nDas Problem wurde identifiziert und behoben.\n\nUrsache: \nDurchgeführte Maßnahme: \n\nMit freundlichen Grüßen,",
                        'it_IT' => self::GREETING_IT . "\n\nIl problema è stato individuato e risolto.\n\nCausa: \nAzione svolta: \n\nCordiali saluti,",
                        'es_ES' => self::GREETING_ES . "\n\nSe ha identificado y corregido el problema.\n\nCausa: \nAcción realizada: \n\nAtentamente,",
                    ],
                ],
                [
                    'name' => 'Remplacement de matériel effectué',
                    'icon' => '🔩',
                    'content' => self::GREETING . "\n\nLe matériel défectueux a été remplacé.\n\nÉquipement concerné : \nNouvel équipement : \n\nCordialement,",
                    'translations' => [
                        'en_GB' => self::GREETING_EN . "\n\nThe faulty equipment has been replaced.\n\nEquipment concerned: \nNew equipment: \n\nKind regards,",
                        'de_DE' => self::GREETING_DE . "\n\nDas defekte Gerät wurde ersetzt.\n\nBetroffenes Gerät: \nNeues Gerät: \n\nMit freundlichen Grüßen,",
                        'it_IT' => self::GREETING_IT . "\n\nL'apparecchiatura difettosa è stata sostituita.\n\nApparecchiatura interessata: \nNuova apparecchiatura: \n\nCordiali saluti,",
                        'es_ES' => self::GREETING_ES . "\n\nSe ha sustituido el equipo defectuoso.\n\nEquipo afectado: \nNuevo equipo: \n\nAtentamente,",
                    ],
                ],
            ],
        ],
        [
            'name' => 'Sécurité',
            'icon' => '🔒',
            'comment' => 'Gestion d\'un incident de sécurité : confinement, éradication de la menace, restauration, collecte de preuves.',
            'is_incident' => 1, 'is_request' => 0, 'is_problem' => 0, 'is_change' => 1,
            'templates' => [
                [
                    'name' => 'Confinement et éradication de la menace',
                    'icon' => '🛡️',
                    'content' => 'Incident de sécurité traité.' . "\n\nAction immédiate : isolation du système impacté.\nÉradication : suppression des éléments malveillants, nettoyage des persistances.\n\nOutils utilisés : \nDate : " . self::SOLVE_DATE,
                    // No GREETING here (matches the French version — a security incident report
                    // isn't addressed "Bonjour X,", same for every language). SOLVE_DATE itself
                    // needs no per-language variant: pure Twig/date filter, no literal words.
                    'translations' => [
                        'en_GB' => 'Security incident handled.' . "\n\nImmediate action: isolation of the affected system.\nEradication: removal of malicious elements, cleanup of persistence mechanisms.\n\nTools used: \nDate: " . self::SOLVE_DATE,
                        'de_DE' => 'Sicherheitsvorfall behandelt.' . "\n\nSofortmaßnahme: Isolierung des betroffenen Systems.\nBeseitigung: Entfernung schädlicher Elemente, Bereinigung von Persistenzmechanismen.\n\nVerwendete Werkzeuge: \nDatum: " . self::SOLVE_DATE,
                        'it_IT' => 'Incidente di sicurezza gestito.' . "\n\nAzione immediata: isolamento del sistema colpito.\nEradicazione: rimozione degli elementi dannosi, pulizia dei meccanismi di persistenza.\n\nStrumenti utilizzati: \nData: " . self::SOLVE_DATE,
                        'es_ES' => 'Incidente de seguridad tratado.' . "\n\nAcción inmediata: aislamiento del sistema afectado.\nErradicación: eliminación de los elementos maliciosos, limpieza de las persistencias.\n\nHerramientas utilizadas: \nFecha: " . self::SOLVE_DATE,
                    ],
                ],
                [
                    'name' => 'Application de correctifs de sécurité',
                    'icon' => '🩹',
                    'content' => "Correctifs appliqués suite à l'incident de sécurité.\n\nVulnérabilité corrigée : \nCorrectifs déployés : \n\nDate : " . self::SOLVE_DATE,
                    'translations' => [
                        'en_GB' => "Patches applied following the security incident.\n\nVulnerability fixed: \nPatches deployed: \n\nDate: " . self::SOLVE_DATE,
                        'de_DE' => "Nach dem Sicherheitsvorfall wurden Patches eingespielt.\n\nBehobene Schwachstelle: \nEingespielte Patches: \n\nDatum: " . self::SOLVE_DATE,
                        'it_IT' => "Patch applicate a seguito dell'incidente di sicurezza.\n\nVulnerabilità corretta: \nPatch distribuite: \n\nData: " . self::SOLVE_DATE,
                        'es_ES' => "Parches aplicados a raíz del incidente de seguridad.\n\nVulnerabilidad corregida: \nParches desplegados: \n\nFecha: " . self::SOLVE_DATE,
                    ],
                ],
            ],
        ],
        [
            'name' => 'Informationnel',
            'icon' => 'ℹ️',
            'comment' => 'Clôtures sans intervention technique : comportement normal, doublon, annulation, hors périmètre.',
            'is_incident' => 1, 'is_request' => 1, 'is_problem' => 1, 'is_change' => 0,
            'templates' => [
                [
                    'name' => 'Fonctionnement normal constaté',
                    'icon' => '✅',
                    'content' => self::GREETING . "\n\nAprès vérification, le comportement signalé est normal, aucune anomalie détectée.\n\nCordialement,",
                    'translations' => [
                        'en_GB' => self::GREETING_EN . "\n\nAfter verification, the reported behavior is normal, no anomaly was detected.\n\nKind regards,",
                        'de_DE' => self::GREETING_DE . "\n\nNach Überprüfung ist das gemeldete Verhalten normal, es wurde keine Anomalie festgestellt.\n\nMit freundlichen Grüßen,",
                        'it_IT' => self::GREETING_IT . "\n\nDopo la verifica, il comportamento segnalato è normale, non è stata rilevata alcuna anomalia.\n\nCordiali saluti,",
                        'es_ES' => self::GREETING_ES . "\n\nTras la verificación, el comportamiento notificado es normal, no se ha detectado ninguna anomalía.\n\nAtentamente,",
                    ],
                ],
                [
                    'name' => 'Ticket doublon',
                    'icon' => '📑',
                    'content' => self::GREETING . "\n\nCe ticket fait doublon avec une demande déjà en cours de traitement.\n\nTicket de référence : \n\nCordialement,",
                    'translations' => [
                        'en_GB' => self::GREETING_EN . "\n\nThis ticket is a duplicate of a request already being processed.\n\nReference ticket: \n\nKind regards,",
                        'de_DE' => self::GREETING_DE . "\n\nDieses Ticket ist ein Duplikat einer bereits in Bearbeitung befindlichen Anfrage.\n\nReferenzticket: \n\nMit freundlichen Grüßen,",
                        'it_IT' => self::GREETING_IT . "\n\nQuesto ticket è un duplicato di una richiesta già in corso di elaborazione.\n\nTicket di riferimento: \n\nCordiali saluti,",
                        'es_ES' => self::GREETING_ES . "\n\nEste ticket es un duplicado de una solicitud que ya está siendo tramitada.\n\nTicket de referencia: \n\nAtentamente,",
                    ],
                ],
                // 11e gabarit — forum GLPI officiel (topic 294630, utilisateur alecomte demandant un
                // statut/bouton dédié pour rejeter un ticket mal formulé ; réponse du contributeur
                // LaDenrée : pas besoin de toucher au workflow natif, un gabarit de solution type
                // suffit). Catégorie "Informationnel" : clôturer un ticket faute d'informations
                // suffisantes n'est pas une résolution technique, exactement le même type de clôture
                // sans intervention que "Fonctionnement normal constaté"/"Ticket doublon" ci-dessus.
                [
                    'name' => 'Demande incomplète',
                    'icon' => '❓',
                    'content' => self::GREETING . "\n\nVotre demande ne contient pas suffisamment d'informations pour être traitée. Merci de bien vouloir préciser :\n\n- Description précise du problème ou de la demande\n- Capture d'écran si applicable\n- Étapes pour reproduire le problème\n\nNous reprendrons le traitement dès réception de ces éléments.\n\nCordialement,",
                    'translations' => [
                        'en_GB' => self::GREETING_EN . "\n\nYour request does not contain enough information to be processed. Please provide:\n\n- A precise description of the issue or request\n- A screenshot if applicable\n- Steps to reproduce the issue\n\nWe will resume processing as soon as we receive these details.\n\nKind regards,",
                        'de_DE' => self::GREETING_DE . "\n\nIhre Anfrage enthält nicht genügend Informationen, um bearbeitet zu werden. Bitte teilen Sie uns mit:\n\n- Eine genaue Beschreibung des Problems oder der Anfrage\n- Einen Screenshot, falls zutreffend\n- Schritte zur Reproduktion des Problems\n\nWir setzen die Bearbeitung fort, sobald wir diese Angaben erhalten haben.\n\nMit freundlichen Grüßen,",
                        'it_IT' => self::GREETING_IT . "\n\nLa vostra richiesta non contiene informazioni sufficienti per essere elaborata. Vi preghiamo di fornire:\n\n- Una descrizione precisa del problema o della richiesta\n- Una schermata, se applicabile\n- I passaggi per riprodurre il problema\n\nRiprenderemo la lavorazione non appena riceveremo questi elementi.\n\nCordiali saluti,",
                        'es_ES' => self::GREETING_ES . "\n\nSu solicitud no contiene suficiente información para ser tramitada. Le rogamos que nos indique:\n\n- Una descripción precisa del problema o de la solicitud\n- Una captura de pantalla si procede\n- Los pasos para reproducir el problema\n\nReanudaremos la tramitación en cuanto recibamos estos datos.\n\nAtentamente,",
                    ],
                ],
            ],
        ],
        [
            'name' => 'Gestion des accès',
            'icon' => '🔑',
            'comment' => 'Comptes utilisateurs, droits d\'accès, mots de passe.',
            'is_incident' => 1, 'is_request' => 1, 'is_problem' => 1, 'is_change' => 0,
            'templates' => [
                [
                    'name' => 'Compte créé ou modifié',
                    'icon' => '👤',
                    'content' => self::GREETING . "\n\nLe compte a été créé/modifié comme demandé.\n\nAccès accordés : \n\nCordialement,",
                    'translations' => [
                        'en_GB' => self::GREETING_EN . "\n\nThe account has been created/modified as requested.\n\nAccess granted: \n\nKind regards,",
                        'de_DE' => self::GREETING_DE . "\n\nDas Konto wurde wie gewünscht angelegt/geändert.\n\nGewährter Zugriff: \n\nMit freundlichen Grüßen,",
                        'it_IT' => self::GREETING_IT . "\n\nL'account è stato creato/modificato come richiesto.\n\nAccessi concessi: \n\nCordiali saluti,",
                        'es_ES' => self::GREETING_ES . "\n\nLa cuenta ha sido creada/modificada según lo solicitado.\n\nAccesos concedidos: \n\nAtentamente,",
                    ],
                ],
                [
                    'name' => 'Mot de passe réinitialisé',
                    'icon' => '🔑',
                    'content' => self::GREETING . "\n\nVotre mot de passe a été réinitialisé. Vous recevrez les identifiants par un canal séparé.\n\nCordialement,",
                    'translations' => [
                        'en_GB' => self::GREETING_EN . "\n\nYour password has been reset. You will receive the credentials through a separate channel.\n\nKind regards,",
                        'de_DE' => self::GREETING_DE . "\n\nIhr Passwort wurde zurückgesetzt. Die Zugangsdaten erhalten Sie über einen separaten Kanal.\n\nMit freundlichen Grüßen,",
                        'it_IT' => self::GREETING_IT . "\n\nLa vostra password è stata reimpostata. Riceverete le credenziali tramite un canale separato.\n\nCordiali saluti,",
                        'es_ES' => self::GREETING_ES . "\n\nSu contraseña ha sido restablecida. Recibirá las credenciales por un canal separado.\n\nAtentamente,",
                    ],
                ],
            ],
        ],
    ];

    /**
     * @return int Number of solution templates created/reused (types are a means to that end, not
     *             counted on their own).
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['solution_library_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['solution_type_icons_enabled']);
        $withTemplateIcons = !empty($config->fields['solution_template_icons_enabled']);
        $count = 0;
        foreach (self::TYPES as $type) {
            $typeId = $this->getOrCreateType($type);
            // Always called (see StateBuilder::build() for the reasoning) so unchecking icons after
            // a prior run actually strips them instead of leaving old rows stuck.
            Translations::applyIcon(SolutionType::class, $typeId, $type['name'], $withIcons ? $type['icon'] : '');
            foreach ($type['templates'] as $template) {
                $templateId = $this->getOrCreateTemplate($template['name'], $template['content'], $typeId);
                // Always called (see StateBuilder::build() for the reasoning) so unchecking icons
                // after a prior run actually strips them instead of leaving old rows stuck.
                Translations::applyIcon(SolutionTemplate::class, $templateId, $template['name'], $withTemplateIcons ? $template['icon'] : '');
                Translations::applyContent(SolutionTemplate::class, $templateId, $template['translations']);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, icon: string, comment: string, templates: array<int, array{name: string, content: string, translations: array<string, string>}>}>
     */
    public static function getLibraryPreview(): array
    {
        return self::TYPES;
    }

    private function getOrCreateType(array $type): int
    {
        $item = new SolutionType();
        if ($item->getFromDBByCrit(['name' => $type['name']])) {
            return (int) $item->getID();
        }

        return (int) $item->add([
            'name' => $type['name'],
            'comment' => $type['comment'],
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_incident' => $type['is_incident'],
            'is_request' => $type['is_request'],
            'is_problem' => $type['is_problem'],
            'is_change' => $type['is_change'],
        ]);
    }

    private function getOrCreateTemplate(string $name, string $content, int $typeId): int
    {
        $item = new SolutionTemplate();
        if ($item->getFromDBByCrit(['name' => $name])) {
            return (int) $item->getID();
        }

        return (int) $item->add([
            'name' => $name,
            'content' => $content,
            'solutiontypes_id' => $typeId,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
    }
}
