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

use ChangeTemplate;
use ChangeTemplateMandatoryField;
use ProblemTemplate;
use ProblemTemplateMandatoryField;
use Profile;

/**
 * Turns on `change_problem_templates_enabled` into one default `ChangeTemplate` and one default
 * `ProblemTemplate`, assigned to every profile (`glpi_profiles.changetemplates_id`/
 * `problemtemplates_id`, native per-profile fields symmetric to `tickettemplates_id`). GLPI ships
 * neither by default.
 *
 * Unlike `TicketTemplateBuilder`'s two-tier split (simplified vs. complete), there's only one
 * template each here — confirmed in `glpi_profilerights` that GLPI's own `Self-Service` profile
 * has zero rights on both `Change` and `Problem` by default, so the "base user vs. staff" split
 * that motivates Ticket's two templates doesn't apply: whoever can even open one of these forms is
 * already staff.
 *
 * Mandatory fields: `content` on both (the ITIL minimum), plus `impact` on Change specifically —
 * risk/impact assessment before approval is Change Management's defining ITIL practice,
 * distinguishing it from Problem (root-cause investigation, no approval gate the same way).
 */
class ChangeProblemTemplateBuilder
{
    private const CHANGE_NAME = 'Changement standard';

    private const PROBLEM_NAME = 'Problème standard';

    public function apply(Config $config): bool
    {
        if (empty($config->fields['change_problem_templates_enabled'])) {
            return false;
        }

        $changeSo = array_flip(ChangeTemplate::getAllowedFields(true));
        $changeId = $this->getOrCreateTemplate(ChangeTemplate::class, self::CHANGE_NAME);
        $this->ensureMandatory(ChangeTemplateMandatoryField::class, 'changetemplates_id', $changeId, $changeSo['content'] ?? -1);
        $this->ensureMandatory(ChangeTemplateMandatoryField::class, 'changetemplates_id', $changeId, $changeSo['impact'] ?? -1);

        $problemSo = array_flip(ProblemTemplate::getAllowedFields(true));
        $problemId = $this->getOrCreateTemplate(ProblemTemplate::class, self::PROBLEM_NAME);
        $this->ensureMandatory(ProblemTemplateMandatoryField::class, 'problemtemplates_id', $problemId, $problemSo['content'] ?? -1);

        if (!empty($config->fields['change_problem_template_icons_enabled'])) {
            Translations::applyIcon(ChangeTemplate::class, $changeId, self::CHANGE_NAME, '🔄');
            Translations::applyIcon(ProblemTemplate::class, $problemId, self::PROBLEM_NAME, '🧩');
        }

        $this->assignToProfiles($changeId, $problemId);

        return true;
    }

    /**
     * @param class-string<ChangeTemplate|ProblemTemplate> $class
     */
    private function getOrCreateTemplate(string $class, string $name): int
    {
        $template = new $class();
        if ($template->getFromDBByCrit(['name' => $name, 'entities_id' => 0])) {
            return (int) $template->getID();
        }

        return (int) $template->add([
            'name' => $name,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
    }

    /**
     * @param class-string<ChangeTemplateMandatoryField|ProblemTemplateMandatoryField> $class
     */
    private function ensureMandatory(string $class, string $fkField, int $templateId, int $num): void
    {
        if ($num < 0) {
            return;
        }
        $field = new $class();
        if (!$field->getFromDBByCrit([$fkField => $templateId, 'num' => $num])) {
            $field->add([$fkField => $templateId, 'num' => $num]);
        }
    }

    private function assignToProfiles(int $changeId, int $problemId): void
    {
        $profile = new Profile();
        foreach ($profile->find() as $row) {
            $profile->update(['id' => $row['id'], 'changetemplates_id' => $changeId, 'problemtemplates_id' => $problemId]);
        }
    }
}
