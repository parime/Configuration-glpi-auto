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

use ApplianceEnvironment;
use ApplianceType;
use BudgetType;
use CableType;
use CartridgeItemType;
use CertificateType;
use ClusterType;
use ComputerType;
use ConsumableItemType;
use ContactType;
use ContractType;
use DatabaseInstanceType;
use DeviceBatteryType;
use DeviceCaseType;
use DeviceHardDriveType;
use DeviceSensorType;
use DomainType;
use Glpi\SocketModel;
use LineType;
use MonitorType;
use NetworkEquipmentType;
use PassiveDCEquipmentType;
use PDUType;
use PeripheralType;
use PhoneType;
use PrinterType;
use RackType;
use SoftwareCategory;
use SupplierType;
use UserTitle;
use VirtualMachineType;

/**
 * Turns on `asset_types_enabled` into real `*Type` rows (`glpi_computertypes`,
 * `glpi_monitortypes`, `glpi_networkequipmenttypes`, `glpi_peripheraltypes`, `glpi_phonetypes`,
 * `glpi_printertypes`) — all native GLPI, all confirmed empty on a fresh install
 * (`information_schema` row-count check against a real instance). Scoped to the same six hardware
 * asset types already covered by `ManufacturerBuilder`/`FieldUnicityBuilder` (the ones with a
 * meaningful, industry-standard "kind of device" categorization) rather than the full ~30-table
 * native "Types" section — the rest (Rack/PDU/Cluster/DatabaseInstance/DeviceSensor...) need their
 * own per-itemtype content research before seeding, deliberately left for a later, separately
 * scoped pass (see ROADMAP.md) rather than rushed through in one block.
 *
 * Second pass (v0.51.0) added 8 more: Racks/PDU (physical infrastructure, same reasoning as the
 * matching `FieldUnicityBuilder` entries), Certificats (SSL/TLS, signature de code, client,
 * S/MIME — standard PKI use-case categories), Disques durs/Batteries/Boîtiers de composant
 * (`DeviceHardDrive`/`DeviceBattery`/`DeviceCase` — standard, stable hardware taxonomy, not
 * vendor-specific), Câbles (Ethernet/fibre/alimentation/USB — physical media types, not brands),
 * Cartouches d'impression (toner/encre/tambour/kit de maintenance). `Enclosure` (Châssis), also
 * flagged as a candidate in the original audit, turned out to have no `Type` dropdown at all in
 * GLPI core (`glpi_enclosuretypes` doesn't exist — confirmed via a direct `DESCRIBE`, not assumed
 * from the table-name pattern) — dropped from scope entirely, not just deferred.
 * `SoftwareLicenseType` deliberately deferred to a later pass despite being audited: unlike every
 * other table here, it's a `CommonTreeDropdown` (`softwarelicensetypes_id`/`level`/
 * `ancestors_cache`/`completename` columns) — needs the same care already given to
 * `CategoryBuilder`'s tree-building, not a flat `add()` bolted onto this class.
 *
 * Deliberately excluded from this batch despite being in the same native "Types" section:
 * `AgentType` (auto-created by `Agent::handleAgent()` the moment any real inventory agent
 * connects — seeding it ourselves would be redundant, not filling a real gap) and
 * `Assets_AssetType` (tied to a specific `AssetDefinition` a plugin/admin has to create first, not
 * a standalone global dropdown).
 *
 * Third pass added 12 more, closing out every remaining table from the original ~30-table audit
 * except `SoftwareLicenseType` (tree, still deferred) and the two already-excluded ones above:
 * `Appliance`/`Budget`/`Cluster`/`ConsumableItem`/`Contact`/`Contract`/`Domain`/`Line`/
 * `PassiveDCEquipment`/`Supplier`/`VirtualMachine`/`DeviceSensor`. `DeviceGeneric`, also from the
 * original audit, is deliberately still excluded — too generic a bucket for meaningful standard
 * content. `ConsumableItemType`'s content deliberately doesn't repeat `CartridgeItemType`'s
 * toner/ink/drum entries — `Consumable` and `CartridgeItem` are distinct GLPI objects (general
 * consumables vs. printer-supply tracking with its own stock mechanism), so the two type lists
 * stay complementary, not duplicated. `VirtualMachineType` is unrelated to `ServerAssetBuilder`'s
 * own free-text "hyperviseur" custom field — this dropdown feeds GLPI's native,
 * inventory-populated `VirtualMachine` component objects (via `HasVirtualMachineCapacity`), not the
 * custom asset's own field.
 *
 * Fourth pass added `DatabaseInstanceType`, on explicit user request after being deferred in the
 * original audit over a real risk: a naive content list ("MySQL", "PostgreSQL", "Oracle"...) would
 * just duplicate `Manufacturer`/vendor-territory, especially since `DatabaseInstance` already has
 * its own `manufacturers_id` field (confirmed via `DESCRIBE glpi_databaseinstances`) for exactly
 * that. Avoided by categorizing by *engine kind* instead (relational, document, key-value...) — a
 * real, standard DBMS taxonomy, not a product list — also confirmed distinct from
 * `databaseinstancecategories_id` (a separate native dropdown, environment/purpose-scoped:
 * prod/test/dev, not engine kind).
 *
 * Fifth pass added `SocketModel` (`Glpi\SocketModel`, confirmed the FQCN via `find` — not
 * top-level like the others), `UserTitle`, `SoftwareCategory` and `ApplianceEnvironment`, on
 * explicit user request after a real GLPI 11 instance was checked table-by-table (`DESCRIBE`) for
 * each candidate rather than guessing which ones exist: `SocketModel` backs *both* "type" and
 * "modèle" of prise/connectique in GLPI (one table, not two), `UserTitle` is job title
 * ("Titre d'utilisateur" per `getTypeName()`), distinct from `UserCategoryBuilder`'s own
 * contract-type classification. `SoftwareCategory` is the one `CommonTreeDropdown` in this batch
 * (confirmed via `is_subclass_of()`) — `getOrCreate()` handles it as a root-only insert rather than
 * duplicating `CategoryBuilder`'s branch-building logic for a flat list. No native "cable harness"
 * or "application model" dropdown exists in GLPI core (confirmed by table/class search, not
 * assumed) — out of scope for this pass, see the corresponding GitHub issues for why.
 *
 * All 27 `*Type` classes extend `CommonDropdown` (directly or via `CommonType`/`CommonDeviceType`)
 * with no `$can_be_translated` override, so the inherited default (`true`) applies — same icon
 * mechanism as `ManufacturerBuilder`. Six (`RackType`/`PDUType`/`CertificateType`/`ApplianceType`/
 * `DomainType`/`ClusterType`) also carry `entities_id`/`is_recursive` columns (confirmed via
 * `DESCRIBE`) — scoped to the root entity, recursive, same as every other entity-scoped dropdown
 * this plugin creates.
 */
