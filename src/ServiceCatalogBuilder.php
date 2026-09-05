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
 *
 * **Issue #207 triage, generalized catalog-wide** : entries flagged `'smart' => true` below are
 * deliberately *not* built by this class's own loop (see the `smart` check in `build()`). The pilot
 * (`AbroadMissionFormBuilder`, issue #208) and the first 6-service wave (#207/#210) initially kept
 * ~44 other entries plain on the theory that most of the catalog is "signaler/demander X"
 * report-style tickets where free text already says everything a structured field would. The
 * plugin owner explicitly revisited that call as too conservative and asked for the exercise to be
 * redone catalog-wide with a real per-service judgment call each time, biased toward "what
 * structured field would make this ticket more actionable" rather than "does free text technically
 * suffice" — resulting in 42 more of those ~44 getting the same treatment (own dedicated builder,
 * real typed/conditional questions, computed ticket title via `Glpi\Form\Tag\AnswerTagProvider` /
 * `FormTagProvider`, never hand-written markup), for a total of 48 smart services out of 50. Only
 * two stayed deliberately plain, each for a reason specific to it rather than a blanket rule:
 * "Demande administrative RH" is the RH branch's own intentional catch-all sitting alongside 4 other
 * well-defined RH services (formation, congés, mouvements de personnel, télétravail) that already
 * partition the well-known cases — a "type" field here would either duplicate those or need an
 * ever-growing option list to stay honest, so free text is the more honest fit for a deliberately
 * open-ended residual category. "Signaler un incident ou une urgence sécurité" is a time-critical
 * emergency report where minimizing friction to submit fast matters more than structured intake;
 * real triage happens by phone/human contact right after submission regardless of what the form
 * asked. The entry stays in `SERVICES` (rather than being deleted) purely so the wizard's per-branch
 * preview list keeps mentioning the service by name; `smart_title`/`smart_conditional` flag which
 * mechanism it actually got, for that same preview to badge accordingly.
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
        ['branch' => 'it', 'name' => "Installation ou mise à jour d'un logiciel", 'path' => ['Logiciels & Applications', 'Installation / Désinstallation'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'it', 'name' => 'Demande de licence logicielle', 'path' => ['Logiciels & Applications', 'Licences & Clés'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'it', 'name' => 'Signaler un bug ou dysfonctionnement logiciel', 'path' => ['Logiciels & Applications', 'Bug / Dysfonctionnement'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'it', 'name' => "Création d'un compte utilisateur", 'path' => ['Comptes & Identités', 'Onboarding / Création de compte'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'it', 'name' => 'Réinitialisation de mot de passe', 'path' => ['Comptes & Identités', 'Mot de passe & Réinitialisation'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'it', 'name' => "Désactivation d'un compte utilisateur", 'path' => ['Comptes & Identités', 'Offboarding / Suppression'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'it', 'name' => "Demande d'accès VPN", 'path' => ['Réseau & Connectivité', 'Accès Distant'], 'smart' => true, 'smart_title' => true, 'smart_conditional' => true],
        ['branch' => 'it', 'name' => "Demande d'accès Wifi", 'path' => ['Réseau & Connectivité', 'Wifi'], 'smart' => true, 'smart_title' => true, 'smart_conditional' => true],
        ['branch' => 'it', 'name' => "Demande d'un nouvel écran", 'path' => ['Poste de travail', 'Écran & Affichage'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'it', 'name' => "Demande d'un ordinateur portable", 'path' => ['Poste de travail', 'Portable'], 'smart' => true, 'smart_title' => true, 'smart_conditional' => true],
        ['branch' => 'it', 'name' => 'Demande de téléphone professionnel', 'path' => ['Téléphonie & VoIP', 'Smartphone & Flotte mobile'], 'smart' => true, 'smart_title' => true, 'smart_conditional' => true],
        ['branch' => 'it', 'name' => "Demande de boîte mail ou d'alias", 'path' => ['Messagerie & Collaboration', 'Messagerie'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'it', 'name' => "Demande d'accès à un espace collaboratif d'équipe", 'path' => ['Messagerie & Collaboration', 'Collaboration'], 'smart' => true, 'smart_title' => true],
        // Bâtiment & Moyens Généraux
        ['branch' => 'batiment', 'name' => 'Signaler un problème de chauffage ou climatisation', 'path' => ['CVC'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'batiment', 'name' => "Demande d'intervention électricité", 'path' => ['Électricité & Éclairage'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'batiment', 'name' => "Demande de mobilier ou d'aménagement de poste", 'path' => ['Mobilier & Aménagement'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'batiment', 'name' => "Signaler une fuite d'eau ou un problème sanitaire", 'path' => ['Plomberie & Sanitaires'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'batiment', 'name' => 'Problème de porte, serrure ou badge de local', 'path' => ['Serrurerie, Portes & Fenêtres'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'batiment', 'name' => "Réservation ou problème d'équipement de salle de réunion", 'path' => ['Salles de réunion & Équipements'], 'smart' => true, 'smart_conditional' => true],
        ['branch' => 'batiment', 'name' => 'Signaler un problème de propreté ou demander un nettoyage', 'path' => ['Prestations & Hygiène', 'Propreté & Nettoyage'], 'smart' => true, 'smart_title' => true],
        // Flotte Automobile & Mobilité
        ['branch' => 'flotte', 'name' => "Demande d'entretien véhicule", 'path' => ['Entretien & Réparation'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'flotte', 'name' => 'Déclarer un sinistre ou un dommage véhicule', 'path' => ['Sinistres & Carrosserie'], 'smart' => true, 'smart_title' => true, 'smart_conditional' => true],
        ['branch' => 'flotte', 'name' => 'Demande de carte carburant ou badge de recharge', 'path' => ['Carburant & Recharge'], 'smart' => true, 'smart_title' => true],
        // Ressources Humaines
        ['branch' => 'rh', 'name' => 'Demande de formation', 'path' => ['Formation & Montée en compétences'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'rh', 'name' => 'Demande de congé ou absence', 'path' => ['Absences & Congés'], 'smart' => true, 'smart_title' => true, 'smart_conditional' => true],
        ['branch' => 'rh', 'name' => "Déclarer une arrivée, un départ ou une mutation", 'path' => ['Mouvements de personnel'], 'smart' => true, 'smart_title' => true, 'smart_conditional' => true],
        ['branch' => 'rh', 'name' => "Demande de télétravail ou d'aménagement du temps de travail", 'path' => ['Organisation du travail'], 'smart' => true, 'smart_title' => true, 'smart_conditional' => true],
        // Deliberately left plain (see class docblock): RH branch's own intentional catch-all,
        // sitting alongside 4 other well-defined RH services that already partition the well-known
        // cases.
        ['branch' => 'rh', 'name' => 'Demande administrative RH', 'path' => ['Administration RH']],
        // Achats & Logistique
        ['branch' => 'achats', 'name' => "Demande d'achat ou commande de fournitures", 'path' => ['Sourcing & Commande'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'achats', 'name' => "Retour ou SAV d'un article", 'path' => ['Retours, SAV & Garanties'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'achats', 'name' => 'Suivi de réception ou de livraison', 'path' => ['Réception, Livraison & Expédition'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'achats', 'name' => "Demande de déménagement ou d'archivage", 'path' => ['Déménagement & Archivage'], 'smart' => true, 'smart_title' => true],
        // Sécurité & Protection des Personnes
        ['branch' => 'securite', 'name' => "Demande de badge d'accès", 'path' => ["Contrôle d'Accès & Badges"], 'smart' => true, 'smart_title' => true],
        ['branch' => 'securite', 'name' => 'Signaler un dysfonctionnement vidéosurveillance ou alarme', 'path' => ['Vidéosurveillance & Alarmes'], 'smart' => true, 'smart_title' => true],
        // Deliberately left plain (see class docblock): time-critical emergency report where
        // minimizing friction to submit fast matters more than structured intake.
        ['branch' => 'securite', 'name' => 'Signaler un incident ou une urgence sécurité', 'path' => ['Gestion des Incidents & Urgences']],
        ['branch' => 'securite', 'name' => 'Demande liée à la santé et sécurité au travail', 'path' => ['Santé & Sécurité au Travail'], 'smart' => true, 'smart_title' => true],
        // Services Généraux & Vie au Travail
        ['branch' => 'services_generaux', 'name' => 'Demande de fournitures de bureau', 'path' => ['Consommables & Fournitures'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'services_generaux', 'name' => 'Signaler un problème lié à la pause ou à la restauration', 'path' => ['Pause & Restauration'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'services_generaux', 'name' => 'Demande liée au tri ou au recyclage', 'path' => ['RSE & Recyclage'], 'smart' => true, 'smart_title' => true],
        // Administratif, Juridique & Finance
        ['branch' => 'administratif', 'name' => 'Demande liée à une facture ou un paiement', 'path' => ['Finance & Comptabilité'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'administratif', 'name' => 'Demande de relecture ou signature de contrat', 'path' => ['Juridique & Contrats'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'administratif', 'name' => 'Envoi de courrier ou demande de reprographie', 'path' => ['Courrier & Reprographie'], 'smart' => true, 'smart_title' => true, 'smart_conditional' => true],
        // Communication & Marketing
        ['branch' => 'communication', 'name' => 'Demande de modification sur le site web ou intranet', 'path' => ['Site Web & Intranet'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'communication', 'name' => 'Demande de publication sur les réseaux sociaux', 'path' => ['Réseaux Sociaux & Marketing'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'communication', 'name' => 'Demande de support pour un événement', 'path' => ['Événementiel & Affichage'], 'smart' => true, 'smart_title' => true],
        // Qualité, QHSE & Conformité
        ['branch' => 'qualite', 'name' => 'Déclarer une non-conformité', 'path' => ['Non-conformités & Actions'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'qualite', 'name' => 'Demande liée à un audit ou un contrôle', 'path' => ['Audits & Contrôles'], 'smart' => true, 'smart_title' => true],
        // Maintenance Industrielle & Technique
        ['branch' => 'maintenance', 'name' => 'Demande de maintenance préventive', 'path' => ['Maintenance Préventive & Contrôles'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'maintenance', 'name' => 'Signaler une panne ou un besoin de maintenance curative', 'path' => ['Maintenance Curative'], 'smart' => true, 'smart_title' => true],
        ['branch' => 'maintenance', 'name' => "Demande d'étalonnage d'un instrument", 'path' => ['Étalonnage & Métrologie'], 'smart' => true, 'smart_title' => true],
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
            if (!empty($service['smart'])) {
                // Built by its own dedicated builder instead (see class docblock), kept in this
                // list only so the wizard preview still mentions it.
                continue;
            }
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
