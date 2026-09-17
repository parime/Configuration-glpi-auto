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

namespace GlpiPlugin\Configurationglpiauto\Audit;

/**
 * One constat produit par une vérification d'audit (issue #112, "Mode Audit avancé") — objet
 * valeur immuable, jamais construit à partir d'une supposition : chaque champ reflète un état
 * réellement observé en base (voir les classes de `Audit\Checks\`), jamais une valeur devinée à la
 * place de l'administrateur.
 */
final class AuditFinding
{
    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    public function __construct(
        public readonly string $checkKey,
        public readonly string $domain,
        public readonly string $severity,
        public readonly string $title,
        public readonly string $description,
        public readonly string $recommendation,
        public readonly bool $fixable = false
    ) {
    }
}
