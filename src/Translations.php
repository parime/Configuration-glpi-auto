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

use DropdownTranslation;

/**
 * Centralized French → {en_GB, de_DE, it_IT, es_ES} lookup for every "intitulé" this plugin gives
 * an icon to, plus the single `applyIcon()` helper every icon-capable builder calls instead of
 * each maintaining its own near-identical `DropdownTranslation`-writing method (9 builders used to
 * duplicate this before Sprint 35).
 *
 * Confirmed the 4 language codes against GLPI core itself (`.mo` files present in `locales/` for
 * all four) — same codes already used for `fr_FR` throughout this plugin. Deliberately scoped to
 * the *data* this plugin generates (state names, category names...), not the wizard's own
 * interface text: this plugin ships zero `.po`/`.mo` files for itself, so every `__()` call in its
 * own templates/PHP only ever renders in French regardless of session language — a separate,
 * unstarted body of work, not touched here.
 *
 * Manufacturer names (`ManufacturerBuilder`) are deliberately absent from `MAP`: they're proper
 * nouns ("Dell", "Cisco"...), identical in every language — `applyIcon()`'s fallback to the French
 * text for missing entries already produces the right result for them without a MAP entry.
 */
final class Translations
{
    private const MAP = [
        // ---- StateBuilder (glpi_states) ----
        'Attribué' => ['en_GB' => 'Assigned', 'de_DE' => 'Zugewiesen', 'it_IT' => 'Assegnato', 'es_ES' => 'Asignado'],
        'En stock' => ['en_GB' => 'In stock', 'de_DE' => 'Auf Lager', 'it_IT' => 'In magazzino', 'es_ES' => 'En stock'],
        'Obsolète' => ['en_GB' => 'Obsolete', 'de_DE' => 'Veraltet', 'it_IT' => 'Obsoleto', 'es_ES' => 'Obsoleto'],
        'Donné' => ['en_GB' => 'Donated', 'de_DE' => 'Gespendet', 'it_IT' => 'Donato', 'es_ES' => 'Donado'],
        'Volé / perdu' => ['en_GB' => 'Stolen / Lost', 'de_DE' => 'Gestohlen / Verloren', 'it_IT' => 'Rubato / Smarrito', 'es_ES' => 'Robado / Perdido'],
        'À identifier' => ['en_GB' => 'To be identified', 'de_DE' => 'Zu identifizieren', 'it_IT' => 'Da identificare', 'es_ES' => 'Por identificar'],
        'Attente restitution' => ['en_GB' => 'Awaiting return', 'de_DE' => 'Rückgabe ausstehend', 'it_IT' => 'In attesa di restituzione', 'es_ES' => 'Pendiente de devolución'],
        'Défectueux' => ['en_GB' => 'Faulty', 'de_DE' => 'Defekt', 'it_IT' => 'Difettoso', 'es_ES' => 'Defectuoso'],
        'En service' => ['en_GB' => 'In service', 'de_DE' => 'Im Betrieb', 'it_IT' => 'In servizio', 'es_ES' => 'En servicio'],
        'Fin de support' => ['en_GB' => 'End of support', 'de_DE' => 'Support-Ende', 'it_IT' => 'Fine del supporto', 'es_ES' => 'Fin del soporte'],
        'Retour fournisseur' => ['en_GB' => 'Returned to supplier', 'de_DE' => 'Rückgabe an Lieferant', 'it_IT' => 'Reso al fornitore', 'es_ES' => 'Devuelto al proveedor'],
        'Externe' => ['en_GB' => 'External', 'de_DE' => 'Extern', 'it_IT' => 'Esterno', 'es_ES' => 'Externo'],
        'Compte de service' => ['en_GB' => 'Service account', 'de_DE' => 'Dienstkonto', 'it_IT' => 'Account di servizio', 'es_ES' => 'Cuenta de servicio'],
        'Vendu' => ['en_GB' => 'Sold', 'de_DE' => 'Verkauft', 'it_IT' => 'Venduto', 'es_ES' => 'Vendido'],

        // ---- TaskCategoryBuilder (glpi_taskcategories) ----
        'Diagnostic & Analyse' => ['en_GB' => 'Diagnosis & Analysis', 'de_DE' => 'Diagnose & Analyse', 'it_IT' => 'Diagnosi e Analisi', 'es_ES' => 'Diagnóstico y Análisis'],
        'Installation logicielle' => ['en_GB' => 'Software installation', 'de_DE' => 'Softwareinstallation', 'it_IT' => 'Installazione software', 'es_ES' => 'Instalación de software'],
        'Déploiement matériel' => ['en_GB' => 'Hardware deployment', 'de_DE' => 'Hardware-Bereitstellung', 'it_IT' => 'Distribuzione hardware', 'es_ES' => 'Despliegue de hardware'],
        'Configuration système' => ['en_GB' => 'System configuration', 'de_DE' => 'Systemkonfiguration', 'it_IT' => 'Configurazione di sistema', 'es_ES' => 'Configuración del sistema'],
        'Maintenance préventive' => ['en_GB' => 'Preventive maintenance', 'de_DE' => 'Vorbeugende Wartung', 'it_IT' => 'Manutenzione preventiva', 'es_ES' => 'Mantenimiento preventivo'],
        'Support & Assistance utilisateur' => ['en_GB' => 'Support & User assistance', 'de_DE' => 'Support & Benutzerunterstützung', 'it_IT' => 'Supporto e Assistenza utente', 'es_ES' => 'Soporte y Asistencia al usuario'],
        'Formation' => ['en_GB' => 'Training', 'de_DE' => 'Schulung', 'it_IT' => 'Formazione', 'es_ES' => 'Formación'],
        'Escalade fournisseur / Éditeur' => ['en_GB' => 'Vendor / Publisher escalation', 'de_DE' => 'Eskalation an Anbieter / Hersteller', 'it_IT' => 'Escalation a fornitore / Produttore', 'es_ES' => 'Escalado a proveedor / Editor'],
        'Sécurité & Audit' => ['en_GB' => 'Security & Audit', 'de_DE' => 'Sicherheit & Audit', 'it_IT' => 'Sicurezza e Audit', 'es_ES' => 'Seguridad y Auditoría'],
        'Documentation' => ['en_GB' => 'Documentation', 'de_DE' => 'Dokumentation', 'it_IT' => 'Documentazione', 'es_ES' => 'Documentación'],
        'Coordination & Réunion' => ['en_GB' => 'Coordination & Meeting', 'de_DE' => 'Koordination & Besprechung', 'it_IT' => 'Coordinamento e Riunione', 'es_ES' => 'Coordinación y Reunión'],
        'Test & Validation' => ['en_GB' => 'Testing & Validation', 'de_DE' => 'Test & Validierung', 'it_IT' => 'Test e Validazione', 'es_ES' => 'Pruebas y Validación'],
        'Gestion des comptes utilisateurs' => ['en_GB' => 'User account management', 'de_DE' => 'Benutzerkontenverwaltung', 'it_IT' => 'Gestione account utente', 'es_ES' => 'Gestión de cuentas de usuario'],
        'Réinitialisation mot de passe' => ['en_GB' => 'Password reset', 'de_DE' => 'Passwort zurücksetzen', 'it_IT' => 'Reimpostazione password', 'es_ES' => 'Restablecimiento de contraseña'],

        // ---- WaitReasonBuilder (glpi_pendingreasons) ----
        'Attente de retour utilisateur' => ['en_GB' => 'Awaiting user response', 'de_DE' => 'Warten auf Antwort des Benutzers', 'it_IT' => "In attesa di risposta dell'utente", 'es_ES' => 'Esperando respuesta del usuario'],
        'Attente livraison fournisseur' => ['en_GB' => 'Awaiting supplier delivery', 'de_DE' => 'Warten auf Lieferung des Lieferanten', 'it_IT' => 'In attesa di consegna dal fornitore', 'es_ES' => 'Esperando entrega del proveedor'],
        'Intervention planifiée' => ['en_GB' => 'Scheduled intervention', 'de_DE' => 'Geplanter Eingriff', 'it_IT' => 'Intervento pianificato', 'es_ES' => 'Intervención programada'],
        'Validation interne en attente' => ['en_GB' => 'Awaiting internal approval', 'de_DE' => 'Interne Freigabe ausstehend', 'it_IT' => 'In attesa di approvazione interna', 'es_ES' => 'Aprobación interna pendiente'],

        // ---- ProjectTaxonomyBuilder (glpi_projecttypes) ----
        'Interne' => ['en_GB' => 'Internal', 'de_DE' => 'Intern', 'it_IT' => 'Interno', 'es_ES' => 'Interno'],
        'Client / Prestation' => ['en_GB' => 'Client / Service', 'de_DE' => 'Kunde / Dienstleistung', 'it_IT' => 'Cliente / Prestazione', 'es_ES' => 'Cliente / Prestación'],
        'Infrastructure' => ['en_GB' => 'Infrastructure', 'de_DE' => 'Infrastruktur', 'it_IT' => 'Infrastruttura', 'es_ES' => 'Infraestructura'],
        'Déploiement / Migration' => ['en_GB' => 'Deployment / Migration', 'de_DE' => 'Bereitstellung / Migration', 'it_IT' => 'Distribuzione / Migrazione', 'es_ES' => 'Despliegue / Migración'],
        'R&D / Innovation' => ['en_GB' => 'R&D / Innovation', 'de_DE' => 'F&E / Innovation', 'it_IT' => 'R&S / Innovazione', 'es_ES' => 'I+D / Innovación'],

        // ---- ProjectTaxonomyBuilder (glpi_projecttasktypes) ----
        'Analyse & Cadrage' => ['en_GB' => 'Analysis & Scoping', 'de_DE' => 'Analyse & Rahmenplanung', 'it_IT' => "Analisi e Definizione dell'ambito", 'es_ES' => 'Análisis y Definición del alcance'],
        'Conception' => ['en_GB' => 'Design', 'de_DE' => 'Konzeption', 'it_IT' => 'Progettazione', 'es_ES' => 'Diseño'],
        'Développement' => ['en_GB' => 'Development', 'de_DE' => 'Entwicklung', 'it_IT' => 'Sviluppo', 'es_ES' => 'Desarrollo'],
        'Tests & Recette' => ['en_GB' => 'Testing & Acceptance', 'de_DE' => 'Test & Abnahme', 'it_IT' => 'Test e Collaudo', 'es_ES' => 'Pruebas y Aceptación'],
        'Déploiement' => ['en_GB' => 'Deployment', 'de_DE' => 'Bereitstellung', 'it_IT' => 'Distribuzione', 'es_ES' => 'Despliegue'],
        'Réunion & Pilotage' => ['en_GB' => 'Meeting & Steering', 'de_DE' => 'Besprechung & Steuerung', 'it_IT' => 'Riunione e Pilotaggio', 'es_ES' => 'Reunión y Seguimiento'],

        // ---- ProjectTaskTemplateBuilder (glpi_projecttasktemplates) ----
        'Cadrage initial' => ['en_GB' => 'Initial scoping', 'de_DE' => 'Erste Rahmenplanung', 'it_IT' => 'Definizione iniziale dell\'ambito', 'es_ES' => 'Definición inicial del alcance'],
        'Point d\'avancement' => ['en_GB' => 'Progress check-in', 'de_DE' => 'Fortschrittsbesprechung', 'it_IT' => 'Punto di avanzamento', 'es_ES' => 'Punto de avance'],
        'Revue de clôture' => ['en_GB' => 'Closure review', 'de_DE' => 'Abschlussbewertung', 'it_IT' => 'Revisione di chiusura', 'es_ES' => 'Revisión de cierre'],

        // ---- SolutionLibraryBuilder (glpi_solutiontypes) ----
        'Assistance / Support utilisateur' => ['en_GB' => 'Assistance / User support', 'de_DE' => 'Unterstützung / Benutzer-Support', 'it_IT' => 'Assistenza / Supporto utente', 'es_ES' => 'Asistencia / Soporte al usuario'],
        'Résolution technique' => ['en_GB' => 'Technical resolution', 'de_DE' => 'Technische Lösung', 'it_IT' => 'Risoluzione tecnica', 'es_ES' => 'Resolución técnica'],
        'Sécurité' => ['en_GB' => 'Security', 'de_DE' => 'Sicherheit', 'it_IT' => 'Sicurezza', 'es_ES' => 'Seguridad'],
        'Informationnel' => ['en_GB' => 'Informational', 'de_DE' => 'Informativ', 'it_IT' => 'Informativo', 'es_ES' => 'Informativo'],
        'Gestion des accès' => ['en_GB' => 'Access management', 'de_DE' => 'Zugriffsverwaltung', 'it_IT' => 'Gestione degli accessi', 'es_ES' => 'Gestión de accesos'],

        // ---- TicketTemplateBuilder (glpi_tickettemplates) ----
        'Ticket simplifié (libre-service)' => ['en_GB' => 'Simplified ticket (self-service)', 'de_DE' => 'Vereinfachtes Ticket (Self-Service)', 'it_IT' => 'Ticket semplificato (self-service)', 'es_ES' => 'Ticket simplificado (autoservicio)'],
        'Ticket complet (support)' => ['en_GB' => 'Full ticket (support)', 'de_DE' => 'Vollständiges Ticket (Support)', 'it_IT' => 'Ticket completo (supporto)', 'es_ES' => 'Ticket completo (soporte)'],

        // ---- ChangeProblemTemplateBuilder (glpi_changetemplates / glpi_problemtemplates) ----
        'Changement standard' => ['en_GB' => 'Standard change', 'de_DE' => 'Standardänderung', 'it_IT' => 'Cambiamento standard', 'es_ES' => 'Cambio estándar'],
        'Problème standard' => ['en_GB' => 'Standard problem', 'de_DE' => 'Standardproblem', 'it_IT' => 'Problema standard', 'es_ES' => 'Problema estándar'],

        // ---- TaskTemplateBuilder (glpi_tasktemplates) ----
        'Onboarding — Arrivée collaborateur' => ['en_GB' => 'Onboarding — New employee', 'de_DE' => 'Onboarding — Neuer Mitarbeiter', 'it_IT' => 'Onboarding — Nuovo dipendente', 'es_ES' => 'Incorporación — Nuevo empleado'],
        'Offboarding — Départ collaborateur' => ['en_GB' => 'Offboarding — Employee departure', 'de_DE' => 'Offboarding — Austritt Mitarbeiter', 'it_IT' => 'Offboarding — Uscita dipendente', 'es_ES' => 'Baja — Salida de empleado'],

        // ---- FollowupLibraryBuilder (glpi_itilfollowuptemplates) ----
        'Relance — informations complémentaires demandées' => ['en_GB' => 'Follow-up — additional information requested', 'de_DE' => 'Nachfrage — zusätzliche Informationen angefordert', 'it_IT' => 'Sollecito — informazioni aggiuntive richieste', 'es_ES' => 'Recordatorio — información adicional solicitada'],
        'Notification — commande ou livraison en cours' => ['en_GB' => 'Notification — order or delivery in progress', 'de_DE' => 'Benachrichtigung — Bestellung oder Lieferung läuft', 'it_IT' => 'Notifica — ordine o consegna in corso', 'es_ES' => 'Notificación — pedido o entrega en curso'],
        'Notification — escalade fournisseur' => ['en_GB' => 'Notification — vendor escalation', 'de_DE' => 'Benachrichtigung — Eskalation an Lieferant', 'it_IT' => 'Notifica — escalation al fornitore', 'es_ES' => 'Notificación — escalado al proveedor'],
        'Notification — intervention planifiée' => ['en_GB' => 'Notification — scheduled intervention', 'de_DE' => 'Benachrichtigung — geplanter Eingriff', 'it_IT' => 'Notifica — intervento pianificato', 'es_ES' => 'Notificación — intervención programada'],
        'Notification — validation en cours' => ['en_GB' => 'Notification — approval in progress', 'de_DE' => 'Benachrichtigung — Freigabe läuft', 'it_IT' => 'Notifica — approvazione in corso', 'es_ES' => 'Notificación — aprobación en curso'],

        // ---- ValidationTemplateBuilder (glpi_itilvalidationtemplates) ----
        'Validation hiérarchique (N+1)' => ['en_GB' => 'Hierarchical approval (line manager)', 'de_DE' => 'Hierarchische Freigabe (Vorgesetzter)', 'it_IT' => 'Approvazione gerarchica (responsabile)', 'es_ES' => 'Aprobación jerárquica (responsable directo)'],
        'Validation technique' => ['en_GB' => 'Technical approval', 'de_DE' => 'Technische Freigabe', 'it_IT' => 'Approvazione tecnica', 'es_ES' => 'Aprobación técnica'],
        'Validation comité' => ['en_GB' => 'Committee approval', 'de_DE' => 'Ausschussfreigabe', 'it_IT' => 'Approvazione del comitato', 'es_ES' => 'Aprobación del comité'],
        'Validation sécurité' => ['en_GB' => 'Security approval', 'de_DE' => 'Sicherheitsfreigabe', 'it_IT' => 'Approvazione sicurezza', 'es_ES' => 'Aprobación de seguridad'],
        'Validation simple' => ['en_GB' => 'Simple approval', 'de_DE' => 'Einfache Freigabe', 'it_IT' => 'Approvazione semplice', 'es_ES' => 'Aprobación simple'],

        // ---- SolutionLibraryBuilder (glpi_solutiontemplates) ----
        'Accompagnement utilisateur réalisé' => ['en_GB' => 'User guided through the issue', 'de_DE' => 'Benutzer begleitet', 'it_IT' => 'Utente assistito passo-passo', 'es_ES' => 'Usuario acompañado paso a paso'],
        'Formation dispensée' => ['en_GB' => 'Training provided', 'de_DE' => 'Schulung durchgeführt', 'it_IT' => 'Formazione erogata', 'es_ES' => 'Formación impartida'],
        'Résolution technique appliquée' => ['en_GB' => 'Technical fix applied', 'de_DE' => 'Technische Lösung angewendet', 'it_IT' => 'Risoluzione tecnica applicata', 'es_ES' => 'Resolución técnica aplicada'],
        'Remplacement de matériel effectué' => ['en_GB' => 'Hardware replaced', 'de_DE' => 'Hardware ersetzt', 'it_IT' => 'Hardware sostituito', 'es_ES' => 'Hardware sustituido'],
        'Confinement et éradication de la menace' => ['en_GB' => 'Threat contained and eradicated', 'de_DE' => 'Bedrohung eingedämmt und beseitigt', 'it_IT' => 'Minaccia contenuta ed eliminata', 'es_ES' => 'Amenaza contenida y erradicada'],
        'Application de correctifs de sécurité' => ['en_GB' => 'Security patches applied', 'de_DE' => 'Sicherheitspatches angewendet', 'it_IT' => 'Patch di sicurezza applicate', 'es_ES' => 'Parches de seguridad aplicados'],
        'Fonctionnement normal constaté' => ['en_GB' => 'Normal behavior confirmed', 'de_DE' => 'Normales Verhalten festgestellt', 'it_IT' => 'Funzionamento normale confermato', 'es_ES' => 'Funcionamiento normal confirmado'],
        'Ticket doublon' => ['en_GB' => 'Duplicate ticket', 'de_DE' => 'Doppeltes Ticket', 'it_IT' => 'Ticket duplicato', 'es_ES' => 'Ticket duplicado'],
        'Compte créé ou modifié' => ['en_GB' => 'Account created or modified', 'de_DE' => 'Konto erstellt oder geändert', 'it_IT' => 'Account creato o modificato', 'es_ES' => 'Cuenta creada o modificada'],
        'Mot de passe réinitialisé' => ['en_GB' => 'Password reset', 'de_DE' => 'Passwort zurückgesetzt', 'it_IT' => 'Password reimpostata', 'es_ES' => 'Contraseña restablecida'],

        // ---- SupportTierBuilder (glpi_groups) ----
        'Support N1' => ['en_GB' => 'Support N1', 'de_DE' => 'Support N1', 'it_IT' => 'Supporto N1', 'es_ES' => 'Soporte N1'],
        'Support N2' => ['en_GB' => 'Support N2', 'de_DE' => 'Support N2', 'it_IT' => 'Supporto N2', 'es_ES' => 'Soporte N2'],
        'Support N3' => ['en_GB' => 'Support N3', 'de_DE' => 'Support N3', 'it_IT' => 'Supporto N3', 'es_ES' => 'Soporte N3'],

        // ---- CategoryBuilder (glpi_itilcategories) — top-level branches ----
        'IT & SI' => ['en_GB' => 'IT & Information Systems', 'de_DE' => 'IT & Informationssysteme', 'it_IT' => 'IT e Sistemi Informativi', 'es_ES' => 'TI y Sistemas de Información'],
        'Bâtiment & Moyens Généraux' => ['en_GB' => 'Facilities & General Services', 'de_DE' => 'Gebäude & Allgemeine Dienste', 'it_IT' => 'Edificio e Servizi Generali', 'es_ES' => 'Edificio y Servicios Generales'],
        'Flotte Automobile & Mobilité' => ['en_GB' => 'Vehicle Fleet & Mobility', 'de_DE' => 'Fuhrpark & Mobilität', 'it_IT' => 'Flotta Auto e Mobilità', 'es_ES' => 'Flota de Vehículos y Movilidad'],
        'Ressources Humaines' => ['en_GB' => 'Human Resources', 'de_DE' => 'Personalwesen', 'it_IT' => 'Risorse Umane', 'es_ES' => 'Recursos Humanos'],
        'Achats & Logistique' => ['en_GB' => 'Purchasing & Logistics', 'de_DE' => 'Einkauf & Logistik', 'it_IT' => 'Acquisti e Logistica', 'es_ES' => 'Compras y Logística'],
        'Sécurité & Protection des Personnes' => ['en_GB' => 'Security & Personal Safety', 'de_DE' => 'Sicherheit & Personenschutz', 'it_IT' => 'Sicurezza e Protezione delle Persone', 'es_ES' => 'Seguridad y Protección de las Personas'],
        'Services Généraux & Vie au Travail' => ['en_GB' => 'General Services & Workplace Life', 'de_DE' => 'Allgemeine Dienste & Arbeitsleben', 'it_IT' => 'Servizi Generali e Vita Lavorativa', 'es_ES' => 'Servicios Generales y Vida Laboral'],
        'Administratif, Juridique & Finance' => ['en_GB' => 'Administration, Legal & Finance', 'de_DE' => 'Verwaltung, Recht & Finanzen', 'it_IT' => 'Amministrazione, Legale e Finanza', 'es_ES' => 'Administración, Legal y Finanzas'],
        'Communication & Marketing' => ['en_GB' => 'Communication & Marketing', 'de_DE' => 'Kommunikation & Marketing', 'it_IT' => 'Comunicazione e Marketing', 'es_ES' => 'Comunicación y Marketing'],
        'Qualité, QHSE & Conformité' => ['en_GB' => 'Quality, HSE & Compliance', 'de_DE' => 'Qualität, HSE & Compliance', 'it_IT' => 'Qualità, HSE e Conformità', 'es_ES' => 'Calidad, HSE y Cumplimiento'],
        'Maintenance Industrielle & Technique' => ['en_GB' => 'Industrial & Technical Maintenance', 'de_DE' => 'Industrielle & Technische Wartung', 'it_IT' => 'Manutenzione Industriale e Tecnica', 'es_ES' => 'Mantenimiento Industrial y Técnico'],

        // ---- IT & SI branch (levels 2-3) ----
        'Poste de travail' => ['en_GB' => 'Workstation', 'de_DE' => 'Arbeitsplatz', 'it_IT' => 'Postazione di lavoro', 'es_ES' => 'Puesto de trabajo'],
        'Impression' => ['en_GB' => 'Printing', 'de_DE' => 'Drucken', 'it_IT' => 'Stampa', 'es_ES' => 'Impresión'],
        'Logiciels & Applications' => ['en_GB' => 'Software & Applications', 'de_DE' => 'Software & Anwendungen', 'it_IT' => 'Software e Applicazioni', 'es_ES' => 'Software y Aplicaciones'],
        'Messagerie & Collaboration' => ['en_GB' => 'Messaging & Collaboration', 'de_DE' => 'Kommunikation & Zusammenarbeit', 'it_IT' => 'Messaggistica e Collaborazione', 'es_ES' => 'Mensajería y Colaboración'],
        'Comptes & Identités' => ['en_GB' => 'Accounts & Identities', 'de_DE' => 'Konten & Identitäten', 'it_IT' => 'Account e Identità', 'es_ES' => 'Cuentas e Identidades'],
        'Réseau & Connectivité' => ['en_GB' => 'Network & Connectivity', 'de_DE' => 'Netzwerk & Konnektivität', 'it_IT' => 'Rete e Connettività', 'es_ES' => 'Red y Conectividad'],
        'Téléphonie & VoIP' => ['en_GB' => 'Telephony & VoIP', 'de_DE' => 'Telefonie & VoIP', 'it_IT' => 'Telefonia e VoIP', 'es_ES' => 'Telefonía y VoIP'],
        'Infrastructure & Serveurs' => ['en_GB' => 'Infrastructure & Servers', 'de_DE' => 'Infrastruktur & Server', 'it_IT' => 'Infrastruttura e Server', 'es_ES' => 'Infraestructura y Servidores'],
        'Sauvegardes & Restauration' => ['en_GB' => 'Backups & Restoration', 'de_DE' => 'Sicherung & Wiederherstellung', 'it_IT' => 'Backup e Ripristino', 'es_ES' => 'Copias de seguridad y Restauración'],
        'Supervision & Alertes' => ['en_GB' => 'Monitoring & Alerts', 'de_DE' => 'Überwachung & Warnmeldungen', 'it_IT' => 'Monitoraggio e Avvisi', 'es_ES' => 'Supervisión y Alertas'],
        'Sécurité SI' => ['en_GB' => 'IT Security', 'de_DE' => 'IT-Sicherheit', 'it_IT' => 'Sicurezza IT', 'es_ES' => 'Seguridad TI'],

        'Ordinateur fixe' => ['en_GB' => 'Desktop computer', 'de_DE' => 'Desktop-Computer', 'it_IT' => 'Computer fisso', 'es_ES' => 'Ordenador de sobremesa'],
        'Portable' => ['en_GB' => 'Laptop', 'de_DE' => 'Laptop', 'it_IT' => 'Portatile', 'es_ES' => 'Portátil'],
        'Station de travail' => ['en_GB' => 'High-performance workstation', 'de_DE' => 'Hochleistungs-Workstation', 'it_IT' => 'Workstation ad alte prestazioni', 'es_ES' => 'Estación de trabajo de altas prestaciones'],
        'Écran & Affichage' => ['en_GB' => 'Monitor & Display', 'de_DE' => 'Bildschirm & Anzeige', 'it_IT' => 'Monitor e Display', 'es_ES' => 'Monitor y Pantalla'],
        'Accessoires' => ['en_GB' => 'Accessories', 'de_DE' => 'Zubehör', 'it_IT' => 'Accessori', 'es_ES' => 'Accesorios'],

        'Imprimante / Copieur' => ['en_GB' => 'Printer / Copier', 'de_DE' => 'Drucker / Kopierer', 'it_IT' => 'Stampante / Fotocopiatrice', 'es_ES' => 'Impresora / Fotocopiadora'],
        'Scanner' => ['en_GB' => 'Scanner', 'de_DE' => 'Scanner', 'it_IT' => 'Scanner', 'es_ES' => 'Escáner'],
        'Incidents matériel' => ['en_GB' => 'Hardware issues', 'de_DE' => 'Hardware-Störungen', 'it_IT' => 'Problemi hardware', 'es_ES' => 'Incidencias de hardware'],

        'Installation / Désinstallation' => ['en_GB' => 'Installation / Uninstallation', 'de_DE' => 'Installation / Deinstallation', 'it_IT' => 'Installazione / Disinstallazione', 'es_ES' => 'Instalación / Desinstalación'],
        'Mise à jour & Patch' => ['en_GB' => 'Update & Patch', 'de_DE' => 'Update & Patch', 'it_IT' => 'Aggiornamento e Patch', 'es_ES' => 'Actualización y Parche'],
        'Licences & Clés' => ['en_GB' => 'Licenses & Keys', 'de_DE' => 'Lizenzen & Schlüssel', 'it_IT' => 'Licenze e Chiavi', 'es_ES' => 'Licencias y Claves'],
        'Bug / Dysfonctionnement' => ['en_GB' => 'Bug / Malfunction', 'de_DE' => 'Fehler / Fehlfunktion', 'it_IT' => 'Bug / Malfunzionamento', 'es_ES' => 'Error / Fallo de funcionamiento'],

        'Messagerie' => ['en_GB' => 'Email', 'de_DE' => 'E-Mail', 'it_IT' => 'Posta elettronica', 'es_ES' => 'Correo electrónico'],
        'Collaboration' => ['en_GB' => 'Collaboration', 'de_DE' => 'Zusammenarbeit', 'it_IT' => 'Collaborazione', 'es_ES' => 'Colaboración'],
        'Bureautique' => ['en_GB' => 'Office software', 'de_DE' => 'Bürosoftware', 'it_IT' => 'Software per ufficio', 'es_ES' => 'Software de ofimática'],
        'Business Intelligence' => ['en_GB' => 'Business Intelligence', 'de_DE' => 'Business Intelligence', 'it_IT' => 'Business Intelligence', 'es_ES' => 'Inteligencia de negocio'],

        'Onboarding / Création de compte' => ['en_GB' => 'Onboarding / Account creation', 'de_DE' => 'Onboarding / Kontoerstellung', 'it_IT' => 'Onboarding / Creazione account', 'es_ES' => 'Incorporación / Creación de cuenta'],
        'Offboarding / Suppression' => ['en_GB' => 'Offboarding / Deletion', 'de_DE' => 'Offboarding / Löschung', 'it_IT' => 'Offboarding / Eliminazione', 'es_ES' => 'Baja / Eliminación'],
        'Mot de passe & Réinitialisation' => ['en_GB' => 'Password & Reset', 'de_DE' => 'Passwort & Zurücksetzen', 'it_IT' => 'Password e Reimpostazione', 'es_ES' => 'Contraseña y Restablecimiento'],
        'Authentification forte' => ['en_GB' => 'Strong authentication', 'de_DE' => 'Starke Authentifizierung', 'it_IT' => 'Autenticazione forte', 'es_ES' => 'Autenticación fuerte'],
        'Droits, Rôles & Groupes AD' => ['en_GB' => 'Rights, Roles & AD Groups', 'de_DE' => 'Rechte, Rollen & AD-Gruppen', 'it_IT' => 'Diritti, Ruoli e Gruppi AD', 'es_ES' => 'Derechos, Roles y Grupos AD'],

        'Wifi' => ['en_GB' => 'Wi-Fi', 'de_DE' => 'WLAN', 'it_IT' => 'Wi-Fi', 'es_ES' => 'Wi-Fi'],
        'Filaire' => ['en_GB' => 'Wired network', 'de_DE' => 'Kabelgebundenes Netzwerk', 'it_IT' => 'Rete cablata', 'es_ES' => 'Red cableada'],
        'Accès Distant' => ['en_GB' => 'Remote access', 'de_DE' => 'Fernzugriff', 'it_IT' => 'Accesso remoto', 'es_ES' => 'Acceso remoto'],
        'Lien Internet & WAN' => ['en_GB' => 'Internet link & WAN', 'de_DE' => 'Internetanbindung & WAN', 'it_IT' => 'Collegamento Internet e WAN', 'es_ES' => 'Enlace a Internet y WAN'],
        'Services Réseau' => ['en_GB' => 'Network services', 'de_DE' => 'Netzwerkdienste', 'it_IT' => 'Servizi di rete', 'es_ES' => 'Servicios de red'],

        'Téléphone fixe / IP' => ['en_GB' => 'Desk phone / IP phone', 'de_DE' => 'Festnetztelefon / IP-Telefon', 'it_IT' => 'Telefono fisso / IP', 'es_ES' => 'Teléfono fijo / IP'],
        'Smartphone & Flotte mobile' => ['en_GB' => 'Smartphone & Mobile fleet', 'de_DE' => 'Smartphone & Mobilgeräteflotte', 'it_IT' => 'Smartphone e Flotta mobile', 'es_ES' => 'Smartphone y Flota móvil'],
        'Carte SIM & Forfaits' => ['en_GB' => 'SIM card & Plans', 'de_DE' => 'SIM-Karte & Tarife', 'it_IT' => 'Scheda SIM e Piani tariffari', 'es_ES' => 'Tarjeta SIM y Tarifas'],
        'Messagerie vocale & SVI' => ['en_GB' => 'Voicemail & IVR', 'de_DE' => 'Sprachbox & IVR', 'it_IT' => 'Segreteria telefonica e IVR', 'es_ES' => 'Buzón de voz e IVR'],

        'Antivirus & Endpoint' => ['en_GB' => 'Antivirus & Endpoint', 'de_DE' => 'Antivirus & Endpoint', 'it_IT' => 'Antivirus ed Endpoint', 'es_ES' => 'Antivirus y Endpoint'],
        'Phishing & Mails suspects' => ['en_GB' => 'Phishing & Suspicious emails', 'de_DE' => 'Phishing & Verdächtige E-Mails', 'it_IT' => 'Phishing e Email sospette', 'es_ES' => 'Phishing y Correos sospechosos'],
        'Indisponibilité / Malware / Ransomware' => ['en_GB' => 'Outage / Malware / Ransomware', 'de_DE' => 'Ausfall / Malware / Ransomware', 'it_IT' => 'Interruzione / Malware / Ransomware', 'es_ES' => 'Interrupción / Malware / Ransomware'],
        'Compte compromis / Fuite de données' => ['en_GB' => 'Compromised account / Data leak', 'de_DE' => 'Kompromittiertes Konto / Datenleck', 'it_IT' => 'Account compromesso / Fuga di dati', 'es_ES' => 'Cuenta comprometida / Fuga de datos'],
        'Certificats SSL' => ['en_GB' => 'SSL certificates', 'de_DE' => 'SSL-Zertifikate', 'it_IT' => 'Certificati SSL', 'es_ES' => 'Certificados SSL'],
        'Vulnérabilités' => ['en_GB' => 'Vulnerabilities', 'de_DE' => 'Schwachstellen', 'it_IT' => 'Vulnerabilità', 'es_ES' => 'Vulnerabilidades'],

        // ---- Bâtiment & Moyens Généraux branch ----
        'CVC' => ['en_GB' => 'HVAC', 'de_DE' => 'HLK', 'it_IT' => 'HVAC', 'es_ES' => 'Climatización (HVAC)'],
        'Électricité & Éclairage' => ['en_GB' => 'Electricity & Lighting', 'de_DE' => 'Elektrizität & Beleuchtung', 'it_IT' => 'Elettricità e Illuminazione', 'es_ES' => 'Electricidad e Iluminación'],
        'Plomberie & Sanitaires' => ['en_GB' => 'Plumbing & Restrooms', 'de_DE' => 'Sanitär & Toiletten', 'it_IT' => 'Idraulica e Servizi igienici', 'es_ES' => 'Fontanería y Aseos'],
        'Serrurerie, Portes & Fenêtres' => ['en_GB' => 'Locksmithing, Doors & Windows', 'de_DE' => 'Schließanlagen, Türen & Fenster', 'it_IT' => 'Serrature, Porte e Finestre', 'es_ES' => 'Cerrajería, Puertas y Ventanas'],
        'Ascenseurs & Monte-charges' => ['en_GB' => 'Elevators & Freight lifts', 'de_DE' => 'Aufzüge & Lastenaufzüge', 'it_IT' => 'Ascensori e Montacarichi', 'es_ES' => 'Ascensores y Montacargas'],
        'Mobilier & Aménagement' => ['en_GB' => 'Furniture & Layout', 'de_DE' => 'Möbel & Einrichtung', 'it_IT' => 'Arredi e Allestimento', 'es_ES' => 'Mobiliario y Distribución'],
        'Salles de réunion & Équipements' => ['en_GB' => 'Meeting rooms & Equipment', 'de_DE' => 'Besprechungsräume & Ausstattung', 'it_IT' => 'Sale riunioni e Attrezzature', 'es_ES' => 'Salas de reuniones y Equipamiento'],
        'Signalétique & Affichage' => ['en_GB' => 'Signage & Display', 'de_DE' => 'Beschilderung & Aushang', 'it_IT' => 'Segnaletica e Affissioni', 'es_ES' => 'Señalización y Cartelería'],
        'Prestations & Hygiène' => ['en_GB' => 'Services & Hygiene', 'de_DE' => 'Dienstleistungen & Hygiene', 'it_IT' => 'Prestazioni e Igiene', 'es_ES' => 'Prestaciones e Higiene'],
        'Propreté & Nettoyage' => ['en_GB' => 'Cleanliness & Cleaning', 'de_DE' => 'Sauberkeit & Reinigung', 'it_IT' => 'Pulizia e Igiene', 'es_ES' => 'Limpieza e Higiene'],
        'Espaces verts & Extérieurs' => ['en_GB' => 'Green spaces & Outdoor areas', 'de_DE' => 'Grünflächen & Außenbereiche', 'it_IT' => 'Aree verdi ed Esterni', 'es_ES' => 'Zonas verdes y Exteriores'],

        // ---- Flotte Automobile & Mobilité branch ----
        'Entretien & Réparation' => ['en_GB' => 'Maintenance & Repair', 'de_DE' => 'Wartung & Reparatur', 'it_IT' => 'Manutenzione e Riparazione', 'es_ES' => 'Mantenimiento y Reparación'],
        'Sinistres & Carrosserie' => ['en_GB' => 'Accidents & Bodywork', 'de_DE' => 'Schäden & Karosserie', 'it_IT' => 'Sinistri e Carrozzeria', 'es_ES' => 'Siniestros y Carrocería'],
        'Carburant & Recharge' => ['en_GB' => 'Fuel & Charging', 'de_DE' => 'Kraftstoff & Aufladen', 'it_IT' => 'Carburante e Ricarica', 'es_ES' => 'Combustible y Recarga'],
        'Conformité & Règlements' => ['en_GB' => 'Compliance & Regulations', 'de_DE' => 'Konformität & Vorschriften', 'it_IT' => 'Conformità e Normative', 'es_ES' => 'Conformidad y Normativa'],
        'Lavage & Nettoyage' => ['en_GB' => 'Washing & Cleaning', 'de_DE' => 'Waschen & Reinigen', 'it_IT' => 'Lavaggio e Pulizia', 'es_ES' => 'Lavado y Limpieza'],

        // ---- Ressources Humaines branch ----
        'Mouvements de personnel' => ['en_GB' => 'Staff movements', 'de_DE' => 'Personalbewegungen', 'it_IT' => 'Movimenti del personale', 'es_ES' => 'Movimientos de personal'],
        'Formation & Montée en compétences' => ['en_GB' => 'Training & Upskilling', 'de_DE' => 'Schulung & Kompetenzaufbau', 'it_IT' => 'Formazione e Sviluppo competenze', 'es_ES' => 'Formación y Desarrollo de competencias'],
        'Absences & Congés' => ['en_GB' => 'Absences & Leave', 'de_DE' => 'Abwesenheiten & Urlaub', 'it_IT' => 'Assenze e Ferie', 'es_ES' => 'Ausencias y Vacaciones'],
        'Organisation du travail' => ['en_GB' => 'Work organization', 'de_DE' => 'Arbeitsorganisation', 'it_IT' => 'Organizzazione del lavoro', 'es_ES' => 'Organización del trabajo'],
        'Administration RH' => ['en_GB' => 'HR administration', 'de_DE' => 'Personalverwaltung', 'it_IT' => 'Amministrazione HR', 'es_ES' => 'Administración de RRHH'],

        // ---- Achats & Logistique branch ----
        'Sourcing & Commande' => ['en_GB' => 'Sourcing & Ordering', 'de_DE' => 'Beschaffung & Bestellung', 'it_IT' => 'Sourcing e Ordini', 'es_ES' => 'Sourcing y Pedidos'],
        'Réception, Livraison & Expédition' => ['en_GB' => 'Receiving, Delivery & Shipping', 'de_DE' => 'Wareneingang, Lieferung & Versand', 'it_IT' => 'Ricezione, Consegna e Spedizione', 'es_ES' => 'Recepción, Entrega y Envío'],
        'Retours, SAV & Garanties' => ['en_GB' => 'Returns, After-sales & Warranties', 'de_DE' => 'Rücksendungen, Kundendienst & Garantien', 'it_IT' => 'Resi, Assistenza post-vendita e Garanzie', 'es_ES' => 'Devoluciones, Posventa y Garantías'],
        'Gestion des Stocks & Inventaire' => ['en_GB' => 'Stock & Inventory management', 'de_DE' => 'Bestands- & Inventarverwaltung', 'it_IT' => 'Gestione Scorte e Inventario', 'es_ES' => 'Gestión de Existencias e Inventario'],
        'Déménagement & Archivage' => ['en_GB' => 'Relocation & Archiving', 'de_DE' => 'Umzug & Archivierung', 'it_IT' => 'Trasloco e Archiviazione', 'es_ES' => 'Mudanza y Archivo'],

        // ---- Sécurité & Protection des Personnes branch ----
        "Contrôle d'Accès & Badges" => ['en_GB' => 'Access control & Badges', 'de_DE' => 'Zutrittskontrolle & Ausweise', 'it_IT' => 'Controllo Accessi e Badge', 'es_ES' => 'Control de Accesos y Tarjetas'],
        'Vidéosurveillance & Alarmes' => ['en_GB' => 'CCTV & Alarms', 'de_DE' => 'Videoüberwachung & Alarmanlagen', 'it_IT' => 'Videosorveglianza e Allarmi', 'es_ES' => 'Videovigilancia y Alarmas'],
        'Gestion des Incidents & Urgences' => ['en_GB' => 'Incident & Emergency management', 'de_DE' => 'Vorfall- & Notfallmanagement', 'it_IT' => 'Gestione Incidenti ed Emergenze', 'es_ES' => 'Gestión de Incidentes y Emergencias'],
        'Santé & Sécurité au Travail' => ['en_GB' => 'Occupational Health & Safety', 'de_DE' => 'Arbeitsschutz & Sicherheit', 'it_IT' => 'Salute e Sicurezza sul Lavoro', 'es_ES' => 'Salud y Seguridad en el Trabajo'],

        // ---- Services Généraux & Vie au Travail branch ----
        'Consommables & Fournitures' => ['en_GB' => 'Consumables & Supplies', 'de_DE' => 'Verbrauchsmaterial & Bürobedarf', 'it_IT' => 'Materiali di consumo e Forniture', 'es_ES' => 'Consumibles y Suministros'],
        'Pause & Restauration' => ['en_GB' => 'Break & Catering', 'de_DE' => 'Pause & Verpflegung', 'it_IT' => 'Pausa e Ristorazione', 'es_ES' => 'Descanso y Restauración'],
        'RSE & Recyclage' => ['en_GB' => 'CSR & Recycling', 'de_DE' => 'CSR & Recycling', 'it_IT' => 'RSI e Riciclo', 'es_ES' => 'RSC y Reciclaje'],

        // ---- Administratif, Juridique & Finance branch ----
        'Finance & Comptabilité' => ['en_GB' => 'Finance & Accounting', 'de_DE' => 'Finanzen & Buchhaltung', 'it_IT' => 'Finanza e Contabilità', 'es_ES' => 'Finanzas y Contabilidad'],
        'Juridique & Contrats' => ['en_GB' => 'Legal & Contracts', 'de_DE' => 'Recht & Verträge', 'it_IT' => 'Legale e Contratti', 'es_ES' => 'Legal y Contratos'],
        'Courrier & Reprographie' => ['en_GB' => 'Mail & Reprographics', 'de_DE' => 'Post & Reprografie', 'it_IT' => 'Posta e Riproduzione documenti', 'es_ES' => 'Correo y Reprografía'],

        // ---- Communication & Marketing branch ----
        'Site Web & Intranet' => ['en_GB' => 'Website & Intranet', 'de_DE' => 'Website & Intranet', 'it_IT' => 'Sito Web e Intranet', 'es_ES' => 'Sitio Web e Intranet'],
        'Réseaux Sociaux & Marketing' => ['en_GB' => 'Social Media & Marketing', 'de_DE' => 'Soziale Medien & Marketing', 'it_IT' => 'Social Media e Marketing', 'es_ES' => 'Redes Sociales y Marketing'],
        'Événementiel & Affichage' => ['en_GB' => 'Events & Signage', 'de_DE' => 'Veranstaltungen & Aushang', 'it_IT' => 'Eventi e Affissioni', 'es_ES' => 'Eventos y Cartelería'],

        // ---- Qualité, QHSE & Conformité branch ----
        'Normes & Certifications' => ['en_GB' => 'Standards & Certifications', 'de_DE' => 'Normen & Zertifizierungen', 'it_IT' => 'Norme e Certificazioni', 'es_ES' => 'Normas y Certificaciones'],
        'Audits & Contrôles' => ['en_GB' => 'Audits & Controls', 'de_DE' => 'Audits & Kontrollen', 'it_IT' => 'Audit e Controlli', 'es_ES' => 'Auditorías y Controles'],
        'Non-conformités & Actions' => ['en_GB' => 'Non-conformities & Actions', 'de_DE' => 'Abweichungen & Maßnahmen', 'it_IT' => 'Non conformità e Azioni', 'es_ES' => 'No conformidades y Acciones'],

        // ---- Maintenance Industrielle & Technique branch ----
        'Maintenance Préventive & Contrôles' => ['en_GB' => 'Preventive maintenance & Checks', 'de_DE' => 'Vorbeugende Wartung & Prüfungen', 'it_IT' => 'Manutenzione Preventiva e Controlli', 'es_ES' => 'Mantenimiento Preventivo y Controles'],
        'Maintenance Curative' => ['en_GB' => 'Corrective maintenance', 'de_DE' => 'Instandsetzung', 'it_IT' => 'Manutenzione Correttiva', 'es_ES' => 'Mantenimiento Correctivo'],
        'Étalonnage & Métrologie' => ['en_GB' => 'Calibration & Metrology', 'de_DE' => 'Kalibrierung & Messtechnik', 'it_IT' => 'Taratura e Metrologia', 'es_ES' => 'Calibración y Metrología'],
        'Pièces Détachées & Intervenants Externe' => ['en_GB' => 'Spare parts & External contractors', 'de_DE' => 'Ersatzteile & Externe Dienstleister', 'it_IT' => 'Ricambi e Fornitori esterni', 'es_ES' => 'Repuestos y Proveedores externos'],

        // ---- DocumentManagementBuilder (glpi_documentcategories) ----
        'Public' => ['en_GB' => 'Public', 'de_DE' => 'Öffentlich', 'it_IT' => 'Pubblico', 'es_ES' => 'Público'],
        'Confidentiel' => ['en_GB' => 'Confidential', 'de_DE' => 'Vertraulich', 'it_IT' => 'Riservato', 'es_ES' => 'Confidencial'],
        'Diffusion restreinte' => ['en_GB' => 'Restricted', 'de_DE' => 'Eingeschränkte Verteilung', 'it_IT' => 'Diffusione limitata', 'es_ES' => 'Difusión restringida'],

        // ---- DocumentManagementBuilder (glpi_businesscriticities) ----
        'Critique' => ['en_GB' => 'Critical', 'de_DE' => 'Kritisch', 'it_IT' => 'Critico', 'es_ES' => 'Crítico'],
        'Élevée' => ['en_GB' => 'High', 'de_DE' => 'Hoch', 'it_IT' => 'Alta', 'es_ES' => 'Alta'],
        'Moyenne' => ['en_GB' => 'Medium', 'de_DE' => 'Mittel', 'it_IT' => 'Media', 'es_ES' => 'Media'],
        'Faible' => ['en_GB' => 'Low', 'de_DE' => 'Niedrig', 'it_IT' => 'Bassa', 'es_ES' => 'Baja'],

        // ---- PlanningEventBuilder (glpi_planningeventcategories) ----
        'Réunion' => ['en_GB' => 'Meeting', 'de_DE' => 'Besprechung', 'it_IT' => 'Riunione', 'es_ES' => 'Reunión'],
        'Maintenance planifiée' => ['en_GB' => 'Scheduled maintenance', 'de_DE' => 'Geplante Wartung', 'it_IT' => 'Manutenzione pianificata', 'es_ES' => 'Mantenimiento programado'],
        'Congés / Absences' => ['en_GB' => 'Leave / Absences', 'de_DE' => 'Urlaub / Abwesenheiten', 'it_IT' => 'Ferie / Assenze', 'es_ES' => 'Vacaciones / Ausencias'],
        'Astreinte / Garde' => ['en_GB' => 'On-call duty', 'de_DE' => 'Bereitschaftsdienst', 'it_IT' => 'Reperibilità', 'es_ES' => 'Guardia'],

        // ---- UserCategoryBuilder (glpi_usercategories) ----
        'Employé' => ['en_GB' => 'Employee', 'de_DE' => 'Mitarbeiter', 'it_IT' => 'Dipendente', 'es_ES' => 'Empleado'],
        'Prestataire externe' => ['en_GB' => 'External contractor', 'de_DE' => 'Externer Dienstleister', 'it_IT' => 'Fornitore esterno', 'es_ES' => 'Proveedor externo'],
        'Stagiaire' => ['en_GB' => 'Intern', 'de_DE' => 'Praktikant', 'it_IT' => 'Tirocinante', 'es_ES' => 'Becario'],
        'Alternant' => ['en_GB' => 'Apprentice', 'de_DE' => 'Auszubildender', 'it_IT' => 'Apprendista', 'es_ES' => 'Aprendiz'],
        'Intérimaire' => ['en_GB' => 'Temporary worker', 'de_DE' => 'Zeitarbeiter', 'it_IT' => 'Lavoratore interinale', 'es_ES' => 'Trabajador temporal'],
        'Consultant' => ['en_GB' => 'Consultant', 'de_DE' => 'Berater', 'it_IT' => 'Consulente', 'es_ES' => 'Consultor'],

        // ---- PlanningEventBuilder (glpi_planningexternaleventtemplates) ----
        'Réunion d\'équipe' => ['en_GB' => 'Team meeting', 'de_DE' => 'Team-Besprechung', 'it_IT' => 'Riunione di team', 'es_ES' => 'Reunión de equipo'],
        'Formation planifiée' => ['en_GB' => 'Scheduled training', 'de_DE' => 'Geplante Schulung', 'it_IT' => 'Formazione pianificata', 'es_ES' => 'Formación programada'],
        'Astreinte' => ['en_GB' => 'On-call duty', 'de_DE' => 'Bereitschaftsdienst', 'it_IT' => 'Reperibilità', 'es_ES' => 'Guardia'],

        // ---- AssetTypeBuilder (glpi_computertypes) ----
        'Ordinateur de bureau' => ['en_GB' => 'Desktop computer', 'de_DE' => 'Desktop-Computer', 'it_IT' => 'Computer desktop', 'es_ES' => 'Ordenador de sobremesa'],
        'Ordinateur portable' => ['en_GB' => 'Laptop', 'de_DE' => 'Laptop', 'it_IT' => 'Laptop', 'es_ES' => 'Portátil'],
        'Serveur' => ['en_GB' => 'Server', 'de_DE' => 'Server', 'it_IT' => 'Server', 'es_ES' => 'Servidor'],
        'Mini PC' => ['en_GB' => 'Mini PC', 'de_DE' => 'Mini-PC', 'it_IT' => 'Mini PC', 'es_ES' => 'Mini PC'],
        'Tablette' => ['en_GB' => 'Tablet', 'de_DE' => 'Tablet', 'it_IT' => 'Tablet', 'es_ES' => 'Tableta'],

        // ---- AssetTypeBuilder (glpi_monitortypes) ----
        'Écran LCD/LED' => ['en_GB' => 'LCD/LED monitor', 'de_DE' => 'LCD/LED-Monitor', 'it_IT' => 'Monitor LCD/LED', 'es_ES' => 'Monitor LCD/LED'],
        'Écran tactile' => ['en_GB' => 'Touchscreen', 'de_DE' => 'Touchscreen', 'it_IT' => 'Touchscreen', 'es_ES' => 'Pantalla táctil'],
        'Vidéoprojecteur' => ['en_GB' => 'Projector', 'de_DE' => 'Beamer', 'it_IT' => 'Videoproiettore', 'es_ES' => 'Proyector'],

        // ---- AssetTypeBuilder (glpi_networkequipmenttypes) ----
        'Switch' => ['en_GB' => 'Switch', 'de_DE' => 'Switch', 'it_IT' => 'Switch', 'es_ES' => 'Conmutador'],
        'Routeur' => ['en_GB' => 'Router', 'de_DE' => 'Router', 'it_IT' => 'Router', 'es_ES' => 'Router'],
        'Pare-feu' => ['en_GB' => 'Firewall', 'de_DE' => 'Firewall', 'it_IT' => 'Firewall', 'es_ES' => 'Cortafuegos'],
        'Point d\'accès Wi-Fi' => ['en_GB' => 'Wi-Fi access point', 'de_DE' => 'WLAN-Zugangspunkt', 'it_IT' => 'Punto di accesso Wi-Fi', 'es_ES' => 'Punto de acceso Wi-Fi'],
        'Répartiteur de charge' => ['en_GB' => 'Load balancer', 'de_DE' => 'Lastverteiler', 'it_IT' => 'Bilanciatore di carico', 'es_ES' => 'Balanceador de carga'],

        // ---- AssetTypeBuilder (glpi_peripheraltypes) ----
        'Clavier' => ['en_GB' => 'Keyboard', 'de_DE' => 'Tastatur', 'it_IT' => 'Tastiera', 'es_ES' => 'Teclado'],
        'Souris' => ['en_GB' => 'Mouse', 'de_DE' => 'Maus', 'it_IT' => 'Mouse', 'es_ES' => 'Ratón'],
        'Webcam' => ['en_GB' => 'Webcam', 'de_DE' => 'Webcam', 'it_IT' => 'Webcam', 'es_ES' => 'Webcam'],
        'Casque' => ['en_GB' => 'Headset', 'de_DE' => 'Headset', 'it_IT' => 'Cuffie', 'es_ES' => 'Auriculares'],
        'Station d\'accueil' => ['en_GB' => 'Docking station', 'de_DE' => 'Dockingstation', 'it_IT' => 'Docking station', 'es_ES' => 'Estación de acoplamiento'],
        'Scanner' => ['en_GB' => 'Scanner', 'de_DE' => 'Scanner', 'it_IT' => 'Scanner', 'es_ES' => 'Escáner'],
        'Disque externe' => ['en_GB' => 'External drive', 'de_DE' => 'Externe Festplatte', 'it_IT' => 'Disco esterno', 'es_ES' => 'Disco externo'],

        // ---- AssetTypeBuilder (glpi_phonetypes) ----
        'Smartphone' => ['en_GB' => 'Smartphone', 'de_DE' => 'Smartphone', 'it_IT' => 'Smartphone', 'es_ES' => 'Smartphone'],
        'Téléphone fixe' => ['en_GB' => 'Desk phone', 'de_DE' => 'Festnetztelefon', 'it_IT' => 'Telefono fisso', 'es_ES' => 'Teléfono fijo'],
        'Téléphone DECT' => ['en_GB' => 'DECT phone', 'de_DE' => 'DECT-Telefon', 'it_IT' => 'Telefono DECT', 'es_ES' => 'Teléfono DECT'],
        'Softphone' => ['en_GB' => 'Softphone', 'de_DE' => 'Softphone', 'it_IT' => 'Softphone', 'es_ES' => 'Softphone'],

        // ---- AssetTypeBuilder (glpi_printertypes) ----
        'Imprimante laser' => ['en_GB' => 'Laser printer', 'de_DE' => 'Laserdrucker', 'it_IT' => 'Stampante laser', 'es_ES' => 'Impresora láser'],
        'Imprimante jet d\'encre' => ['en_GB' => 'Inkjet printer', 'de_DE' => 'Tintenstrahldrucker', 'it_IT' => 'Stampante a getto d\'inchiostro', 'es_ES' => 'Impresora de inyección de tinta'],
        'Multifonction' => ['en_GB' => 'Multifunction printer', 'de_DE' => 'Multifunktionsdrucker', 'it_IT' => 'Stampante multifunzione', 'es_ES' => 'Impresora multifunción'],
        'Imprimante d\'étiquettes' => ['en_GB' => 'Label printer', 'de_DE' => 'Etikettendrucker', 'it_IT' => 'Stampante di etichette', 'es_ES' => 'Impresora de etiquetas'],

        // ---- AssetTypeBuilder (glpi_racktypes) ----
        'Rack serveur 19″' => ['en_GB' => '19″ server rack', 'de_DE' => '19″-Server-Rack', 'it_IT' => 'Rack server 19″', 'es_ES' => 'Rack de servidor 19″'],
        'Rack réseau' => ['en_GB' => 'Network rack', 'de_DE' => 'Netzwerk-Rack', 'it_IT' => 'Rack di rete', 'es_ES' => 'Rack de red'],
        'Rack ouvert' => ['en_GB' => 'Open frame rack', 'de_DE' => 'Offenes Rack', 'it_IT' => 'Rack aperto', 'es_ES' => 'Rack abierto'],

        // ---- AssetTypeBuilder (glpi_pdutypes) ----
        'Basique' => ['en_GB' => 'Basic', 'de_DE' => 'Einfach', 'it_IT' => 'Base', 'es_ES' => 'Básico'],
        'Mesuré (metered)' => ['en_GB' => 'Metered', 'de_DE' => 'Gemessen', 'it_IT' => 'Misurato', 'es_ES' => 'Medido'],
        'Commuté (switched)' => ['en_GB' => 'Switched', 'de_DE' => 'Schaltbar', 'it_IT' => 'Commutato', 'es_ES' => 'Conmutado'],
        'Commuté et mesuré' => ['en_GB' => 'Switched and metered', 'de_DE' => 'Schaltbar und gemessen', 'it_IT' => 'Commutato e misurato', 'es_ES' => 'Conmutado y medido'],

        // ---- AssetTypeBuilder (glpi_certificatetypes) ----
        'SSL/TLS' => ['en_GB' => 'SSL/TLS', 'de_DE' => 'SSL/TLS', 'it_IT' => 'SSL/TLS', 'es_ES' => 'SSL/TLS'],
        'Signature de code' => ['en_GB' => 'Code signing', 'de_DE' => 'Codesignatur', 'it_IT' => 'Firma del codice', 'es_ES' => 'Firma de código'],
        'Certificat client' => ['en_GB' => 'Client certificate', 'de_DE' => 'Clientzertifikat', 'it_IT' => 'Certificato client', 'es_ES' => 'Certificado de cliente'],
        'S/MIME (messagerie)' => ['en_GB' => 'S/MIME (email)', 'de_DE' => 'S/MIME (E-Mail)', 'it_IT' => 'S/MIME (posta elettronica)', 'es_ES' => 'S/MIME (correo)'],

        // ---- AssetTypeBuilder (glpi_deviceharddrivetypes) ----
        'HDD' => ['en_GB' => 'HDD', 'de_DE' => 'HDD', 'it_IT' => 'HDD', 'es_ES' => 'HDD'],
        'SSD SATA' => ['en_GB' => 'SATA SSD', 'de_DE' => 'SATA-SSD', 'it_IT' => 'SSD SATA', 'es_ES' => 'SSD SATA'],
        'SSD NVMe' => ['en_GB' => 'NVMe SSD', 'de_DE' => 'NVMe-SSD', 'it_IT' => 'SSD NVMe', 'es_ES' => 'SSD NVMe'],
        'Hybride (SSHD)' => ['en_GB' => 'Hybrid (SSHD)', 'de_DE' => 'Hybrid (SSHD)', 'it_IT' => 'Ibrido (SSHD)', 'es_ES' => 'Híbrido (SSHD)'],

        // ---- AssetTypeBuilder (glpi_devicebatterytypes) ----
        'Lithium-ion' => ['en_GB' => 'Lithium-ion', 'de_DE' => 'Lithium-Ionen', 'it_IT' => 'Litio-ione', 'es_ES' => 'Ion-litio'],
        'Lithium-polymère' => ['en_GB' => 'Lithium-polymer', 'de_DE' => 'Lithium-Polymer', 'it_IT' => 'Litio-polimero', 'es_ES' => 'Polímero de litio'],
        'NiMH' => ['en_GB' => 'NiMH', 'de_DE' => 'NiMH', 'it_IT' => 'NiMH', 'es_ES' => 'NiMH'],
        'Plomb-acide' => ['en_GB' => 'Lead-acid', 'de_DE' => 'Blei-Säure', 'it_IT' => 'Piombo-acido', 'es_ES' => 'Plomo-ácido'],

        // ---- AssetTypeBuilder (glpi_devicecasetypes) ----
        'Tour (Tower)' => ['en_GB' => 'Tower', 'de_DE' => 'Tower', 'it_IT' => 'Tower', 'es_ES' => 'Torre'],
        'Rack-mount' => ['en_GB' => 'Rack-mount', 'de_DE' => 'Rack-Montage', 'it_IT' => 'Montaggio a rack', 'es_ES' => 'Montaje en rack'],
        'Format compact (SFF)' => ['en_GB' => 'Small form factor (SFF)', 'de_DE' => 'Kleiner Formfaktor (SFF)', 'it_IT' => 'Formato compatto (SFF)', 'es_ES' => 'Factor de forma reducido (SFF)'],
        'Tout-en-un (AIO)' => ['en_GB' => 'All-in-one (AIO)', 'de_DE' => 'All-in-One (AIO)', 'it_IT' => 'Tutto-in-uno (AIO)', 'es_ES' => 'Todo en uno (AIO)'],

        // ---- AssetTypeBuilder (glpi_cabletypes) ----
        'Ethernet Cat5e' => ['en_GB' => 'Ethernet Cat5e', 'de_DE' => 'Ethernet Cat5e', 'it_IT' => 'Ethernet Cat5e', 'es_ES' => 'Ethernet Cat5e'],
        'Ethernet Cat6' => ['en_GB' => 'Ethernet Cat6', 'de_DE' => 'Ethernet Cat6', 'it_IT' => 'Ethernet Cat6', 'es_ES' => 'Ethernet Cat6'],
        'Ethernet Cat6a' => ['en_GB' => 'Ethernet Cat6a', 'de_DE' => 'Ethernet Cat6a', 'it_IT' => 'Ethernet Cat6a', 'es_ES' => 'Ethernet Cat6a'],
        'Fibre optique' => ['en_GB' => 'Fiber optic', 'de_DE' => 'Glasfaser', 'it_IT' => 'Fibra ottica', 'es_ES' => 'Fibra óptica'],
        'Alimentation' => ['en_GB' => 'Power', 'de_DE' => 'Stromversorgung', 'it_IT' => 'Alimentazione', 'es_ES' => 'Alimentación'],
        'USB' => ['en_GB' => 'USB', 'de_DE' => 'USB', 'it_IT' => 'USB', 'es_ES' => 'USB'],

        // ---- AssetTypeBuilder (glpi_cartridgeitemtypes) ----
        'Toner' => ['en_GB' => 'Toner', 'de_DE' => 'Toner', 'it_IT' => 'Toner', 'es_ES' => 'Tóner'],
        'Cartouche d\'encre' => ['en_GB' => 'Ink cartridge', 'de_DE' => 'Tintenpatrone', 'it_IT' => 'Cartuccia d\'inchiostro', 'es_ES' => 'Cartucho de tinta'],
        'Tambour (drum)' => ['en_GB' => 'Drum', 'de_DE' => 'Trommel', 'it_IT' => 'Tamburo', 'es_ES' => 'Tambor'],
        'Kit de maintenance' => ['en_GB' => 'Maintenance kit', 'de_DE' => 'Wartungskit', 'it_IT' => 'Kit di manutenzione', 'es_ES' => 'Kit de mantenimiento'],

        // ---- AssetTypeBuilder (glpi_appliancetypes) ----
        'Sauvegarde' => ['en_GB' => 'Backup', 'de_DE' => 'Sicherung', 'it_IT' => 'Backup', 'es_ES' => 'Copia de seguridad'],
        'Supervision' => ['en_GB' => 'Monitoring', 'de_DE' => 'Überwachung', 'it_IT' => 'Monitoraggio', 'es_ES' => 'Supervisión'],
        'Stockage (NAS/SAN)' => ['en_GB' => 'Storage (NAS/SAN)', 'de_DE' => 'Speicher (NAS/SAN)', 'it_IT' => 'Storage (NAS/SAN)', 'es_ES' => 'Almacenamiento (NAS/SAN)'],
        'VPN' => ['en_GB' => 'VPN', 'de_DE' => 'VPN', 'it_IT' => 'VPN', 'es_ES' => 'VPN'],
        'Proxy' => ['en_GB' => 'Proxy', 'de_DE' => 'Proxy', 'it_IT' => 'Proxy', 'es_ES' => 'Proxy'],

        // ---- AssetTypeBuilder (glpi_budgettypes) ----
        'Investissement (CAPEX)' => ['en_GB' => 'Capital expenditure (CAPEX)', 'de_DE' => 'Investitionsausgaben (CAPEX)', 'it_IT' => 'Spesa in conto capitale (CAPEX)', 'es_ES' => 'Gasto de capital (CAPEX)'],
        'Fonctionnement (OPEX)' => ['en_GB' => 'Operating expenditure (OPEX)', 'de_DE' => 'Betriebsausgaben (OPEX)', 'it_IT' => 'Spesa operativa (OPEX)', 'es_ES' => 'Gasto operativo (OPEX)'],
        'Exceptionnel' => ['en_GB' => 'Exceptional', 'de_DE' => 'Außerordentlich', 'it_IT' => 'Eccezionale', 'es_ES' => 'Excepcional'],
        'Projet' => ['en_GB' => 'Project', 'de_DE' => 'Projekt', 'it_IT' => 'Progetto', 'es_ES' => 'Proyecto'],

        // ---- AssetTypeBuilder (glpi_clustertypes) ----
        'Haute disponibilité' => ['en_GB' => 'High availability', 'de_DE' => 'Hochverfügbarkeit', 'it_IT' => 'Alta disponibilità', 'es_ES' => 'Alta disponibilidad'],
        'Répartition de charge' => ['en_GB' => 'Load balancing', 'de_DE' => 'Lastverteilung', 'it_IT' => 'Bilanciamento del carico', 'es_ES' => 'Equilibrio de carga'],
        'Calcul distribué' => ['en_GB' => 'Distributed computing', 'de_DE' => 'Verteiltes Rechnen', 'it_IT' => 'Calcolo distribuito', 'es_ES' => 'Computación distribuida'],
        'Stockage' => ['en_GB' => 'Storage', 'de_DE' => 'Speicher', 'it_IT' => 'Storage', 'es_ES' => 'Almacenamiento'],

        // ---- AssetTypeBuilder (glpi_consumableitemtypes) ----
        'Papier' => ['en_GB' => 'Paper', 'de_DE' => 'Papier', 'it_IT' => 'Carta', 'es_ES' => 'Papel'],
        'Pile / Batterie' => ['en_GB' => 'Battery', 'de_DE' => 'Batterie', 'it_IT' => 'Batteria', 'es_ES' => 'Pila / Batería'],
        'Câble' => ['en_GB' => 'Cable', 'de_DE' => 'Kabel', 'it_IT' => 'Cavo', 'es_ES' => 'Cable'],
        'Support de stockage (CD/DVD/USB)' => ['en_GB' => 'Storage media (CD/DVD/USB)', 'de_DE' => 'Speichermedium (CD/DVD/USB)', 'it_IT' => 'Supporto di archiviazione (CD/DVD/USB)', 'es_ES' => 'Soporte de almacenamiento (CD/DVD/USB)'],
        'Badge d\'accès' => ['en_GB' => 'Access badge', 'de_DE' => 'Zutrittsausweis', 'it_IT' => 'Badge di accesso', 'es_ES' => 'Tarjeta de acceso'],
        'Fourniture de bureau' => ['en_GB' => 'Office supplies', 'de_DE' => 'Büromaterial', 'it_IT' => 'Materiale da ufficio', 'es_ES' => 'Material de oficina'],

        // ---- AssetTypeBuilder (glpi_contacttypes) ----
        'Commercial' => ['en_GB' => 'Sales', 'de_DE' => 'Vertrieb', 'it_IT' => 'Commerciale', 'es_ES' => 'Comercial'],
        'Technique' => ['en_GB' => 'Technical', 'de_DE' => 'Technisch', 'it_IT' => 'Tecnico', 'es_ES' => 'Técnico'],
        'Direction' => ['en_GB' => 'Management', 'de_DE' => 'Geschäftsführung', 'it_IT' => 'Direzione', 'es_ES' => 'Dirección'],
        'Comptabilité' => ['en_GB' => 'Accounting', 'de_DE' => 'Buchhaltung', 'it_IT' => 'Contabilità', 'es_ES' => 'Contabilidad'],
        'Juridique' => ['en_GB' => 'Legal', 'de_DE' => 'Recht', 'it_IT' => 'Legale', 'es_ES' => 'Jurídico'],

        // ---- AssetTypeBuilder (glpi_contracttypes) ----
        'Location' => ['en_GB' => 'Lease', 'de_DE' => 'Miete', 'it_IT' => 'Noleggio', 'es_ES' => 'Alquiler'],
        'Assurance' => ['en_GB' => 'Insurance', 'de_DE' => 'Versicherung', 'it_IT' => 'Assicurazione', 'es_ES' => 'Seguro'],
        'Garantie' => ['en_GB' => 'Warranty', 'de_DE' => 'Garantie', 'it_IT' => 'Garanzia', 'es_ES' => 'Garantía'],
        'Prestation de services' => ['en_GB' => 'Service provision', 'de_DE' => 'Dienstleistung', 'it_IT' => 'Prestazione di servizi', 'es_ES' => 'Prestación de servicios'],
        'Abonnement' => ['en_GB' => 'Subscription', 'de_DE' => 'Abonnement', 'it_IT' => 'Abbonamento', 'es_ES' => 'Suscripción'],

        // ---- AssetTypeBuilder (glpi_domaintypes) ----
        'Nom de domaine internet' => ['en_GB' => 'Internet domain name', 'de_DE' => 'Internet-Domainname', 'it_IT' => 'Nome di dominio internet', 'es_ES' => 'Nombre de dominio de internet'],
        'Sous-domaine' => ['en_GB' => 'Subdomain', 'de_DE' => 'Subdomain', 'it_IT' => 'Sottodominio', 'es_ES' => 'Subdominio'],
        'Domaine interne (annuaire)' => ['en_GB' => 'Internal domain (directory)', 'de_DE' => 'Interne Domäne (Verzeichnis)', 'it_IT' => 'Dominio interno (directory)', 'es_ES' => 'Dominio interno (directorio)'],
        'Domaine technique (DNS/certificat)' => ['en_GB' => 'Technical domain (DNS/certificate)', 'de_DE' => 'Technische Domäne (DNS/Zertifikat)', 'it_IT' => 'Dominio tecnico (DNS/certificato)', 'es_ES' => 'Dominio técnico (DNS/certificado)'],

        // ---- AssetTypeBuilder (glpi_linetypes) ----
        'Fixe' => ['en_GB' => 'Landline', 'de_DE' => 'Festnetz', 'it_IT' => 'Fisso', 'es_ES' => 'Fijo'],
        'Mobile' => ['en_GB' => 'Mobile', 'de_DE' => 'Mobil', 'it_IT' => 'Mobile', 'es_ES' => 'Móvil'],
        'Internet' => ['en_GB' => 'Internet', 'de_DE' => 'Internet', 'it_IT' => 'Internet', 'es_ES' => 'Internet'],
        'VoIP' => ['en_GB' => 'VoIP', 'de_DE' => 'VoIP', 'it_IT' => 'VoIP', 'es_ES' => 'VoIP'],
        'Satellite' => ['en_GB' => 'Satellite', 'de_DE' => 'Satellit', 'it_IT' => 'Satellite', 'es_ES' => 'Satélite'],

        // ---- AssetTypeBuilder (glpi_passivedcequipmenttypes) ----
        'Panneau de brassage' => ['en_GB' => 'Patch panel', 'de_DE' => 'Patchpanel', 'it_IT' => 'Pannello di permutazione', 'es_ES' => 'Panel de parcheo'],
        'Baie de brassage' => ['en_GB' => 'Patch bay', 'de_DE' => 'Patchfeld-Rack', 'it_IT' => 'Armadio di permutazione', 'es_ES' => 'Bastidor de parcheo'],
        'Goulotte' => ['en_GB' => 'Cable trunking', 'de_DE' => 'Kabelkanal', 'it_IT' => 'Canalina', 'es_ES' => 'Canaleta'],
        'Chemin de câbles' => ['en_GB' => 'Cable tray', 'de_DE' => 'Kabelrinne', 'it_IT' => 'Passerella portacavi', 'es_ES' => 'Bandeja portacables'],
        'Armoire technique' => ['en_GB' => 'Technical cabinet', 'de_DE' => 'Technikschrank', 'it_IT' => 'Armadio tecnico', 'es_ES' => 'Armario técnico'],

        // ---- AssetTypeBuilder (glpi_suppliertypes) ----
        'Fournisseur matériel' => ['en_GB' => 'Hardware supplier', 'de_DE' => 'Hardwarelieferant', 'it_IT' => 'Fornitore hardware', 'es_ES' => 'Proveedor de hardware'],
        'Prestataire de services' => ['en_GB' => 'Service provider', 'de_DE' => 'Dienstleister', 'it_IT' => 'Fornitore di servizi', 'es_ES' => 'Proveedor de servicios'],
        'Opérateur télécom' => ['en_GB' => 'Telecom operator', 'de_DE' => 'Telekommunikationsanbieter', 'it_IT' => 'Operatore telecom', 'es_ES' => 'Operador de telecomunicaciones'],
        'Revendeur' => ['en_GB' => 'Reseller', 'de_DE' => 'Wiederverkäufer', 'it_IT' => 'Rivenditore', 'es_ES' => 'Distribuidor'],
        'Éditeur logiciel' => ['en_GB' => 'Software vendor', 'de_DE' => 'Softwarehersteller', 'it_IT' => 'Produttore di software', 'es_ES' => 'Editor de software'],
        'Intégrateur' => ['en_GB' => 'Integrator', 'de_DE' => 'Systemintegrator', 'it_IT' => 'Integratore', 'es_ES' => 'Integrador'],

        // ---- AssetTypeBuilder (glpi_virtualmachinetypes) ----
        'VMware ESXi' => ['en_GB' => 'VMware ESXi', 'de_DE' => 'VMware ESXi', 'it_IT' => 'VMware ESXi', 'es_ES' => 'VMware ESXi'],
        'Microsoft Hyper-V' => ['en_GB' => 'Microsoft Hyper-V', 'de_DE' => 'Microsoft Hyper-V', 'it_IT' => 'Microsoft Hyper-V', 'es_ES' => 'Microsoft Hyper-V'],
        'Proxmox VE' => ['en_GB' => 'Proxmox VE', 'de_DE' => 'Proxmox VE', 'it_IT' => 'Proxmox VE', 'es_ES' => 'Proxmox VE'],
        'KVM' => ['en_GB' => 'KVM', 'de_DE' => 'KVM', 'it_IT' => 'KVM', 'es_ES' => 'KVM'],
        'Citrix XenServer' => ['en_GB' => 'Citrix XenServer', 'de_DE' => 'Citrix XenServer', 'it_IT' => 'Citrix XenServer', 'es_ES' => 'Citrix XenServer'],
        'VirtualBox' => ['en_GB' => 'VirtualBox', 'de_DE' => 'VirtualBox', 'it_IT' => 'VirtualBox', 'es_ES' => 'VirtualBox'],
        'Docker' => ['en_GB' => 'Docker', 'de_DE' => 'Docker', 'it_IT' => 'Docker', 'es_ES' => 'Docker'],
        'LXC' => ['en_GB' => 'LXC', 'de_DE' => 'LXC', 'it_IT' => 'LXC', 'es_ES' => 'LXC'],

        // ---- AssetTypeBuilder (glpi_devicesensortypes) ----
        'Température' => ['en_GB' => 'Temperature', 'de_DE' => 'Temperatur', 'it_IT' => 'Temperatura', 'es_ES' => 'Temperatura'],
        'Humidité' => ['en_GB' => 'Humidity', 'de_DE' => 'Luftfeuchtigkeit', 'it_IT' => 'Umidità', 'es_ES' => 'Humedad'],
        'Fumée' => ['en_GB' => 'Smoke', 'de_DE' => 'Rauch', 'it_IT' => 'Fumo', 'es_ES' => 'Humo'],
        'Mouvement' => ['en_GB' => 'Motion', 'de_DE' => 'Bewegung', 'it_IT' => 'Movimento', 'es_ES' => 'Movimiento'],
        'Ouverture de porte' => ['en_GB' => 'Door opening', 'de_DE' => 'Türöffnung', 'it_IT' => 'Apertura porta', 'es_ES' => 'Apertura de puerta'],
        'Fuite d\'eau' => ['en_GB' => 'Water leak', 'de_DE' => 'Wasserleck', 'it_IT' => 'Perdita d\'acqua', 'es_ES' => 'Fuga de agua'],
        'Vibration' => ['en_GB' => 'Vibration', 'de_DE' => 'Vibration', 'it_IT' => 'Vibrazione', 'es_ES' => 'Vibración'],

        // ---- FireSafetyAssetBuilder (glpi_assets_assettypes, "SecuriteIncendieSecours" definition) ----
        'Extincteur' => ['en_GB' => 'Fire extinguisher', 'de_DE' => 'Feuerlöscher', 'it_IT' => 'Estintore', 'es_ES' => 'Extintor'],
        "Robinet d'incendie armé (RIA)" => ['en_GB' => 'Fire hose reel', 'de_DE' => 'Wandhydrant', 'it_IT' => 'Idrante a muro', 'es_ES' => 'Boca de incendio equipada'],
        'Système de désenfumage' => ['en_GB' => 'Smoke extraction system', 'de_DE' => 'Rauch- und Wärmeabzugsanlage', 'it_IT' => 'Sistema di evacuazione fumi', 'es_ES' => 'Sistema de extracción de humos'],
        'Détecteur de fumée / alarme incendie' => ['en_GB' => 'Smoke detector / Fire alarm', 'de_DE' => 'Rauchmelder / Brandalarm', 'it_IT' => 'Rilevatore di fumo / Allarme antincendio', 'es_ES' => 'Detector de humo / Alarma de incendio'],
        'Éclairage de sécurité / issue de secours' => ['en_GB' => 'Emergency lighting / Fire exit', 'de_DE' => 'Sicherheitsbeleuchtung / Notausgang', 'it_IT' => 'Illuminazione di sicurezza / Uscita di emergenza', 'es_ES' => 'Alumbrado de seguridad / Salida de emergencia'],
        'Défibrillateur automatisé externe (DAE)' => ['en_GB' => 'Automated external defibrillator (AED)', 'de_DE' => 'Automatisierter externer Defibrillator (AED)', 'it_IT' => 'Defibrillatore semiautomatico esterno (DAE)', 'es_ES' => 'Desfibrilador externo automático (DEA)'],

        // ---- PhysicalSecurityAssetBuilder (glpi_assets_assettypes, "SecuritePhysique" definition) ----
        'Caméra de vidéosurveillance' => ['en_GB' => 'CCTV camera', 'de_DE' => 'Videoüberwachungskamera', 'it_IT' => 'Telecamera di videosorveglianza', 'es_ES' => 'Cámara de videovigilancia'],
        "Centrale d'alarme intrusion" => ['en_GB' => 'Intrusion alarm panel', 'de_DE' => 'Einbruchmeldezentrale', 'it_IT' => 'Centrale antintrusione', 'es_ES' => 'Central de alarma antiintrusión'],
        'Détecteur de mouvement' => ['en_GB' => 'Motion detector', 'de_DE' => 'Bewegungsmelder', 'it_IT' => 'Rilevatore di movimento', 'es_ES' => 'Detector de movimiento'],
        "Contrôle d'accès (lecteur de badge)" => ['en_GB' => 'Access control (badge reader)', 'de_DE' => 'Zutrittskontrolle (Ausweisleser)', 'it_IT' => 'Controllo accessi (lettore badge)', 'es_ES' => 'Control de acceso (lector de tarjetas)'],
        'Serrure électronique' => ['en_GB' => 'Electronic lock', 'de_DE' => 'Elektronisches Schloss', 'it_IT' => 'Serratura elettronica', 'es_ES' => 'Cerradura electrónica'],
        'Interphone / Vidéophone' => ['en_GB' => 'Intercom / Video intercom', 'de_DE' => 'Gegensprechanlage / Video-Gegensprechanlage', 'it_IT' => 'Citofono / Videocitofono', 'es_ES' => 'Portero automático / Videoportero'],
    ];

