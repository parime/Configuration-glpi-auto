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

use DropdownTranslation;
use RequestType;

/**
 * Turns on `request_type_translations_enabled` into real translations for GLPI's own 6 native
 * `RequestType` rows ("Sources des demandes") — confirmed a genuine gap by reading GLPI's own
 * installer (`install/empty_data.php`): these 6 rows (`Helpdesk`/`E-Mail`/`Phone`/`Direct`/
 * `Written`/`Other`) are hardcoded literal strings, never wrapped in `__()`, inserted identically
 * regardless of the language chosen at install time. `RequestType extends CommonDropdown`, so
 * `Dropdown::getDropdownName()` *does* consult `DropdownTranslation` for a session-language
 * override — but since GLPI never seeds one, every non-English session sees the same raw English
 * text. Confirmed no `glpi_dropdowntranslations` row exists for `RequestType` on a fresh install.
 *
 * A different shape from `Translations::applyIcon()`'s other 9 callers: those all translate a name
 * *this plugin itself* wrote in French, so French is always correct as-is and only the other 4
 * languages need a real translation. Here the existing native name is English, so all 5 languages
 * (including `fr_FR`) need a real translation — `Translations::applyIcon()` doesn't fit that shape,
 * hence this builder writes its own `DropdownTranslation` rows directly instead of going through
 * the shared French-keyed `MAP`. Never creates `RequestType` rows (GLPI already ships exactly 6,
 * confirmed sufficient in an earlier audit — see ROADMAP.md) — only translates what's already
 * there, found by name lookup, skipped silently if a row has been renamed/removed.
 */
class RequestTypeTranslationBuilder
{
    private const TRANSLATIONS = [
        'Helpdesk' => ['fr_FR' => 'Formulaire web', 'de_DE' => 'Weboberfläche', 'it_IT' => 'Modulo web', 'es_ES' => 'Formulario web'],
        'E-Mail' => ['fr_FR' => 'E-mail', 'de_DE' => 'E-Mail', 'it_IT' => 'E-mail', 'es_ES' => 'Correo electrónico'],
        'Phone' => ['fr_FR' => 'Téléphone', 'de_DE' => 'Telefon', 'it_IT' => 'Telefono', 'es_ES' => 'Teléfono'],
        'Direct' => ['fr_FR' => 'Direct', 'de_DE' => 'Direkt', 'it_IT' => 'Diretto', 'es_ES' => 'Directo'],
        'Written' => ['fr_FR' => 'Écrit', 'de_DE' => 'Schriftlich', 'it_IT' => 'Scritto', 'es_ES' => 'Escrito'],
        'Other' => ['fr_FR' => 'Autre', 'de_DE' => 'Sonstige', 'it_IT' => 'Altro', 'es_ES' => 'Otro'],
    ];

    /**
     * @return int Number of native RequestType rows translated (found + at least one language
     *             written) — not a count of DropdownTranslation rows, one per source type instead.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['request_type_translations_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::TRANSLATIONS as $englishName => $byLanguage) {
            $type = new RequestType();
            if (!$type->getFromDBByCrit(['name' => $englishName])) {
                continue;
            }
            $id = (int) $type->getID();
            foreach ($byLanguage as $language => $text) {
                $translation = new DropdownTranslation();
                $crit = ['itemtype' => RequestType::class, 'items_id' => $id, 'language' => $language, 'field' => 'name'];
                if (!$translation->getFromDBByCrit($crit)) {
                    $translation->add($crit + ['value' => $text]);
                }
            }
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, array{fr_FR: string, de_DE: string, it_IT: string, es_ES: string}>
     */
    public static function getPreview(): array
    {
        return self::TRANSLATIONS;
    }
}
