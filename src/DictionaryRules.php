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

use Rule;
use RuleAction;
use RuleCriteria;

/**
 * Issue #297 : une règle de dictionnaire GLPI (Administration > Dictionnaires) qui ramène plusieurs
 * noms remontés par l'inventaire à un nom unique, partagée par les dictionnaires des fabricants et
 * des types d'ordinateur.
 *
 * Critères en OU sur le champ `name`, action `assign` du nom canonique. Relancer l'assistant après
 * une mise à jour du plugin ajoute seulement les critères manquants (couple condition + motif), sans
 * dupliquer la règle ni toucher aux critères ajoutés à la main par l'administrateur.
 */
final class DictionaryRules
{
    /**
     * @param class-string<Rule> $subType
     * @param list<string> $exactNames comparés sans tenir compte des majuscules (Rule::PATTERN_IS)
     * @param string|null $regex expression régulière (Rule::REGEX_MATCH, GLPI ajoute lui-même les
     *        options « si »), pour rattraper les variantes que la liste exacte ne connaît pas
     * @param string $canonical nom attribué ; avec $actionType = 'regex_result', « #0 » reprend le
     *        premier groupe capturé par $regex (issue #303 : « Java 8 Update 401 » -> « Java 8 »)
     * @param string $actionField champ modifié par la règle : le nom, ou « _ignore_import » (valeur
     *        « 1 ») pour écarter l'élément à l'import (issue #315 : imprimantes virtuelles)
     */
    public static function ensure(string $subType, string $ruleName, string $canonical, array $exactNames, ?string $regex = null, string $actionType = 'assign', string $actionField = 'name'): void
    {
        $criteria = [];
        foreach ($exactNames as $name) {
            $criteria[] = [Rule::PATTERN_IS, $name];
        }
        if ($regex !== null) {
            $criteria[] = [Rule::REGEX_MATCH, $regex];
        }

        $rule = new Rule();
        if (Lookup::loadFirst($rule, ['name' => $ruleName, 'sub_type' => $subType])) {
            self::addMissingCriteria((int) $rule->getID(), $criteria);

            return;
        }

        $rulesId = (int) $rule->add([
            'name' => $ruleName,
            'sub_type' => $subType,
            'match' => Rule::OR_MATCHING,
            'is_active' => 1,
        ]);
        self::addMissingCriteria($rulesId, $criteria);

        (new RuleAction())->add([
            'rules_id' => $rulesId,
            'action_type' => $actionType,
            'field' => $actionField,
            'value' => $canonical,
        ]);
    }

    /**
     * @param list<array{0: int, 1: string}> $criteria
     */
    private static function addMissingCriteria(int $rulesId, array $criteria): void
    {
        $existing = [];
        foreach ((new RuleCriteria())->find(['rules_id' => $rulesId, 'criteria' => 'name']) as $row) {
            $existing[(int) $row['condition'] . "\0" . mb_strtolower((string) $row['pattern'])] = true;
        }

        foreach ($criteria as [$condition, $pattern]) {
            $key = $condition . "\0" . mb_strtolower($pattern);
            if (isset($existing[$key])) {
                continue;
            }
            (new RuleCriteria())->add([
                'rules_id' => $rulesId,
                'criteria' => 'name',
                'condition' => $condition,
                'pattern' => $pattern,
            ]);
            $existing[$key] = true;
        }
    }
}
