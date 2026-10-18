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

use Rule;
use RuleAction;
use RuleCollection;
use RuleSoftwareCategory;
use RuleSoftwareCategoryCollection;
use SoftwareCategory;

/**
 * Issue #304 : catégories des logiciels courants.
 *
 * Deux mécanismes GLPI, pour deux moments différents :
 * - à l'inventaire, la catégorie vient du dictionnaire des logiciels (`Glpi\Inventory\Asset\
 *   Software` lit son action « Catégorie ») : chaque règle de SoftwareDictionaryBuilder reçoit
 *   donc la catégorie de son logiciel ;
 * - pour un logiciel créé à la main, et pour classer ceux déjà en base (rejeu natif
 *   `glpi:rules:process_software_category_rules`, ou le contrôle d'audit
 *   UncategorizedSoftwareCheck), GLPI applique les règles « Catégories de logiciels »
 *   (RuleSoftwareCategory) : une règle par catégorie, avec les mêmes expressions.
 *
 * Une catégorie choisie à la main n'est jamais remplacée : la règle ne joue qu'à la création, et
 * le contrôle d'audit ne classe que les logiciels sans catégorie.
 */
class SoftwareCategoryBuilder
{
    /** Début du nom des règles « Catégories de logiciels » créées par l'assistant. */
    public const RULE_PREFIX = 'Catégorie de logiciel « ';

    /** @var array<string, string> clé => nom de la catégorie GLPI */
    public const CATEGORIES = [
        'browser' => 'Navigateurs',
        'office' => 'Bureautique',
        'communication' => 'Communication et messagerie',
        'storage' => 'Stockage et synchronisation',
        'pdf' => 'PDF',
        'utility' => 'Utilitaires',
        'media' => 'Multimédia et graphisme',
        'remote' => 'Accès distant et VPN',
        'admin' => 'Outils d\'administration',
        'dev' => 'Développement',
        'runtime' => 'Environnements d\'exécution',
        'security' => 'Sécurité',
        'vendor' => 'Outils constructeur',
    ];

    /** @var array<string, string> libellé de la règle du dictionnaire (SoftwareDictionaryBuilder) => clé de catégorie */
    public const SOFTWARE = [
        'Mozilla Firefox ESR' => 'browser', 'Mozilla Firefox' => 'browser', 'Google Chrome' => 'browser',
        'Microsoft Edge WebView2 Runtime' => 'runtime', 'Microsoft Edge' => 'browser', 'Brave' => 'browser',
        'Opera' => 'browser', 'Vivaldi' => 'browser',
        'Microsoft 365 Apps' => 'office', 'LibreOffice' => 'office', 'Microsoft Power BI Desktop' => 'office',
        'Microsoft Office Professional Plus (année)' => 'office', 'Microsoft Office Standard (année)' => 'office',
        'Microsoft Office Home and Business (année)' => 'office', 'Microsoft Office Home and Student (année)' => 'office',
        'Microsoft Visio / Project Professional (année)' => 'office',
        'Microsoft Teams' => 'communication', 'Mozilla Thunderbird' => 'communication', 'Zoom Workplace' => 'communication',
        'Webex' => 'communication', 'Slack' => 'communication', 'Skype for Business' => 'communication',
        'Skype' => 'communication', 'WhatsApp' => 'communication', 'Discord' => 'communication',
        'Microsoft OneDrive' => 'storage', 'Google Drive' => 'storage', 'Dropbox' => 'storage',
        'Adobe Acrobat Reader' => 'pdf', 'Adobe Acrobat' => 'pdf', 'Foxit PDF Reader' => 'pdf',
        'PDF24 Creator' => 'pdf', 'PDFCreator' => 'pdf',
        '7-Zip' => 'utility', 'WinRAR' => 'utility', 'Notepad++' => 'utility', 'Greenshot' => 'utility',
        'PowerToys' => 'utility', 'KeePassXC' => 'utility', 'KeePass Password Safe' => 'utility',
        'VLC media player' => 'media', 'Audacity' => 'media', 'OBS Studio' => 'media', 'GIMP' => 'media',
        'Paint.NET' => 'media', 'Spotify' => 'media', 'iTunes' => 'media', 'Adobe Creative Cloud' => 'media',
        'Google Earth Pro' => 'media',
        'TeamViewer Host' => 'remote', 'TeamViewer' => 'remote', 'AnyDesk' => 'remote', 'Citrix Workspace' => 'remote',
        'FortiClient' => 'remote', 'Cisco Secure Client' => 'remote', 'GlobalProtect' => 'remote',
        'OpenVPN' => 'remote', 'WireGuard' => 'remote',
        'PuTTY' => 'admin', 'WinSCP' => 'admin', 'FileZilla Client' => 'admin', 'Wireshark' => 'admin',
        'SQL Server Management Studio' => 'admin', 'GLPI Agent' => 'admin', 'FusionInventory Agent' => 'admin',
        'Git' => 'dev', 'Microsoft Visual Studio Code' => 'dev', 'Node.js' => 'dev', 'Docker Desktop' => 'dev',
        'Postman' => 'dev', 'Python (version majeure.mineure)' => 'dev',
        'Java (version majeure)' => 'runtime', 'Microsoft Visual C++ Redistributable (année, architecture)' => 'runtime',
        'Microsoft .NET Runtime (version majeure, architecture)' => 'runtime',
        'Microsoft Windows Desktop Runtime (version majeure, architecture)' => 'runtime',
        'Microsoft ASP.NET Core (version majeure, architecture)' => 'runtime',
        'Kaspersky Endpoint Security' => 'security', 'ESET Endpoint Security' => 'security',
        'ESET Endpoint Antivirus' => 'security', 'ESET Management Agent' => 'security',
        'Sophos Endpoint Agent' => 'security', 'Bitdefender Endpoint Security Tools' => 'security',
        'Trend Micro Apex One' => 'security', 'SentinelOne Agent' => 'security', 'CrowdStrike Falcon Sensor' => 'security',
        'Dell Command | Update' => 'vendor', 'Dell SupportAssist' => 'vendor', 'HP Support Assistant' => 'vendor',
        'Lenovo Vantage' => 'vendor', 'Lenovo System Update' => 'vendor',
    ];

