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

use CronTask;
use GlpiPlugin\Configurationglpiauto\Audit\AuditReportCron;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\EndOfLifeCheck;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeCache;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeMapping;
use Notification;
use Session;

/**
 * Issue #495 : réglages du plugin, affichés par sa page de configuration (Configuration > Plugins >
 * Configuration GLPI Auto, front/config.php), en onglets. Auparavant sur la page d'audit, qui ne
 * garde que l'usage (constats, corrections, téléchargement des dates, envoi du rapport).
 *
 * Mêmes droits qu'avant le déplacement : lecture du droit du plugin et du droit natif 'config' pour
 * consulter, UPDATE des deux pour enregistrer (l'audit lit toute l'instance).
 */
final class ConfigSettings
{
    /** Clés des onglets de la page de configuration (paramètre « tab » de son adresse). */
    public const TAB_GENERAL = 'general';
    public const TAB_AUDIT = 'controls';
    public const TAB_EOL = 'eol';

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function tabs(): array
    {
        return [
            self::TAB_GENERAL => [__('Général', 'configurationglpiauto'), 'ti ti-settings'],
            self::TAB_AUDIT   => [__('Audit', 'configurationglpiauto'), 'ti ti-shield-check'],
            self::TAB_EOL     => [__('Fin de support', 'configurationglpiauto'), 'ti ti-calendar-off'],
        ];
    }

    public static function url(string $tab = self::TAB_GENERAL): string
    {
        return FrontUrl::get('config.php') . '?tab=' . rawurlencode(self::tab($tab));
    }

    /** Onglet demandé, ou l'onglet Général s'il n'existe pas. */
    public static function tab(string $tab): string
    {
        return array_key_exists($tab, self::tabs()) ? $tab : self::TAB_GENERAL;
    }

    public static function canView(): bool
    {
        return (bool) Session::haveRight(Config::$rightname, READ) && (bool) Session::haveRight('config', READ);
    }

    public static function canEdit(): bool
    {
        return (bool) Session::haveRight(Config::$rightname, UPDATE) && (bool) Session::haveRight('config', UPDATE);
    }

    /**
     * Vrai si le formulaire posté contient un réglage traité par handle().
     *
     * @param array<string, mixed> $post
     */
    public static function isSettingsPost(array $post): bool
    {
        return isset($post['save_park']) || isset($post['save_report'])
            || isset($post['save_eol']) || isset($post['suggest_eol']) || isset($post['import_eol'])
            || isset($post['save_drift']);
    }

