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

use Notification;
use Notification_NotificationTemplate;
use NotificationTemplate;
use NotificationTemplateTranslation;

/**
 * Replaces GLPI's single shared "Tickets" notification template
 * (`glpi_notifications_notificationtemplates` — confirmed in a fresh install: every ticket event
 * points at the same template id, so they're all stuck with identical, plain-text-looking HTML)
 * with one dedicated, branded template per event — using the admin's own primary color
 * (`BrandingBuilder`) and root-entity logo, real GLPI notification tags confirmed one by one
 * against `NotificationTargetCommonITILObject`/`NotificationTargetTicket` source (never guessed):
 * every `##xxx##` tag below was grep'd out of GLPI 11 core before being used here.
 *
 * Covers all 23 events exposed by `NotificationTargetTicket::getEvents()` (14 ticket-specific:
 * new/update/solved/rejectsolution/validation/validation_answer/validation_reminder/closed/delete/
 * alertnotclosed/recall/recall_ola/satisfaction/replysatisfaction — 9 shared with every ITILObject
 * via `NotificationTargetCommonITILObject::getEvents()`: requester_user/requester_group/
 * observer_user/observer_group/assign_user/assign_group/assign_supplier/add_task/update_task/
 * delete_task/add_followup/update_followup/delete_followup/user_mention/add_document — not every
 * one of those ~28 has a natural "branded card" shape (recall/recall_ola/satisfaction/
 * replysatisfaction/rejectsolution/validation_reminder/pendingreason_* are cron/survey/reminder
 * events whose native plain-text content is already adequate and whose exact tag set wasn't
 * cross-checked here): TEMPLATES below deliberately covers the 23 events with a clear "new
 * information to review" shape — the ones a recipient actually reads and acts on.
 *
 * Layout directly modeled on a real production HTML notification set handed over for audit (dark
 * header, colored accent bar, colored CTA button, "-- via GLPI" style footer) — same "generalize a
 * real pattern, drop the org-specific branding" approach already used for `RuleRightBuilder`/
 * `SlaBuilder`. The reference export's exact colors/company name are never reused, only the
 * structure; the admin's own computed color/logo (already collected in step 9) replace them.
 *
 * Links (`##ticket.url##`/`##ticket.urlvalidation##`) are GLPI's own native tags, resolved by
 * `NotificationTarget::formatURL()` from `$CFG_GLPI['url_base']` (Configuration générale > URL de
 * l'application) — nothing built or guessed here, this class never touches that config value
 * directly, it just never hand-builds a URL either.
 *
 * One `NotificationTemplateTranslation` row per language (`LABELS` below) rather than a single
 * `language=''` one — a real bug caught in review: GLPI resolves content by the *recipient's own*
 * language (`NotificationTemplate::getByLanguage()`, confirmed in source:
 * `WHERE language IN ($recipient_language, '')`), so a single French-only row would have shown
 * French labels to every recipient regardless of their GLPI language, undermining the 6-language UI
 * translation work shipped just before this. `''` (empty language) doubles as both the universal
 * fallback *and* the French-specific row — matches the SQL `IN` clause, one less row to maintain,
 * same reasoning already used for this plugin's own `fr_FR` locale file (no separate row needed
 * when the fallback content IS the French content).
 *
 * Idempotent via an HTML comment marker rather than the usual "row already exists, skip" pattern:
 * this *modifies* native GLPI rows (or its own, on a second run) rather than creating new ones, so
 * "already has our marker" is the only safe re-run signal — an admin's own hand-edit after the fact
 * (no marker anymore, since they'd naturally replace the whole content) is respected, matching the
 * project's "point of entry, not final" convention rather than clobbering it silently. Checked only
 * on the `''` row: all languages are always written together in the same run, so that row's state
 * is representative of the whole set.
 */
class NotificationBrandingBuilder
{
    private const MARKER = '<!-- configurationglpiauto:notification-branding -->';

