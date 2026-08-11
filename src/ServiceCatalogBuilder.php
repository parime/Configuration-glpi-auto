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

use Glpi\Form\Category as FormCategory;
use Glpi\Form\Destination\CommonITILField\ITILCategoryField;
use Glpi\Form\Destination\CommonITILField\ITILCategoryFieldConfig;
use Glpi\Form\Destination\CommonITILField\ITILCategoryFieldStrategy;
use Glpi\Form\Destination\FormDestination;
use Glpi\Form\Destination\FormDestinationTicket;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\QuestionType\QuestionTypeLongText;
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Form\Section;
use ITILCategory;

/**
 * Builds a self-service Service Catalog (GLPI 11's native `Glpi\Form\Form`/`Question` system —
 * confirmed a completely separate mechanism from `TicketTemplate`/`ITILCategory`, see
 * `HelpdeskFormBuilder`'s docblock) out of `CategoryBuilder`'s already-built category tree, one
 * catalog section per selected top-level branch.
 *
 * Each service is a minimal form (Title + Description only, same "enter the least possible"
 * philosophy as `HelpdeskFormBuilder`) that routes its resulting ticket to a *fixed* pre-existing
 * `ITILCategory` — the user picks a specific service, never a category. Wired through
 * `FormDestinationTicket`'s `ITILCategoryFieldConfig` with the `SPECIFIC_VALUE` strategy
 * (confirmed via source: the alternative `LAST_VALID_ANSWER` strategy reads from a "Category"-type
 * question instead, which these forms deliberately don't have).
 *
 * `Form::add()` already creates a first `Section` and a default `FormDestination` on its own
 * (`Form::post_addItem()` → `createFirstSection()` / `addDefaultDestinations()`) — reused here
 * instead of creating them by hand.
 */
class ServiceCatalogBuilder
{
    /**
     * Flat list: 'branch' must match a `CategoryBuilder::CATEGORIES` key, 'path' is the chain of
     * names to walk from that branch's root to the target `ITILCategory` (1 or 2 levels). A
     * service whose branch isn't selected, or whose target category can't be resolved (branch
     * deselected, category renamed), is skipped rather than created without a category.
     */
    private const SERVICES = [
        // IT & SI
        ['branch' => 'it', 'name' => "Installation ou mise à jour d'un logiciel", 'path' => ['Logiciels & Applications', 'Installation / Désinstallation']],
        ['branch' => 'it', 'name' => 'Demande de licence logicielle', 'path' => ['Logiciels & Applications', 'Licences & Clés']],
        ['branch' => 'it', 'name' => 'Signaler un bug ou dysfonctionnement logiciel', 'path' => ['Logiciels & Applications', 'Bug / Dysfonctionnement']],
        ['branch' => 'it', 'name' => "Création d'un compte utilisateur", 'path' => ['Comptes & Identités', 'Onboarding / Création de compte']],
        ['branch' => 'it', 'name' => 'Réinitialisation de mot de passe', 'path' => ['Comptes & Identités', 'Mot de passe & Réinitialisation']],
        ['branch' => 'it', 'name' => "Désactivation d'un compte utilisateur", 'path' => ['Comptes & Identités', 'Offboarding / Suppression']],
        ['branch' => 'it', 'name' => "Demande d'accès VPN", 'path' => ['Réseau & Connectivité', 'Accès Distant']],
        ['branch' => 'it', 'name' => "Demande d'accès Wifi", 'path' => ['Réseau & Connectivité', 'Wifi']],
        ['branch' => 'it', 'name' => "Demande d'un nouvel écran", 'path' => ['Poste de travail', 'Écran & Affichage']],
        ['branch' => 'it', 'name' => "Demande d'un ordinateur portable", 'path' => ['Poste de travail', 'Portable']],
        ['branch' => 'it', 'name' => 'Demande de téléphone professionnel', 'path' => ['Téléphonie & VoIP', 'Smartphone & Flotte mobile']],
        ['branch' => 'it', 'name' => "Demande de boîte mail ou d'alias", 'path' => ['Messagerie & Collaboration', 'Messagerie']],
        ['branch' => 'it', 'name' => "Demande d'accès à un espace collaboratif d'équipe", 'path' => ['Messagerie & Collaboration', 'Collaboration']],
        // Bâtiment & Moyens Généraux
        ['branch' => 'batiment', 'name' => 'Signaler un problème de chauffage ou climatisation', 'path' => ['CVC']],
        ['branch' => 'batiment', 'name' => "Demande d'intervention électricité", 'path' => ['Électricité & Éclairage']],
        ['branch' => 'batiment', 'name' => "Demande de mobilier ou d'aménagement de poste", 'path' => ['Mobilier & Aménagement']],
        // Flotte Automobile & Mobilité
        ['branch' => 'flotte', 'name' => "Demande d'entretien véhicule", 'path' => ['Entretien & Réparation']],
        // Ressources Humaines
        ['branch' => 'rh', 'name' => 'Demande de formation', 'path' => ['Formation & Montée en compétences']],
        ['branch' => 'rh', 'name' => 'Demande de congé ou absence', 'path' => ['Absences & Congés']],
        // Achats & Logistique
        ['branch' => 'achats', 'name' => "Demande d'achat ou commande de fournitures", 'path' => ['Sourcing & Commande']],
        ['branch' => 'achats', 'name' => "Retour ou SAV d'un article", 'path' => ['Retours, SAV & Garanties']],
        // Sécurité & Protection des Personnes
        ['branch' => 'securite', 'name' => "Demande de badge d'accès", 'path' => ["Contrôle d'Accès & Badges"]],
        // Services Généraux & Vie au Travail
        ['branch' => 'services_generaux', 'name' => 'Demande de fournitures de bureau', 'path' => ['Consommables & Fournitures']],
    ];

