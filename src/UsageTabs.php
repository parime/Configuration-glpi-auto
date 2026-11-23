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
use GlpiPlugin\Configurationglpiauto\AddressPlan\AddressPlanImporter;
use Session;
use Ticket;

/**
 * Issue #495 : barre d'onglets commune des écrans d'usage, sous la seule entrée de menu du plugin
 * (Configuration > Configuration GLPI Auto). Chaque écran y est joignable depuis les autres, sans
 * connaître son adresse. Les réglages du plugin ne sont pas ici : ils sont dans sa page de
 * configuration (Configuration > Plugins, front/config.php).
 *
 * Un onglet n'apparaît qu'avec les droits que sa page exige (les mêmes contrôles que la page).
 */
final class UsageTabs
{
    /**
     * Onglets dans l'ordre d'affichage : clé => [libellé, page, icône]. Six onglets, sept au plus
     * (charte, section 9). L'onglet « import » couvre les deux imports de tickets (Jira et CSV).
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function tabs(): array
    {
        return [
            'wizard'      => [__('Assistant', 'configurationglpiauto'), 'wizard.php', 'ti ti-wand'],
            'profiles'    => [__('Profils et Blueprints', 'configurationglpiauto'), 'profile.php', 'ti ti-template'],
            'audit'       => [__('Audit de configuration', 'configurationglpiauto'), 'audit.php', 'ti ti-shield-check'],
            'history'     => [__('Historique', 'configurationglpiauto'), 'history.php', 'ti ti-history'],
            'addressplan' => [__('Plan d\'adressage', 'configurationglpiauto'), 'addressplan.php', 'ti ti-network'],
            'import'      => [__('Importer des tickets', 'configurationglpiauto'), 'jiraimport.php', 'ti ti-file-import'],
        ];
    }

    /**
     * Vrai si la personne connectée peut ouvrir la page de l'onglet.
     */
    public static function canOpen(string $key): bool
    {
        $read = (bool) Session::haveRight(Config::$rightname, READ);
        $update = (bool) Session::haveRight(Config::$rightname, UPDATE);

        return match ($key) {
            'wizard', 'history' => $read,
            'profiles'          => (bool) Session::haveRight(ConfigurationProfile::$rightname, READ),
            'audit'             => $read && (bool) Session::haveRight('config', READ),
            'addressplan'       => $update && AddressPlanImporter::canImport(),
            'import'            => $update && (bool) Session::haveRight(Ticket::$rightname, CREATE),
            default             => false,
        };
    }

    /**
     * Onglets visibles pour la personne connectée, celui de la page courante marqué actif.
     *
     * @return list<array{key: string, label: string, url: string, icon: string, active: bool}>
     */
    public static function visible(string $current): array
    {
        $visible = [];
        foreach (self::tabs() as $key => [$label, $page, $icon]) {
            if ($key !== $current && !self::canOpen($key)) {
                continue;
            }
            $visible[] = [
                'key'    => $key,
                'label'  => $label,
                'url'    => FrontUrl::get($page),
                'icon'   => $icon,
                'active' => $key === $current,
            ];
        }

        return $visible;
    }

    /**
     * Affiche la barre, juste après Html::header() de la page.
     *
     * @param string $current clé de l'onglet de la page (voir tabs())
     * @param string|null $importPage page d'import affichée (jiraimport.php ou csvimport.php), pour
     *                                le choix entre les deux imports sous l'onglet « import »
     */
    public static function display(string $current, ?string $importPage = null): void
    {
        TemplateRenderer::getInstance()->display('@configurationglpiauto/_usage_tabs.html.twig', [
            'usage_tabs'  => self::visible($current),
            'import_page' => $current === 'import' ? $importPage : null,
            'jira_url'    => FrontUrl::get('jiraimport.php'),
            'csv_url'     => FrontUrl::get('csvimport.php'),
        ]);
    }
}
