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

namespace GlpiPlugin\Configurationglpiauto\Tests\Unit;

use GlpiPlugin\Configurationglpiauto\Faq\HygieneArticles;
use GlpiPlugin\Configurationglpiauto\Faq\HygieneArticlesFr;
use PHPUnit\Framework\TestCase;

/**
 * Issue #321 : chaque traduction des fiches de bonnes pratiques suit le texte source.
 */
final class HygieneArticlesTest extends TestCase
{
    /** Suite des balises HTML d'un article (la structure doit être la même dans chaque langue). */
    private static function tags(string $html): array
    {
        preg_match_all('/<\/?(p|h4|ul|li|strong)\b/', $html, $matches);

        return $matches[0];
    }

    public function testEveryLanguageHasTheSameArticlesAndStructure(): void
    {
        $articles = HygieneArticles::all();
        $this->assertCount(12, $articles);

        foreach ($articles as $index => $article) {
            $this->assertSame(HygieneArticlesFr::ARTICLES[$index]['name'], $article['name']);
            $this->assertSame(['en_GB', 'de_DE', 'it_IT', 'es_ES', 'pt_BR'], array_keys($article['translations']));
            foreach ($article['translations'] as $language => $translation) {
                $this->assertNotSame('', trim($translation['name']), "$language #$index");
                $this->assertStringNotContainsString('Bonnes pratiques', $translation['name'], "$language #$index");
                $this->assertSame(
                    self::tags($article['answer']),
                    self::tags($translation['answer']),
                    "Structure HTML différente : $language, article #$index"
                );
            }
        }
    }

    public function testNamesAreUniquePerLanguage(): void
    {
        $articles = HygieneArticles::all();
        $this->assertCount(12, array_unique(array_column($articles, 'name')));
        foreach (['en_GB', 'de_DE', 'it_IT', 'es_ES', 'pt_BR'] as $language) {
            $names = array_map(static fn (array $a): string => $a['translations'][$language]['name'], $articles);
            $this->assertCount(12, array_unique($names), $language);
        }
    }
}
