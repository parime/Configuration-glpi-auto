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
use PendingReason;
use SolutionTemplate;

/**
 * Turns a Config's wait-reason setting into real `PendingReason` rows (`glpi_pendingreasons`) — a
 * native GLPI mechanism for putting a ticket "on hold" with a reason, an automatic follow-up
 * cadence, and an automatic resolution after N unanswered follow-ups. Confirmed `glpi_pendingreasons`
 * ships empty on a fresh install (same pattern as states/holidays), and that the automation engine
 * itself is already active out of the box — `PendingReasonCron`'s crontask
 * (`pendingreason_autobump_autosolve`) ships `state = 1` (active) by default, running every 30
 * minutes. So this only needs to create the reasons themselves; nothing else to enable.
 *
 * Only the wait-on-requester reason gets full automation (auto follow-up + auto-resolve) — auto-
 * closing a ticket while waiting on a supplier delivery or an internal approval would be
 * inappropriate (the org doesn't control that timeline), so those stay reminder-only or fully
 * manual, matching a real production reference confirmed to make the same distinction.
 *
 * `followup_frequency` is stored in seconds, restricted by GLPI's own admin UI to a fixed enum of
 * day/week multiples (`PendingReason::getFollowupFrequencyValues()`) — built here from GLPI's own
 * `WEEK_TIMESTAMP` global constant rather than a hardcoded seconds value.
 */
class WaitReasonBuilder
{
    private const REASONS = [
        [
            'name' => 'Attente de retour utilisateur',
            'icon' => '⏳',
            'followup_weeks' => 2,
            'followups_before_resolution' => 3,
            'followup_content' => "Bonjour,\n\nNous sommes en attente d'informations complémentaires de votre part pour poursuivre le traitement de ce ticket. Merci de nous répondre dans les meilleurs délais.\n\nSans retour de votre part, ce ticket sera automatiquement clôturé après plusieurs relances.",
            'solution_content' => "Ticket clôturé automatiquement après plusieurs relances sans réponse de l'utilisateur. N'hésitez pas à le rouvrir ou à en créer un nouveau si le besoin persiste.",
        ],
        [
            'name' => 'Attente livraison fournisseur',
            'icon' => '🚚',
            'followup_weeks' => 0,
            'followups_before_resolution' => 0,
        ],
        [
            'name' => 'Intervention planifiée',
            'icon' => '🗓️',
            'followup_weeks' => 0,
            'followups_before_resolution' => 0,
        ],
        [
            'name' => 'Validation interne en attente',
            'icon' => '✅',
            'followup_weeks' => 1,
            'followups_before_resolution' => 0,
            'followup_content' => "Bonjour,\n\nCe ticket est en attente d'une validation interne. Merci de nous tenir informés de l'avancement de cette validation.",
        ],
    ];

    /**
     * @return int Number of wait reasons created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['wait_reasons_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['wait_reason_icons_enabled']);
        $count = 0;
        foreach (self::REASONS as $reason) {
            $this->getOrCreateReason($reason, $withIcons);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, icon: string, followup_weeks: int, followups_before_resolution: int}>
     */
    public static function getReasonsPreview(): array
    {
        return self::REASONS;
    }

    private function getOrCreateReason(array $reason, bool $withIcons): void
    {
        // Resolved once and reused below (see StateBuilder::build() for why this is always called,
        // never skipped, when icons are unchecked) so unchecking icons after a prior run actually
        // strips them instead of leaving old rows stuck.
        $icon = $withIcons ? $reason['icon'] : '';

        $pendingReason = new PendingReason();
        if ($pendingReason->getFromDBByCrit(['name' => $reason['name'], 'entities_id' => 0])) {
            Translations::applyIcon(PendingReason::class, (int) $pendingReason->getID(), $reason['name'], $icon);
            // The linked follow-up/solution templates were created on a prior run, before this
            // branch existed to touch them — re-apply here too so re-running the wizard against
            // an already-configured instance still catches up, not just a fresh install.
            if ((int) $pendingReason->fields['itilfollowuptemplates_id'] > 0) {
                Translations::applyIcon(ITILFollowupTemplate::class, (int) $pendingReason->fields['itilfollowuptemplates_id'], $reason['name'], $icon);
            }
            if ((int) $pendingReason->fields['solutiontemplates_id'] > 0) {
                Translations::applyIcon(SolutionTemplate::class, (int) $pendingReason->fields['solutiontemplates_id'], $reason['name'], $icon);
            }
            return;
        }

        $followupTemplateId = !empty($reason['followup_content'])
            ? $this->getOrCreateFollowupTemplate($reason['name'], $reason['followup_content'])
            : 0;
        $solutionTemplateId = !empty($reason['solution_content'])
            ? $this->getOrCreateSolutionTemplate($reason['name'], $reason['solution_content'])
            : 0;

        if ($followupTemplateId > 0) {
            Translations::applyIcon(ITILFollowupTemplate::class, $followupTemplateId, $reason['name'], $icon);
        }
        if ($solutionTemplateId > 0) {
            Translations::applyIcon(SolutionTemplate::class, $solutionTemplateId, $reason['name'], $icon);
        }

        $id = $pendingReason->add([
            'name' => $reason['name'],
            'entities_id' => 0,
            'is_recursive' => 1,
            'followup_frequency' => $reason['followup_weeks'] * WEEK_TIMESTAMP,
            'followups_before_resolution' => $reason['followups_before_resolution'],
            'itilfollowuptemplates_id' => $followupTemplateId,
            'solutiontemplates_id' => $solutionTemplateId,
        ]);

        // Cross-link back so an admin picking this follow-up template by hand elsewhere is also
        // offered to set the matching pending status — cosmetic, not needed by the automation
        // itself (that reads PendingReason -> template, not the other way around).
        if ($followupTemplateId > 0) {
            (new ITILFollowupTemplate())->update(['id' => $followupTemplateId, 'pendingreasons_id' => $id]);
        }

        Translations::applyIcon(PendingReason::class, (int) $id, $reason['name'], $icon);
    }

    private function getOrCreateFollowupTemplate(string $name, string $content): int
    {
        $item = new ITILFollowupTemplate();
        if (!$item->getFromDBByCrit(['name' => $name, 'entities_id' => 0])) {
            $id = $item->add([
                'name' => $name,
                'content' => $content,
                'entities_id' => 0,
                'is_recursive' => 1,
            ]);
            $item->getFromDB($id);
        }

        return (int) $item->getID();
    }

    private function getOrCreateSolutionTemplate(string $name, string $content): int
    {
        $item = new SolutionTemplate();
        if (!$item->getFromDBByCrit(['name' => $name, 'entities_id' => 0])) {
            $id = $item->add([
                'name' => $name,
                'content' => $content,
                'entities_id' => 0,
                'is_recursive' => 1,
            ]);
            $item->getFromDB($id);
        }

        return (int) $item->getID();
    }
}
