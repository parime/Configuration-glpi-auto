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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration;

use Glpi\Form\Condition\VisibilityStrategy;
use Glpi\Form\Form;
use Glpi\Form\Question;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\HelpdeskFormBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unlike every other `*FormBuilder` in this plugin, `HelpdeskFormBuilder` doesn't create a form — it
 * mutates GLPI 11's own two native self-service forms ("Report an issue"/"Request a service",
 * real, shared, permanent fixtures on this instance) to hide 3 specific questions. `setUp()`/
 * `tearDown()` capture and restore each targeted question's real original visibility state, so this
 * suite leaves no lasting change on the shared dev instance.
 *
 * Real regression this class guards against (see its own docblock) : `Question::conditions` has no
 * "always hidden" strategy — an empty `conditions` array combined with `VISIBLE_IF` is what actually
 * achieves it (confirmed by reading `Engine::computeConditions()`). Asserting the exact stored
 * strategy + empty conditions is the only way to catch a regression to, say, `HIDDEN_IF` with an
 * empty array, which per that same engine logic would do the opposite (always visible).
 */
final class HelpdeskFormBuilderTest extends TestCase
{
    private const NATIVE_FORM_NAMES = ['Report an issue', 'Request a service'];

    private const HIDE_QUESTION_NAMES = ['Urgency', 'Observers', 'Location'];

    /** @var array<string, array<string, array{visibility_strategy: string, conditions: string}>> */
    private array $originalStateByFormAndQuestion = [];

    protected function setUp(): void
    {
        foreach (self::NATIVE_FORM_NAMES as $formName) {
            $form = new Form();
            if (!$form->getFromDBByCrit(['name' => $formName])) {
                continue;
            }
            foreach ($form->getQuestions() as $question) {
                if (!in_array($question->fields['name'], self::HIDE_QUESTION_NAMES, true)) {
                    continue;
                }
                $this->originalStateByFormAndQuestion[$formName][$question->fields['name']] = [
                    'visibility_strategy' => $question->fields['visibility_strategy'],
                    'conditions' => $question->fields['conditions'],
                ];
            }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalStateByFormAndQuestion as $formName => $questionsByName) {
            $form = new Form();
            if (!$form->getFromDBByCrit(['name' => $formName])) {
                continue;
            }
            foreach ($form->getQuestions() as $question) {
                if (!isset($questionsByName[$question->fields['name']])) {
                    continue;
                }
                $original = $questionsByName[$question->fields['name']];
                $question->update([
                    'id' => $question->getID(),
                    'visibility_strategy' => $original['visibility_strategy'],
                    'conditions' => $original['conditions'],
                ]);
            }
        }
    }

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'helpdesk_form_hide_fields' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsFalseWhenDisabled(): void
    {
        $applied = (new HelpdeskFormBuilder())->apply($this->buildConfig(false));

        $this->assertFalse($applied);
    }

    public function testHidesUrgencyObserversAndLocationOnBothNativeForms(): void
    {
        $applied = (new HelpdeskFormBuilder())->apply($this->buildConfig(true));

        $this->assertTrue($applied);

        foreach (self::NATIVE_FORM_NAMES as $formName) {
            $form = new Form();
            $this->assertTrue($form->getFromDBByCrit(['name' => $formName]), "Native form '$formName' must exist on this instance.");

            $foundQuestionNames = [];
            foreach ($form->getQuestions() as $question) {
                if (!in_array($question->fields['name'], self::HIDE_QUESTION_NAMES, true)) {
                    continue;
                }
                $foundQuestionNames[] = $question->fields['name'];

                $this->assertSame(
                    VisibilityStrategy::VISIBLE_IF->value,
                    $question->fields['visibility_strategy'],
                    "'{$question->fields['name']}' on '$formName' must use VISIBLE_IF — there is no plain \"always hidden\" strategy."
                );
                $this->assertSame(
                    [],
                    json_decode($question->fields['conditions'], true),
                    "'{$question->fields['name']}' on '$formName' must have an empty condition list — VISIBLE_IF + no conditions is what actually hides it permanently."
                );
            }

            sort($foundQuestionNames);
            $expected = self::HIDE_QUESTION_NAMES;
            sort($expected);
            $this->assertSame($expected, $foundQuestionNames, "All 3 target questions must exist on '$formName'.");
        }
    }
}
