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

use Glpi\Application\View\TemplateRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Chaque gabarit Twig du plugin se compile. Une apostrophe non échappée dans une chaîne
 * traduite cassait tout l'assistant sans qu'aucun test ne le voie (relevé pendant l'issue #321).
 */
final class TemplateSyntaxTest extends TestCase
{
    public function testEveryTemplateCompiles(): void
    {
        $environment = TemplateRenderer::getInstance()->getEnvironment();
        $root = dirname(__DIR__, 2) . '/templates';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $count = 0;
        foreach ($files as $file) {
            if (!str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }
            $name = '@configurationglpiauto/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $source = $environment->getLoader()->getSourceContext($name);
            $environment->compileSource($source);
            $count++;
        }
        $this->assertGreaterThanOrEqual(7, $count);
    }
}
