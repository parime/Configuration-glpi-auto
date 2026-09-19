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

/**
 * Configuration SMTP avancée (issue #121) : contrairement à tous les autres `*Builder` de ce
 * plugin, celui-ci n'écrit JAMAIS dans la table `Config` propre au plugin, ni via
 * `BlueprintSerializer`/`ConfigHistory` — il écrit directement dans la configuration native GLPI
 * (`\Config::setConfigurationValues('core', ...)`). Décision de sécurité délibérée : `Config`/
 * `BlueprintSerializer` stockent leurs colonnes en clair et les exportent telles quelles dans un
 * fichier `.json` téléchargeable (voir le Système de Blueprints, #113) — un mot de passe SMTP qui
 * transiterait par là fuiterait dans chaque export de Blueprint, chaque entrée d'historique
 * automatique (#114). GLPI cœur chiffre déjà `smtp_passwd` via `GLPIKey` dès que
 * `setConfigurationValues()` est appelé (confirmé en lisant `src/Config.php`) — cette classe ne
 * réimplémente aucun chiffrement, elle ne fait qu'assembler le tableau d'entrée et le lui passer.
 *
 * "Gestion des certificats" (texte de l'issue) : GLPI cœur n'expose qu'un seul réglage ici,
 * `smtp_check_certificate` (vérification du certificat TLS du serveur, oui/non) — aucune vraie
 * gestion de certificats clients n'existe dans GLPI, et rien n'indique un besoin réel pour
 * l'authentification SMTP sortante. Construire davantage ici serait de l'invention de périmètre.
 * "Tests de connectivité" : `\NotificationMailing::testNotification()` (GLPI cœur) fait déjà ça —
 * envoie un vrai email de test et retourne un résultat exploitable — mais teste la configuration
 * ENREGISTRÉE (`$CFG_GLPI`), jamais un formulaire en cours de saisie ; voir `front/wizard.php`
 * pour le lien vers l'écran natif GLPI où ce test existe déjà, plutôt qu'un test réimplémenté ici
 * sur des valeurs pas encore appliquées.
 */
final class SmtpBuilder
{
    /**
     * @param array<string, mixed> $input Typiquement `$_POST` du bloc "finish" de
     *     `front/wizard.php`.
     * @return bool `true` si la configuration SMTP a réellement été appliquée (l'étape était
     *     cochée), `false` sinon — même convention de retour que les autres `*Builder` de ce
     *     plugin pour alimenter le message de fin de course.
     */
    public function build(array $input): bool
    {
        if (empty($input['smtp_enabled'])) {
            return false;
        }

        $update = [
            'smtp_mode'              => (int) ($input['smtp_mode'] ?? MAIL_MAIL) === MAIL_SMTP ? MAIL_SMTP : MAIL_MAIL,
            'smtp_host'              => trim((string) ($input['smtp_host'] ?? '')),
            'smtp_port'              => max(1, (int) ($input['smtp_port'] ?? 25)),
            'smtp_sender'            => trim((string) ($input['smtp_sender'] ?? '')),
            'smtp_check_certificate' => empty($input['smtp_check_certificate']) ? 0 : 1,
            'smtp_username'          => trim((string) ($input['smtp_username'] ?? '')),
        ];

        // Même règle que Config::handleSmtpInput() côté cœur GLPI (confirmé en le lisant) : un mot
        // de passe vide dans le formulaire n'écrase jamais le secret déjà enregistré — seul un
        // nouveau mot de passe réellement saisi, ou la case "Effacer" explicitement cochée,
        // modifie la valeur stockée (chiffrée) en base.
        if (!empty($input['smtp_passwd'])) {
            $update['smtp_passwd'] = (string) $input['smtp_passwd'];
        } elseif (!empty($input['_blank_smtp_passwd'])) {
            $update['smtp_passwd'] = '';
        }

        \Config::setConfigurationValues('core', $update);

        return true;
    }
}
