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

use CommonDBTM;

/**
 * Recherche « trouver ou créer » tolérante aux doublons.
 *
 * `CommonDBTM::getFromDBByCrit()` lève une `TooManyResultsException` (GLPI 11 et 12) dès que plus
 * d'une ligne correspond — or GLPI autorise des noms en double, et les comparaisons MySQL ignorent la
 * casse : un fabricant « LENOVO » remonté par l'inventaire et un « Lenovo » saisi à la main suffisaient
 * à faire planter l'assistant. Ici, la plus ancienne ligne (plus petit identifiant) est retenue.
 */
final class Lookup
{
    /**
     * Charge dans $item le premier enregistrement correspondant aux critères.
     *
     * @param array<string|int, mixed> $criteria critères au format de DBmysql::request() (WHERE)
     */
    public static function loadFirst(CommonDBTM $item, array $criteria): bool
    {
        global $DB;

        $index = $item::getIndexName();
        $row = $DB->request([
            'SELECT' => [$index],
            'FROM'   => $item::getTable(),
            'WHERE'  => $criteria,
            'ORDER'  => $index . ' ASC',
            'LIMIT'  => 1,
        ])->current();

        return $row !== null && $item->getFromDB((int) $row[$index]);
    }
}
