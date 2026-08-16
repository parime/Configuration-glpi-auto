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

use Planning;
use TaskCategory;
use TaskTemplate;

/**
 * Turns on `task_templates_enabled` into a real `TaskTemplate` library (Configuration >
 * Intitulés > Assistance > "Gabarits de tâche") — reusable checklists a technician attaches to a
 * ticket instead of retyping the same steps every time. GLPI ships none by default.
 *
 * Each template's `taskcategories_id` is resolved by name against whatever `TaskCategoryBuilder`
 * already created, independently re-looked-up rather than threading IDs through — same
 * independent-resolution pattern `ServiceCatalogBuilder` already uses against `CategoryBuilder`'s
 * tree. A template whose target category doesn't exist (category toggle off, or renamed) still
 * gets created, just without a category — never blocks on a missing dependency.
 */
class TaskTemplateBuilder
{
    private const TEMPLATES = [
        [
            'name' => 'Onboarding — Arrivée collaborateur',
            'icon' => '🆕',
            'category' => 'Gestion des comptes utilisateurs',
            'content' => "CHECKLIST ONBOARDING\n\n- Créer le compte et la messagerie\n- Configurer le poste de travail et les périphériques\n- Installer les logiciels métier et licences nécessaires\n- Configurer les accès applicatifs et VPN si besoin\n- Remettre le matériel et le guide utilisateur\n- Valider les accès avec le collaborateur",
            'translations' => [
                'en_GB' => "ONBOARDING CHECKLIST\n\n- Create the account and mailbox\n- Set up the workstation and peripherals\n- Install the required business software and licenses\n- Configure application and VPN access if needed\n- Hand over the equipment and user guide\n- Confirm access with the new team member",
                'de_DE' => "CHECKLISTE ONBOARDING\n\n- Konto und Postfach anlegen\n- Arbeitsplatz und Peripheriegeräte einrichten\n- Erforderliche Fachsoftware und Lizenzen installieren\n- Anwendungs- und VPN-Zugänge bei Bedarf konfigurieren\n- Ausrüstung und Benutzerhandbuch übergeben\n- Zugänge mit dem neuen Mitarbeiter bestätigen",
                'it_IT' => "CHECKLIST ONBOARDING\n\n- Creare l'account e la casella di posta\n- Configurare la postazione di lavoro e le periferiche\n- Installare i software aziendali e le licenze necessarie\n- Configurare gli accessi applicativi e VPN se necessario\n- Consegnare il materiale e la guida utente\n- Convalidare gli accessi con il collaboratore",
                'es_ES' => "LISTA DE VERIFICACIÓN DE INCORPORACIÓN\n\n- Crear la cuenta y el correo electrónico\n- Configurar el puesto de trabajo y los periféricos\n- Instalar el software corporativo y las licencias necesarias\n- Configurar los accesos a aplicaciones y VPN si es necesario\n- Entregar el equipo y la guía del usuario\n- Validar los accesos con el colaborador",
            ],
        ],
        [
            'name' => 'Offboarding — Départ collaborateur',
            'icon' => '🚪',
            'category' => 'Gestion des comptes utilisateurs',
            'content' => "CHECKLIST OFFBOARDING\n\n- Désactiver le compte et la messagerie\n- Révoquer les accès VPN et applicatifs\n- Récupérer et reconditionner le matériel\n- Archiver ou transférer les données\n- Résilier les licences nominatives\n- Mettre à jour le statut de l'équipement dans GLPI",
            'translations' => [
                'en_GB' => "OFFBOARDING CHECKLIST\n\n- Disable the account and mailbox\n- Revoke VPN and application access\n- Retrieve and refurbish the equipment\n- Archive or transfer the data\n- Cancel named licenses\n- Update the equipment status in GLPI",
                'de_DE' => "CHECKLISTE OFFBOARDING\n\n- Konto und Postfach deaktivieren\n- VPN- und Anwendungszugänge entziehen\n- Ausrüstung zurücknehmen und aufbereiten\n- Daten archivieren oder übertragen\n- Personenbezogene Lizenzen kündigen\n- Gerätestatus in GLPI aktualisieren",
                'it_IT' => "CHECKLIST OFFBOARDING\n\n- Disattivare l'account e la casella di posta\n- Revocare gli accessi VPN e applicativi\n- Recuperare e ricondizionare il materiale\n- Archiviare o trasferire i dati\n- Disdire le licenze nominative\n- Aggiornare lo stato del dispositivo in GLPI",
                'es_ES' => "LISTA DE VERIFICACIÓN DE DESVINCULACIÓN\n\n- Desactivar la cuenta y el correo electrónico\n- Revocar los accesos VPN y a aplicaciones\n- Recuperar y reacondicionar el equipo\n- Archivar o transferir los datos\n- Cancelar las licencias nominales\n- Actualizar el estado del equipo en GLPI",
            ],
        ],
        [
            'name' => 'Maintenance préventive',
            'icon' => '🧰',
            'category' => 'Maintenance préventive',
            'content' => "CHECKLIST MAINTENANCE PRÉVENTIVE\n\n- Vérifier les mises à jour système et pilotes\n- Contrôler l'espace disque et l'état du disque\n- Vérifier les sauvegardes\n- Nettoyer physiquement le matériel si nécessaire\n- Consigner les anomalies constatées",
            'translations' => [
                'en_GB' => "PREVENTIVE MAINTENANCE CHECKLIST\n\n- Check system updates and drivers\n- Check disk space and disk health\n- Check backups\n- Physically clean the equipment if needed\n- Log any anomalies found",
                'de_DE' => "CHECKLISTE VORBEUGENDE WARTUNG\n\n- Systemupdates und Treiber prüfen\n- Speicherplatz und Zustand der Festplatte prüfen\n- Sicherungen prüfen\n- Gerät bei Bedarf physisch reinigen\n- Festgestellte Auffälligkeiten dokumentieren",
                'it_IT' => "CHECKLIST MANUTENZIONE PREVENTIVA\n\n- Verificare gli aggiornamenti di sistema e i driver\n- Controllare lo spazio disco e lo stato del disco\n- Verificare i backup\n- Pulire fisicamente il dispositivo se necessario\n- Registrare le anomalie riscontrate",
                'es_ES' => "LISTA DE VERIFICACIÓN DE MANTENIMIENTO PREVENTIVO\n\n- Comprobar las actualizaciones del sistema y los controladores\n- Comprobar el espacio en disco y el estado del disco\n- Comprobar las copias de seguridad\n- Limpiar físicamente el equipo si es necesario\n- Registrar las anomalías detectadas",
            ],
        ],
    ];

