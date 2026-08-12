<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Parime
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
                    'content' => "Bonjour,\n\nNous avons accompagné l'utilisateur pas à pas pour résoudre sa demande.\n\nAction réalisée : \n\nCordialement,",
                ],
                [
                    'name' => 'Formation dispensée',
                    'icon' => '🎓',
                    'content' => "Bonjour,\n\nUne session de formation/sensibilisation a été dispensée à l'utilisateur sur l'outil concerné.\n\nPoints abordés : \n\nCordialement,",
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
                    'content' => "Bonjour,\n\nLe problème a été identifié et corrigé.\n\nCause : \nAction réalisée : \n\nCordialement,",
                ],
                [
                    'name' => 'Remplacement de matériel effectué',
                    'icon' => '🔩',
                    'content' => "Bonjour,\n\nLe matériel défectueux a été remplacé.\n\nÉquipement concerné : \nNouvel équipement : \n\nCordialement,",
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
                    'content' => "Incident de sécurité traité.\n\nAction immédiate : isolation du système impacté.\nÉradication : suppression des éléments malveillants, nettoyage des persistances.\n\nOutils utilisés : \nDate : {{ ticket.solvedate | date(\"d/m/Y H:i\") }}",
                ],
                [
                    'name' => 'Application de correctifs de sécurité',
                    'icon' => '🩹',
                    'content' => "Correctifs appliqués suite à l'incident de sécurité.\n\nVulnérabilité corrigée : \nCorrectifs déployés : \n\nDate : {{ ticket.solvedate | date(\"d/m/Y H:i\") }}",
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
                    'content' => "Bonjour,\n\nAprès vérification, le comportement signalé est normal, aucune anomalie détectée.\n\nCordialement,",
                ],
                [
                    'name' => 'Ticket doublon',
                    'icon' => '📑',
                    'content' => "Bonjour,\n\nCe ticket fait doublon avec une demande déjà en cours de traitement.\n\nTicket de référence : \n\nCordialement,",
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
                    'content' => "Bonjour,\n\nLe compte a été créé/modifié comme demandé.\n\nAccès accordés : \n\nCordialement,",
                ],
                [
                    'name' => 'Mot de passe réinitialisé',
                    'icon' => '🔑',
                    'content' => "Bonjour,\n\nVotre mot de passe a été réinitialisé. Vous recevrez les identifiants par un canal séparé.\n\nCordialement,",
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
            if ($withIcons) {
                Translations::applyIcon(SolutionType::class, $typeId, $type['name'], $type['icon']);
            }
            foreach ($type['templates'] as $template) {
                $templateId = $this->getOrCreateTemplate($template['name'], $template['content'], $typeId);
                if ($withTemplateIcons) {
                    Translations::applyIcon(SolutionTemplate::class, $templateId, $template['name'], $template['icon']);
                }
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, icon: string, comment: string, templates: array<int, array{name: string, content: string}>}>
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
