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

use Glpi\Form\Destination\FormDestination;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\Section;

/**
 * Issue #320 : suivi des formulaires du catalogue construits par le plugin, pour qu'une nouvelle
 * exécution de l'assistant les **mette à jour** quand leur définition a évolué, sans doublon et sans
 * écraser les retouches d'un administrateur.
 *
 * Une ligne par formulaire : sa clé de définition, l'identifiant du formulaire, l'empreinte de la
 * définition qui l'a produit et l'empreinte de sa structure telle qu'enregistrée juste après la
 * construction (nom, en-tête, description, sections, questions, conditions, destinations). Si la
 * structure actuelle diffère de celle enregistrée, quelqu'un l'a modifiée dans GLPI : le formulaire
 * est laissé tel quel. Un formulaire créé par une version antérieure (sans ligne) n'est jamais
 * repris : rien ne permet de savoir s'il a été retouché.
 */
final class ManagedForm
{
    public const TABLE = 'glpi_plugin_configurationglpiauto_managedforms';

    /** Le formulaire a été créé. */
    public const CREATED = 'created';

    /** Le formulaire existant a été reconstruit selon la nouvelle définition. */
    public const UPDATED = 'updated';

    /** Rien à faire : déjà à jour. */
    public const UNCHANGED = 'unchanged';

    /** Le formulaire a été retouché dans GLPI (ou précède ce suivi) : laissé tel quel. */
    public const KEPT = 'kept';

    /**
     * @return array{forms_forms_id: int, definition_hash: string, structure_hash: string}|null
     */
    public static function find(string $key): ?array
    {
        global $DB;

        $row = $DB->request([
            'FROM'  => self::TABLE,
            'WHERE' => ['form_key' => $key],
            'LIMIT' => 1,
        ])->current();
        if ($row === null) {
            return null;
        }

        return [
            'forms_forms_id' => (int) $row['forms_forms_id'],
            'definition_hash' => (string) $row['definition_hash'],
            'structure_hash' => (string) $row['structure_hash'],
        ];
    }

    public static function record(string $key, int $formId, string $definitionHash): void
    {
        global $DB;

        $values = [
            'forms_forms_id' => $formId,
            'definition_hash' => $definitionHash,
            'structure_hash' => self::structureHash($formId),
            'date_mod' => date('Y-m-d H:i:s'),
        ];
        if (self::find($key) === null) {
            $DB->insert(self::TABLE, ['form_key' => $key, 'date_creation' => $values['date_mod']] + $values);
        } else {
            $DB->update(self::TABLE, $values, ['form_key' => $key]);
        }
    }

    public static function forget(string $key): void
    {
        global $DB;

        $DB->delete(self::TABLE, ['form_key' => $key]);
    }

    public static function definitionHash(array $definition): string
    {
        return hash('sha256', (string) json_encode($definition));
    }

    /**
     * Empreinte de ce qu'un administrateur peut modifier dans l'éditeur de formulaires. Les
     * identifiants, dates, compteurs d'usage et l'état actif/épinglé n'en font pas partie : les
     * questions sont recréées à chaque reconstruction, et activer ou désactiver un formulaire ne
     * change pas son contenu.
     */
    public static function structureHash(int $formId): string
    {
        global $DB;

        $form = $DB->request([
            'SELECT' => ['name', 'header', 'description'],
            'FROM'   => Form::getTable(),
            'WHERE'  => ['id' => $formId],
        ])->current();

        $sections = [];
        $sectionRows = $DB->request([
            'SELECT' => ['id', 'name', 'description', 'rank', 'visibility_strategy', 'conditions'],
            'FROM'   => Section::getTable(),
            'WHERE'  => ['forms_forms_id' => $formId],
            'ORDER'  => ['rank ASC', 'id ASC'],
        ]);
        foreach ($sectionRows as $section) {
            $questions = [];
            $questionRows = $DB->request([
                'SELECT' => ['name', 'type', 'is_mandatory', 'vertical_rank', 'horizontal_rank', 'description',
                    'default_value', 'extra_data', 'visibility_strategy', 'conditions', 'validation_strategy',
                    'validation_conditions'],
                'FROM'   => Question::getTable(),
                'WHERE'  => ['forms_sections_id' => $section['id']],
                'ORDER'  => ['vertical_rank ASC', 'horizontal_rank ASC', 'id ASC'],
            ]);
            foreach ($questionRows as $question) {
                $questions[] = array_values($question);
            }
            unset($section['id']);
            $sections[] = [array_values($section), $questions];
        }

        $destinations = [];
        $destinationRows = $DB->request([
            'SELECT' => ['itemtype', 'name', 'config', 'creation_strategy', 'conditions'],
            'FROM'   => FormDestination::getTable(),
            'WHERE'  => ['forms_forms_id' => $formId],
            'ORDER'  => ['id ASC'],
        ]);
        foreach ($destinationRows as $destination) {
            $destinations[] = array_values($destination);
        }

        return hash('sha256', (string) json_encode([
            $form === null ? null : array_values($form),
            $sections,
            $destinations,
        ]));
    }
}
