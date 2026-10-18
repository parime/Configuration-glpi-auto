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

use RuleDictionnarySoftware;

/**
 * Issue #303 : dictionnaire des logiciels (Administration > Dictionnaires > Logiciels).
 *
 * L'agent remonte « Mozilla Firefox (x64 fr) », « Mozilla Firefox 131.0 (x64 en-US) »… : sans
 * dictionnaire, GLPI crée un logiciel par variante, ce qui fausse le nombre d'installations et le
 * suivi des licences. L'inventaire passe chaque nom par ce dictionnaire avant de chercher le
 * logiciel (`Glpi\Inventory\Asset\Software`) ; la version reste dans la version du logiciel.
 *
 * Une règle par logiciel, une expression « commence par » (ancrée, fermée par `\b`) :
 * - action `assign` : nom fixe (« Mozilla Firefox ») ;
 * - action `regex_result` (« #0 », premier groupe capturé) quand une partie du nom distingue
 *   réellement deux logiciels : la version majeure de Java ou de Python, l'année et
 *   l'architecture d'un Visual C++ Redistributable (x86 et x64 s'installent côte à côte).
 *
 * Les éditions différentes restent distinctes (Firefox / Firefox ESR, Edge / Edge WebView2,
 * Acrobat / Acrobat Reader, TeamViewer / TeamViewer Host) ; les modules d'un logiciel (packs de
 * langue LibreOffice, extension Outlook de Zoom) ne sont pas fusionnés avec lui. GLPI s'arrête à
 * la première règle qui correspond.
 */
