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
 * Writes a GLPI Network registration key to GLPI core's own native config, the exact same
 * mechanism/field the native "Configuration > Générale > Enregistrement" page uses
 * (`\Config::setConfigurationValues('core', ['glpinetwork_registration_key' => ...])` —
 * `glpinetwork_registration_key` is one of the fields `GLPIKey` auto-encrypts on save, confirmed
 * in `src/GLPIKey.php`). Deliberately never persisted in this plugin's own config table (unlike
 * every other setting here): our table has no field-level encryption at all, so storing a second,
 * unencrypted copy of a real credential would be a security downgrade rather than a convenience —
 * the wizard step instead reads the current value back from `GLPINetwork::getRegistrationKey()`
 * itself to pre-fill the field, same as the native page already does.
 *
 * A free GLPI Network registration is enough to unlock the native marketplace (confirmed: without
 * one, `Marketplace\Api\Plugins` simulates an empty `[]` response client-side rather than even
 * calling the real API) — this alone is what makes the recommended-plugins list below actually
 * installable in one click from Configuration > Plugins > Marketplace afterward.
 *
 * The recommended-plugins list itself creates nothing (informational only, deliberately not an
 * automated cross-plugin install — downloading and running third-party code from within this
 * wizard is a materially different risk category than the native-GLPI-content builders elsewhere
 * in this plugin). Every entry was verified against the real, now-unlocked native marketplace
 * (`data-key` attribute, license, author, star rating) rather than guessed from a GitHub repo name
 * — confirmed the hard way that `one-timesecret` (the repo name) and `onetimesecret` (the real
 * marketplace key) actually differ.
 */
class MarketplaceBuilder
{
    private const RECOMMENDED_PLUGINS = [
        [
            'name' => 'remise-glpi',
            'key' => null,
            'description' => 'Gestion de feuilles de prêt, retour, vente ou don de matériel, pour la traçabilité des mouvements de parc et la centralisation des documents associés dans GLPI.',
            'url' => 'https://github.com/parime/remise-glpi',
            'note' => 'Pas encore publié sur le marketplace natif — installation manuelle depuis GitHub.',
        ],
        [
            'name' => 'Escalade',
            'key' => 'escalade',
            'description' => 'Simplifie l\'escalade de ticket vers des groupes différents (pas des utilisateurs individuels) : historique graphique des groupes assignés, widget de tableau de bord, critère de recherche dédié, clonage de ticket.',
            'url' => 'https://github.com/pluginsGLPI/escalade',
            'note' => 'Gratuit, installable en un clic depuis Configuration > Plugins > Marketplace.',
        ],
        [
            'name' => 'One-Time Secret',
            'key' => 'onetimesecret',
            'description' => 'Ajoute un bouton sur la timeline d\'un ticket pour partager un mot de passe via un lien à usage unique et durée de vie configurable, plutôt que de l\'écrire en clair dans le ticket.',
            'url' => 'https://tic.gal/en/project/onetimesecret/',
            'note' => 'Gratuit, installable en un clic depuis Configuration > Plugins > Marketplace.',
        ],
    ];

    /**
     * @return int 1 if a registration key was written, 0 otherwise.
     */
    public function build(string $registrationKey): int
    {
        $registrationKey = trim($registrationKey);
        if ($registrationKey === '') {
            return 0;
        }

        // Re-submitting the same key (e.g. re-running the wizard) writes a different ciphertext
        // each time — `GLPIKey`'s encryption uses a fresh IV per call, not a bug. Verified in real
        // conditions: the marketplace still authenticated correctly after this round-trip.
        \Config::setConfigurationValues('core', ['glpinetwork_registration_key' => $registrationKey]);

        return 1;
    }

    /**
     * @return array<int, array{name: string, key: ?string, description: string, url: string, note: string}>
     */
    public static function getRecommendedPluginsPreview(): array
    {
        return self::RECOMMENDED_PLUGINS;
    }
}
