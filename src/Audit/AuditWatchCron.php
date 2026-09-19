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

use CronTask;
use GlpiPlugin\Configurationglpiauto\Config;

/**
 * Issue #131 ("Analyse continue") : ré-exécute le registre d'audit existant (`AuditService`,
 * issue #112/#125) à intervalle régulier via une tâche planifiée GLPI native (`CronTask`) — la
 * première que ce plugin enregistre lui-même (`GeneralSettingsBuilder` n'active jusqu'ici que des
 * CronTasks *existants* de GLPI cœur, jamais les siens).
 *
 * Volontairement sans nouvelle infrastructure de notification (`NotificationTarget`/
 * `NotificationTemplate` propres à ce plugin) : cette tâche ne fait qu'accumuler un compteur de
 * *nouveaux* constats critiques dans `Config::audit_watch_state`, affiché en bannière par
 * `front/audit.php` à la prochaine visite d'un administrateur — jamais un envoi automatique, jamais
 * une correction, seulement un signal que l'audit existant détaillera de toute façon sur place.
 */
final class AuditWatchCron
{
    /**
     * @param string $name Nom de la tâche (toujours "auditwatch" ici, seule tâche enregistrée par
     *        ce plugin) — paramètre imposé par le contrat `CronTask::cronInfo()`.
     */
    public static function cronInfo($name): array
    {
        return [
            'description' => __(
                'Ré-exécute l\'audit de configuration et détecte les nouveaux constats critiques',
                'configurationglpiauto'
            ),
        ];
    }

    /**
     * Nom de méthode imposé par `CronTask::launch()` : `{$itemtype}::cron{$name}($task)`, `$name`
     * étant celui passé à `CronTask::register()` à l'installation ("auditwatch").
     *
     * @return int 0 si aucun nouveau constat critique, 1 sinon — même convention que
     *         `CartridgeItem::cronCartridge()` (0 rien à signaler, 1 action effectuée).
     */
    public static function cronAuditwatch(CronTask $task): int
    {
        $criticalKeys = self::currentCriticalKeys();

        $config = Config::getConfig();
        $state = json_decode((string) ($config->fields['audit_watch_state'] ?? ''), true);
        $state = is_array($state) ? $state : [];

        $previousKeys = is_array($state['last_critical_keys'] ?? null) ? $state['last_critical_keys'] : [];
        $newKeys = array_values(array_diff($criticalKeys, $previousKeys));

        $config->update([
            'id' => $config->getID(),
            'audit_watch_state' => json_encode([
                'last_critical_keys' => $criticalKeys,
                'unseen_new_critical_count' => (int) ($state['unseen_new_critical_count'] ?? 0) + count($newKeys),
            ]),
        ]);

        $task->addVolume(count($newKeys));

        return $newKeys === [] ? 0 : 1;
    }

    /**
     * @return list<string>
     */
    private static function currentCriticalKeys(): array
    {
        $keys = [];

        foreach (AuditService::runAll() as $finding) {
            if ($finding->severity === AuditFinding::SEVERITY_CRITICAL) {
                $keys[] = $finding->checkKey;
            }
        }

        return array_values(array_unique($keys));
    }
}
