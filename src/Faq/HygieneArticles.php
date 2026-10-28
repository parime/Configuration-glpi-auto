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
 * mises à jour...), ajoutées à la FAQ du portail avec ses deux articles d'usage (PortalArticles).
 * Le contenu est dans data/faq-hygiene/<langue>.json, voir JsonArticleSet.
 */
final class HygieneArticles
{
    private const SET = 'faq-hygiene';

    /**
     * @return list<array{name: string, answer: string}>
     */
    public static function forLanguage(string $language): array
    {
        return JsonArticleSet::forLanguage(self::SET, $language);
    }

    /**
     * @return list<array{name: string, answer: string, translations: array<string, array{name: string, answer: string}>}>
     */
    public static function all(): array
    {
        return JsonArticleSet::all(self::SET);
    }
}