    // GLPI's own bundled illustration catalog (`public/lib/glpi-project/illustrations/icons.json`,
    // resolved via `Glpi\UI\IllustrationManager` — no custom SVG import needed, these ship with
    // core already) — one recognizable icon per branch instead of the generic default
    // ("request-service") every rubric/form falls back to when `illustration` is left unset.
    private const BRANCH_ILLUSTRATIONS = [
        'it' => 'asset-desktop-1',
        'batiment' => 'building',
        'flotte' => 'car',
        'rh' => 'group',
        'achats' => 'order-supplies',
        'securite' => 'security',
        'services_generaux' => 'inventory',
        'administratif' => 'legal',
        'communication' => 'presentation',
        'qualite' => 'diagnostic',
        'maintenance' => 'factory',
    ];

    /**
     * @return int Number of service forms created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['service_catalog_enabled'])) {
            return 0;
        }

        $branches = $config->getCategoryBranches();
        $branchLookup = [];
        foreach (CategoryBuilder::getCategoriesPreview() as $branch) {
            $branchLookup[$branch['key']] = $branch;
        }

        $formCategoryIds = [];
        foreach ($branches as $key) {
            if (isset($branchLookup[$key])) {
                $formCategoryIds[$key] = $this->getOrCreateFormCategory($branchLookup[$key], self::BRANCH_ILLUSTRATIONS[$key] ?? '');
            }
        }

        $count = 0;
        foreach (self::SERVICES as $service) {
            if (!isset($formCategoryIds[$service['branch']])) {
                continue;
            }
            $itilCategoryId = $this->resolveItilCategoryId($branchLookup[$service['branch']], $service['path']);
            if ($itilCategoryId === null) {
                continue;
            }
            $illustration = self::BRANCH_ILLUSTRATIONS[$service['branch']] ?? '';
            $this->getOrCreateServiceForm($service['name'], $formCategoryIds[$service['branch']], $itilCategoryId, $illustration);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array{branch: string, name: string}> Preview grouped by branch key, for
     *         the wizard's read-only listing.
     */
    public static function getServicesPreview(): array
    {
        return self::SERVICES;
    }

    private function getOrCreateFormCategory(array $branch, string $illustration): int
    {
        $item = new FormCategory();
        if (!$item->getFromDBByCrit(['name' => $branch['icon'] . ' ' . $branch['name'], 'forms_categories_id' => 0])) {
            $id = $item->add([
                'name' => $branch['icon'] . ' ' . $branch['name'],
                'forms_categories_id' => 0,
                'illustration' => $illustration,
            ]);
            $item->getFromDB($id);
        }

        return (int) $item->getID();
    }

    /**
     * Walks $path (1 or 2 names) starting from $branch's own root `ITILCategory` — the same tree
     * `CategoryBuilder` already built. Returns null (skip the service) if any step isn't found.
     */
    private function resolveItilCategoryId(array $branch, array $path): ?int
    {
        $item = new ITILCategory();
        if (!$item->getFromDBByCrit(['name' => $branch['name'], 'itilcategories_id' => 0])) {
            return null;
        }
        $parentId = (int) $item->getID();

        foreach ($path as $name) {
            if (!$item->getFromDBByCrit(['name' => $name, 'itilcategories_id' => $parentId])) {
                return null;
            }
            $parentId = (int) $item->getID();
        }

        return $parentId;
    }

    private function getOrCreateServiceForm(string $name, int $formCategoryId, int $itilCategoryId, string $illustration): void
    {
        $form = new Form();
        if (!$form->getFromDBByCrit(['name' => $name, 'forms_categories_id' => $formCategoryId])) {
            $formId = $form->add([
                'name' => $name,
                'forms_categories_id' => $formCategoryId,
                'entities_id' => 0,
                'is_recursive' => 1,
                'is_active' => 1,
                'illustration' => $illustration,
            ]);
            $form->getFromDB($formId);
            $this->addQuestions((int) $form->getID());
            $this->configureDestinationCategory((int) $form->getID(), $itilCategoryId);
        }
    }

    private function addQuestions(int $formId): void
    {
        $section = new Section();
        if (!$section->getFromDBByCrit(['forms_forms_id' => $formId])) {
            return;
        }
        $sectionId = (int) $section->getID();

        $title = new Question();
        $title->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Titre', 'configurationglpiauto'),
            'type' => QuestionTypeShortText::class,
            'is_mandatory' => 0,
            'vertical_rank' => 0,
        ]);

        $description = new Question();
        $description->add([
            'forms_sections_id' => $sectionId,
            'name' => __('Description', 'configurationglpiauto'),
            'type' => QuestionTypeLongText::class,
            'is_mandatory' => 1,
            'vertical_rank' => 1,
        ]);
    }

    private function configureDestinationCategory(int $formId, int $itilCategoryId): void
    {
        $destination = new FormDestination();
        if (!$destination->getFromDBByCrit(['forms_forms_id' => $formId, 'itemtype' => FormDestinationTicket::class])) {
            return;
        }

        $config = [
            ITILCategoryField::getKey() => (new ITILCategoryFieldConfig(
                strategy: ITILCategoryFieldStrategy::SPECIFIC_VALUE,
                specific_itilcategory_id: $itilCategoryId,
            ))->jsonSerialize(),
        ];

        $destination->update([
            'id' => $destination->getID(),
            'config' => json_encode($config),
        ]);
    }
}
