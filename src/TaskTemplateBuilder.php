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

use Planning;
use TaskCategory;
use TaskTemplate;

/**
 * Turns on `task_templates_enabled` into a real `TaskTemplate` library (Configuration >
 * Intitulés > Assistance > "Gabarits de tâche") — reusable checklists a technician attaches to a
 * ticket instead of retyping the same steps every time. GLPI ships none by default.
 *
 * Each template's `taskcategories_id` is resolved by name against whatever `TaskCategoryBuilder`
 * already created, independently re-looked-up rather than threading IDs through — same
 * independent-resolution pattern `ServiceCatalogBuilder` already uses against `CategoryBuilder`'s
 * tree. A template whose target category doesn't exist (category toggle off, or renamed) still
 * gets created, just without a category — never blocks on a missing dependency.
 */
class TaskTemplateBuilder
{
    private const TEMPLATES = [
        [
            'name' => 'Onboarding — Arrivée collaborateur',
            'category' => 'Gestion des comptes utilisateurs',
            'content' => "CHECKLIST ONBOARDING\n\n- Créer le compte et la messagerie\n- Configurer le poste de travail et les périphériques\n- Installer les logiciels métier et licences nécessaires\n- Configurer les accès applicatifs et VPN si besoin\n- Remettre le matériel et le guide utilisateur\n- Valider les accès avec le collaborateur",
        ],
        [
            'name' => 'Offboarding — Départ collaborateur',
            'category' => 'Gestion des comptes utilisateurs',
            'content' => "CHECKLIST OFFBOARDING\n\n- Désactiver le compte et la messagerie\n- Révoquer les accès VPN et applicatifs\n- Récupérer et reconditionner le matériel\n- Archiver ou transférer les données\n- Résilier les licences nominatives\n- Mettre à jour le statut de l'équipement dans GLPI",
        ],
        [
            'name' => 'Maintenance préventive',
            'category' => 'Maintenance préventive',
            'content' => "CHECKLIST MAINTENANCE PRÉVENTIVE\n\n- Vérifier les mises à jour système et pilotes\n- Contrôler l'espace disque et l'état du disque\n- Vérifier les sauvegardes\n- Nettoyer physiquement le matériel si nécessaire\n- Consigner les anomalies constatées",
        ],
    ];

    /**
     * @return int Number of task templates created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['task_templates_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::TEMPLATES as $template) {
            $this->getOrCreateTemplate($template['name'], $template['content'], $this->findCategoryId($template['category']));
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, category: string, content: string}>
     */
    public static function getLibraryPreview(): array
    {
        return self::TEMPLATES;
    }

    private function findCategoryId(string $name): int
    {
        $category = new TaskCategory();

        return $category->getFromDBByCrit(['name' => $name, 'taskcategories_id' => 0]) ? (int) $category->getID() : 0;
    }

    private function getOrCreateTemplate(string $name, string $content, int $categoryId): int
    {
        $item = new TaskTemplate();
        if ($item->getFromDBByCrit(['name' => $name])) {
            return (int) $item->getID();
        }

        return (int) $item->add([
            'name' => $name,
            'content' => $content,
            'taskcategories_id' => $categoryId,
            'state' => Planning::TODO,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
    }
}
