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
 * `request_type_icons_enabled` (added on explicit user request, #154) bakes an icon into both the
 * native `name` column and each translated value — `glpi_requesttypes` is a small, dedicated table
 * (only these 6 rows, never shared across contexts), unlike the custom-asset "type" dropdown table
 * where the same icon mechanism broke `Search` (see `FireSafetyAssetBuilder`'s docblock) — safe
 * here for the same reason it's safe on every other native dropdown this plugin applies icons to.
 *
 * A different shape from `Translations::applyIcon()`'s other 9 callers: those all translate a name
 * *this plugin itself* wrote in French, so French is always correct as-is and only the other 4
 * languages need a real translation. Here the existing native name is English, so all 6 languages
 * (including `fr_FR`) need a real translation — `Translations::applyIcon()` doesn't fit that shape,
 * hence this builder writes its own `DropdownTranslation` rows directly instead of going through
 * the shared French-keyed `MAP`. Never creates `RequestType` rows (GLPI already ships exactly 6,
 * confirmed sufficient in an earlier audit — see ROADMAP.md) — only translates what's already
 * there, found by name lookup, skipped silently if a row has been renamed/removed.
 */
class RequestTypeTranslationBuilder
{
    private const TRANSLATIONS = [
        'Helpdesk' => ['fr_FR' => 'Formulaire web', 'de_DE' => 'Weboberfläche', 'it_IT' => 'Modulo web', 'es_ES' => 'Formulario web', 'pt_BR' => 'Formulário web'],
        'E-Mail' => ['fr_FR' => 'E-mail', 'de_DE' => 'E-Mail', 'it_IT' => 'E-mail', 'es_ES' => 'Correo electrónico', 'pt_BR' => 'E-mail'],
        'Phone' => ['fr_FR' => 'Téléphone', 'de_DE' => 'Telefon', 'it_IT' => 'Telefono', 'es_ES' => 'Teléfono', 'pt_BR' => 'Telefone'],
        'Direct' => ['fr_FR' => 'Direct', 'de_DE' => 'Direkt', 'it_IT' => 'Diretto', 'es_ES' => 'Directo', 'pt_BR' => 'Direto'],
        'Written' => ['fr_FR' => 'Écrit', 'de_DE' => 'Schriftlich', 'it_IT' => 'Scritto', 'es_ES' => 'Escrito', 'pt_BR' => 'Escrito'],
        'Other' => ['fr_FR' => 'Autre', 'de_DE' => 'Sonstige', 'it_IT' => 'Altro', 'es_ES' => 'Otro', 'pt_BR' => 'Outro'],
    ];

    private const ICONS = [
        'Helpdesk' => '🖥️',
        'E-Mail' => '📧',
        'Phone' => '☎️',
        'Direct' => '🚶',
        'Written' => '✍️',
        'Other' => '❓',
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

        $withIcons = !empty($config->fields['request_type_icons_enabled']);

        $count = 0;
        foreach (self::TRANSLATIONS as $englishName => $byLanguage) {
            $icon = self::ICONS[$englishName] ?? '';
            $iconVariant = $icon !== '' ? trim($icon . ' ' . $englishName) : $englishName;

            // Matches either the bare or icon-prefixed name regardless of *this* run's own
            // withIcons value, same idempotency fix already applied to FireSafetyAssetBuilder/
            // PhysicalSecurityAssetBuilder — otherwise toggling icons off after a prior run baked
            // one into `name` would never find the row again (still searching for the bare name).
            $type = new RequestType();
            if (!$type->getFromDBByCrit(['name' => [$englishName, $iconVariant]])) {
                continue;
            }
            $id = (int) $type->getID();

            // The native English `name` column has no DropdownTranslation entry of its own (it's
            // already the source language) — bake the icon directly into it, same reasoning as the
            // two asset builders above: a session with no matching translation falls back to this
            // raw column.
            $displayName = $withIcons ? $iconVariant : $englishName;
            if ($type->fields['name'] !== $displayName) {
                $type->update(['id' => $id, 'name' => $displayName]);
            }

            foreach ($byLanguage as $language => $text) {
                $displayText = $icon !== '' && $withIcons ? trim($icon . ' ' . $text) : $text;
                $translation = new DropdownTranslation();
                $crit = ['itemtype' => RequestType::class, 'items_id' => $id, 'language' => $language, 'field' => 'name'];
                if (!$translation->getFromDBByCrit($crit)) {
                    $translation->add($crit + ['value' => $displayText]);
                } elseif ($translation->fields['value'] !== $displayText) {
                    // DropdownTranslation::prepareInputForUpdate() re-derives whether the update is
                    // legal from checkBeforeAddorUpdate(), which reads itemtype/items_id/field/
                    // language straight off $input — omitting them (id+value alone, the usual
                    // CommonDBTM update() shape) makes it silently reject the update (returns
                    // false, no exception) rather than actually change the row. Confirmed live: an
                    // id-only update() call returned false and left the old value in place.
                    $translation->update($crit + ['id' => (int) $translation->getID(), 'value' => $displayText]);
                }
            }
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, array{fr_FR: string, de_DE: string, it_IT: string, es_ES: string, pt_BR: string, icon: string}>
     */
    public static function getPreview(): array
    {
        $preview = [];
        foreach (self::TRANSLATIONS as $name => $translations) {
            $preview[$name] = $translations + ['icon' => self::ICONS[$name] ?? ''];
        }

        return $preview;
    }
}
