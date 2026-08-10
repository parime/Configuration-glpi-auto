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

use Glpi\Form\Condition\VisibilityStrategy;
use Glpi\Form\Form;

/**
 * Hides Urgency/Observers/Location on GLPI 11's native self-service forms ("Report an issue" /
 * "Request a service", `Glpi\Form\Form` ids 1/2) — a distinct system from `TicketTemplate` and its
 * hidden/mandatory fields (`TicketTemplateBuilder`). Confirmed by testing the real Self-Service
 * portal: it renders through this newer Form/Question engine (`/Helpdesk`), not the classic
 * `front/ticket.form.php` ITILTemplate flow — so `TicketTemplateHiddenField` has no effect there at
 * all, however it's configured.
 *
 * Urgency specifically: base users have no visibility into actual business impact and consistently
 * rate their own issue as urgent (a well-documented ITSM anti-pattern) — better decided by the
 * service desk during triage, or derived from the selected category, than asked of the requester.
 *
 * `Question::visibility_strategy`/`conditions` only support ALWAYS_VISIBLE / VISIBLE_IF / HIDDEN_IF
 * (`Glpi\Form\Condition\VisibilityStrategy`) — there's no plain "always hidden". Confirmed in
 * `Engine::computeConditions()`: an empty `conditions` array evaluates to `false`. Combined with
 * `VISIBLE_IF` (`mustBeVisible($conditions_result)` returns `$conditions_result` as-is), an empty
 * condition list is therefore permanently hidden — no dummy/always-false condition needed.
 */
class HelpdeskFormBuilder
{
    // GLPI's own native form names — stored in English in the DB regardless of UI language (the
    // literal string doubles as a translation key, same pattern already seen on ProjectState).
    private const NATIVE_FORM_NAMES = ['Report an issue', 'Request a service'];

    private const HIDE_QUESTION_NAMES = ['Urgency', 'Observers', 'Location'];

    public function apply(Config $config): bool
    {
        if (empty($config->fields['helpdesk_form_hide_fields'])) {
            return false;
        }

        $applied = false;
        $form = new Form();
        foreach (self::NATIVE_FORM_NAMES as $formName) {
            if (!$form->getFromDBByCrit(['name' => $formName])) {
                continue;
            }
            foreach ($form->getQuestions() as $question) {
                if (!in_array($question->fields['name'], self::HIDE_QUESTION_NAMES, true)) {
                    continue;
                }
                $question->update([
                    'id'                   => $question->getID(),
                    'visibility_strategy'  => VisibilityStrategy::VISIBLE_IF->value,
                    'conditions'           => json_encode([]),
                ]);
                $applied = true;
            }
        }

        return $applied;
    }
}
