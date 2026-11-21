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

use GlpiPlugin\Configurationglpiauto\Config;

/**
 * Issue #466 : poids, libellés et applicabilité des contrôles de l'audit, pour la note de
 * durcissement et la comparaison entre deux audits (voir HardeningScore).
 */
final class AuditControlCatalog
{
    public const WEIGHT_SECURITY   = 3;
    public const WEIGHT_OPERATIONS = 2;
    public const WEIGHT_DATA       = 1;

    /**
     * Poids des contrôles qui ne valent pas 1 (qualité des données).
     */
    private const WEIGHTS = [
        'default_credentials_still_active' => self::WEIGHT_SECURITY,
        'weak_password_policy'             => self::WEIGHT_SECURITY,
        'privileged_accounts_without_2fa'  => self::WEIGHT_SECURITY,
        'privileged_accounts_review'       => self::WEIGHT_SECURITY,
        'end_of_life'                      => self::WEIGHT_SECURITY,
        'notifications_disabled'           => self::WEIGHT_OPERATIONS,
        'default_admin_email'              => self::WEIGHT_OPERATIONS,
        'ghost_assets'                     => self::WEIGHT_OPERATIONS,
        'warranty_expiring'                => self::WEIGHT_OPERATIONS,
        'network_consistency'              => self::WEIGHT_OPERATIONS,
        'mail_collectors'                  => self::WEIGHT_OPERATIONS,
    ];

    public static function weight(string $key): int
    {
        return self::WEIGHTS[$key] ?? self::WEIGHT_DATA;
    }

    /**
     * @param list<string> $keys
     * @return array<string, int>
     */
    public static function weights(array $keys): array
    {
        $weights = [];
        foreach ($keys as $key) {
            $weights[$key] = self::weight($key);
        }

        return $weights;
    }

    public static function label(string $key): string
    {
        $labels = [
            'notifications_disabled'           => __('Notifications activées', 'configurationglpiauto'),
            'entities_without_address'         => __('Adresse des entités', 'configurationglpiauto'),
            'default_admin_email'              => __('Adresse e-mail de l\'administrateur', 'configurationglpiauto'),
            'no_states_configured'             => __('Statuts des éléments définis', 'configurationglpiauto'),
            'missing_recommended_states'       => __('Statuts recommandés présents', 'configurationglpiauto'),
            'duplicate_dropdowns'              => __('Intitulés en double', 'configurationglpiauto'),
            'duplicate_software'               => __('Logiciels en double', 'configurationglpiauto'),
            'ghost_assets'                     => __('Matériel qui ne remonte plus', 'configurationglpiauto'),
            'warranty_expiring'                => __('Garanties expirées ou proches', 'configurationglpiauto'),
            'end_of_life'                      => __('Fin de support des systèmes et du matériel', 'configurationglpiauto'),
            'incomplete_assets'                => __('Fiches de matériel incomplètes', 'configurationglpiauto'),
            'network_consistency'              => __('Cohérence du réseau', 'configurationglpiauto'),
            'uncategorized_software'           => __('Logiciels sans catégorie', 'configurationglpiauto'),
            'no_active_calendar'               => __('Calendrier actif', 'configurationglpiauto'),
            'no_sla_configured'                => __('SLA définis', 'configurationglpiauto'),
            'no_ticket_categories_configured'  => __('Catégories de tickets définies', 'configurationglpiauto'),
            'default_credentials_still_active' => __('Comptes par défaut neutralisés', 'configurationglpiauto'),
            'weak_password_policy'             => __('Politique de mots de passe', 'configurationglpiauto'),
            'privileged_accounts_without_2fa'  => __('Double authentification des comptes à privilèges', 'configurationglpiauto'),
            'privileged_accounts_review'       => __('Revue des comptes à privilèges', 'configurationglpiauto'),
            'dictionary_rules'                 => __('Dictionnaires d\'inventaire', 'configurationglpiauto'),
            'mail_collectors'                  => __('Collecteurs de messagerie', 'configurationglpiauto'),
        ];

        return $labels[$key] ?? $key;
    }

    /**
     * Contrôles désactivés dans les réglages du plugin (délai à 0, contrôle non activé) : ils sont
     * exclus de la note au lieu de la gonfler par une absence de constat.
     *
     * @param array<string, mixed> $configFields
     * @return list<string>
     */
    public static function notApplicable(array $configFields): array
    {
        $keys = [];
        if ((int) ($configFields['audit_ghost_days'] ?? 30) === 0) {
            $keys[] = 'ghost_assets';
        }
        if ((int) ($configFields['audit_warranty_days'] ?? 30) === 0) {
            $keys[] = 'warranty_expiring';
        }
        if (array_key_exists('audit_eol_enabled', $configFields) && empty($configFields['audit_eol_enabled'])) {
            $keys[] = 'end_of_life';
        }

        return $keys;
    }

    /**
     * Exécute l'audit (lecture seule) et calcule la note globale et par domaine.
     *
     * @return array{
     *     score: ?int,
     *     level: string,
     *     statuses: array<string, string>,
     *     domains: list<array{domain: string, score: int, controls: int, level: string}>,
     *     applicable: int,
     *     not_applicable: list<string>,
     *     findings: list<AuditFinding>
     * }
     */
    public static function evaluate(): array
    {
        $keys    = [];
        $domains = [];
        foreach (AuditService::getRegisteredChecks() as $checkClass) {
            $check = new $checkClass();
            $keys[] = $check->getKey();
            $domains[$check->getKey()] = $check->getDomain();
        }

        $findings      = AuditService::runAll();
        $notApplicable = self::notApplicable(Config::getConfig()->fields);
        $statuses      = HardeningScore::statuses($keys, $findings, $notApplicable);
        $weights       = self::weights($keys);
        $score         = HardeningScore::score($statuses, $weights);

        return [
            'score'          => $score,
            'level'          => HardeningScore::level($score),
            'statuses'       => $statuses,
            'domains'        => array_map(
                static fn (array $d): array => $d + ['level' => HardeningScore::level($d['score'])],
                HardeningScore::byDomain($statuses, $weights, $domains)
            ),
            'applicable'     => count($keys) - count(array_intersect($notApplicable, $keys)),
            'not_applicable' => array_values(array_map(self::label(...), array_intersect($notApplicable, $keys))),
            'findings'       => $findings,
        ];
    }
}
