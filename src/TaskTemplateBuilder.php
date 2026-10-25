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
                'pt_BR' => "CHECKLIST DE ONBOARDING\n\n- Criar a conta e o e-mail\n- Configurar a estação de trabalho e os periféricos\n- Instalar os softwares corporativos e as licenças necessárias\n- Configurar os acessos a aplicativos e VPN, se necessário\n- Entregar o equipamento e o guia do usuário\n- Validar os acessos com o colaborador",
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
                'pt_BR' => "CHECKLIST DE OFFBOARDING\n\n- Desativar a conta e o e-mail\n- Revogar os acessos VPN e a aplicativos\n- Recolher e recondicionar o equipamento\n- Arquivar ou transferir os dados\n- Cancelar as licenças nominais\n- Atualizar o status do equipamento no GLPI",
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
                'pt_BR' => "CHECKLIST DE MANUTENÇÃO PREVENTIVA\n\n- Verificar as atualizações do sistema e os drivers\n- Verificar o espaço em disco e a saúde do disco\n- Verificar os backups\n- Limpar fisicamente o equipamento, se necessário\n- Registrar as anomalias constatadas",
            ],
        ],
        // Issue #340 : gabarits d'hygiène informatique (poste infecté, prestataire, privilèges).
        [
            'name' => 'Sécurité — Nettoyage d\'un poste infecté',
            'icon' => '🦠',
            'category' => 'Sécurité & Audit',
            'content' => "PROCÉDURE POSTE INFECTÉ\n\n- Isoler le poste du réseau (câble, Wi-Fi, VPN) sans l'éteindre\n- Noter l'heure, les symptômes et le message éventuel\n- Prévenir le responsable sécurité et ouvrir un incident de sécurité si besoin\n- Lancer une analyse antivirus complète hors ligne\n- Sauvegarder les données utiles après analyse, puis réinstaller le poste si l'infection est confirmée\n- Réinitialiser les mots de passe de l'utilisateur (session, messagerie, applications)\n- Vérifier les autres postes du même réseau ou ayant reçu le même message\n- Remettre le poste en service et rappeler les bons réflexes à l'utilisateur",
            'translations' => [
                'en_GB' => "INFECTED WORKSTATION PROCEDURE\n\n- Isolate the workstation from the network (cable, Wi-Fi, VPN) without shutting it down\n- Note the time, the symptoms and any message displayed\n- Inform the security officer and open a security incident if needed\n- Run a full offline antivirus scan\n- Back up the useful data after the scan, then reinstall the workstation if the infection is confirmed\n- Reset the user's passwords (session, email, applications)\n- Check the other workstations on the same network or that received the same message\n- Put the workstation back into service and remind the user of the right habits",
                'de_DE' => "VERFAHREN INFIZIERTER ARBEITSPLATZ\n\n- Arbeitsplatz vom Netzwerk trennen (Kabel, WLAN, VPN), ohne ihn auszuschalten\n- Uhrzeit, Symptome und eventuelle Meldung notieren\n- Sicherheitsverantwortlichen informieren und bei Bedarf einen Sicherheitsvorfall eröffnen\n- Vollständigen Offline-Virenscan durchführen\n- Nützliche Daten nach dem Scan sichern, dann den Arbeitsplatz bei bestätigter Infektion neu installieren\n- Passwörter des Benutzers zurücksetzen (Sitzung, E-Mail, Anwendungen)\n- Andere Arbeitsplätze im selben Netzwerk oder mit derselben Nachricht prüfen\n- Arbeitsplatz wieder in Betrieb nehmen und den Benutzer an die richtigen Verhaltensweisen erinnern",
                'it_IT' => "PROCEDURA POSTAZIONE INFETTA\n\n- Isolare la postazione dalla rete (cavo, Wi-Fi, VPN) senza spegnerla\n- Annotare l'ora, i sintomi e l'eventuale messaggio\n- Avvisare il responsabile della sicurezza e aprire un incidente di sicurezza se necessario\n- Eseguire una scansione antivirus completa offline\n- Salvare i dati utili dopo la scansione, poi reinstallare la postazione se l'infezione è confermata\n- Reimpostare le password dell'utente (sessione, posta, applicazioni)\n- Verificare le altre postazioni della stessa rete o che hanno ricevuto lo stesso messaggio\n- Rimettere in servizio la postazione e ricordare all'utente i comportamenti corretti",
                'es_ES' => "PROCEDIMIENTO EQUIPO INFECTADO\n\n- Aislar el equipo de la red (cable, Wi-Fi, VPN) sin apagarlo\n- Anotar la hora, los síntomas y el mensaje que aparezca\n- Avisar al responsable de seguridad y abrir un incidente de seguridad si es necesario\n- Ejecutar un análisis antivirus completo sin conexión\n- Guardar los datos útiles tras el análisis y reinstalar el equipo si se confirma la infección\n- Restablecer las contraseñas del usuario (sesión, correo, aplicaciones)\n- Comprobar los demás equipos de la misma red o que hayan recibido el mismo mensaje\n- Volver a poner el equipo en servicio y recordar al usuario las buenas prácticas",
                'pt_BR' => "PROCEDIMENTO ESTAÇÃO INFECTADA\n\n- Isolar a estação da rede (cabo, Wi-Fi, VPN) sem desligá-la\n- Anotar o horário, os sintomas e a mensagem exibida\n- Avisar o responsável pela segurança e abrir um incidente de segurança, se necessário\n- Executar uma verificação antivírus completa off-line\n- Salvar os dados úteis após a verificação e reinstalar a estação se a infecção for confirmada\n- Redefinir as senhas do usuário (sessão, e-mail, aplicativos)\n- Verificar as outras estações da mesma rede ou que receberam a mesma mensagem\n- Recolocar a estação em serviço e lembrar o usuário das boas práticas",
            ],
        ],
        [
            'name' => 'Sécurité — Accueil d\'un prestataire externe',
            'icon' => '🤝',
            'category' => 'Gestion des comptes utilisateurs',
            'content' => "CHECKLIST PRESTATAIRE EXTERNE\n\n- Vérifier le contrat ou le bon de commande et le nom du référent interne\n- Faire signer la charte informatique et l'engagement de confidentialité\n- Créer un compte nominatif avec une date de fin (jamais de compte partagé)\n- Limiter les accès au strict nécessaire de la mission (applications, partages, VPN)\n- Fournir un poste maîtrisé ou vérifier la conformité du poste du prestataire (antivirus, mises à jour)\n- Consigner les accès accordés dans ce ticket\n- À la fin de la mission : désactiver le compte, révoquer les accès et récupérer le matériel",
            'translations' => [
                'en_GB' => "EXTERNAL CONTRACTOR CHECKLIST\n\n- Check the contract or purchase order and the name of the internal contact\n- Have the IT charter and the confidentiality agreement signed\n- Create a named account with an end date (never a shared account)\n- Limit access to what the assignment strictly requires (applications, shares, VPN)\n- Provide a managed workstation or check the contractor's workstation (antivirus, updates)\n- Record the access granted in this ticket\n- At the end of the assignment: disable the account, revoke access and retrieve the equipment",
                'de_DE' => "CHECKLISTE EXTERNER DIENSTLEISTER\n\n- Vertrag oder Bestellung und Namen des internen Ansprechpartners prüfen\n- IT-Charta und Vertraulichkeitserklärung unterschreiben lassen\n- Personenbezogenes Konto mit Enddatum anlegen (nie ein geteiltes Konto)\n- Zugänge auf das für den Auftrag unbedingt Nötige beschränken (Anwendungen, Freigaben, VPN)\n- Verwalteten Arbeitsplatz bereitstellen oder den Arbeitsplatz des Dienstleisters prüfen (Virenschutz, Updates)\n- Gewährte Zugänge in diesem Ticket festhalten\n- Am Ende des Auftrags: Konto deaktivieren, Zugänge entziehen und Ausrüstung zurücknehmen",
                'it_IT' => "CHECKLIST FORNITORE ESTERNO\n\n- Verificare il contratto o l'ordine e il nome del referente interno\n- Far firmare la carta informatica e l'impegno di riservatezza\n- Creare un account nominativo con data di fine (mai un account condiviso)\n- Limitare gli accessi allo stretto necessario per l'incarico (applicazioni, condivisioni, VPN)\n- Fornire una postazione gestita o verificare la postazione del fornitore (antivirus, aggiornamenti)\n- Registrare gli accessi concessi in questo ticket\n- Alla fine dell'incarico: disattivare l'account, revocare gli accessi e recuperare il materiale",
                'es_ES' => "LISTA DE VERIFICACIÓN PROVEEDOR EXTERNO\n\n- Comprobar el contrato o la orden de compra y el nombre del referente interno\n- Hacer firmar la carta informática y el compromiso de confidencialidad\n- Crear una cuenta nominativa con fecha de fin (nunca una cuenta compartida)\n- Limitar los accesos a lo estrictamente necesario para la misión (aplicaciones, recursos compartidos, VPN)\n- Proporcionar un equipo gestionado o comprobar el equipo del proveedor (antivirus, actualizaciones)\n- Registrar los accesos concedidos en este ticket\n- Al final de la misión: desactivar la cuenta, revocar los accesos y recuperar el equipo",
                'pt_BR' => "CHECKLIST PRESTADOR EXTERNO\n\n- Verificar o contrato ou o pedido de compra e o nome do responsável interno\n- Coletar a assinatura da carta de TI e do termo de confidencialidade\n- Criar uma conta nominal com data de término (nunca uma conta compartilhada)\n- Limitar os acessos ao estritamente necessário para a missão (aplicativos, compartilhamentos, VPN)\n- Fornecer uma estação gerenciada ou verificar a estação do prestador (antivírus, atualizações)\n- Registrar os acessos concedidos neste ticket\n- Ao final da missão: desativar a conta, revogar os acessos e recolher o equipamento",
            ],
        ],
        [
            'name' => 'Sécurité — Revue des privilèges d\'accès',
            'icon' => '🔐',
            'category' => 'Sécurité & Audit',
            'content' => "REVUE DES PRIVILÈGES\n\n- Lister les comptes administrateurs (domaine, serveurs, GLPI, applications, cloud)\n- Lister les comptes de service et leurs propriétaires\n- Faire confirmer chaque privilège par le responsable de la personne ou de l'application\n- Retirer les privilèges non justifiés et les comptes devenus inutiles\n- Vérifier que les comptes à privilèges ont l'authentification multifacteur\n- Joindre la liste validée à ce ticket comme preuve de la revue",
            'translations' => [
                'en_GB' => "ACCESS PRIVILEGE REVIEW\n\n- List the administrator accounts (domain, servers, GLPI, applications, cloud)\n- List the service accounts and their owners\n- Have each privilege confirmed by the manager of the person or of the application\n- Remove unjustified privileges and accounts no longer needed\n- Check that privileged accounts use multi-factor authentication\n- Attach the validated list to this ticket as evidence of the review",
                'de_DE' => "ÜBERPRÜFUNG DER ZUGRIFFSRECHTE\n\n- Administratorkonten auflisten (Domäne, Server, GLPI, Anwendungen, Cloud)\n- Dienstkonten und ihre Verantwortlichen auflisten\n- Jedes Recht vom Vorgesetzten der Person oder vom Anwendungsverantwortlichen bestätigen lassen\n- Nicht begründete Rechte und nicht mehr benötigte Konten entfernen\n- Prüfen, dass privilegierte Konten die Mehrfaktor-Authentifizierung nutzen\n- Die bestätigte Liste diesem Ticket als Nachweis der Überprüfung anhängen",
                'it_IT' => "REVISIONE DEI PRIVILEGI DI ACCESSO\n\n- Elencare gli account amministratore (dominio, server, GLPI, applicazioni, cloud)\n- Elencare gli account di servizio e i loro responsabili\n- Far confermare ogni privilegio dal responsabile della persona o dell'applicazione\n- Rimuovere i privilegi non giustificati e gli account non più necessari\n- Verificare che gli account privilegiati utilizzino l'autenticazione a più fattori\n- Allegare a questo ticket l'elenco convalidato come prova della revisione",
                'es_ES' => "REVISIÓN DE LOS PRIVILEGIOS DE ACCESO\n\n- Enumerar las cuentas de administrador (dominio, servidores, GLPI, aplicaciones, nube)\n- Enumerar las cuentas de servicio y sus responsables\n- Hacer confirmar cada privilegio por el responsable de la persona o de la aplicación\n- Retirar los privilegios no justificados y las cuentas que ya no se necesitan\n- Comprobar que las cuentas con privilegios usan la autenticación multifactor\n- Adjuntar a este ticket la lista validada como prueba de la revisión",
                'pt_BR' => "REVISÃO DOS PRIVILÉGIOS DE ACESSO\n\n- Listar as contas de administrador (domínio, servidores, GLPI, aplicativos, nuvem)\n- Listar as contas de serviço e seus responsáveis\n- Fazer o responsável pela pessoa ou pelo aplicativo confirmar cada privilégio\n- Remover os privilégios não justificados e as contas que não são mais necessárias\n- Verificar se as contas privilegiadas usam a autenticação multifator\n- Anexar a lista validada a este ticket como evidência da revisão",
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
            // Always called (see StateBuilder for the reasoning) so unchecking icons after a prior
            // run actually strips them instead of leaving old rows stuck.
            Translations::applyIcon(TaskTemplate::class, $templateId, $template['name'], $withIcons ? $template['icon'] : '');
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

        return Lookup::loadFirst($category, ['name' => $name, 'taskcategories_id' => 0]) ? (int) $category->getID() : 0;
    }

    private function getOrCreateTemplate(string $name, string $content, int $categoryId): int
    {
        $item = new TaskTemplate();
        if (Lookup::loadFirst($item, ['name' => $name])) {
            // Self-heals a template left with `taskcategories_id = 0` by an earlier run where
            // `TaskCategoryBuilder` hadn't been run yet (the wizard's steps are independent and can
            // be run in any order, or re-run individually) — see
            // `ProjectTaskTemplateBuilder::getOrCreateTemplate()`'s own docblock for the same
            // reasoning applied to project task templates.
            if ($categoryId > 0 && (int) $item->fields['taskcategories_id'] !== $categoryId) {
                $item->update(['id' => $item->getID(), 'taskcategories_id' => $categoryId]);
            }

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