class AssetTypeBuilder
{
    private const ENTITY_SCOPED_ITEMTYPES = [
        RackType::class,
        PDUType::class,
        CertificateType::class,
        ApplianceType::class,
        DomainType::class,
        ClusterType::class,
    ];
    /**
     * @var array<string, array<int, array{name: string, icon: string}>>
     */
    private const TYPES = [
        ComputerType::class => [
            ['name' => 'Ordinateur de bureau', 'icon' => '🖥️'],
            ['name' => 'Ordinateur portable', 'icon' => '💻'],
            ['name' => 'Serveur', 'icon' => '🗄️'],
            ['name' => 'Mini PC', 'icon' => '📦'],
            ['name' => 'Tablette', 'icon' => '📱'],
        ],
        MonitorType::class => [
            ['name' => 'Écran LCD/LED', 'icon' => '🖥️'],
            ['name' => 'Écran tactile', 'icon' => '👆'],
            ['name' => 'Vidéoprojecteur', 'icon' => '📽️'],
        ],
        NetworkEquipmentType::class => [
            ['name' => 'Switch', 'icon' => '🔀'],
            ['name' => 'Routeur', 'icon' => '📡'],
            ['name' => 'Pare-feu', 'icon' => '🛡️'],
            ['name' => 'Point d\'accès Wi-Fi', 'icon' => '📶'],
            ['name' => 'Répartiteur de charge', 'icon' => '⚖️'],
        ],
        PeripheralType::class => [
            ['name' => 'Clavier', 'icon' => '⌨️'],
            ['name' => 'Souris', 'icon' => '🖱️'],
            ['name' => 'Webcam', 'icon' => '📷'],
            ['name' => 'Casque', 'icon' => '🎧'],
            ['name' => 'Station d\'accueil', 'icon' => '🔌'],
            ['name' => 'Scanner', 'icon' => '🖨️'],
            ['name' => 'Disque externe', 'icon' => '💾'],
        ],
        PhoneType::class => [
            ['name' => 'Smartphone', 'icon' => '📱'],
            ['name' => 'Téléphone fixe', 'icon' => '☎️'],
            ['name' => 'Téléphone DECT', 'icon' => '📞'],
            ['name' => 'Softphone', 'icon' => '💬'],
        ],
        PrinterType::class => [
            ['name' => 'Imprimante laser', 'icon' => '🖨️'],
            ['name' => 'Imprimante jet d\'encre', 'icon' => '🖨️'],
            ['name' => 'Multifonction', 'icon' => '📠'],
            ['name' => 'Imprimante d\'étiquettes', 'icon' => '🏷️'],
        ],
        RackType::class => [
            ['name' => 'Rack serveur 19″', 'icon' => '🗄️'],
            ['name' => 'Rack réseau', 'icon' => '🔀'],
            ['name' => 'Rack ouvert', 'icon' => '📐'],
            ['name' => 'Baie murale', 'icon' => '🧱'],
            ['name' => 'Micro-baie (mini-rack)', 'icon' => '📦'],
        ],
        PDUType::class => [
            ['name' => 'Basique', 'icon' => '🔌'],
            ['name' => 'Mesuré (metered)', 'icon' => '📊'],
            ['name' => 'Commuté (switched)', 'icon' => '🔀'],
            ['name' => 'Commuté et mesuré', 'icon' => '⚡'],
        ],
        CertificateType::class => [
            ['name' => 'SSL/TLS', 'icon' => '🔒'],
            ['name' => 'Signature de code', 'icon' => '✍️'],
            ['name' => 'Certificat client', 'icon' => '🪪'],
            ['name' => 'S/MIME (messagerie)', 'icon' => '📧'],
        ],
        DeviceHardDriveType::class => [
            ['name' => 'HDD', 'icon' => '💽'],
            ['name' => 'SSD SATA', 'icon' => '💾'],
            ['name' => 'SSD NVMe', 'icon' => '💾'],
            ['name' => 'Hybride (SSHD)', 'icon' => '💿'],
        ],
        DeviceBatteryType::class => [
            ['name' => 'Lithium-ion', 'icon' => '🔋'],
            ['name' => 'Lithium-polymère', 'icon' => '🔋'],
            ['name' => 'NiMH', 'icon' => '🔋'],
            ['name' => 'Plomb-acide', 'icon' => '🔋'],
        ],
        DeviceCaseType::class => [
            ['name' => 'Tour (Tower)', 'icon' => '🖥️'],
            ['name' => 'Rack-mount', 'icon' => '🗄️'],
            ['name' => 'Format compact (SFF)', 'icon' => '📦'],
            ['name' => 'Tout-en-un (AIO)', 'icon' => '🖥️'],
        ],
        CableType::class => [
            ['name' => 'Ethernet Cat3', 'icon' => '🔌'],
            ['name' => 'Ethernet Cat5e', 'icon' => '🔌'],
            ['name' => 'Ethernet Cat6', 'icon' => '🔌'],
            ['name' => 'Ethernet Cat6a', 'icon' => '🔌'],
            ['name' => 'Ethernet Cat7', 'icon' => '🔌'],
            ['name' => 'Coaxial', 'icon' => '🔌'],
            ['name' => 'Fibre optique monomode (OS2)', 'icon' => '💡'],
            ['name' => 'Fibre optique multimode OM3', 'icon' => '💡'],
            ['name' => 'Fibre optique multimode OM4', 'icon' => '💡'],
            ['name' => 'Alimentation', 'icon' => '⚡'],
            ['name' => 'USB', 'icon' => '🔌'],
        ],
        CartridgeItemType::class => [
            ['name' => 'Toner', 'icon' => '🖨️'],
            ['name' => 'Cartouche d\'encre', 'icon' => '🖋️'],
            ['name' => 'Tambour (drum)', 'icon' => '🥁'],
            ['name' => 'Kit de maintenance', 'icon' => '🧰'],
        ],
        ApplianceType::class => [
            ['name' => 'Pare-feu', 'icon' => '🛡️'],
            ['name' => 'Répartiteur de charge', 'icon' => '⚖️'],
            ['name' => 'Sauvegarde', 'icon' => '💾'],
            ['name' => 'Supervision', 'icon' => '📊'],
            ['name' => 'Stockage (NAS/SAN)', 'icon' => '🗄️'],
            ['name' => 'VPN', 'icon' => '🔐'],
            ['name' => 'Proxy', 'icon' => '🔀'],
        ],
        BudgetType::class => [
            ['name' => 'Investissement (CAPEX)', 'icon' => '💰'],
            ['name' => 'Fonctionnement (OPEX)', 'icon' => '🔄'],
            ['name' => 'Exceptionnel', 'icon' => '⚡'],
            ['name' => 'Projet', 'icon' => '📁'],
        ],
        ClusterType::class => [
            ['name' => 'Haute disponibilité', 'icon' => '🛡️'],
            ['name' => 'Répartition de charge', 'icon' => '⚖️'],
            ['name' => 'Calcul distribué', 'icon' => '🖥️'],
            ['name' => 'Stockage', 'icon' => '💾'],
        ],
        ConsumableItemType::class => [
            ['name' => 'Papier', 'icon' => '📄'],
            ['name' => 'Pile / Batterie', 'icon' => '🔋'],
            ['name' => 'Câble', 'icon' => '🔌'],
            ['name' => 'Support de stockage (CD/DVD/USB)', 'icon' => '💿'],
            ['name' => 'Badge d\'accès', 'icon' => '🪪'],
            ['name' => 'Fourniture de bureau', 'icon' => '📎'],
        ],
        ContactType::class => [
            ['name' => 'Commercial', 'icon' => '💼'],
            ['name' => 'Technique', 'icon' => '🔧'],
            ['name' => 'Support', 'icon' => '🎧'],
            ['name' => 'Direction', 'icon' => '👔'],
            ['name' => 'Comptabilité', 'icon' => '💰'],
            ['name' => 'Juridique', 'icon' => '⚖️'],
        ],
        ContractType::class => [
            ['name' => 'Maintenance', 'icon' => '🔧'],
            ['name' => 'Location', 'icon' => '📋'],
            ['name' => 'Assurance', 'icon' => '🛡️'],
            ['name' => 'Support', 'icon' => '☎️'],
            ['name' => 'Garantie', 'icon' => '✅'],
            ['name' => 'Prestation de services', 'icon' => '🤝'],
            ['name' => 'Abonnement', 'icon' => '🔄'],
        ],
        DomainType::class => [
            ['name' => 'Nom de domaine internet', 'icon' => '🌐'],
            ['name' => 'Sous-domaine', 'icon' => '🔗'],
            ['name' => 'Domaine interne (annuaire)', 'icon' => '🏢'],
            ['name' => 'Domaine technique (DNS/certificat)', 'icon' => '🔒'],
        ],
        LineType::class => [
            ['name' => 'Fixe', 'icon' => '☎️'],
            ['name' => 'Mobile', 'icon' => '📱'],
            ['name' => 'Internet', 'icon' => '🌐'],
            ['name' => 'VoIP', 'icon' => '💬'],
            ['name' => 'Satellite', 'icon' => '📡'],
        ],
        PassiveDCEquipmentType::class => [
            ['name' => 'Panneau de brassage', 'icon' => '🔌'],
            ['name' => 'Baie de brassage', 'icon' => '🗄️'],
            ['name' => 'Goulotte', 'icon' => '📏'],
            ['name' => 'Chemin de câbles', 'icon' => '🛤️'],
            ['name' => 'Armoire technique', 'icon' => '🚪'],
        ],
        SupplierType::class => [
            ['name' => 'Fournisseur matériel', 'icon' => '📦'],
            ['name' => 'Prestataire de services', 'icon' => '🤝'],
            ['name' => 'Opérateur télécom', 'icon' => '📡'],
            ['name' => 'Revendeur', 'icon' => '🏪'],
            ['name' => 'Éditeur logiciel', 'icon' => '💿'],
            ['name' => 'Intégrateur', 'icon' => '🔧'],
        ],
        VirtualMachineType::class => [
            ['name' => 'VMware ESXi', 'icon' => '🖥️'],
            ['name' => 'Microsoft Hyper-V', 'icon' => '🖥️'],
            ['name' => 'Proxmox VE', 'icon' => '🖥️'],
            ['name' => 'KVM', 'icon' => '🖥️'],
            ['name' => 'Citrix XenServer', 'icon' => '🖥️'],
            ['name' => 'VirtualBox', 'icon' => '📦'],
            ['name' => 'Docker', 'icon' => '🐳'],
            ['name' => 'LXC', 'icon' => '📦'],
        ],
        DeviceSensorType::class => [
            ['name' => 'Température', 'icon' => '🌡️'],
            ['name' => 'Humidité', 'icon' => '💧'],
            ['name' => 'Fumée', 'icon' => '🔥'],
            ['name' => 'Mouvement', 'icon' => '🚶'],
            ['name' => 'Ouverture de porte', 'icon' => '🚪'],
            ['name' => 'Fuite d\'eau', 'icon' => '💧'],
            ['name' => 'Vibration', 'icon' => '📳'],
        ],
        DatabaseInstanceType::class => [
            ['name' => 'Relationnelle', 'icon' => '🗃️'],
            ['name' => 'Document/NoSQL', 'icon' => '📄'],
            ['name' => 'Clé-valeur', 'icon' => '🔑'],
            ['name' => 'Colonne large', 'icon' => '📊'],
            ['name' => 'Graphe', 'icon' => '🕸️'],
            ['name' => 'Séries temporelles', 'icon' => '⏱️'],
            ['name' => 'Recherche/index', 'icon' => '🔎'],
            ['name' => 'Cache en mémoire', 'icon' => '⚡'],
            ['name' => 'Base vectorielle (IA/RAG)', 'icon' => '🧠'],
        ],

        // Prise/connectique physique (RJ45, fibre, USB, alimentation...) — même table
        // `glpi_socketmodels` derriere le "type" et le "modele" de prise dans GLPI (pas deux
        // referentiels distincts), confirmee via DESCRIBE avant d'ecrire ce contenu.
        SocketModel::class => [
            ['name' => 'RJ45', 'icon' => '🔌'],
            ['name' => 'Fibre LC', 'icon' => '💡'],
            ['name' => 'Fibre SC', 'icon' => '💡'],
            ['name' => 'USB-A', 'icon' => '🔌'],
            ['name' => 'USB-C', 'icon' => '🔌'],
            ['name' => 'Prise électrique (secteur)', 'icon' => '⚡'],
            ['name' => 'HDMI', 'icon' => '📺'],
            ['name' => 'DisplayPort', 'icon' => '📺'],
            ['name' => 'Jack audio', 'icon' => '🎧'],
        ],

        // "Titre" = intitule de poste (confirme via getTypeName() : "Titre d'utilisateur"),
        // distinct de UserCategoryBuilder (type de contrat : stagiaire/prestataire/alternant...).
        UserTitle::class => [
            ['name' => 'Directeur / Directrice', 'icon' => '👔'],
            ['name' => 'Responsable', 'icon' => '📋'],
            ['name' => 'Chef de projet', 'icon' => '🗂️'],
            ['name' => 'Ingénieur', 'icon' => '⚙️'],
            ['name' => 'Technicien / Technicienne', 'icon' => '🔧'],
            ['name' => 'Assistant(e)', 'icon' => '📝'],
            ['name' => 'Consultant(e)', 'icon' => '💼'],
        ],

        // Categorie de logiciel — CommonTreeDropdown (confirme via is_subclass_of), pas juste un
        // CommonDropdown comme le reste de cette classe : getOrCreate() gere ce cas a part (racine
        // uniquement, pas de sous-arbre) en detectant CommonTreeDropdown au lieu de dupliquer ici la
        // logique de construction d'arbre deja propre a CategoryBuilder.
        SoftwareCategory::class => [
            ['name' => 'Bureautique', 'icon' => '📄'],
            ['name' => 'Sécurité', 'icon' => '🔒'],
            ['name' => 'Développement', 'icon' => '💻'],
            ['name' => 'Conception / CAO', 'icon' => '📐'],
            ['name' => 'Communication / Collaboration', 'icon' => '💬'],
            ['name' => 'Gestion / ERP', 'icon' => '📊'],
            ['name' => 'Navigateur', 'icon' => '🌐'],
            ['name' => 'Utilitaire système', 'icon' => '🛠️'],
            ['name' => 'Multimédia', 'icon' => '🎬'],
            ['name' => 'Métier', 'icon' => '🏢'],
        ],

        // Environnement d'execution (Production/Test/Dev...) — seul referentiel natif GLPI portant
        // cette semantique (rattache aux Appliances), reutilise ici au sens large plutot que
        // d'inventer un nouveau referentiel hors GLPI pour un besoin equivalent.
        ApplianceEnvironment::class => [
            ['name' => 'Production', 'icon' => '🟢'],
            ['name' => 'Pré-production', 'icon' => '🟠'],
            ['name' => 'Test / Recette', 'icon' => '🧪'],
            ['name' => 'Développement', 'icon' => '💻'],
        ],
    ];

