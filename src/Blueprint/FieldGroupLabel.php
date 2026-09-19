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

namespace GlpiPlugin\Configurationglpiauto\Blueprint;

/**
 * Regroupement purement visuel des champs `Config` par préfixe de nom (calculé, jamais une table
 * de correspondance figée à maintenir face à chaque nouveau champ) — utilisé par l'écran de
 * restauration (`front/history_restore.php`, issue #114) et l'export sélectif (`front/
 * profile.form.php`, issue #117). Extrait dans sa propre classe plutôt que dupliqué dans les deux
 * fichiers : ~40 libellés traduits à maintenir à un seul endroit, pas deux copies qui pourraient
 * diverger. Repli sur le préfixe brut si absent de cette table : dégradation propre, pas une
 * exigence d'exhaustivité (un futur champ `Config` tombe automatiquement dans son groupe, avec ou
 * sans libellé traduit).
 */
final class FieldGroupLabel
{
    /**
     * Une méthode plutôt qu'une `const` : chaque libellé doit rester un argument littéral direct
     * d'un appel `__(...)` pour que `vendor/bin/extract-locales` puisse le découvrir statiquement
     * (une constante de classe ne peut pas contenir d'appel de fonction) — sinon un nouveau préfixe
     * ajouté plus tard resterait silencieusement non traduit, sans que la vérification CI
     * "Locale Completeness" ne le détecte jamais.
     *
     * @return array<string, string>
     */
    private static function labels(): array
    {
        return [
            'entity' => __('Entités', 'configurationglpiauto'), 'calendar' => __('Calendrier', 'configurationglpiauto'), 'sla' => __('SLA', 'configurationglpiauto'), 'ola' => __('OLA', 'configurationglpiauto'),
            'escalation' => __('Escalade', 'configurationglpiauto'), 'category' => __('Catégories', 'configurationglpiauto'), 'state' => __('Statuts', 'configurationglpiauto'),
            'branding' => __('Personnalisation', 'configurationglpiauto'), 'notifications' => __('Notifications', 'configurationglpiauto'), 'ldap' => __('LDAP', 'configurationglpiauto'),
            'location' => __('Lieux', 'configurationglpiauto'), 'ticket' => __('Tickets', 'configurationglpiauto'), 'task' => __('Tâches', 'configurationglpiauto'), 'validation' => __('Validations', 'configurationglpiauto'),
            'change' => __('Changements', 'configurationglpiauto'), 'project' => __('Projets', 'configurationglpiauto'), 'kb' => __('Base de connaissances', 'configurationglpiauto'),
            'manufacturer' => __('Fabricants', 'configurationglpiauto'), 'document' => __('Documents', 'configurationglpiauto'), 'planning' => __('Planning', 'configurationglpiauto'),
            'satisfaction' => __('Satisfaction', 'configurationglpiauto'), 'committee' => __('Comité de validation', 'configurationglpiauto'), 'wait' => __('Raisons d\'attente', 'configurationglpiauto'),
            'service' => __('Catalogue de services', 'configurationglpiauto'), 'abroad' => __('Missions à l\'étranger', 'configurationglpiauto'), 'solution' => __('Solutions', 'configurationglpiauto'),
            'followup' => __('Suivis', 'configurationglpiauto'), 'user' => __('Utilisateurs', 'configurationglpiauto'), 'field' => __('Unicité des champs', 'configurationglpiauto'),
            'rss' => __('Flux RSS', 'configurationglpiauto'), 'line' => __('Lignes téléphoniques', 'configurationglpiauto'), 'asset' => __('Types d\'actifs', 'configurationglpiauto'),
            'software' => __('Licences logicielles', 'configurationglpiauto'), 'certificate' => __('Certificats', 'configurationglpiauto'), 'recurring' => __('Tickets récurrents', 'configurationglpiauto'),
            'country' => __('Jours fériés', 'configurationglpiauto'), 'vip' => __('Groupe VIP', 'configurationglpiauto'), 'tag' => __('Étiquettes', 'configurationglpiauto'), 'fire' => __('Sécurité incendie', 'configurationglpiauto'),
            'physical' => __('Sécurité physique', 'configurationglpiauto'), 'general' => __('Réglages généraux', 'configurationglpiauto'), 'financial' => __('Informations financières', 'configurationglpiauto'),
            'inventory' => __('Inventaire', 'configurationglpiauto'), 'request' => __('Types de requête', 'configurationglpiauto'),
        ];
    }

    public static function for(string $field): string
    {
        $prefix = substr($field, 0, strpos($field, '_') ?: strlen($field));

        return self::labels()[$prefix] ?? ucfirst($prefix);
    }
}