    /**
     * Writes one `DropdownTranslation` row per language (fr_FR + the 4 above) — icon in front of
     * the text, same visual format as the original French-only version. A name with no `MAP` entry
     * (manufacturer brand names, or anything not yet catalogued here) falls back to the French text
     * itself for every language: the icon still has to render regardless of session language, even
     * without a real translation available.
     */
    public static function applyIcon(string $itemtype, int $id, string $frenchName, string $icon): void
    {
        $translated = self::MAP[$frenchName] ?? [];
        $byLanguage = [
            'fr_FR' => $frenchName,
            'en_GB' => $translated['en_GB'] ?? $frenchName,
            'de_DE' => $translated['de_DE'] ?? $frenchName,
            'it_IT' => $translated['it_IT'] ?? $frenchName,
            'es_ES' => $translated['es_ES'] ?? $frenchName,
        ];

        foreach ($byLanguage as $language => $text) {
            // trim(): callers with no icon of their own (e.g. CategoryBuilder's leaf nodes, which
            // were never given an emoji) pass '' here — still want the translated text alone, not
            // a stray leading space from the empty icon slot.
            $value = trim(sprintf('%s %s', $icon, $text));

            $translation = new DropdownTranslation();
            $crit = ['itemtype' => $itemtype, 'items_id' => $id, 'language' => $language, 'field' => 'name'];
            if (!$translation->getFromDBByCrit($crit)) {
                $translation->add($crit + ['value' => $value]);
            } elseif ($translation->fields['value'] !== $value) {
                // Real bug found live (2026-08-23): this used to only ever add(), never update() —
                // a name/icon change between versions (or re-running after this MAP itself changed)
                // left the old value stuck forever, no error anywhere. DropdownTranslation's own
                // prepareInputForUpdate() re-derives whether the update is legal from
                // itemtype/items_id/field/language on $input (not from the loaded item), so those
                // must be resent alongside id/value or it silently rejects the update.
                $translation->update($crit + ['id' => (int) $translation->getID(), 'value' => $value]);
            }
        }
    }

