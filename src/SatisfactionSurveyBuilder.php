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

/**
 * First cross-plugin integration in this project: when the third-party "More satisfaction" plugin
 * (marketplace key `satisfaction`, `pluginsGLPI/satisfaction`) is installed and active, creates one
 * ready-to-use `Survey` with 3 real questions — installed and inspected live on a real GLPI 11.0.8
 * instance before writing this (marketplace unlocked with a real registration key, plugin
 * downloaded/activated via the native marketplace, `glpi_plugin_satisfaction_*` tables read via
 * `DESCRIBE`), not guessed from documentation.
 *
 * Writes directly to the plugin's own tables via GLPI's `$DB` global rather than instantiating its
 * PHP classes (`GlpiPlugin\Satisfaction\Survey`/`SurveyQuestion`) — that plugin isn't part of this
 * one's own dependencies or CI environment (PHPStan has no stubs for it, and a hard `use` of a
 * class that may not exist on a given install is exactly the kind of fragility a config-generation
 * plugin already avoids elsewhere), so the schema — verified directly, not the class API — is the
 * stable contract to build against here.
 *
 * `Survey` fields: `entities_id`/`is_recursive` (same root+recursive scoping as everywhere else in
 * this plugin), `is_active`, `reminders_days` (native default 30, left as-is). `SurveyQuestion`
 * fields: `type` (`note`/`yesno`/`textarea`, confirmed exhaustive via `SurveyQuestion::getQuestionTypeList()`),
 * `number` (dual meaning confirmed in `SurveyQuestion.php`: display/insertion order for
 * `yesno`/`textarea`, the rating *scale* max — not order — specifically for `note`), `default_value`
 * (pre-selected value on that scale, 1..number).
 */
class SatisfactionSurveyBuilder
{
    private const SURVEY_NAME = 'Enquête de satisfaction standard';

    private const QUESTIONS = [
        ['name' => 'Note de satisfaction globale', 'type' => 'note', 'number' => 5, 'default_value' => 3],
        ['name' => 'Votre problème a-t-il été résolu ?', 'type' => 'yesno', 'number' => 1, 'default_value' => 1],
        ['name' => 'Avez-vous des remarques ou suggestions ?', 'type' => 'textarea', 'number' => 2, 'default_value' => 1],
    ];

    /**
     * @return int 1 if the survey was created/already existed while the plugin is active, 0 otherwise
     *             (toggle off, or the third-party plugin isn't installed/active).
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['satisfaction_plugin_survey_enabled'])) {
            return 0;
        }

        if (!self::isThirdPartyPluginActive()) {
            return 0;
        }

        global $DB;

        $existing = $DB->request([
            'FROM' => 'glpi_plugin_satisfaction_surveys',
            'WHERE' => ['name' => self::SURVEY_NAME, 'entities_id' => 0],
        ]);
        if (count($existing) > 0) {
            return 1;
        }

        $DB->insert('glpi_plugin_satisfaction_surveys', [
            'name' => self::SURVEY_NAME,
            'comment' => 'Créée par l\'assistant de configuration GLPI Auto.',
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_active' => 1,
        ]);
        $surveyId = $DB->insertId();

        foreach (self::QUESTIONS as $question) {
            $DB->insert('glpi_plugin_satisfaction_surveyquestions', [
                'plugin_satisfaction_surveys_id' => $surveyId,
                'name' => $question['name'],
                'type' => $question['type'],
                'number' => $question['number'],
                'default_value' => $question['default_value'],
            ]);
        }

        return 1;
    }

    /**
     * Exposed separately from build() so front/wizard.php can decide whether to show the wizard
     * section at all — the user's own requirement: no trace in the form when the plugin isn't
     * installed, not just a toggle that would silently do nothing.
     */
    public static function isThirdPartyPluginActive(): bool
    {
        return class_exists('Plugin') && \Plugin::isPluginActive('satisfaction');
    }
}
