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
 * Issue #321 : fiches de bonnes pratiques de sécurité (mot de passe, verrouillage, hameçonnage,
 * mises à jour...), ajoutées à la FAQ du portail avec ses deux articles d'usage (FaqBuilder).
 * Le contenu est dans data/faq-hygiene/<langue>.json (texte source en français, même ordre dans
 * chaque langue) : un contenu éditorial, pas du HTML d'écran écrit en PHP.
 */
final class HygieneArticles
{
    private const SOURCE = 'fr_FR';

    private const TRANSLATIONS = ['en_GB', 'de_DE', 'it_IT', 'es_ES', 'pt_BR'];

    /**
     * Articles d'une langue, dans l'ordre du texte source.
     *
     * @return list<array{name: string, answer: string}>
     */
    public static function forLanguage(string $language): array
    {
        $file = dirname(__DIR__, 2) . '/data/faq-hygiene/' . basename($language) . '.json';
        $decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * Même forme que FaqBuilder::ARTICLES.
     *
     * @return list<array{name: string, answer: string, translations: array<string, array{name: string, answer: string}>}>
     */
    public static function all(): array
    {
        $translated = [];
        foreach (self::TRANSLATIONS as $language) {
            $translated[$language] = self::forLanguage($language);
        }
        $articles = [];
        foreach (self::forLanguage(self::SOURCE) as $index => $source) {
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
