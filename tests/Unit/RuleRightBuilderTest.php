<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Parime
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

use GlpiPlugin\Configurationglpiauto\RuleRightBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Covers only RuleRightBuilder::preview() — a pure function over the tree shape, no GLPI
 * bootstrap needed (see EntityBuilderTest for why build() itself isn't unit-tested here).
 */
final class RuleRightBuilderTest extends TestCase
{
    public function testPreviewOnlyIncludesLeafEntities(): void
    {
        $tree = [
            [
                'name' => 'Conserto',
                'children' => [
                    ['name' => 'Bordeaux', 'children' => []],
                    ['name' => 'Rennes', 'children' => []],
                ],
            ],
        ];

        $this->assertSame(
            [
                ['name' => 'Bordeaux', 'group' => 'GLPI_Bordeaux'],
                ['name' => 'Rennes', 'group' => 'GLPI_Rennes'],
            ],
            RuleRightBuilder::preview($tree, 'GLPI_{ENTITY}')
        );
    }

    public function testPreviewSubstitutesSpacesWithUnderscoresInGroupName(): void
    {
        $tree = [['name' => 'Fabrique Digitale', 'children' => []]];

        $this->assertSame(
            [['name' => 'Fabrique Digitale', 'group' => 'AD_Fabrique_Digitale']],
            RuleRightBuilder::preview($tree, 'AD_{ENTITY}')
        );
    }

    public function testPreviewOnEmptyTreeIsEmptyArray(): void
    {
        $this->assertSame([], RuleRightBuilder::preview([], 'GLPI_{ENTITY}'));
    }

    public function testPreviewOnTemplateWithoutPlaceholderIsEmptyArray(): void
    {
        $tree = [['name' => 'Bordeaux', 'children' => []]];

        $this->assertSame([], RuleRightBuilder::preview($tree, 'GLPI_FIXED'));
    }

    public function testPreviewSkipsIntermediateContainerNodes(): void
    {
        $tree = [
            [
                'name' => 'Client A',
                'children' => [
                    [
                        'name' => 'Region Sud',
                        'children' => [
                            ['name' => 'Toulouse', 'children' => []],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame(
            [['name' => 'Toulouse', 'group' => 'GLPI_Toulouse']],
            RuleRightBuilder::preview($tree, 'GLPI_{ENTITY}')
        );
    }
}
