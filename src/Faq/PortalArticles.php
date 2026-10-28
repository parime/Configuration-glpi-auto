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
 * Les deux articles d'usage du portail (utiliser le catalogue de services, choisir entre
 * « Signaler un incident » et « Faire une demande »). Contenu dans data/faq-portal/<langue>.json,
 * voir JsonArticleSet ; écrit jusqu'ici en HTML dans FaqBuilder (#355).
 */
final class PortalArticles
{
    private const SET = 'faq-portal';

    /**
     * @return list<array{name: string, answer: string, translations: array<string, array{name: string, answer: string}>}>
     */
    public static function all(): array
    {
        return JsonArticleSet::all(self::SET);
    }
}
