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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration;

use Entity_KnowbaseItem;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\FaqBuilder;
use KnowbaseItem;
use KnowbaseItemTranslation;
use PHPUnit\Framework\TestCase;

/**
 * Runs against a real GLPI instance (issues #143/#144).
 */
final class FaqBuilderTest extends TestCase
{
    private const NAMES = [
        'Comment utiliser le catalogue de services pour faire une demande',
        "Signaler un incident ou faire une demande : quelle différence et comment s'y prendre",
    ];

    protected function tearDown(): void
    {
        foreach (self::NAMES as $name) {
            $item = new KnowbaseItem();
            if ($item->getFromDBByCrit(['name' => $name])) {
                $id = (int) $item->getID();
                $link = new Entity_KnowbaseItem();
                foreach ($link->find(['knowbaseitems_id' => $id]) as $row) {
                    $link->delete(['id' => $row['id']], true);
                }
                $translation = new KnowbaseItemTranslation();
                foreach ($translation->find(['knowbaseitems_id' => $id]) as $row) {
                    $translation->delete(['id' => $row['id']], true);
                }
                $item->delete(['id' => $id], true);
            }
        }
    }

    public function testBuildDoesNothingWhenDisabled(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['kb_faq_enabled' => 0]);

        $this->assertSame(0, (new FaqBuilder())->build($config));

        $item = new KnowbaseItem();
        $this->assertFalse($item->getFromDBByCrit(['name' => self::NAMES[0]]));
    }

    public function testBuildCreatesBothArticlesAsVisibleFaqEntries(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['kb_faq_enabled' => 1]);

        $created = (new FaqBuilder())->build($config);
        $this->assertSame(2, $created);

        foreach (self::NAMES as $name) {
            $item = new KnowbaseItem();
            $this->assertTrue($item->getFromDBByCrit(['name' => $name]));
            $this->assertSame(1, (int) $item->fields['is_faq']);
            $this->assertNotEmpty($item->fields['answer']);

            $link = new Entity_KnowbaseItem();
            $this->assertTrue($link->getFromDBByCrit(['knowbaseitems_id' => $item->getID(), 'entities_id' => 0]));
            $this->assertSame(1, (int) $link->fields['is_recursive']);

            foreach (['en_GB', 'de_DE', 'it_IT', 'es_ES'] as $language) {
                $translation = new KnowbaseItemTranslation();
                $this->assertTrue(
                    $translation->getFromDBByCrit(['knowbaseitems_id' => $item->getID(), 'language' => $language]),
                    "Missing {$language} translation for \"{$name}\""
                );
                $this->assertNotEmpty($translation->fields['name']);
                $this->assertNotEmpty($translation->fields['answer']);
            }
        }
    }

    public function testBuildIsIdempotent(): void
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), ['kb_faq_enabled' => 1]);
        $builder = new FaqBuilder();

        $builder->build($config);
        $builder->build($config);

        $item = new KnowbaseItem();
        $matches = $item->find(['name' => self::NAMES[0]]);
        $this->assertCount(1, $matches);

        $itemId = (int) array_key_first($matches);
        $link = new Entity_KnowbaseItem();
        $this->assertCount(1, $link->find(['knowbaseitems_id' => $itemId, 'entities_id' => 0]));

        $translation = new KnowbaseItemTranslation();
        $this->assertCount(1, $translation->find(['knowbaseitems_id' => $itemId, 'language' => 'en_GB']));
    }
}
