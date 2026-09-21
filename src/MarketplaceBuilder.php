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
 *
 * The first two entries below are this plugin's own sibling repos (same author, deliberately
 * complementary rather than overlapping — see this plugin's own ROADMAP.md "Vision"): `assetsign-glpi`
 * interoperates directly with `StateBuilder`'s recommended states (see that class' own docblock),
 * `glpi-iso27001-management` with the ITIL categories/service catalog this wizard seeds (a "Sécurité
 * SI" category and equivalent already exist for its Security incidents to file under). Re-verified
 * both names/URLs against the real repos (`gh repo view`) rather than trusting an old citation —
 * `assetsign-glpi` was named `remise-glpi` until a rename partway through this plugin's own
 * development (GitHub keeps the old URL as a redirect, which is how a stale name/description sat
 * here undetected for a while: the link never actually broke, it just stopped matching reality).
 */
class MarketplaceBuilder
{
    private const RECOMMENDED_PLUGINS = [
        [
            'name' => 'assetsign-glpi',
            'key' => null,
            'description' => 'Plugin GLPI de remise, restitution, don et vente de matériel : signature électronique intégrée (sans service tiers), état des lieux visuel, passeport numérique et score de santé du matériel.',
            'url' => 'https://github.com/parime/assetsign-glpi',
            'note' => 'Pas encore publié sur le marketplace natif — installation manuelle depuis GitHub.',
        ],
        [
            'name' => 'glpi-iso27001-management',
            'key' => null,
            'description' => 'Plateforme open source de gouvernance, risques et conformité (GRC/ISO 27001) intégrée à GLPI : registre des risques, incidents de sécurité, audits, non-conformités et plan de traitement, sans quitter GLPI.',
            'url' => 'https://github.com/parime/glpi-iso27001-management',
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
