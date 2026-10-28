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

namespace GlpiPlugin\Configurationglpiauto\Faq;

/**
 * Articles de FAQ rangés dans data/<jeu>/<langue>.json : texte source en français, même ordre dans
 * chaque langue. Un contenu éditorial, pas du HTML d'écran écrit en PHP (issues #321 et #355).
 */
final class JsonArticleSet
{
    private const SOURCE = 'fr_FR';

    private const TRANSLATIONS = ['en_GB', 'de_DE', 'it_IT', 'es_ES', 'pt_BR'];

    /**
     * Articles d'une langue, dans l'ordre du texte source.
     *
     * @return list<array{name: string, answer: string}>
     */
    public static function forLanguage(string $set, string $language): array
    {
        $file = dirname(__DIR__, 2) . '/data/' . basename($set) . '/' . basename($language) . '.json';
        $decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * Articles en français, chacun avec ses traductions (forme attendue par FaqBuilder).
     *
     * @return list<array{name: string, answer: string, translations: array<string, array{name: string, answer: string}>}>
     */
    public static function all(string $set): array
    {
        $translated = [];
        foreach (self::TRANSLATIONS as $language) {
            $translated[$language] = self::forLanguage($set, $language);
        }
        $articles = [];
        foreach (self::forLanguage($set, self::SOURCE) as $index => $source) {
            $translations = [];
            foreach ($translated as $language => $list) {
                if (isset($list[$index])) {
                    $translations[$language] = $list[$index];
                }
            }
            $articles[] = $source + ['translations' => $translations];
        }

        return $articles;
    }
}