class SoftwareDictionaryBuilder
{
    /**
     * Nom attribué (ou « #0 ») => expression régulière.
     *
     * @var array<string, array{0: string, 1: string}> nom de la règle => [expression, nom attribué]
     */
    private const RULES = [
        // Navigateurs
        'Mozilla Firefox ESR' => ['/^mozilla firefox\b.*\besr\b/', 'Mozilla Firefox ESR'],
        'Mozilla Firefox' => ['/^mozilla firefox\b/', 'Mozilla Firefox'],
        'Google Chrome' => ['/^google chrome\b(?! remote desktop)/', 'Google Chrome'],
        'Microsoft Edge WebView2 Runtime' => ['/^microsoft edge webview2\b/', 'Microsoft Edge WebView2 Runtime'],
        'Microsoft Edge' => ['/^microsoft edge\b(?! update)/', 'Microsoft Edge'],
        'Brave' => ['/^brave\b/', 'Brave'],
        'Opera' => ['/^opera( stable)?\b(?! gx)/', 'Opera'],
        'Vivaldi' => ['/^vivaldi\b/', 'Vivaldi'],
        // Bureautique, messagerie, collaboration
        'Microsoft 365 Apps' => ['/^microsoft (365|office 365)\b/', 'Microsoft 365 Apps'],
        'Microsoft Teams' => ['/^microsoft teams\b/', 'Microsoft Teams'],
        'Microsoft OneDrive' => ['/^microsoft onedrive\b/', 'Microsoft OneDrive'],
        'Mozilla Thunderbird' => ['/^mozilla thunderbird\b/', 'Mozilla Thunderbird'],
        'LibreOffice' => ['/^libreoffice\b(?!.*\b(help pack|language pack)\b)/', 'LibreOffice'],
        'Microsoft Power BI Desktop' => ['/^microsoft power bi desktop\b/', 'Microsoft Power BI Desktop'],
        'Zoom Workplace' => ['/^zoom( workplace)?\b(?!.*\b(plugin|add-in|outlook)\b)/', 'Zoom Workplace'],
        'Webex' => ['/^(cisco )?webex\b/', 'Webex'],
        'Slack' => ['/^slack\b/', 'Slack'],
        'Google Drive' => ['/^google drive\b/', 'Google Drive'],
        'Dropbox' => ['/^dropbox\b/', 'Dropbox'],
        // PDF
        'Adobe Acrobat Reader' => ['/^adobe (acrobat )?reader\b/', 'Adobe Acrobat Reader'],
        'Adobe Acrobat' => ['/^adobe acrobat\b/', 'Adobe Acrobat'],
        'Foxit PDF Reader' => ['/^foxit (pdf )?reader\b/', 'Foxit PDF Reader'],
        'PDF24 Creator' => ['/^pdf24\b/', 'PDF24 Creator'],
        'PDFCreator' => ['/^pdfcreator\b/', 'PDFCreator'],
        // Compression, utilitaires
        '7-Zip' => ['/^7-zip\b/', '7-Zip'],
        'WinRAR' => ['/^winrar\b/', 'WinRAR'],
        'Notepad++' => ['/^notepad\+\+/', 'Notepad++'],
        'Greenshot' => ['/^greenshot\b/', 'Greenshot'],
        'PowerToys' => ['/^(microsoft )?powertoys\b/', 'PowerToys'],
        'KeePassXC' => ['/^keepassxc\b/', 'KeePassXC'],
        'KeePass Password Safe' => ['/^keepass\b/', 'KeePass Password Safe'],
        // Multimédia, graphisme
        'VLC media player' => ['/^vlc media player\b/', 'VLC media player'],
        'Audacity' => ['/^audacity\b/', 'Audacity'],
        'OBS Studio' => ['/^obs studio\b/', 'OBS Studio'],
        'GIMP' => ['/^gimp\b/', 'GIMP'],
        'Paint.NET' => ['/^paint\.net\b/', 'Paint.NET'],
        // Accès distant, VPN
        'TeamViewer Host' => ['/^teamviewer host\b/', 'TeamViewer Host'],
        'TeamViewer' => ['/^teamviewer\b/', 'TeamViewer'],
        'AnyDesk' => ['/^anydesk\b/', 'AnyDesk'],
        'Citrix Workspace' => ['/^citrix workspace\b/', 'Citrix Workspace'],
        'FortiClient' => ['/^forticlient\b/', 'FortiClient'],
        'Cisco Secure Client' => ['/^cisco (anyconnect|secure client)\b/', 'Cisco Secure Client'],
        'GlobalProtect' => ['/^globalprotect\b/', 'GlobalProtect'],
        'OpenVPN' => ['/^openvpn\b/', 'OpenVPN'],
        'WireGuard' => ['/^wireguard\b/', 'WireGuard'],
        // Administration, développement
        'PuTTY' => ['/^putty\b/', 'PuTTY'],
        'WinSCP' => ['/^winscp\b/', 'WinSCP'],
        'FileZilla Client' => ['/^filezilla\b(?!.*\bserver\b)/', 'FileZilla Client'],
        'Wireshark' => ['/^wireshark\b/', 'Wireshark'],
        'Git' => ['/^git( version [\d.]+)?$/', 'Git'],
        'Microsoft Visual Studio Code' => ['/^microsoft visual studio code\b/', 'Microsoft Visual Studio Code'],
        'SQL Server Management Studio' => ['/^(microsoft )?sql server management studio\b/', 'SQL Server Management Studio'],
        'Node.js' => ['/^node\.js\b/', 'Node.js'],
        'Docker Desktop' => ['/^docker desktop\b/', 'Docker Desktop'],
        'Postman' => ['/^postman\b/', 'Postman'],
        // Agents d'inventaire
        'GLPI Agent' => ['/^glpi[ -]agent\b/', 'GLPI Agent'],
        'FusionInventory Agent' => ['/^fusioninventory[ -]agent\b/', 'FusionInventory Agent'],
        // Issue #315 : Office et outils Microsoft en licence perpétuelle — éditions françaises
        // ramenées au nom anglais des contrats, année conservée (#0).
        'Microsoft Office Professional Plus (année)' => ['/^microsoft office (?:professionnel|professional) plus (\d{4})\b/', 'Microsoft Office Professional Plus #0'],
        'Microsoft Office Standard (année)' => ['/^microsoft office standard (\d{4})\b/', 'Microsoft Office Standard #0'],
        'Microsoft Office Home and Business (année)' => ['/^microsoft office (?:famille et petite entreprise|home and business) (\d{4})\b/', 'Microsoft Office Home and Business #0'],
        'Microsoft Office Home and Student (année)' => ['/^microsoft office (?:famille et (?:e|é|É)tudiant|home and student) (\d{4})\b/', 'Microsoft Office Home and Student #0'],
        'Microsoft Visio / Project Professional (année)' => ['/^microsoft (visio|project) (?:professionnel|professional) (\d{4})\b/', 'Microsoft #0 Professional #1'],
        // Runtimes .NET : version majeure et architecture conservées, pas une fiche par correctif.
        'Microsoft .NET Runtime (version majeure, architecture)' => ['/^(microsoft \.net runtime) - (\d+)\.\d+\.\d+ \((x64|x86|arm64)\)/', '#0 #1 (#2)'],
        'Microsoft Windows Desktop Runtime (version majeure, architecture)' => ['/^(microsoft windows desktop runtime) - (\d+)\.\d+\.\d+ \((x64|x86|arm64)\)/', '#0 #1 (#2)'],
        'Microsoft ASP.NET Core (version majeure, architecture)' => ['/^(microsoft asp\.net core) (\d+)\.\d+\.\d+ - shared framework \((x64|x86|arm64)\)/', '#0 #1 Shared Framework (#2)'],
        // Sécurité des postes
        'Kaspersky Endpoint Security' => ['/^kaspersky endpoint security\b/', 'Kaspersky Endpoint Security'],
        'ESET Endpoint Security' => ['/^eset endpoint security\b/', 'ESET Endpoint Security'],
        'ESET Endpoint Antivirus' => ['/^eset endpoint antivirus\b/', 'ESET Endpoint Antivirus'],
        'ESET Management Agent' => ['/^eset management agent\b/', 'ESET Management Agent'],
        'Sophos Endpoint Agent' => ['/^sophos endpoint agent\b/', 'Sophos Endpoint Agent'],
        'Bitdefender Endpoint Security Tools' => ['/^bitdefender endpoint security tools\b/', 'Bitdefender Endpoint Security Tools'],
        'Trend Micro Apex One' => ['/^trend micro apex one\b/', 'Trend Micro Apex One'],
        'SentinelOne Agent' => ['/^sentinel ?(one )?agent\b/', 'SentinelOne Agent'],
        'CrowdStrike Falcon Sensor' => ['/^crowdstrike( windows)? (falcon )?sensor\b/', 'CrowdStrike Falcon Sensor'],
        // Outils des constructeurs
        'Dell Command | Update' => ['/^dell command \| update\b/', 'Dell Command | Update'],
        'Dell SupportAssist' => ['/^dell supportassist\b(?! os recovery)/', 'Dell SupportAssist'],
        'HP Support Assistant' => ['/^hp support assistant\b/', 'HP Support Assistant'],
        'Lenovo Vantage' => ['/^lenovo vantage\b/', 'Lenovo Vantage'],
        'Lenovo System Update' => ['/^lenovo system update\b/', 'Lenovo System Update'],
        // Messageries et divers
        'Skype for Business' => ['/^(skype for business|skype entreprise)\b/', 'Skype for Business'],
        'Skype' => ['/^skype\b/', 'Skype'],
        'WhatsApp' => ['/^whatsapp\b/', 'WhatsApp'],
        'Discord' => ['/^discord\b/', 'Discord'],
        'Spotify' => ['/^spotify\b/', 'Spotify'],
        'iTunes' => ['/^itunes\b/', 'iTunes'],
        'Adobe Creative Cloud' => ['/^adobe creative cloud\b/', 'Adobe Creative Cloud'],
        'Google Earth Pro' => ['/^google earth pro\b/', 'Google Earth Pro'],
        // Environnements d'exécution : la partie qui distingue deux logiciels est conservée (#0).
        'Java (version majeure)' => ['/^(java \d+)\b/', '#0'],
        'Python (version majeure.mineure)' => ['/^(python \d+\.\d+)\b/', '#0'],
        'Microsoft Visual C++ Redistributable (année, architecture)' => ['/^(microsoft visual c\+\+ \d{4}(?:-\d{4})? redistributable \(x(?:64|86)\))/', '#0'],
    ];

    /**
     * @return int Number of dictionary rules created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['software_dictionary_enabled'])) {
            return 0;
        }

        foreach (self::RULES as $label => [$regex, $name]) {
            DictionaryRules::ensure(
                RuleDictionnarySoftware::class,
                sprintf('Logiciel — normalisation « %s »', $label),
                $name,
                [],
                $regex,
                str_contains($name, '#') ? 'regex_result' : 'assign'
            );
        }

        return count(self::RULES);
    }

    /**
     * @return list<string> libellés des règles (aperçu de l'assistant)
     */
    public static function getPreview(): array
    {
        return array_keys(self::RULES);
    }
}