    /**
     * Enregistre un bloc de réglages (droits vérifiés ici) et renvoie l'onglet où revenir.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    public static function handle(array $post, array $files): string
    {
        Session::checkRight(Config::$rightname, UPDATE);
        Session::checkRight('config', UPDATE);

        return self::save($post, $files);
    }

    /**
     * Enregistrement d'un bloc de réglages, droits déjà vérifiés par handle() (appelée directement
     * par les tests, sans session web).
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    public static function save(array $post, array $files): string
    {
        $config = Config::getConfig();

        // Issue #306 : délais des contrôles du parc (jours, 0 = contrôle désactivé).
        if (isset($post['save_park'])) {
            $config->update([
                'id'                  => $config->getID(),
                'audit_ghost_days'    => max(0, min(3650, (int) ($post['audit_ghost_days'] ?? 30))),
                'audit_warranty_days' => max(0, min(3650, (int) ($post['audit_warranty_days'] ?? 30))),
            ]);
            Session::addMessageAfterRedirect(__('Contrôles du parc enregistrés.', 'configurationglpiauto'));

            return self::TAB_AUDIT;
        }

        // Issue #468 : familles comparées au dernier Blueprint appliqué.
        if (isset($post['save_drift'])) {
            $objects = $post['audit_drift_objects'] ?? [];
            $config->update([
                'id'                  => $config->getID(),
                'audit_drift_objects' => is_array($objects) ? $objects : [],
            ]);
            Session::addMessageAfterRedirect(__('Éléments surveillés enregistrés.', 'configurationglpiauto'));

            return self::TAB_AUDIT;
        }

        // Issue #285 : rapport d'audit périodique. La fréquence est celle de la tâche planifiée
        // GLPI elle-même, pour rester cohérente avec Configuration > Actions automatiques.
        if (isset($post['save_report'])) {
            $config->update([
                'id'                          => $config->getID(),
                'audit_report_only_on_change' => !empty($post['audit_report_only_on_change']) ? 1 : 0,
                'audit_report_emails'         => (string) ($post['audit_report_emails'] ?? ''),
            ]);
            $task = new CronTask();
            if ($task->getFromDBbyName(AuditReportCron::class, AuditReportCron::TASK_NAME)) {
                $frequencies = ['week' => WEEK_TIMESTAMP, 'month' => MONTH_TIMESTAMP];
                $frequency = (string) ($post['audit_report_frequency'] ?? 'off');
                $task->update([
                    'id'        => $task->getID(),
                    'state'     => isset($frequencies[$frequency]) ? CronTask::STATE_WAITING : CronTask::STATE_DISABLE,
                    'frequency' => $frequencies[$frequency] ?? $task->fields['frequency'],
                ]);
            }
            Session::addMessageAfterRedirect(__('Réglages du rapport d\'audit enregistrés.', 'configurationglpiauto'));

            return self::TAB_AUDIT;
        }

        // Issue #467 : fin de support (endoflife.date). Rien n'est téléchargé d'ici : le
        // téléchargement des dates est une action de la page d'audit.
        if (isset($post['save_eol'])) {
            $config->update([
                'id'                => $config->getID(),
                'audit_eol_enabled' => !empty($post['audit_eol_enabled']) ? 1 : 0,
                'audit_eol_offline' => !empty($post['audit_eol_offline']) ? 1 : 0,
                'audit_eol_days'    => (int) ($post['audit_eol_days'] ?? 180),
                'audit_eol_mapping' => (string) ($post['audit_eol_mapping'] ?? ''),
            ]);
            Session::addMessageAfterRedirect(__('Réglages de fin de support enregistrés.', 'configurationglpiauto'));
        } elseif (isset($post['suggest_eol'])) {
            $config->update([
                'id'                => $config->getID(),
                'audit_eol_mapping' => EndOfLifeMapping::withSuggestions(EndOfLifeCheck::mappingText(), EndOfLifeCheck::osNames()),
            ]);
            Session::addMessageAfterRedirect(__('Correspondances proposées ajoutées : vérifiez-les avant de télécharger les dates.', 'configurationglpiauto'));
        } elseif (isset($post['import_eol'])) {
            if (!EndOfLifeCheck::enabled()) {
                Session::addMessageAfterRedirect(__('Activez d\'abord le contrôle de fin de support.', 'configurationglpiauto'), false, ERROR);

                return self::TAB_EOL;
            }
            $product = strtolower(trim((string) ($post['eol_product'] ?? '')));
            $file = $files['eol_file'] ?? null;
            $json = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int) ($file['size'] ?? 0) <= 2 * 1024 * 1024
                ? (string) file_get_contents((string) $file['tmp_name'])
                : '';
            try {
                $count = EndOfLifeCache::import($product, $json);
                Session::addMessageAfterRedirect(sprintf(__('%1$d cycle(s) importé(s) pour %2$s.', 'configurationglpiauto'), $count, $product));
            } catch (\RuntimeException) {
                Session::addMessageAfterRedirect(__('Fichier refusé : choisissez le fichier JSON d\'un produit endoflife.date (2 Mo au plus) et indiquez son identifiant, par exemple « windows ».', 'configurationglpiauto'), false, ERROR);
            }
        }

        return self::TAB_EOL;
    }

    /**
     * Valeurs affichées par templates/config.html.twig.
     *
     * @return array<string, mixed>
     */
    public static function view(string $tab): array
    {
        global $CFG_GLPI;

        $config = Config::getConfig();
        $task = new CronTask();
        $frequency = 'off';
        if ($task->getFromDBbyName(AuditReportCron::class, AuditReportCron::TASK_NAME) && (int) $task->fields['state'] !== CronTask::STATE_DISABLE) {
            $frequency = (int) $task->fields['frequency'] >= MONTH_TIMESTAMP ? 'month' : 'week';
        }
        $notifications = (new Notification())->find(['itemtype' => Config::class, 'event' => AuditReportCron::EVENT], [], 1);
        $notificationId = (int) (array_key_first($notifications) ?? 0);

        $tabs = [];
        foreach (self::tabs() as $key => [$label, $icon]) {
            $tabs[] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'url' => self::url($key), 'active' => $key === $tab];
        }
        // Lue sur GitHub seulement pour l'onglet Général, qui l'affiche (cache de 24 h).
        $latest = $tab === self::TAB_GENERAL ? Config::getLatestGithubVersion() : null;

        return [
            'tab'      => $tab,
            'tabs'     => $tabs,
            'action'   => FrontUrl::get('config.php'),
            'can_edit' => self::canEdit(),
            'version'  => [
                'installed'        => PLUGIN_CONFIGURATIONGLPIAUTO_VERSION,
                'latest'           => $latest,
                'update_available' => $latest !== null && version_compare(PLUGIN_CONFIGURATIONGLPIAUTO_VERSION, $latest, '<'),
            ],
            'links'    => [
                // Liste des profils GLPI (onglet « Configuration GLPI Auto » de chaque profil).
                'rights'        => \Toolbox::getItemTypeSearchURL(\Profile::class),
                'notifications' => Notification::getSearchURL(),
                'crontasks'     => CronTask::getSearchURL(),
            ],
            'park'     => [
                'ghost_days'    => (int) ($config->fields['audit_ghost_days'] ?? 30),
                'warranty_days' => (int) ($config->fields['audit_warranty_days'] ?? 30),
            ],
            'drift'    => [
                'labels'  => \GlpiPlugin\Configurationglpiauto\Audit\BlueprintDrift::objectLabels(),
                'watched' => $config->getDriftObjects(),
            ],
            'report'   => [
                'frequency'        => $frequency,
                'only_on_change'   => !empty($config->fields['audit_report_only_on_change']),
                'emails'           => implode("\n", $config->getAuditReportEmails()),
                'notification_url' => $notificationId > 0 ? Notification::getFormURLWithID($notificationId) : null,
                'notifications_on' => !empty($CFG_GLPI['use_notifications']) && !empty($CFG_GLPI['notifications_mailing']),
            ],
            'eol'      => [
                'enabled' => !empty($config->fields['audit_eol_enabled']),
                'offline' => !empty($config->fields['audit_eol_offline']),
                'days'    => (int) ($config->fields['audit_eol_days'] ?? 180),
                'mapping' => (string) ($config->fields['audit_eol_mapping'] ?? ''),
            ],
        ];
    }
}
