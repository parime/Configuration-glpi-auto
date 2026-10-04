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

use GlpiPlugin\Configurationglpiauto\Audit\AuditReport;
use GlpiPlugin\Configurationglpiauto\Audit\AuditReportCron;
use GlpiPlugin\Configurationglpiauto\Audit\AuditService;
use Html;
use NotificationTarget;

/**
 * Issue #285 : cible de notification du rapport d'audit périodique. Nom imposé par
 * NotificationTarget::getInstanceClass() pour l'itemtype `GlpiPlugin\Configurationglpiauto\Config`
 * (la ligne de configuration du plugin sert d'objet porteur de l'événement, voir AuditReportCron).
 *
 * Destinataires : ceux de GLPI (administrateur, profils, groupes — ajoutés par
 * NotificationTarget::addNotificationTargets()) plus une cible propre, « adresses
 * supplémentaires », alimentée par Config::audit_report_emails pour des destinataires sans compte
 * GLPI (RSSI externe, prestataire).
 *
 * @extends NotificationTarget<Config>
 */
class NotificationTargetConfig extends NotificationTarget
{
    /** Identifiant de la cible « adresses supplémentaires » (hors plage des constantes de Notification). */
    public const EXTRA_ADDRESSES_TARGET = 2850;

    public function getEvents()
    {
        return [AuditReportCron::EVENT => __('Rapport d\'audit périodique', 'configurationglpiauto')];
    }

    public function addAdditionalTargets($event = '')
    {
        $this->addTarget(
            self::EXTRA_ADDRESSES_TARGET,
            __('Adresses supplémentaires (réglage du rapport d\'audit)', 'configurationglpiauto')
        );
    }

    public function addSpecificTargets($data, $options)
    {
        if ((int) ($data['items_id'] ?? 0) !== self::EXTRA_ADDRESSES_TARGET) {
            return;
        }
        foreach (Config::getConfig()->getAuditReportEmails() as $email) {
            $this->addToRecipientsList(['email' => $email, 'language' => '']);
        }
    }

    public function addDataForTemplate($event, $options = [])
    {
        global $CFG_GLPI;

        // Constats recalculés ICI plutôt que repris de $options : GLPI rappelle cette méthode une
        // fois par langue de destinataire, après avoir chargé cette langue — titres et
        // recommandations arrivent ainsi traduits pour chacun (les statuts, eux, ne dépendent pas
        // de la langue et restent identiques à ceux calculés par AuditReportCron).
        $report    = AuditReport::build($options['check_keys'] ?? [], AuditService::runAll());
        $evolution = $options['evolution'] ?? AuditReport::evolution(null, []);
        $events    = $this->getAllEvents();

        $this->data['##audit.action##']   = $events[$event] ?? '';
        $this->data['##audit.date##']     = Html::convDateTime(date('Y-m-d H:i:s'));
        $this->data['##audit.total##']    = (string) $report['counts']['total'];
        $this->data['##audit.ok##']       = (string) $report['counts']['ok'];
        $this->data['##audit.warning##']  = (string) $report['counts']['warning'];
        $this->data['##audit.critical##'] = (string) $report['counts']['critical'];
        $this->data['##audit.url##']      = rtrim((string) ($CFG_GLPI['url_base'] ?? ''), '/')
            . '/plugins/configurationglpiauto/front/audit.php';

        if ($evolution['first_report']) {
            $this->data['##audit.evolution##'] = __('Premier rapport : pas encore de comparaison possible.', 'configurationglpiauto');
        } elseif ($evolution['worsened'] === [] && $evolution['improved'] === []) {
            $this->data['##audit.evolution##'] = sprintf(
                __('Aucune évolution depuis le rapport du %s.', 'configurationglpiauto'),
                Html::convDateTime($options['previous_date'] ?? null)
            );
        } else {
            $this->data['##audit.evolution##'] = sprintf(
                __('Depuis le rapport du %1$s : %2$d contrôle(s) dégradé(s), %3$d amélioré(s).', 'configurationglpiauto'),
                Html::convDateTime($options['previous_date'] ?? null),
                count($evolution['worsened']),
                count($evolution['improved'])
            );
        }

        $severityLabels = [
            AuditReport::STATUS_CRITICAL => __('En échec', 'configurationglpiauto'),
            AuditReport::STATUS_WARNING  => __('En alerte', 'configurationglpiauto'),
        ];
        $problems = [];
        foreach ($report['problems'] as $problem) {
            $problems[] = [
                '##problem.severity##'       => $severityLabels[$problem['severity']] ?? $problem['severity'],
                '##problem.domain##'         => $problem['domain'],
                '##problem.title##'          => $problem['title'],
                '##problem.recommendation##' => $problem['recommendation'],
            ];
        }
        // Bloc ##FOREACHproblems## : une liste de lignes, forme attendue par le moteur de modèles de
        // GLPI (même forme que les blocs FOREACH de ses propres cibles, ex. tickets), que le type
        // déclaré de NotificationTarget::$data ne décrit pas.
        $this->data['problems'] = $problems; // @phpstan-ignore assign.propertyType

        $this->getTags();
        foreach ($this->tag_descriptions[NotificationTarget::TAG_LANGUAGE] as $tag => $values) {
            if (!isset($this->data[$tag])) {
                $this->data[$tag] = $values['label'];
            }
        }
    }

    public function getTags()
    {
        $tags = [
            'audit.action'    => _n('Event', 'Events', 1),
            'audit.date'      => __('Date'),
            'audit.total'     => __('Contrôles exécutés', 'configurationglpiauto'),
            'audit.ok'        => __('Contrôles OK', 'configurationglpiauto'),
            'audit.warning'   => __('Contrôles en alerte', 'configurationglpiauto'),
            'audit.critical'  => __('Contrôles en échec', 'configurationglpiauto'),
            'audit.evolution' => __('Évolution depuis le rapport précédent', 'configurationglpiauto'),
            'audit.url'       => __('URL'),
            'problem.severity'       => __('Gravité', 'configurationglpiauto'),
            'problem.domain'         => __('Domaine', 'configurationglpiauto'),
            'problem.title'          => __('Constat', 'configurationglpiauto'),
            'problem.recommendation' => __('Recommandation', 'configurationglpiauto'),
        ];
        foreach ($tags as $tag => $label) {
            $this->addTagToList(['tag' => $tag, 'label' => $label, 'value' => true]);
        }
        $this->addTagToList([
            'tag'     => 'problems',
            'label'   => __('Points à traiter', 'configurationglpiauto'),
            'value'   => false,
            'foreach' => true,
        ]);

        asort($this->tag_descriptions);
    }
}
