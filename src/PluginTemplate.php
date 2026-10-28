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

use Glpi\Application\View\TemplateRenderer;
use Twig\Loader\FilesystemLoader;

/**
 * Contenu généré par un gabarit du plugin (#355) : ticket d'audit, modèle de notification...
 *
 * GLPI ne déclare l'espace de gabarits @configurationglpiauto qu'une fois le plugin activé ;
 * pendant l'installation (modèle de notification du rapport d'audit), il est déclaré ici.
 */
final class PluginTemplate
{
    /**
     * Rendu en chaîne, sans le saut de ligne final du fichier de gabarit (contenu stocké tel quel).
     * Un seul est retiré : un texte qui finit lui-même par un saut de ligne (commentaire Jira) le
     * garde.
     *
     * @param array<string, mixed> $variables
     */
    public static function render(string $template, array $variables = []): string
    {
        $environment = TemplateRenderer::getInstance()->getEnvironment();
        $loader = $environment->getLoader();
        if ($loader instanceof FilesystemLoader && !in_array('configurationglpiauto', $loader->getNamespaces(), true)) {
            $loader->addPath(dirname(__DIR__) . '/templates', 'configurationglpiauto');
        }

        $html = $environment->render('@configurationglpiauto/' . $template, $variables);

        return str_ends_with($html, "\n") ? substr($html, 0, -1) : $html;
    }
}
