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
 * Contrat commun à chaque vérification d'audit (issue #112, "Mode Audit avancé") — volontairement
 * introduit ici plutôt que calqué sur la convention des `*Builder` (qui n'en ont pas), car cette
 * fois un registre générique (`AuditService`) doit pouvoir parcourir toutes les vérifications sans
 * connaître leur nom à l'avance, contrairement à `front/wizard.php` qui appelle chaque Builder
 * explicitement par son nom.
 *
 * Chaque vérification lit l'état réel de GLPI (pas seulement la table `Config` de ce plugin, qui
 * peut ne jamais avoir été utilisée) — l'audit doit rester valable sur n'importe quelle instance,
 * pas seulement celles configurées via l'assistant de ce plugin.
 */
interface AuditCheckInterface
{
    /**
     * Identifiant stable, utilisé pour retrouver la vérification lors d'une correction (voir
     * `AuditService::fix()`) — jamais affiché tel quel à l'utilisateur.
     */
    public function getKey(): string;

    /**
     * Libellé de catégorie affiché à l'utilisateur (ex. "Notifications", "Entités").
     */
    public function getDomain(): string;

    /**
     * @return list<AuditFinding> Liste vide si aucun problème détecté — jamais un constat
     *         "tout va bien" explicite, l'absence de constat est le signal.
     */
    public function analyze(): array;

    /**
     * Faux pour la plupart des vérifications : seul un état "correct" non ambigu (ex. activer les
     * notifications) peut être corrigé d'un clic sans deviner une donnée propre à l'organisation
     * (une adresse, un SLA...) à la place de l'administrateur.
     */
    public function canFix(): bool;

    /**
     * Applique réellement la correction. Ne doit jamais être appelée si `canFix()` est faux —
     * l'appelant (`AuditService::fix()`) est responsable de cette vérification.
     */
    public function fix(): void;
}
