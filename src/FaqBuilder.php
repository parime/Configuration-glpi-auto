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

use Entity_KnowbaseItem;
use GlpiPlugin\Configurationglpiauto\Faq\HygieneArticles;
use GlpiPlugin\Configurationglpiauto\Faq\PortalArticles;
use KnowbaseItem;
use KnowbaseItemTranslation;

/**
 * Turns on `kb_faq_enabled` into two real, ready-to-read `KnowbaseItem` FAQ articles (issues #143
 * and #144) — written for the *end user* browsing GLPI's self-service Helpdesk portal (the
 * `/Helpdesk` React app this plugin's other builders configure), not for the GLPI administrator
 * running this wizard. Deliberately zero plugin/product jargon and zero internal class/field
 * names in the article bodies themselves: a non-technical employee who has never used GLPI is the
 * target reader, confirmed against a real run of the portal (`/Helpdesk`, `/ServiceCatalog`,
 * `/front/helpdesk.faq.php` as the `post-only` demo profile) so the step-by-step wording matches
 * the actual menu labels ("Catalogue de services", "Signaler un incident", "Demander un service",
 * "Tickets en cours"/"Tickets résolus") rather than guessed ones.
 *
 * No static docs equivalent exists in this repo (`docs/TUTORIAL.md` is admin-facing, walking the
 * wizard itself, not the portal an end user lands on afterward) — this repo's own precedent for
 * *any* end-user-facing content is always to seed it as real GLPI data via a `Config`-gated
 * builder (`KnowbaseCategoryBuilder` for KB categories, `SolutionLibraryBuilder` for solution
 * templates...), never a markdown file nobody using the deployed instance would ever open. Seeding
 * real `KnowbaseItem` rows with `is_faq=1` means these two articles actually show up in the
 * instance's native FAQ (searchable/browsable from `/front/helpdesk.faq.php`) the moment the admin
 * runs this wizard, exactly like every other "content library" this plugin ships.
 *
 * Visibility: confirmed by reading GLPI 11.0.8's own `KnowbaseItem::getVisibilityCriteriaKB()`
 * that an FAQ row with *no* visibility link row at all is invisible to everyone except its own
 * author/a KB admin — `is_faq=1` alone is not enough, unlike what the admin-facing "add" screen
 * might suggest. An `Entity_KnowbaseItem` row scoped to entity 0 + `is_recursive=1` (same "root,
 * recursive" idiom every other builder in this plugin already uses for instance-wide visibility,
 * e.g. `KnowbaseCategoryBuilder`) is what actually makes the article visible from any entity.
 *
 * Translation: full 6-language translation of the article body (not just a short label), same
 * precedent as `SolutionLibraryBuilder`'s solution templates — the source string is long-form
 * prose, not a UI string, so it stays a plain PHP literal here rather than going through
 * `__()`/`locales/*.po` (confirmed against this repo's own locale-completeness CI check, which
 * only extracts `__()`/`_n()` calls). Stored via `KnowbaseItemTranslation` (GLPI's own per-language
 * KB content table, confirmed by reading `KnowbaseItem.php`/`KnowbaseItemTranslation.php` — a
 * completely different mechanism from `DropdownTranslation`, which only covers `CommonDropdown`
 * item names/content and does not apply to `KnowbaseItem`), not `Translations::applyContent()`
 * (scoped to `DropdownTranslation` by its own docblock).
 */
class FaqBuilder
{
    /**
     * @return int Number of FAQ articles created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['kb_faq_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::articles() as $article) {
            $id = $this->getOrCreate($article['name'], $article['answer']);
            $this->ensureVisibleToEveryEntity($id);
            $this->applyTranslations($id, $article['translations']);
            $count++;
        }

        return $count;
    }

    /**
     * Articles d'usage du portail, puis (issue #321) les fiches de bonnes pratiques de sécurité.
     *
     * @return list<array{name: string, answer: string, translations: array<string, array{name: string, answer: string}>}>
     */
    private static function articles(): array
    {
        return array_merge(PortalArticles::all(), HygieneArticles::all());
    }

    /**
     * @return array<int, array{name: string, answer: string}>
     */
    public static function getArticlesPreview(): array
    {
        return array_map(
            static fn (array $article): array => ['name' => $article['name'], 'answer' => $article['answer']],
            self::articles()
        );
    }

    private function getOrCreate(string $name, string $answer): int
    {
        $item = new KnowbaseItem();
        if (Lookup::loadFirst($item, ['name' => $name])) {
            return (int) $item->getID();
        }

        return (int) $item->add([
            'name' => $name,
            'answer' => $answer,
            'entities_id' => 0,
            'is_recursive' => 1,
            'is_faq' => 1,
            'users_id' => 0,
        ]);
    }

    /**
     * See this class's own docblock for why this is required: `is_faq=1` alone does not make an
     * article visible to anyone but its author/a KB admin in GLPI 11.0.8.
     */
    private function ensureVisibleToEveryEntity(int $knowbaseitemsId): void
    {
        $link = new Entity_KnowbaseItem();
        $crit = ['knowbaseitems_id' => $knowbaseitemsId, 'entities_id' => 0];
        if (!Lookup::loadFirst($link, $crit)) {
            $link->add($crit + ['is_recursive' => 1]);
        }
    }

    /**
     * @param array<string, array{name: string, answer: string}> $translations
     */
    private function applyTranslations(int $knowbaseitemsId, array $translations): void
    {
        foreach ($translations as $language => $fields) {
            $translation = new KnowbaseItemTranslation();
            $crit = ['knowbaseitems_id' => $knowbaseitemsId, 'language' => $language];
            if (!Lookup::loadFirst($translation, $crit)) {
                $translation->add($crit + ['name' => $fields['name'], 'answer' => $fields['answer'], 'users_id' => 0]);
            } elseif ($translation->fields['name'] !== $fields['name'] || $translation->fields['answer'] !== $fields['answer']) {
                // Same add-only pitfall already fixed on Translations::applyIcon()/applyContent()
                // (see that class's own comments) — re-running the wizard after this content
                // itself changes must update the existing row, not silently leave stale text.
                $translation->update($crit + ['id' => (int) $translation->getID(), 'name' => $fields['name'], 'answer' => $fields['answer']]);
            }
        }
    }
}
