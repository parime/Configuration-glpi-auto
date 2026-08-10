<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Parime
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

use DropdownTranslation;
use ITILCategory;

/**
 * Turns a Config's category settings into a real topical ITILCategory tree (up to 3 levels —
 * `itilcategories_id` already supports arbitrary parent/child nesting, same as Entity). Replaces
 * an earlier version (Sprint 16) that created one category per ITIL ticket type
 * (Incident/Demande/Problème/Changement) — dropped after user feedback: `Ticket` already has a
 * native `type` field for Incident/Demande, and Problem/Change are already their own GLPI object
 * types, so a category-per-type never added anything. This tree is topical instead (IT, Bâtiment,
 * Flotte, RH...), each of the 11 top-level branches independently selectable so an organization
 * without a vehicle fleet or industrial maintenance doesn't end up with those branches.
 *
 * Every category gets all 4 `is_incident`/`is_request`/`is_problem`/`is_change` flags set — the
 * category doesn't decide which ticket type it's usable for, that's an orthogonal, native concern.
 *
 * Icons (optional, `category_icons_enabled`) follow the exact same rule established for State
 * (Sprint 16): only on nodes the user actually gave an emoji for (top two levels here — the
 * bullet-only leaf items were never given one), stored as a `DropdownTranslation` (fr_FR, field
 * `name`) since GLPI renders that value as escaped plain text, never on the `name` field itself.
 * Parenthetical text in the user's original list (e.g. "Accessoires (Dock USB-C, Webcam...)") is
 * explicitly *not* a further tree level — confirmed with the user it's example/guidance text for
 * the admin, so it becomes each node's `comment` instead.
 */