    /**
     * @return int Number of task templates created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['task_templates_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['task_template_icons_enabled']);
        $count = 0;
        foreach (self::TEMPLATES as $template) {
            $templateId = $this->getOrCreateTemplate($template['name'], $template['content'], $this->findCategoryId($template['category']));
            if ($withIcons) {
                Translations::applyIcon(TaskTemplate::class, $templateId, $template['name'], $template['icon']);
            }
            Translations::applyContent(TaskTemplate::class, $templateId, $template['translations']);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{name: string, icon: string, category: string, content: string, translations: array<string, string>}>
     */
    public static function getLibraryPreview(): array
    {
        return self::TEMPLATES;
    }

    private function findCategoryId(string $name): int
    {
        $category = new TaskCategory();

        return $category->getFromDBByCrit(['name' => $name, 'taskcategories_id' => 0]) ? (int) $category->getID() : 0;
    }

    private function getOrCreateTemplate(string $name, string $content, int $categoryId): int
    {
        $item = new TaskTemplate();
        if ($item->getFromDBByCrit(['name' => $name])) {
            return (int) $item->getID();
        }

        return (int) $item->add([
            'name' => $name,
            'content' => $content,
            'taskcategories_id' => $categoryId,
            'state' => Planning::TODO,
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
    }
}
