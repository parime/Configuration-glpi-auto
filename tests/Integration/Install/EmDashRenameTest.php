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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration\Install;

use DropdownTranslation;
use GlpiPlugin\Configurationglpiauto\Install\EmDashRename;
use ITILCategory;
use PHPUnit\Framework\TestCase;
use TaskTemplate;

/**
 * Issue #390 : à la mise à jour, les objets créés par une version précédente sont renommés (nom,
 * nom complet de l'arborescence, traductions) ; un nom choisi par l'administrateur est conservé.
 */
final class EmDashRenameTest extends TestCase
{
    /** @var list<array{0: class-string<\CommonDBTM>, 1: int}> */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->created) as [$itemtype, $id]) {
            (new $itemtype())->delete(['id' => $id], true);
        }
    }

    public function testPluginNamesAreRenamedAndCustomNamesKept(): void
    {
        $template = $this->create(TaskTemplate::class, ['name' => "Onboarding \u{2014} New employee", 'content' => 'Test']);
        $custom = $this->create(TaskTemplate::class, ['name' => "Mon gabarit \u{2014} personnel", 'content' => 'Test']);
        $parent = $this->create(ITILCategory::class, ['name' => 'PHPUnit 390']);
        $child = $this->create(ITILCategory::class, ['name' => "Escalade \u{2014} Niveau 2", 'itilcategories_id' => $parent]);
        $translation = $this->create(DropdownTranslation::class, [
            'itemtype' => TaskTemplate::class, 'items_id' => $template, 'language' => 'de_DE',
            'field' => 'name', 'value' => "Onboarding \u{2014} Neuer Mitarbeiter",
        ]);

        $this->assertGreaterThanOrEqual(3, EmDashRename::migrate());

        $item = new TaskTemplate();
        $item->getFromDB($template);
        $this->assertSame('Onboarding: New employee', $item->fields['name']);
        $item->getFromDB($custom);
        $this->assertSame("Mon gabarit \u{2014} personnel", $item->fields['name'], 'Nom personnalisé conservé.');

        $category = new ITILCategory();
        $category->getFromDB($child);
        $this->assertSame('Escalade - Niveau 2', $category->fields['name']);
        $this->assertSame('PHPUnit 390 > Escalade - Niveau 2', $category->fields['completename']);

        $row = new DropdownTranslation();
        $row->getFromDB($translation);
        $this->assertSame('Onboarding: Neuer Mitarbeiter', $row->fields['value']);

        $this->assertSame(0, EmDashRename::migrate(), 'Seconde passe : plus rien à renommer.');
    }

    /**
     * @param class-string<\CommonDBTM> $itemtype
     * @param array<string, mixed> $input
     */
    private function create(string $itemtype, array $input): int
    {
        $id = (int) (new $itemtype())->add($input + ['entities_id' => 0, 'is_recursive' => 1]);
        $this->assertGreaterThan(0, $id, $itemtype);
        $this->created[] = [$itemtype, $id];
        return $id;
    }
}
