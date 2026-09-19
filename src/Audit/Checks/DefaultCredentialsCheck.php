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

namespace GlpiPlugin\Configurationglpiauto\Audit\Checks;

use GlpiPlugin\Configurationglpiauto\Audit\AuditCheckInterface;
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;

/**
 * Les 4 comptes de démonstration fournis par toute installation GLPI standard
 * (`glpi`/`glpi`, `tech`/`tech`, `normal`/`normal`, `post-only`/`postonly`) sont des identifiants
 * publiquement documentés — un compte actif qui les utilise encore est une vraie faille (ISO 27001
 * A.9, comptes par défaut), pas une question de choix d'organisation. `password_verify()` compare
 * de façon fiable un mot de passe en clair à un hash quel que soit son sel, donc cette vérification
 * ne lit ni ne stocke jamais de mot de passe réel d'un utilisateur : elle ne fait que confirmer si
 * le hash stocké correspond encore exactement à l'un de ces 4 mots de passe publiquement connus.
 *
 * Jamais corrigée automatiquement : deviner ou forcer un nouveau mot de passe à la place d'un
 * humain serait pire que le constat lui-même (perte d'accès), et désactiver le compte pourrait
 * casser un usage de démonstration délibéré — contrairement à `NotificationsDisabledCheck`, il
 * n'existe ici aucune correction non ambiguë possible.
 */
final class DefaultCredentialsCheck implements AuditCheckInterface
{
    /**
     * @var array<string, string> Nom de compte => mot de passe d'usine associé.
     */
    private const DEFAULT_CREDENTIALS = [
        'glpi'      => 'glpi',
        'tech'      => 'tech',
        'normal'    => 'normal',
        'post-only' => 'postonly',
    ];

    public function getKey(): string
    {
        return 'default_credentials_still_active';
    }

    public function getDomain(): string
    {
        return __('Comptes par défaut', 'configurationglpiauto');
    }

    public function analyze(): array
    {
        global $DB;

        $names = [];

        foreach (
            $DB->request([
                'SELECT' => ['name', 'password'],
                'FROM'   => 'glpi_users',
                'WHERE'  => ['name' => array_keys(self::DEFAULT_CREDENTIALS), 'is_active' => 1],
            ]) as $row
        ) {
            $defaultPassword = self::DEFAULT_CREDENTIALS[$row['name']];

            if (password_verify($defaultPassword, (string) $row['password'])) {
                $names[] = $row['name'];
            }
        }

        if ($names === []) {
            return [];
        }

        return [new AuditFinding(
            checkKey: $this->getKey(),
            domain: $this->getDomain(),
            severity: AuditFinding::SEVERITY_CRITICAL,
            title: sprintf(
                _n(
                    '%d compte par défaut utilise encore son mot de passe d\'usine',
                    '%d comptes par défaut utilisent encore leur mot de passe d\'usine',
                    count($names),
                    'configurationglpiauto'
                ),
                count($names)
            ),
            description: implode(', ', $names),
            recommendation: __(
                'Changez immédiatement le mot de passe de ces comptes, ou désactivez-les s\'ils ne sont pas utilisés : ce sont des identifiants publiquement connus.',
                'configurationglpiauto'
            ),
            fixable: false
        )];
    }

    public function canFix(): bool
    {
        return false;
    }

    public function fix(): void
    {
        throw new \LogicException(self::class . ' cannot be auto-fixed.');
    }
}
