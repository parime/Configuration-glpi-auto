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

use Config;
use ITILCategory;
use TaskTemplate;

/**
 * Arrivées et départs (issue grcmanager #362) : identifiants des gabarits de tâche et des
 * catégories que ce plugin crée pour l'arrivée et le départ d'un collaborateur, enregistrés dans
 * la configuration de GLPI au moment de leur création.
 *
 * Contrat stable lu par les autres plugins (assetsign) sans dépendre de ce code : contexte
 * `plugin:configurationglpiauto`, clés `arrival_tasktemplates_id`, `arrival_itilcategories_id`,
 * `departure_tasktemplates_id` et `departure_itilcategories_id`. On repère ainsi les objets par
 * identifiant, jamais par leur nom affiché (traduit, et susceptible d'être renommé).
 */
final class StaffMovement
{
    public const CONTEXT = 'plugin:configurationglpiauto';

    public const EVENTS = ['arrival', 'departure'];

    /**
     * Noms donnés par ce plugin, anciens compris : servent seulement à retrouver les objets d'une
     * installation antérieure à l'enregistrement des identifiants.
     */
    private const KNOWN_NAMES = [
        'arrival' => [
            TaskTemplate::class => ["Onboarding \u{2014} Arrivée collaborateur", 'Onboarding : arrivée collaborateur'],
            ITILCategory::class => ['Onboarding / Création de compte'],
        ],
        'departure' => [
            TaskTemplate::class => ["Offboarding \u{2014} Départ collaborateur", 'Offboarding : départ collaborateur'],
            ITILCategory::class => ['Offboarding / Suppression'],
        ],
    ];

    public static function key(string $event, string $itemtype): string
    {
        return $event . '_' . ($itemtype === TaskTemplate::class ? 'tasktemplates_id' : 'itilcategories_id');
    }

    /**
     * Enregistre l'identifiant d'un gabarit ou d'une catégorie d'arrivée ou de départ.
     */
    public static function record(string $event, string $itemtype, int $id): void
    {
        if ($id > 0 && in_array($event, self::EVENTS, true)) {
            Config::setConfigurationValues(self::CONTEXT, [self::key($event, $itemtype) => $id]);
        }
    }

    /**
     * Identifiants enregistrés et encore existants (0 sinon).
     *
     * @return array<string, array{tasktemplates_id: int, itilcategories_id: int}>
     */
    public static function ids(): array
    {
        $result = [];
        foreach (self::EVENTS as $event) {
            foreach ([TaskTemplate::class, ITILCategory::class] as $itemtype) {
                $key = self::key($event, $itemtype);
                $id = (int) (Config::getConfigurationValues(self::CONTEXT, [$key])[$key] ?? 0);
                $short = $itemtype === TaskTemplate::class ? 'tasktemplates_id' : 'itilcategories_id';
                $result[$event][$short] = $id > 0 && (new $itemtype())->getFromDB($id) ? $id : 0;
            }
        }

        return $result;
    }

    /**
     * Installation antérieure : retrouve une seule fois les objets déjà créés par ce plugin (par
     * leurs noms connus) et enregistre leurs identifiants. Un identifiant déjà enregistré n'est
     * jamais remplacé.
     */
    public static function recordExisting(): void
    {
        $current = self::ids();
        foreach (self::KNOWN_NAMES as $event => $byType) {
            foreach ($byType as $itemtype => $names) {
                $short = $itemtype === TaskTemplate::class ? 'tasktemplates_id' : 'itilcategories_id';
                if ($current[$event][$short] > 0) {
                    continue;
                }
                $item = new $itemtype();
                if (Lookup::loadFirst($item, ['name' => $names])) {
                    self::record($event, $itemtype, (int) $item->getID());
                }
            }
        }
    }

    public static function uninstall(): void
    {
        $keys = [];
        foreach (self::EVENTS as $event) {
            $keys[] = self::key($event, TaskTemplate::class);
            $keys[] = self::key($event, ITILCategory::class);
        }
        Config::deleteConfigurationValues(self::CONTEXT, $keys);
    }
}