    /**
     * @param array<string, string> $regexByLabel libellé de règle => expression (SoftwareDictionaryBuilder::RULES)
     * @param array<string, int> $dictionaryRuleIds libellé de règle => id de la règle du dictionnaire
     * @return int Number of category rules created/reused.
     */
    public function build(Config $config, array $regexByLabel, array $dictionaryRuleIds): int
    {
        if (empty($config->fields['software_categories_enabled'])) {
            return 0;
        }

        $categoryIds = [];
        foreach (self::CATEGORIES as $key => $name) {
            $categoryIds[$key] = self::getOrCreateCategory($name);
        }

        // Inventaire : action « Catégorie » sur chaque règle du dictionnaire.
        foreach ($dictionaryRuleIds as $label => $rulesId) {
            $key = self::SOFTWARE[$label] ?? null;
            if ($key !== null && $categoryIds[$key] > 0) {
                self::alignCategoryAction($rulesId, $categoryIds[$key]);
            }
        }

        // Création manuelle et rejeu : une règle « Catégories de logiciels » par catégorie.
        $count = 0;
        foreach (self::CATEGORIES as $key => $name) {
            $patterns = [];
            foreach (self::SOFTWARE as $label => $softwareKey) {
                if ($softwareKey === $key && isset($regexByLabel[$label])) {
                    $patterns[] = $regexByLabel[$label];
                }
            }
            if ($patterns === [] || $categoryIds[$key] <= 0) {
                continue;
            }
            $rulesId = DictionaryRules::ensure(
                RuleSoftwareCategory::class,
                sprintf(self::RULE_PREFIX . '%s »', $name),
                (string) $categoryIds[$key],
                [],
                array_shift($patterns),
                'assign',
                'softwarecategories_id'
            );
            foreach ($patterns as $pattern) {
                DictionaryRules::ensure(RuleSoftwareCategory::class, sprintf(self::RULE_PREFIX . '%s »', $name), (string) $categoryIds[$key], [], $pattern, 'assign', 'softwarecategories_id');
            }
            if ($rulesId > 0) {
                self::alignCategoryAction($rulesId, $categoryIds[$key]);
                $count++;
            }
        }
        self::moveBeforeOtherRules();

        return $count;
    }

    /**
     * Les règles « Catégories de logiciels » s'arrêtent à la première qui correspond. GLPI fournit
     * « Import category from inventory tool », qui correspond à tout logiciel : placée avant, elle
     * empêchait nos règles de classer un logiciel créé à la main. Nos règles passent donc avant
     * toute autre (GLPI ou administrateur), dans leur ordre actuel ; les autres restent après.
     */
    private static function moveBeforeOtherRules(): void
    {
        global $DB;

        $mine = [];
        $firstOther = null;
        $rows = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => Rule::getTable(),
            'WHERE'  => ['sub_type' => RuleSoftwareCategory::class],
            'ORDER'  => 'ranking ASC',
        ]);
        foreach ($rows as $row) {
            if (str_starts_with((string) $row['name'], self::RULE_PREFIX)) {
                if ($firstOther !== null) {
                    $mine[] = (int) $row['id'];
                }
            } elseif ($firstOther === null) {
                $firstOther = (int) $row['id'];
            }
        }
        $collection = new RuleSoftwareCategoryCollection();
        foreach ($mine as $rulesId) {
            $collection->moveRule($rulesId, (int) $firstOther, RuleCollection::MOVE_BEFORE);
        }
    }

    /**
     * Aligne l'action « Catégorie » d'une règle sur la catégorie actuelle. Quand une catégorie est
     * supprimée, GLPI désactive les règles qui la visent : si la catégorie référencée n'existe plus
     * (elle vient d'être recréée), la règle est réactivée. Une règle désactivée volontairement,
     * dont la catégorie existe toujours, reste désactivée.
     */
    private static function alignCategoryAction(int $rulesId, int $categoryId): void
    {
        $action = new RuleAction();
        if (Lookup::loadFirst($action, ['rules_id' => $rulesId, 'field' => 'softwarecategories_id'])) {
            $previous = (int) $action->fields['value'];
            if ($previous !== $categoryId && !(new SoftwareCategory())->getFromDB($previous)) {
                (new Rule())->update(['id' => $rulesId, 'is_active' => 1]);
            }
        }
        DictionaryRules::ensureAction($rulesId, 'softwarecategories_id', (string) $categoryId);
    }

    private static function getOrCreateCategory(string $name): int
    {
        $category = new SoftwareCategory();
        if (Lookup::loadFirst($category, ['name' => $name, 'softwarecategories_id' => 0])) {
            return (int) $category->getID();
        }

        return (int) $category->add(['name' => $name, 'softwarecategories_id' => 0]);
    }

    /**
     * Règle du dictionnaire et règles de catégorie doivent rester alignées : utile aux tests.
     *
     * @param list<string> $labels
     * @return list<string> libellés du dictionnaire sans catégorie
     */
    public static function unmappedLabels(array $labels): array
    {
        return array_values(array_diff($labels, array_keys(self::SOFTWARE)));
    }
}
