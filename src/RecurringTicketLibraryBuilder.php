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

use TicketTemplate;
use TicketTemplatePredefinedField;

/**
 * Answers #149 ("identifier les tickets récurrents les plus fréquemment mis en place... et évaluer
 * l'intérêt de les proposer par défaut") with a deliberately narrower slice than the issue's literal
 * wording: this seeds ready-to-use `TicketTemplate` content for the 3 patterns the issue itself
 * names (revue utilisateur, maintenance, mise à jour), but does **not** activate GLPI's native
 * `TicketRecurrent` scheduling (Configuration > Intitulés > Assistance > "Tickets récurrents").
 *
 * Read `CommonITILRecurrent`/`TicketRecurrent` in GLPI core before deciding this: turning one on for
 * real needs a `begin_date`, `periodicity`, `create_before` delay, and (optionally) a `calendars_id`
 * — and, more importantly, a specific group/assignee to actually work the ticket once created. Every
 * other choice this plugin's builders make (which category names, which icon, which default
 * profiles) is either universal enough to guess safely or reversible with zero side effects if
 * wrong. A live recurring-ticket schedule is neither: guessing "monthly, unassigned, starting today"
 * would start silently generating real tickets in someone's queue with no one responsible for them
 * — worse than not offering the feature at all. The actual schedule/assignee is an organizational
 * decision only the admin can make, same reasoning `EscalationBuilder`/`ValidationSupervisorRouting`
 * elsewhere in this plugin already apply to real workflow-behavior changes (opt-in, not guessed).
 *
 * What *is* safe to guess, and genuinely saves setup time: the ticket *content* itself (title +a
 * ready checklist body) so an admin who does want to set up recurrence has a real starting point
 * to pick in GLPI's native "Tickets récurrents" screen (`tickettemplates_id` is exactly the field
 * it asks for), instead of writing one from scratch. `TicketTemplatePredefinedField` (num=1 for the
 * title/`name` field, num=21 for `content` — resolved via `TicketTemplate::getAllowedFields(true)`,
 * confirmed live rather than guessed, same method `TicketTemplateBuilder` already uses) is what
 * pre-fills those two fields the moment this template is selected, whether picked by hand or via
 * `TicketRecurrent`.
 */
class RecurringTicketLibraryBuilder
{
    /**
     * @var array<int, array{name: string, content: string}>
     */
    private const TEMPLATES = [
        [
            'name' => 'Revue mensuelle des comptes utilisateurs inactifs',
            'content' => "Vérifier les comptes utilisateurs sans connexion depuis plus de 90 jours.\n\n"
                . "- Identifier les comptes concernés (Administration > Utilisateurs, tri par dernière connexion)\n"
                . "- Confirmer auprès du service RH/manager si le départ est effectif\n"
                . "- Désactiver ou supprimer les comptes confirmés inactifs\n"
                . "- Révoquer les accès associés (VPN, applications tierces, badges)",
        ],
        [
            'name' => 'Maintenance planifiée du parc informatique',
            'content' => "Cycle de maintenance préventive périodique du parc.\n\n"
                . "- Vérifier l'état de santé des postes/serveurs critiques (espace disque, température, état SMART)\n"
                . "- Nettoyer les fichiers temporaires et journaux surdimensionnés\n"
                . "- Vérifier les sauvegardes des dernières 24h/7 jours\n"
                . "- Consigner les anomalies relevées dans ce ticket",
        ],
        [
            'name' => 'Vérification des mises à jour système',
            'content' => "Cycle de vérification des mises à jour de sécurité en attente.\n\n"
                . "- Relever les mises à jour de sécurité disponibles (OS, applications critiques)\n"
                . "- Planifier leur application en dehors des heures de production\n"
                . "- Tester sur un périmètre restreint avant déploiement large si possible\n"
                . "- Documenter les mises à jour appliquées et les éventuels incidents",
        ],
        // Issue #340 : revues récurrentes d'hygiène informatique.
        [
            'name' => 'Revue annuelle des privilèges d\'accès',
            'content' => "Revue annuelle des comptes à privilèges (ISO 27001 A.5.18, A.8.2).\n\n"
                . "- Lister les comptes administrateurs (domaine, serveurs, GLPI, applications, cloud)\n"
                . "- Faire confirmer chaque privilège par le responsable de la personne ou de l'application\n"
                . "- Retirer les privilèges non justifiés et vérifier l'authentification multifacteur\n"
                . "- Joindre la liste validée à ce ticket comme preuve de la revue",
        ],
        [
            'name' => 'Test de restauration des sauvegardes',
            'content' => "Test périodique de restauration (ISO 27001 A.8.13).\n\n"
                . "- Choisir un jeu de données ou une machine représentatif (pas toujours le même)\n"
                . "- Restaurer dans un environnement isolé, sans écraser la production\n"
                . "- Vérifier l'intégrité des données restaurées et mesurer la durée de restauration\n"
                . "- Comparer cette durée à l'objectif de reprise (RTO) et consigner le résultat dans ce ticket",
        ],
        [
            'name' => 'Revue des comptes de service',
            'content' => "Revue des comptes techniques et de service.\n\n"
                . "- Lister les comptes de service (applications, tâches planifiées, connecteurs, API)\n"
                . "- Vérifier pour chacun le propriétaire, l'usage et les droits accordés\n"
                . "- Renouveler les mots de passe ou secrets anciens et retirer les droits interactifs inutiles\n"
                . "- Désactiver les comptes sans propriétaire ou sans usage",
        ],
    ];

    /**
     * @return int Number of templates created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['recurring_ticket_library_enabled'])) {
            return 0;
        }

        $so = array_flip(TicketTemplate::getAllowedFields(true));
        $nameField = $so['name'] ?? -1;
        $contentField = $so['content'] ?? -1;

        $count = 0;
        foreach (self::TEMPLATES as $template) {
            $templateId = $this->getOrCreateTemplate($template['name']);
            $this->ensurePredefined($templateId, $nameField, $template['name']);
            $this->ensurePredefined($templateId, $contentField, $template['content']);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, content: string}>
     */
    public static function getLibraryPreview(): array
    {
        return self::TEMPLATES;
    }

    private function getOrCreateTemplate(string $name): int
    {
        $template = new TicketTemplate();
        if (Lookup::loadFirst($template, ['name' => $name, 'entities_id' => 0])) {
            return (int) $template->getID();
        }

        return (int) $template->add([
            'name' => $name,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
    }

    private function ensurePredefined(int $templateId, int $num, string $value): void
    {
        if ($num < 0) {
            return;
        }

        $field = new TicketTemplatePredefinedField();
        $crit = ['tickettemplates_id' => $templateId, 'num' => $num];
        if (!Lookup::loadFirst($field, $crit)) {
            $field->add($crit + ['value' => $value]);
        } elseif ($field->fields['value'] !== $value) {
            $field->update($crit + ['id' => (int) $field->getID(), 'value' => $value]);
        }
    }
}