class CategoryBuilder
{
    private const CATEGORIES = [
        ['key' => 'it', 'icon' => '💻', 'name' => 'IT & SI', 'children' => [
            ['icon' => '🖥️', 'name' => 'Poste de travail', 'children' => [
                ['name' => 'Ordinateur fixe'],
                ['name' => 'Portable'],
                ['name' => 'Station de travail'],
                ['name' => 'Écran & Affichage'],
                ['name' => 'Accessoires', 'comment' => 'Dock USB-C, Webcam, Casque, Clavier, Souris, Batterie'],
            ]],
            ['icon' => '🖨️', 'name' => 'Impression', 'children' => [
                ['name' => 'Imprimante / Copieur'],
                ['name' => 'Scanner'],
                ['name' => 'Incidents matériel', 'comment' => 'Bourrage, Toner'],
            ]],
            ['icon' => '📦', 'name' => 'Logiciels & Applications', 'children' => [
                ['name' => 'Installation / Désinstallation'],
                ['name' => 'Mise à jour & Patch'],
                ['name' => 'Licences & Clés'],
                ['name' => 'Bug / Dysfonctionnement'],
            ]],
            ['icon' => '🟧', 'name' => 'Microsoft 365 / Workspace', 'children' => [
                ['name' => 'Messagerie', 'comment' => 'Outlook'],
                ['name' => 'Collaboration', 'comment' => 'Teams, SharePoint, OneDrive'],
                ['name' => 'Bureautique', 'comment' => 'Excel, Word, PowerPoint'],
                ['name' => 'Business Intelligence', 'comment' => 'Power BI'],
            ]],
            ['icon' => '👤', 'name' => 'Comptes & Identités', 'comment' => 'IAM', 'children' => [
                ['name' => 'Onboarding / Création de compte'],
                ['name' => 'Offboarding / Suppression'],
                ['name' => 'Mot de passe & Réinitialisation'],
                ['name' => 'Authentification forte', 'comment' => 'MFA'],
                ['name' => 'Droits, Rôles & Groupes AD'],
            ]],
            ['icon' => '🌐', 'name' => 'Réseau & Connectivité', 'children' => [
                ['name' => 'Wifi', 'comment' => 'Interne / Visiteur'],
                ['name' => 'Filaire', 'comment' => 'Ethernet / Prise RJ45'],
                ['name' => 'Accès Distant', 'comment' => 'VPN'],
                ['name' => 'Lien Internet & WAN'],
                ['name' => 'Services Réseau', 'comment' => 'DNS, DHCP, IP'],
            ]],
            ['icon' => '📞', 'name' => 'Téléphonie & VoIP', 'children' => [
                ['name' => 'Téléphone fixe / IP'],
                ['name' => 'Smartphone & Flotte mobile'],
                ['name' => 'Carte SIM & Forfaits', 'comment' => 'Portabilité'],
                ['name' => 'Messagerie vocale & SVI'],
            ]],
            ['icon' => '🖥️', 'name' => 'Infrastructure & Serveurs', 'children' => []],
            ['icon' => '💾', 'name' => 'Sauvegardes & Restauration', 'children' => []],
            ['icon' => '📊', 'name' => 'Supervision & Alertes', 'children' => []],
            ['icon' => '🛡️', 'name' => 'Sécurité SI', 'children' => [
                ['name' => 'Antivirus & Endpoint', 'comment' => 'EDR'],
                ['name' => 'Phishing & Mails suspects'],
                ['name' => 'Indisponibilité / Malware / Ransomware'],
                ['name' => 'Compte compromis / Fuite de données'],
                ['name' => 'Certificats SSL & Vulnérabilités'],
            ]],
        ]],
        ['key' => 'batiment', 'icon' => '🏢', 'name' => 'Bâtiment & Moyens Généraux', 'children' => [
            ['icon' => '🌡️', 'name' => 'CVC', 'comment' => 'Chauffage, Ventilation, Climatisation', 'children' => []],
            ['icon' => '⚡', 'name' => 'Électricité & Éclairage', 'children' => []],
            ['icon' => '🚰', 'name' => 'Plomberie & Sanitaires', 'children' => []],
            ['icon' => '🔑', 'name' => 'Serrurerie, Portes & Fenêtres', 'children' => []],
            ['icon' => '🛗', 'name' => 'Ascenseurs & Monte-charges', 'children' => []],
            ['icon' => '🪑', 'name' => 'Mobilier & Aménagement', 'children' => []],
            ['icon' => '📅', 'name' => 'Salles de réunion & Équipements', 'children' => []],
            ['icon' => '🪧', 'name' => 'Signalétique & Affichage', 'children' => []],
            ['icon' => '🧹', 'name' => 'Prestations & Hygiène', 'children' => [
                ['name' => 'Propreté & Nettoyage'],
                ['name' => 'Espaces verts & Extérieurs'],
            ]],
        ]],
        ['key' => 'flotte', 'icon' => '🚗', 'name' => 'Flotte Automobile & Mobilité', 'children' => [
            ['icon' => '🔧', 'name' => 'Entretien & Réparation', 'comment' => 'Révision, Pneus, Freins, Batterie', 'children' => []],
            ['icon' => '💥', 'name' => 'Sinistres & Carrosserie', 'comment' => 'Accident, Dégradations', 'children' => []],
            ['icon' => '⛽', 'name' => 'Carburant & Recharge', 'comment' => 'Cartes, Badges', 'children' => []],
            ['icon' => '📋', 'name' => 'Conformité & Règlements', 'comment' => 'Contrôle technique, Assurance, Carte grise', 'children' => []],
            ['icon' => '🧽', 'name' => 'Lavage & Nettoyage', 'children' => []],
        ]],
        ['key' => 'rh', 'icon' => '👤', 'name' => 'Ressources Humaines', 'children' => [
            ['icon' => '🚀', 'name' => 'Mouvements de personnel', 'comment' => 'Arrivée / Onboarding, Départ / Offboarding, Mutation', 'children' => []],
            ['icon' => '🎓', 'name' => 'Formation & Montée en compétences', 'children' => []],
            ['icon' => '🏖️', 'name' => 'Absences & Congés', 'children' => []],
            ['icon' => '🏠', 'name' => 'Organisation du travail', 'comment' => 'Télétravail, Plannings', 'children' => []],
            ['icon' => '📑', 'name' => 'Administration RH', 'comment' => 'Contrats, Badges RH, Notes de frais', 'children' => []],
        ]],
        ['key' => 'achats', 'icon' => '🛒', 'name' => 'Achats & Logistique', 'children' => [
            ['icon' => '🛍️', 'name' => 'Sourcing & Commande', 'children' => []],
            ['icon' => '🚚', 'name' => 'Réception, Livraison & Expédition', 'children' => []],
            ['icon' => '🔁', 'name' => 'Retours, SAV & Garanties', 'children' => []],
            ['icon' => '📦', 'name' => 'Gestion des Stocks & Inventaire', 'children' => []],
            ['icon' => '🏗️', 'name' => 'Déménagement & Archivage', 'children' => []],
        ]],
        ['key' => 'securite', 'icon' => '🔐', 'name' => 'Sécurité & Protection des Personnes', 'children' => [
            ['icon' => '🪪', 'name' => 'Contrôle d\'Accès & Badges', 'children' => []],
            ['icon' => '📹', 'name' => 'Vidéosurveillance & Alarmes', 'children' => []],
            ['icon' => '🚨', 'name' => 'Gestion des Incidents & Urgences', 'comment' => 'Incendie, Evacuation', 'children' => []],
            ['icon' => '🥽', 'name' => 'Santé & Sécurité au Travail', 'comment' => 'SST, EPI', 'children' => []],
        ]],
        ['key' => 'services_generaux', 'icon' => '🧹', 'name' => 'Services Généraux & Vie au Travail', 'children' => [
            ['icon' => '✏️', 'name' => 'Consommables & Fournitures', 'children' => []],
            ['icon' => '☕', 'name' => 'Pause & Restauration', 'comment' => 'Cuisine, Machine à café, Distributeurs, Eau', 'children' => []],
            ['icon' => '♻️', 'name' => 'RSE & Recyclage', 'comment' => 'Gestion des déchets, Tri', 'children' => []],
        ]],
        ['key' => 'administratif', 'icon' => '📄', 'name' => 'Administratif, Juridique & Finance', 'children' => [
            ['icon' => '💰', 'name' => 'Finance & Comptabilité', 'comment' => 'Factures, Paiements, Budgets, Immobilisations', 'children' => []],
            ['icon' => '⚖️', 'name' => 'Juridique & Contrats', 'comment' => 'Signatures, Assurances, Baux', 'children' => []],
            ['icon' => '📮', 'name' => 'Courrier & Reprographie', 'children' => []],
        ]],
        ['key' => 'communication', 'icon' => '📢', 'name' => 'Communication & Marketing', 'children' => [
            ['icon' => '🌐', 'name' => 'Site Web & Intranet', 'children' => []],
            ['icon' => '📲', 'name' => 'Réseaux Sociaux & Marketing', 'children' => []],
            ['icon' => '🎤', 'name' => 'Événementiel & Affichage', 'children' => []],
        ]],
        ['key' => 'qualite', 'icon' => '📋', 'name' => 'Qualité, QHSE & Conformité', 'children' => [
            ['icon' => '📜', 'name' => 'Normes & Certifications', 'comment' => 'ISO 9001, ISO 27001', 'children' => []],
            ['icon' => '🔍', 'name' => 'Audits & Contrôles', 'children' => []],
            ['icon' => '⚠️', 'name' => 'Non-conformités & Actions', 'comment' => 'Correctives / Préventives', 'children' => []],
        ]],
        ['key' => 'maintenance', 'icon' => '⚙️', 'name' => 'Maintenance Industrielle & Technique', 'children' => [
            ['icon' => '🛠️', 'name' => 'Maintenance Préventive & Contrôles', 'children' => []],
            ['icon' => '🚨', 'name' => 'Maintenance Curative', 'children' => []],
            ['icon' => '📐', 'name' => 'Étalonnage & Métrologie', 'children' => []],
            ['icon' => '🧩', 'name' => 'Pièces Détachées & Intervenants Externe', 'children' => []],
        ]],
    ];

