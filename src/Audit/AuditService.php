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

use GlpiPlugin\Configurationglpiauto\Audit\Checks\DefaultAdminEmailCheck;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\DefaultCredentialsCheck;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\EntitiesWithoutAddressCheck;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\NoActiveCalendarCheck;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\NoSlaConfiguredCheck;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\NoStatesConfiguredCheck;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\NoTicketCategoriesCheck;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\NotificationsDisabledCheck;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\WeakPasswordPolicyCheck;
use LogicException;

/**
 * Registre + orchestrateur des vérifications d'audit (issue #112, "Mode Audit avancé") —
 * contrairement à `front/wizard.php` qui appelle chaque `*Builder` explicitement par son nom, ce
 * registre est générique : ajouter une future vérification ne demande qu'une nouvelle classe
 * implémentant `AuditCheckInterface` et une ligne dans `getRegisteredChecks()`, jamais de
 * changement à `front/audit.php`.
 */
final class AuditService
{
    /**
     * @return list<class-string<AuditCheckInterface>>
     */
    public static function getRegisteredChecks(): array
    {
        return [
            NotificationsDisabledCheck::class,
            EntitiesWithoutAddressCheck::class,
            DefaultAdminEmailCheck::class,
            NoStatesConfiguredCheck::class,
            NoActiveCalendarCheck::class,
            NoSlaConfiguredCheck::class,
            NoTicketCategoriesCheck::class,
            DefaultCredentialsCheck::class,
            WeakPasswordPolicyCheck::class,
        ];
    }

    /**
     * @return list<AuditFinding>
     */
    public static function runAll(): array
    {
        $findings = [];

        foreach (self::getRegisteredChecks() as $checkClass) {
            $check = new $checkClass();
            foreach ($check->analyze() as $finding) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * @throws LogicException Si `$checkKey` est inconnue ou que la vérification correspondante ne
     *         propose pas de correction (`canFix()` faux) — jamais une correction silencieusement
     *         ignorée, l'appelant doit savoir que rien ne s'est passé.
     */
    public static function fix(string $checkKey): void
    {
        foreach (self::getRegisteredChecks() as $checkClass) {
            $check = new $checkClass();
            if ($check->getKey() !== $checkKey) {
                continue;
            }

            if (!$check->canFix()) {
                throw new LogicException("Audit check '{$checkKey}' cannot be auto-fixed.");
            }

            $check->fix();

            return;
        }

        throw new LogicException("Unknown audit check key '{$checkKey}'.");
    }
}