    /**
     * Same `DropdownTranslation` mechanism as `applyIcon()`, but for the `content` field of
     * `ITILFollowupTemplate`/`SolutionTemplate`/`TaskTemplate` instead of `name` — confirmed in
     * `AbstractITILChildTemplate::getRenderedContent()` that GLPI resolves *this* field the same
     * way, by the session language of whichever technician applies the template. Unlike `applyIcon`,
     * translations are supplied directly by the caller (each builder's own `TEMPLATES` array) rather
     * than looked up from a shared `MAP`: template bodies are full paragraphs, not short recurring
     * words, so a giant central map keyed by huge strings would be fragile (one whitespace
     * difference and the lookup silently misses) for no real reuse benefit — the *only* piece that
     * does repeat across templates (the "Bonjour"-style greeting Twig prefix) is already factored
     * out as a `GREETING_EN`/`_DE`/`_IT`/`_ES` constant in each builder, same "small local
     * duplication" convention as the French `GREETING` constant it mirrors.
     *
     * @param array<string, string> $translations Language code (`en_GB`/`de_DE`/`it_IT`/`es_ES`) =>
     *        full translated content. No `fr_FR` key: the French row is the `content` field already
     *        written by `add()`/`update()`, this only adds the *other* four.
     */
    public static function applyContent(string $itemtype, int $id, array $translations): void
    {
        foreach ($translations as $language => $content) {
            $translation = new DropdownTranslation();
            $crit = ['itemtype' => $itemtype, 'items_id' => $id, 'language' => $language, 'field' => 'content'];
            if (!$translation->getFromDBByCrit($crit)) {
                $translation->add($crit + ['value' => $content]);
            } elseif ($translation->fields['value'] !== $content) {
                // Same add-only bug already fixed on applyIcon() — see that method's comment.
                $translation->update($crit + ['id' => (int) $translation->getID(), 'value' => $content]);
            }
        }
    }
}