    /**
     * @return int Number of types created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['asset_types_enabled'])) {
            return 0;
        }

        $withIcons = !empty($config->fields['asset_type_icons_enabled']);
        $count = 0;
        foreach (self::TYPES as $itemtype => $types) {
            foreach ($types as $type) {
                $id = $this->getOrCreate($itemtype, $type['name']);
                if ($withIcons) {
                    Translations::applyIcon($itemtype, $id, $type['name'], $type['icon']);
                }
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, array<int, array{name: string, icon: string}>>
     */
    public static function getTypesPreview(): array
    {
        return self::TYPES;
    }

    private function getOrCreate(string $itemtype, string $name): int
    {
        $isEntityScoped = in_array($itemtype, self::ENTITY_SCOPED_ITEMTYPES, true);
        // SoftwareCategory is the one CommonTreeDropdown in this class (confirmed via
        // is_subclass_of() before writing this) — every entry here is seeded as a root node (no
        // sub-tree), so the only extra requirement is its own foreign key set to 0 on add(), same
        // "root parent" convention CommonTreeDropdown itself expects.
        $isTreeDropdown = is_subclass_of($itemtype, \CommonTreeDropdown::class);

        $item = new $itemtype();
        $crit = $isEntityScoped ? ['name' => $name, 'entities_id' => 0] : ['name' => $name];
        if ($item->getFromDBByCrit($crit)) {
            return (int) $item->getID();
        }

        $input = ['name' => $name];
        if ($isEntityScoped) {
            $input['entities_id'] = 0;
            $input['is_recursive'] = 1;
        }
        if ($isTreeDropdown) {
            $input[$itemtype::getForeignKeyField()] = 0;
        }

        return (int) $item->add($input);
    }
}
