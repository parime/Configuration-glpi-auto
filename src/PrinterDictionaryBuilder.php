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

use RuleDictionnaryPrinter;

/**
 * Issue #315 : dictionnaire des imprimantes — ignorer à l'import les imprimantes virtuelles.
 *
 * Chaque poste remonte ses imprimantes locales (`Glpi\Inventory\Asset\Printer`) : à côté des vraies,
 * « Microsoft Print to PDF », « Microsoft XPS Document Writer », « OneNote », « Fax », les
 * imprimantes PDF et celles que le Bureau à distance redirige depuis le poste du client. Sans
 * règle, elles deviennent autant de fausses imprimantes dans le parc. L'action GLPI « Ne pas
 * importer » (`_ignore_import`) les écarte à l'inventaire suivant ; rien n'est supprimé de ce qui
 * existe déjà.
 *
 * Expressions ancrées au début (le nom d'une vraie imprimante ne commence jamais par « Microsoft
 * Print to PDF »), sauf pour la redirection du Bureau à distance, signalée en fin de nom.
 */
class PrinterDictionaryBuilder
{
    /**
     * @var array<string, string> libellé de la règle => expression
     */
    private const IGNORED = [
        'Microsoft Print to PDF' => '/^microsoft print to pdf\b/',
        'Microsoft XPS Document Writer' => '/^microsoft xps document writer\b/',
        'OneNote' => '/^(send to )?onenote\b|^envoyer (à|vers) onenote\b/',
        'Fax' => '/^(fax|microsoft fax)\b/',
        'Imprimantes PDF' => '/^(adobe pdf|pdfcreator|pdf24|cutepdf writer|foxit pdf (reader )?printer|nitro pdf creator|bullzip pdf printer|pdf-xchange)\b/',
        'Outils de capture et d\'accès distant' => '/^(snagit|anydesk printer|teamviewer printer|webex document loader)\b/',
        'Imprimantes redirigées (Bureau à distance)' => '/\((redirection|redirig(?:e|é|É)e?|redirected) ?\d*\)$/',
    ];

    /**
     * @return int Number of dictionary rules created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['virtual_printers_ignored'])) {
            return 0;
        }

        foreach (self::IGNORED as $label => $regex) {
            DictionaryRules::ensure(
                RuleDictionnaryPrinter::class,
                sprintf('Imprimante — ne pas importer « %s »', $label),
                '1',
                [],
                $regex,
                'assign',
                '_ignore_import'
            );
        }

        return count(self::IGNORED);
    }

    /**
     * @return list<string>
     */
    public static function getPreview(): array
    {
        return array_keys(self::IGNORED);
    }
}