    /**
     * @return int Number of categories created/reused, for the confirmation message.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['category_enabled'])) {
            return 0;
        }

        $branches = $config->getCategoryBranches();
        $withIcons = !empty($config->fields['category_icons_enabled']);

        $count = 0;
        foreach (self::CATEGORIES as $branch) {
            if (!in_array($branch['key'], $branches, true)) {
                continue;
            }
            $count += $this->buildNode($branch, 0, $withIcons);
        }

        return $count;
    }

    /**
     * Exposed so the wizard can render the per-branch checkboxes and a read-only preview before
     * anything is actually created.
     *
     * @return array<int, array{key: string, icon: string, name: string, children: array}>
     */
    public static function getCategoriesPreview(): array
    {
        return self::CATEGORIES;
    }

    private function buildNode(array $node, int $parentId, bool $withIcons): int
    {
        $item = new ITILCategory();
        $crit = ['name' => $node['name'], 'itilcategories_id' => $parentId];
        if (!$item->getFromDBByCrit($crit)) {
            $id = $item->add($crit + [
                'comment' => $node['comment'] ?? '',
                'entities_id' => 0,
                'is_recursive' => 1,
                'is_incident' => 1,
                'is_request' => 1,
                'is_problem' => 1,
                'is_change' => 1,
            ]);
            $item->getFromDB($id);
        }
        $itemId = (int) $item->getID();
        $count = 1;

        if ($withIcons && isset($node['icon'])) {
            $translation = new DropdownTranslation();
            $transCrit = ['itemtype' => ITILCategory::class, 'items_id' => $itemId, 'language' => 'fr_FR', 'field' => 'name'];
            if (!$translation->getFromDBByCrit($transCrit)) {
                $translation->add($transCrit + ['value' => sprintf('%s %s', $node['icon'], $node['name'])]);
            }
        }

        foreach ($node['children'] ?? [] as $child) {
            $count += $this->buildNode($child, $itemId, $withIcons);
        }

        return $count;
    }
}
