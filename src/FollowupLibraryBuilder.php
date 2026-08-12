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
    private const TEMPLATES = [
        [
            'name' => 'Relance — informations complémentaires demandées',
            'icon' => '❓',
            'content' => "Bonjour,\n\nNous avons besoin d'informations complémentaires pour avancer sur votre demande :\n- \n\nMerci de nous répondre dans les meilleurs délais.\n\nCordialement,",
        ],
        [
            'name' => 'Notification — commande ou livraison en cours',
            'icon' => '📦',
            'content' => "Bonjour,\n\nVotre demande est en cours de traitement. Nous attendons la livraison du matériel/logiciel nécessaire et vous tiendrons informé dès réception.\n\nCordialement,",
        ],
        [
            'name' => 'Notification — escalade fournisseur',
            'icon' => '🪜',
            'content' => "Bonjour,\n\nVotre ticket a été transmis à notre fournisseur/éditeur pour analyse. Nous reviendrons vers vous dès que nous aurons un retour.\n\nCordialement,",
        ],
        [
            'name' => 'Notification — intervention planifiée',
            'icon' => '🗓️',
            'content' => "Bonjour,\n\nUne intervention est planifiée pour résoudre votre demande. Merci de vous assurer de votre disponibilité à la date convenue.\n\nCordialement,",
        ],
        [
            'name' => 'Notification — validation en cours',
            'icon' => '✅',
            'content' => "Bonjour,\n\nVotre demande nécessite une validation avant de pouvoir être traitée. Nous vous informerons dès qu'elle aura été obtenue.\n\nCordialement,",
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
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, icon: string, content: string}>
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
