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
use NotificationEvent;

/**
 * Issue #285 : rapport d'audit périodique par e-mail. Contrairement à AuditWatchCron (issue #131),
 * qui ne fait qu'allumer une bannière dans front/audit.php, cette tâche envoie une vraie
 * notification GLPI (événement `audit_report` sur la ligne de configuration du plugin, voir
 * NotificationTargetConfig) : modèle, destinataires et mode d'envoi restent modifiables par
 * l'administrateur dans Configuration > Notifications, comme toute notification native.
 *
 * Fréquence = celle de la tâche planifiée GLPI `auditreport` (hebdomadaire par défaut, désactivée
 * tant que l'administrateur ne l'active pas depuis front/audit.php ou Configuration > Actions
 * automatiques). L'état du dernier rapport (statut de chaque contrôle, date) est conservé dans
 * Config::audit_report_state pour calculer l'évolution et l'option « seulement si changement ou
 * anomalie ».
 */
final class AuditReportCron
{
    public const TASK_NAME = 'auditreport';
    public const EVENT     = 'audit_report';

    /**
     * @param string $name nom de la tâche, imposé par le contrat CronTask::cronInfo()
     * @return array{description: string}
     */
    public static function cronInfo($name): array
    {
        return [
            'description' => __('Envoie par e-mail le rapport périodique de l\'audit de configuration', 'configurationglpiauto'),
        ];
    }

    /**
     * Nom imposé par CronTask::launch() : `cron{$name}`.
     */
    public static function cronAuditreport(CronTask $task): int
    {
        $sent = self::run(false);
        $task->addVolume($sent ? 1 : 0);

        return $sent ? 1 : 0;
    }

    /**
     * @param bool $force true pour « Envoyer maintenant » depuis front/audit.php : ignore l'option
     *                    « seulement si changement ou anomalie ».
     * @return bool true si la notification a été émise
     */
    public static function run(bool $force): bool
    {
        $config = Config::getConfig();
        $checkKeys = array_map(
            static fn (string $checkClass): string => (new $checkClass())->getKey(),
            AuditService::getRegisteredChecks()
        );
        $report = AuditReport::build($checkKeys, AuditService::runAll());

        $state = json_decode((string) ($config->fields['audit_report_state'] ?? ''), true);
        $previousStatuses = is_array($state) && is_array($state['statuses'] ?? null) ? $state['statuses'] : null;

        if (!$force && !AuditReport::shouldSend(!empty($config->fields['audit_report_only_on_change']), $report, $previousStatuses)) {
            return false;
        }

        $sent = NotificationEvent::raiseEvent(self::EVENT, $config, [
            'entities_id'   => 0,
            'check_keys'    => $checkKeys,
            'evolution'     => AuditReport::evolution($previousStatuses, $report['statuses']),
            'previous_date' => is_array($state) ? ($state['date'] ?? null) : null,
        ]);

        if ($sent) {
            $config->update([
                'id'                 => $config->getID(),
                'audit_report_state' => json_encode(['statuses' => $report['statuses'], 'date' => date('Y-m-d H:i:s')]),
            ]);
        }

        return (bool) $sent;
    }
}