    /**
     * Chaque evenement definit : un intitule admin, l'evenement natif GLPI, la ligne d'en-tete
     * (meta_label/meta_tag), une liste de lignes cle/valeur (rows), un bloc de mise en avant
     * optionnel (highlight_*, pour le texte libre : contenu du ticket, description d'un suivi/
     * tache, commentaire de validation...), et le tag utilise pour le bouton d'action (cta_tag —
     * ##ticket.urlvalidation## pour les evenements de validation, ##ticket.url## partout ailleurs).
     *
     * @var array<int, array{
     *     name: string, event: string, meta_label: string, meta_tag: string,
     *     rows: array<int, array{0: string, 1: string}>,
     *     highlight_label: ?string, highlight_tag: ?string,
     *     cta_label: string, cta_tag: string
     * }>
     */
    private const TEMPLATES = [
        [
            'name' => 'Notification personnalisée — Nouveau ticket',
            'event' => 'new',
            'meta_label' => 'opened_on', 'meta_tag' => '##ticket.creationdate##',
            'rows' => [['requesters', '##ticket.authors##'], ['category', '##ticket.category##'], ['urgency', '##ticket.urgency##']],
            'highlight_label' => 'description', 'highlight_tag' => '##ticket.content##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Mise à jour du ticket',
            'event' => 'update',
            'meta_label' => 'status', 'meta_tag' => '##ticket.status##',
            'rows' => [['requesters', '##ticket.authors##'], ['category', '##ticket.category##'], ['last_update_by', '##ticket.lastupdater##']],
            'highlight_label' => null, 'highlight_tag' => null,
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Ticket résolu',
            'event' => 'solved',
            'meta_label' => 'resolved_on', 'meta_tag' => '##ticket.solvedate##',
            'rows' => [['requesters', '##ticket.authors##'], ['category', '##ticket.category##'], ['solution_type', '##ticket.solution.type##'], ['solution_author', '##ticket.solution.author##']],
            'highlight_label' => 'solution_provided', 'highlight_tag' => '##ticket.solution.description##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Clôture du ticket',
            'event' => 'closed',
            'meta_label' => 'closed_on', 'meta_tag' => '##ticket.closedate##',
            'rows' => [['requesters', '##ticket.authors##'], ['category', '##ticket.category##'], ['solution_type', '##ticket.solution.type##'], ['solution_author', '##ticket.solution.author##']],
            'highlight_label' => 'solution_provided', 'highlight_tag' => '##ticket.solution.description##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Ticket supprimé',
            'event' => 'delete',
            'meta_label' => 'opened_on', 'meta_tag' => '##ticket.creationdate##',
            'rows' => [['requesters', '##ticket.authors##'], ['category', '##ticket.category##'], ['last_update_by', '##ticket.lastupdater##']],
            'highlight_label' => null, 'highlight_tag' => null,
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Tickets non résolus (rappel)',
            'event' => 'alertnotclosed',
            'meta_label' => 'status', 'meta_tag' => '##ticket.status##',
            'rows' => [['requesters', '##ticket.authors##'], ['assigned_users', '##ticket.assigntousers##'], ['opened_on', '##ticket.creationdate##'], ['due_date', '##ticket.duedate##'], ['priority', '##ticket.priority##']],
            'highlight_label' => null, 'highlight_tag' => null,
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Nouveau suivi',
            'event' => 'add_followup',
            'meta_label' => 'status', 'meta_tag' => '##ticket.status##',
            'rows' => [['writer', '##followup.author##'], ['written_on', '##followup.date##'], ['private', '##followup.isprivate##']],
            'highlight_label' => 'description', 'highlight_tag' => '##followup.description##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Suivi modifié',
            'event' => 'update_followup',
            'meta_label' => 'status', 'meta_tag' => '##ticket.status##',
            'rows' => [['writer', '##followup.author##'], ['written_on', '##followup.date##'], ['private', '##followup.isprivate##']],
            'highlight_label' => 'description', 'highlight_tag' => '##followup.description##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Suivi supprimé',
            'event' => 'delete_followup',
            'meta_label' => 'status', 'meta_tag' => '##ticket.status##',
            'rows' => [['requesters', '##ticket.authors##'], ['opened_on', '##ticket.creationdate##']],
            'highlight_label' => null, 'highlight_tag' => null,
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Nouvelle tâche',
            'event' => 'add_task',
            'meta_label' => 'status', 'meta_tag' => '##task.status##',
            'rows' => [['writer', '##task.author##'], ['assignee', '##task.user##'], ['category', '##task.category##'], ['start', '##task.begin##'], ['end', '##task.end##'], ['duration', '##task.time##']],
            'highlight_label' => 'description', 'highlight_tag' => '##task.description##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Tâche modifiée',
            'event' => 'update_task',
            'meta_label' => 'status', 'meta_tag' => '##task.status##',
            'rows' => [['writer', '##task.author##'], ['assignee', '##task.user##'], ['start', '##task.begin##'], ['end', '##task.end##']],
            'highlight_label' => 'description', 'highlight_tag' => '##task.description##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Tâche supprimée',
            'event' => 'delete_task',
            'meta_label' => 'status', 'meta_tag' => '##ticket.status##',
            'rows' => [['requesters', '##ticket.authors##'], ['assigned_users', '##ticket.assigntousers##'], ['opened_on', '##ticket.creationdate##']],
            'highlight_label' => null, 'highlight_tag' => null,
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Nouveau demandeur',
            'event' => 'requester_user',
            'meta_label' => 'status', 'meta_tag' => '##ticket.status##',
            'rows' => [['requesters', '##ticket.authors##'], ['assigned_users', '##ticket.assigntousers##'], ['category', '##ticket.category##'], ['opened_on', '##ticket.creationdate##']],
            'highlight_label' => 'description', 'highlight_tag' => '##ticket.content##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Nouveau groupe demandeur',
            'event' => 'requester_group',
            'meta_label' => 'status', 'meta_tag' => '##ticket.status##',
            'rows' => [['requester_group', '##ticket.groups##'], ['category', '##ticket.category##'], ['opened_on', '##ticket.creationdate##']],
            'highlight_label' => 'description', 'highlight_tag' => '##ticket.content##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Nouvel observateur',
            'event' => 'observer_user',
            'meta_label' => 'status', 'meta_tag' => '##ticket.status##',
            'rows' => [['requesters', '##ticket.authors##'], ['assigned_users', '##ticket.assigntousers##'], ['category', '##ticket.category##'], ['opened_on', '##ticket.creationdate##']],
            'highlight_label' => 'description', 'highlight_tag' => '##ticket.content##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Nouveau groupe observateur',
            'event' => 'observer_group',
            'meta_label' => 'status', 'meta_tag' => '##ticket.status##',
            'rows' => [['observer_group', '##ticket.observergroups##'], ['category', '##ticket.category##'], ['opened_on', '##ticket.creationdate##']],
            'highlight_label' => 'description', 'highlight_tag' => '##ticket.content##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Nouvelle assignation (utilisateur)',
            'event' => 'assign_user',
            'meta_label' => 'priority', 'meta_tag' => '##ticket.priority##',
            'rows' => [['assigned_groups', '##ticket.assigntogroups##'], ['assigned_users', '##ticket.assigntousers##'], ['category', '##ticket.category##'], ['due_date', '##ticket.duedate##']],
            'highlight_label' => 'description', 'highlight_tag' => '##ticket.content##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Nouvelle assignation (groupe)',
            'event' => 'assign_group',
            'meta_label' => 'priority', 'meta_tag' => '##ticket.priority##',
            'rows' => [['assigned_groups', '##ticket.assigntogroups##'], ['category', '##ticket.category##'], ['due_date', '##ticket.duedate##']],
            'highlight_label' => 'description', 'highlight_tag' => '##ticket.content##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Assignation à un fournisseur',
            'event' => 'assign_supplier',
            'meta_label' => 'priority', 'meta_tag' => '##ticket.priority##',
            'rows' => [['assigned_supplier', '##ticket.assigntosupplier##'], ['category', '##ticket.category##'], ['due_date', '##ticket.duedate##']],
            'highlight_label' => 'description', 'highlight_tag' => '##ticket.content##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Utilisateur mentionné',
            'event' => 'user_mention',
            'meta_label' => 'status', 'meta_tag' => '##ticket.status##',
            'rows' => [['requesters', '##ticket.authors##'], ['assigned_users', '##ticket.assigntousers##'], ['opened_on', '##ticket.creationdate##']],
            'highlight_label' => null, 'highlight_tag' => null,
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Nouveau document',
            'event' => 'add_document',
            'meta_label' => 'status', 'meta_tag' => '##ticket.status##',
            'rows' => [['document_name', '##document.name##'], ['document_file', '##document.filename##'], ['requesters', '##ticket.authors##']],
            'highlight_label' => null, 'highlight_tag' => null,
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
        [
            'name' => 'Notification personnalisée — Demande de validation',
            'event' => 'validation',
            'meta_label' => 'requested_on', 'meta_tag' => '##validation.submissiondate##',
            'rows' => [['writer', '##validation.author##'], ['validator', '##validation.validator##']],
            'highlight_label' => 'comment_submission', 'highlight_tag' => '##validation.commentsubmission##',
            'cta_label' => 'validate_ticket', 'cta_tag' => '##ticket.urlvalidation##',
        ],
        [
            'name' => 'Notification personnalisée — Réponse à la validation',
            'event' => 'validation_answer',
            'meta_label' => 'validated_on', 'meta_tag' => '##validation.validationdate##',
            'rows' => [['validator', '##validation.validator##'], ['validation_status', '##validation.status##']],
            'highlight_label' => 'comment_validation', 'highlight_tag' => '##validation.commentvalidation##',
            'cta_label' => 'view_ticket', 'cta_tag' => '##ticket.url##',
        ],
    ];

    /**
     * `''` est le code langue de repli propre a GLPI, qui double ici comme ligne francaise (voir
     * docblock de la classe) — toute autre cle doit etre une vraie cle `$CFG_GLPI['languages']`
     * pour que `NotificationTemplate::getByLanguage()` la selectionne un jour pour ce destinataire.
     *
     * @var array<string, array<string, string>>
     */
    private const LABELS = [
        '' => [
            'description' => 'Description',
            'solution_provided' => 'Solution apportée',
            'status' => 'Statut',
            'opened_on' => 'Ouvert le',
            'resolved_on' => 'Résolu le',
            'closed_on' => 'Clôturé le',
            'view_ticket' => '→ Voir le ticket dans GLPI',
            'validate_ticket' => '→ Traiter la demande dans GLPI',
            'access_glpi' => 'Accéder à GLPI',
            'requesters' => 'Demandeurs',
            'category' => 'Catégorie',
            'urgency' => 'Urgence',
            'priority' => 'Priorité',
            'last_update_by' => 'Dernière modification par',
            'writer' => 'Auteur',
            'written_on' => 'Écrit le',
            'private' => 'Privé',
            'assignee' => 'Assigné à',
            'start' => 'Début',
            'end' => 'Fin',
            'duration' => 'Durée',
            'due_date' => 'Échéance',
            'requester_group' => 'Groupe demandeur',
            'observer_group' => 'Groupe observateur',
            'assigned_groups' => 'Groupes assignés',
            'assigned_users' => 'Utilisateurs assignés',
            'assigned_supplier' => 'Fournisseur assigné',
            'document_name' => 'Document',
            'document_file' => 'Fichier',
            'validator' => 'Validateur',
            'validation_status' => 'Statut de la validation',
            'comment_submission' => 'Commentaire de la demande',
            'comment_validation' => 'Commentaire de la réponse',
            'requested_on' => 'Demandé le',
            'validated_on' => 'Traité le',
            'solution_type' => 'Type de solution',
            'solution_author' => 'Solution apportée par',
        ],
        'en_GB' => [
            'description' => 'Description',
            'solution_provided' => 'Solution provided',
            'status' => 'Status',
            'opened_on' => 'Opened on',
            'resolved_on' => 'Resolved on',
            'closed_on' => 'Closed on',
            'view_ticket' => '→ View the ticket in GLPI',
            'validate_ticket' => '→ Handle the request in GLPI',
            'access_glpi' => 'Access GLPI',
            'requesters' => 'Requesters',
            'category' => 'Category',
            'urgency' => 'Urgency',
            'priority' => 'Priority',
            'last_update_by' => 'Last updated by',
            'writer' => 'Written by',
            'written_on' => 'Written on',
            'private' => 'Private',
            'assignee' => 'Assigned to',
            'start' => 'Start',
            'end' => 'End',
            'duration' => 'Duration',
            'due_date' => 'Due date',
            'requester_group' => 'Requester group',
            'observer_group' => 'Observer group',
            'assigned_groups' => 'Assigned groups',
            'assigned_users' => 'Assigned users',
            'assigned_supplier' => 'Assigned supplier',
            'document_name' => 'Document',
            'document_file' => 'File',
            'validator' => 'Approver',
            'validation_status' => 'Approval status',
            'comment_submission' => 'Request comment',
            'comment_validation' => 'Answer comment',
            'requested_on' => 'Requested on',
            'validated_on' => 'Answered on',
            'solution_type' => 'Solution type',
            'solution_author' => 'Solution provided by',
        ],
        'de_DE' => [
            'description' => 'Beschreibung',
            'solution_provided' => 'Bereitgestellte Lösung',
            'status' => 'Status',
            'opened_on' => 'Eröffnet am',
            'resolved_on' => 'Gelöst am',
            'closed_on' => 'Geschlossen am',
            'view_ticket' => '→ Ticket in GLPI ansehen',
            'validate_ticket' => '→ Anfrage in GLPI bearbeiten',
            'access_glpi' => 'Zu GLPI',
            'requesters' => 'Antragsteller',
            'category' => 'Kategorie',
            'urgency' => 'Dringlichkeit',
            'priority' => 'Priorität',
            'last_update_by' => 'Zuletzt geändert von',
            'writer' => 'Verfasst von',
            'written_on' => 'Verfasst am',
            'private' => 'Privat',
            'assignee' => 'Zugewiesen an',
            'start' => 'Beginn',
            'end' => 'Ende',
            'duration' => 'Dauer',
            'due_date' => 'Fällig am',
            'requester_group' => 'Antragstellergruppe',
            'observer_group' => 'Beobachtergruppe',
            'assigned_groups' => 'Zugewiesene Gruppen',
            'assigned_users' => 'Zugewiesene Benutzer',
            'assigned_supplier' => 'Zugewiesener Lieferant',
            'document_name' => 'Dokument',
            'document_file' => 'Datei',
            'validator' => 'Genehmiger',
            'validation_status' => 'Genehmigungsstatus',
            'comment_submission' => 'Kommentar zur Anfrage',
            'comment_validation' => 'Kommentar zur Antwort',
            'requested_on' => 'Angefragt am',
            'validated_on' => 'Beantwortet am',
            'solution_type' => 'Lösungstyp',
            'solution_author' => 'Lösung bereitgestellt von',
        ],
        'it_IT' => [
            'description' => 'Descrizione',
            'solution_provided' => 'Soluzione fornita',
            'status' => 'Stato',
            'opened_on' => 'Aperto il',
            'resolved_on' => 'Risolto il',
            'closed_on' => 'Chiuso il',
            'view_ticket' => '→ Visualizza il ticket in GLPI',
            'validate_ticket' => '→ Gestisci la richiesta in GLPI',
            'access_glpi' => 'Accedi a GLPI',
            'requesters' => 'Richiedenti',
            'category' => 'Categoria',
            'urgency' => 'Urgenza',
            'priority' => 'Priorità',
            'last_update_by' => 'Ultima modifica di',
            'writer' => 'Scritto da',
            'written_on' => 'Scritto il',
            'private' => 'Privato',
            'assignee' => 'Assegnato a',
            'start' => 'Inizio',
            'end' => 'Fine',
            'duration' => 'Durata',
            'due_date' => 'Scadenza',
            'requester_group' => 'Gruppo richiedente',
            'observer_group' => 'Gruppo osservatore',
            'assigned_groups' => 'Gruppi assegnati',
            'assigned_users' => 'Utenti assegnati',
            'assigned_supplier' => 'Fornitore assegnato',
            'document_name' => 'Documento',
            'document_file' => 'File',
            'validator' => 'Approvatore',
            'validation_status' => 'Stato dell\'approvazione',
            'comment_submission' => 'Commento della richiesta',
            'comment_validation' => 'Commento della risposta',
            'requested_on' => 'Richiesto il',
            'validated_on' => 'Risposto il',
            'solution_type' => 'Tipo di soluzione',
            'solution_author' => 'Soluzione fornita da',
        ],
        'es_ES' => [
            'description' => 'Descripción',
            'solution_provided' => 'Solución proporcionada',
            'status' => 'Estado',
            'opened_on' => 'Abierto el',
            'resolved_on' => 'Resuelto el',
            'closed_on' => 'Cerrado el',
            'view_ticket' => '→ Ver el ticket en GLPI',
            'validate_ticket' => '→ Gestionar la solicitud en GLPI',
            'access_glpi' => 'Acceder a GLPI',
            'requesters' => 'Solicitantes',
            'category' => 'Categoría',
            'urgency' => 'Urgencia',
            'priority' => 'Prioridad',
            'last_update_by' => 'Última modificación por',
            'writer' => 'Escrito por',
            'written_on' => 'Escrito el',
            'private' => 'Privado',
            'assignee' => 'Asignado a',
            'start' => 'Inicio',
            'end' => 'Fin',
            'duration' => 'Duración',
            'due_date' => 'Fecha límite',
            'requester_group' => 'Grupo solicitante',
            'observer_group' => 'Grupo observador',
            'assigned_groups' => 'Grupos asignados',
            'assigned_users' => 'Usuarios asignados',
            'assigned_supplier' => 'Proveedor asignado',
            'document_name' => 'Documento',
            'document_file' => 'Archivo',
            'validator' => 'Aprobador',
            'validation_status' => 'Estado de la aprobación',
            'comment_submission' => 'Comentario de la solicitud',
            'comment_validation' => 'Comentario de la respuesta',
            'requested_on' => 'Solicitado el',
            'validated_on' => 'Respondido el',
            'solution_type' => 'Tipo de solución',
            'solution_author' => 'Solución proporcionada por',
        ],
        'pt_BR' => [
            'description' => 'Descrição',
            'solution_provided' => 'Solução fornecida',
            'status' => 'Status',
            'opened_on' => 'Aberto em',
            'resolved_on' => 'Resolvido em',
            'closed_on' => 'Encerrado em',
            'view_ticket' => '→ Ver o chamado no GLPI',
            'validate_ticket' => '→ Tratar a solicitação no GLPI',
            'access_glpi' => 'Acessar o GLPI',
            'requesters' => 'Solicitantes',
            'category' => 'Categoria',
            'urgency' => 'Urgência',
            'priority' => 'Prioridade',
            'last_update_by' => 'Última atualização por',
            'writer' => 'Escrito por',
            'written_on' => 'Escrito em',
            'private' => 'Privado',
            'assignee' => 'Atribuído a',
            'start' => 'Início',
            'end' => 'Fim',
            'duration' => 'Duração',
            'due_date' => 'Prazo',
            'requester_group' => 'Grupo solicitante',
            'observer_group' => 'Grupo observador',
            'assigned_groups' => 'Grupos atribuídos',
            'assigned_users' => 'Usuários atribuídos',
            'assigned_supplier' => 'Fornecedor atribuído',
            'document_name' => 'Documento',
            'document_file' => 'Arquivo',
            'validator' => 'Aprovador',
            'validation_status' => 'Status da aprovação',
            'comment_submission' => 'Comentário da solicitação',
            'comment_validation' => 'Comentário da resposta',
            'requested_on' => 'Solicitado em',
            'validated_on' => 'Respondido em',
            'solution_type' => 'Tipo de solução',
            'solution_author' => 'Solução fornecida por',
        ],
    ];

    public function apply(Config $config, string $color, ?string $logoDataUri): int
    {
        if (empty($config->fields['notification_branding_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::TEMPLATES as $definition) {
            if ($this->applyOne($definition, $color, $logoDataUri)) {
                $count++;
            }
        }

        return $count;
    }

    private function applyOne(array $definition, string $color, ?string $logoDataUri): bool
    {
        $templateId = $this->getOrCreateTemplate($definition, $color, $logoDataUri);
        if ($templateId === null) {
            // Marker already present on the '' row: an earlier run (or, once edited, an admin) owns
            // this content now — don't overwrite it again.
            return false;
        }

        $this->assignToEvent($definition['event'], $templateId);

        return true;
    }

    private function getOrCreateTemplate(array $definition, string $color, ?string $logoDataUri): ?int
    {
        $template = new NotificationTemplate();
        $isNewTemplate = !$template->getFromDBByCrit(['name' => $definition['name'], 'itemtype' => 'Ticket']);

        if (!$isNewTemplate) {
            $templateId = (int) $template->getID();

            $marker = new NotificationTemplateTranslation();
            if ($marker->getFromDBByCrit(['notificationtemplates_id' => $templateId, 'language' => ''])) {
                $existing = (string) ($marker->fields['content_html'] ?? '');
                if (str_contains($existing, self::MARKER)) {
                    return null;
                }
            }
        } else {
            $templateId = (int) $template->add([
                'name' => $definition['name'],
                'itemtype' => 'Ticket',
                'comment' => 'Créé par Configuration GLPI Auto — habillage HTML personnalisé.',
            ]);
        }

        foreach (self::LABELS as $language => $labels) {
            $html = $this->buildHtml($definition, $labels, $color, $logoDataUri);
            $subject = '##ticket.title##';

            $translation = new NotificationTemplateTranslation();
            if ($translation->getFromDBByCrit(['notificationtemplates_id' => $templateId, 'language' => $language])) {
                $translation->update(['id' => $translation->getID(), 'content_html' => $html, 'subject' => $subject]);
            } else {
                $translation->add([
                    'notificationtemplates_id' => $templateId,
                    'language' => $language,
                    'subject' => $subject,
                    'content_html' => $html,
                    'content_text' => '',
                ]);
            }
        }

        return $templateId;
    }

    /**
     * Re-points the existing `mailing` join row for this event from GLPI's shared default template
     * to our dedicated one — the row already exists natively for every core Ticket event, so this
     * is always an update, never an insert (confirmed via `DESCRIBE`: one row per event+mode).
     */
    private function assignToEvent(string $event, int $templateId): void
    {
        $notification = new Notification();
        if (!$notification->getFromDBByCrit(['itemtype' => 'Ticket', 'event' => $event])) {
            return;
        }
        $notificationId = (int) $notification->getID();

        $join = new Notification_NotificationTemplate();
        if ($join->getFromDBByCrit(['notifications_id' => $notificationId, 'mode' => 'mailing'])) {
            $join->update(['id' => $join->getID(), 'notificationtemplates_id' => $templateId]);
        } else {
            $join->add([
                'notifications_id' => $notificationId,
                'mode' => 'mailing',
                'notificationtemplates_id' => $templateId,
            ]);
        }
    }

    private function buildHtml(array $definition, array $labels, string $color, ?string $logoDataUri): string
    {
        [$r, $g, $b] = $this->hexToRgb($color);
        $ctaFg = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 149 ? '#1e293b' : '#ffffff';

        $logoBlock = $logoDataUri !== null
            ? '<img src="' . htmlspecialchars($logoDataUri, ENT_QUOTES, 'UTF-8') . '" alt="" style="max-height:32px;margin-bottom:12px;display:block;">'
            : '';

        $metaLabel = $labels[$definition['meta_label']];

        $rowsHtml = '';
        foreach ($definition['rows'] as [$labelKey, $tag]) {
            $rowsHtml .= '<tr>'
                . '<td style="background:#fafafa;padding:10px 14px;font-family:Arial,sans-serif;font-size:12px;color:#999999;border-bottom:1px solid #eeeeee;width:160px;">' . $labels[$labelKey] . '</td>'
                . '<td style="background:#ffffff;padding:10px 14px;font-family:Arial,sans-serif;font-size:13px;color:#1a1a2e;border-bottom:1px solid #eeeeee;">' . $tag . '</td>'
                . '</tr>';
        }

        $highlightHtml = '';
        if ($definition['highlight_label'] !== null) {
            $highlightHtml = '<div style="font-size:10px;color:' . $color . ';letter-spacing:3px;text-transform:uppercase;font-family:Arial,sans-serif;margin:24px 0 12px;">' . $labels[$definition['highlight_label']] . '</div>'
                . '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                . '<td width="4" style="background:' . $color . ';border-radius:4px;">&nbsp;</td>'
                . '<td style="background:#f9f9fb;padding:16px 20px;border-radius:0 8px 8px 0;font-family:Arial,sans-serif;font-size:14px;color:#333333;line-height:1.8;">' . $definition['highlight_tag'] . '</td>'
                . '</tr></table>';
        }

        return self::MARKER . "\n"
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0f0f5;padding:30px 0;"><tr><td align="center">'
            . '<table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;border-radius:12px;overflow:hidden;">'
            . '<tr><td style="background:#1a1a2e;padding:30px 36px 24px 36px;">'
            . $logoBlock
            . '<div style="border-left:4px solid ' . $color . ';padding-left:16px;">'
            . '<a href="##ticket.url##" style="color:#ffffff;text-decoration:none;font-size:20px;font-weight:bold;font-family:Arial,sans-serif;">##ticket.title##</a>'
            . '</div>'
            . '<div style="margin-top:16px;padding-left:20px;">'
            . '<span style="background:rgba(255,255,255,0.1);border:1px solid ' . $color . ';color:' . $color . ';font-size:11px;padding:4px 12px;border-radius:20px;font-family:Arial,sans-serif;"><strong>##ticket.id##</strong></span>'
            . '&nbsp;&nbsp;<span style="color:#888888;font-size:12px;font-family:Arial,sans-serif;">' . $metaLabel . ' ' . $definition['meta_tag'] . '</span>'
            . '</div></td></tr>'
            . '<tr><td style="padding:32px 36px;background:#ffffff;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-radius:8px;overflow:hidden;border:1px solid #eeeeee;">'
            . $rowsHtml
            . '</table>'
            . $highlightHtml
            . '</td></tr>'
            . '<tr><td align="center" style="background:' . $color . ';padding:18px 36px;">'
            . '<a href="' . $definition['cta_tag'] . '" style="color:' . $ctaFg . ';text-decoration:none;font-family:Arial,sans-serif;font-size:13px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">' . $labels[$definition['cta_label']] . '</a>'
            . '</td></tr>'
            . '<tr><td align="center" style="background:#f8f8fa;padding:12px 36px;border-top:1px solid #eeeeee;">'
            . '<span style="font-family:Arial,sans-serif;font-size:11px;color:#bbbbbb;">##ticket.id## &nbsp;·&nbsp; <a href="##ticket.url##" style="color:' . $color . ';text-decoration:none;">' . $labels['access_glpi'] . '</a></span>'
            . '</td></tr>'
            . '</table></td></tr></table>';
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $color): array
    {
        $hex = ltrim($color, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
