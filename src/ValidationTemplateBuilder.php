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

use ITILValidationTemplate;
use ValidationStep;

/**
 * Turns on `validation_templates_enabled` into a real `ITILValidationTemplate` library
 * (Configuration > Intitulés > Assistance > "Gabarits de validation") — ready-to-use validation
 * request messages, so a technician submitting a ticket for approval doesn't write the same
 * explanation from scratch every time. GLPI ships none by default.
 *
 * "Validation comité" is linked to the "Validation comité (2/3)" `ValidationStep` if it exists
 * (`GeneralSettingsBuilder`'s `committee_validation_enabled`, Sprint 25/26) — looked up by name,
 * silently left on GLPI's default "Validation" step otherwise, same defensive lookup pattern used
 * throughout this plugin (e.g. `GeneralSettingsBuilder::projectTaskStateMapping()`): never guess
 * an ID, skip the wiring rather than link to the wrong step.
 */
class ValidationTemplateBuilder
{
    private const COMMITTEE_STEP_NAME = 'Validation comité (2/3)';

    private const TEMPLATES = [
        [
            'name' => 'Validation hiérarchique (N+1)',
            'content' => "Bonjour,\n\nUne demande nécessite votre validation en tant que responsable hiérarchique du demandeur.\n\nMerci de valider ou refuser avec commentaire.",
        ],
        [
            'name' => 'Validation technique',
            'content' => "Bonjour,\n\nCette demande est soumise à validation technique.\n\nMerci de vérifier la conformité technique/budgétaire avant validation.",
        ],
        [
            'name' => 'Validation comité',
            'content' => "Bonjour,\n\nCette demande requiert l'approbation du comité.\n\nMerci de valider ou refuser avec commentaire.",
            'committee' => true,
        ],
        [
            'name' => 'Validation sécurité',
            'content' => "Bonjour,\n\nCette demande a un impact sécurité, merci d'évaluer les risques avant validation.",
        ],
        [
            'name' => 'Validation simple',
            'content' => "Bonjour,\n\nMerci de valider ou refuser cette demande.",
        ],
    ];

    /**
     * @return int Number of validation templates created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['validation_templates_enabled'])) {
            return 0;
        }

        $committeeStepId = $this->findCommitteeStepId();

        $count = 0;
        foreach (self::TEMPLATES as $template) {
            $stepId = (!empty($template['committee']) && $committeeStepId !== null) ? $committeeStepId : 0;
            $this->getOrCreateTemplate($template['name'], $template['content'], $stepId);
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

    private function findCommitteeStepId(): ?int
    {
        $step = new ValidationStep();
        if ($step->getFromDBByCrit(['name' => self::COMMITTEE_STEP_NAME])) {
            return (int) $step->getID();
        }

        return null;
    }

    private function getOrCreateTemplate(string $name, string $content, int $validationStepId): int
    {
        $item = new ITILValidationTemplate();
        if ($item->getFromDBByCrit(['name' => $name])) {
            return (int) $item->getID();
        }

        return (int) $item->add([
            'name' => $name,
            'content' => $content,
            'validationsteps_id' => $validationStepId,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
    }
}
