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
 * Le texte source est en français ; chaque langue est une classe à part, dans le même ordre, pour
 * qu'une traduction se relise et se corrige sans toucher aux autres.
 */
final class HygieneArticles
{
    /** Langue GLPI => classe portant la traduction. */
    private const TRANSLATIONS = [
        'en_GB' => HygieneArticlesEn::class,
        'de_DE' => HygieneArticlesDe::class,
        'it_IT' => HygieneArticlesIt::class,
        'es_ES' => HygieneArticlesEs::class,
        'pt_BR' => HygieneArticlesPtBr::class,
    ];

    /**
     * Même forme que FaqBuilder::ARTICLES.
     *
     * @return list<array{name: string, answer: string, translations: array<string, array{name: string, answer: string}>}>
     */
    public static function all(): array
    {
        $articles = [];
        foreach (HygieneArticlesFr::ARTICLES as $index => $source) {
            $translations = [];
            foreach (self::TRANSLATIONS as $language => $class) {
                $translations[$language] = $class::ARTICLES[$index];
            }
            $articles[] = $source + ['translations' => $translations];
        }

        return $articles;
    }
}
