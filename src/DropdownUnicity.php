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

use CommonDBTM;
use ComputerModel;
use ComputerType;
use Manufacturer;
use MonitorModel;
use MonitorType;
use NetworkEquipmentModel;
use NetworkEquipmentType;
use OperatingSystem;
use OperatingSystemArchitecture;
use OperatingSystemEdition;
use OperatingSystemServicePack;
use OperatingSystemVersion;
use PeripheralModel;
use PeripheralType;
use PhoneModel;
use PhoneType;
use PrinterModel;
use PrinterType;
use Session;
use Software;

/**
 * Issue #298 : refuse la création (ou le renommage) d'un fabricant / éditeur, logiciel, modèle,
 * type ou système d'exploitation qui porte le nom d'une entrée existante. GLPI n'a pas d'unicité
 * pour ses listes déroulantes : saisie manuelle, import CSV, API ou autre plugin pouvaient créer
 * dix « Lenovo ». L'éditeur d'un logiciel est un `Manufacturer` : même liste, même contrôle.
 *
 * « Même nom » = ce que la base considère comme égal (collation `utf8mb4_unicode_ci` : majuscules,
 * accents et espaces finaux ignorés), après réduction des espaces multiples. C'est volontairement
 * le critère de l'inventaire (`CommonDropdown::findID()`, appelé avant toute création ; les
 * logiciels sont insérés par l'inventaire sans passer par `add()`) : un inventaire n'est jamais
 * bloqué. Les variantes plus éloignées (« Lenovo » / « Lenovo Inc. ») relèvent du dictionnaire
 * (#297) et du contrôle d'audit (#299).
 *
 * Un logiciel est identifié par son nom ET son éditeur, comme le fait GLPI lui-même
 * (`Software::addOrRestoreFromTrash()`, rejeu du dictionnaire des logiciels) : ces chemins du
 * cœur cherchent un logiciel nom + éditeur avant d'en créer un, ils ne sont donc jamais bloqués.
 *
 * Les logiciels sont rattachés à une entité : deux entités clientes peuvent chacune avoir leur
 * « Firefox ». Un doublon est donc un logiciel du même nom dans la même entité, ou récursif dans
 * une entité parente (il y est déjà visible), ou — si le nouveau est récursif — dans une entité
 * fille. L'« Unicité des champs » native de GLPI ne sait pas faire cette distinction : posée sur
 * l'entité racine, elle compare toutes les entités entre elles.
 */
final class DropdownUnicity
{
    /** @var list<class-string<CommonDBTM>> */
    public const ITEMTYPES = [
        Manufacturer::class,
        Software::class,
        ComputerModel::class, ComputerType::class,
        MonitorModel::class, MonitorType::class,
        NetworkEquipmentModel::class, NetworkEquipmentType::class,
        PeripheralModel::class, PeripheralType::class,
        PhoneModel::class, PhoneType::class,
        PrinterModel::class, PrinterType::class,
        OperatingSystem::class, OperatingSystemVersion::class, OperatingSystemEdition::class,
        OperatingSystemArchitecture::class, OperatingSystemServicePack::class,
    ];

    /**
     * Champs qui, avec le nom, identifient une entrée.
     *
     * @var array<class-string<CommonDBTM>, list<string>>
     */
    private const IDENTITY_FIELDS = [
        Software::class => ['manufacturers_id'],
    ];

    /**
     * @return array<class-string<CommonDBTM>, callable> pour `$PLUGIN_HOOKS[pre_item_add|update]`
     */
    public static function hooks(string $method): array
    {
        return array_fill_keys(self::ITEMTYPES, [self::class, $method]);
    }

    public static function preItemAdd(CommonDBTM $item): void
    {
        self::guard($item, null);
    }

    public static function preItemUpdate(CommonDBTM $item): void
    {
        // Seulement si le nom change : modifier le commentaire d'un doublon déjà en base (avant
        // l'activation de l'option) ne doit pas être refusé — l'audit (#299) les fusionne.
        if (!is_array($item->input) || !isset($item->input['name'])) {
            return;
        }
        $changed = mb_strtolower(self::clean((string) $item->input['name'])) !== mb_strtolower(self::clean((string) ($item->fields['name'] ?? '')));
        foreach (self::IDENTITY_FIELDS[$item::class] ?? [] as $field) {
            $changed = $changed || (isset($item->input[$field]) && (string) $item->input[$field] !== (string) ($item->fields[$field] ?? ''));
        }
        if ($changed) {
            self::guard($item, (int) $item->getID());
        }
    }

    public static function clean(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     * @param array<string, mixed> $identity valeurs des IDENTITY_FIELDS (ex. éditeur d'un logiciel)
     * @return int|null id de l'entrée existante qui porte déjà ce nom
     */
    public static function findSameName(string $itemtype, string $name, ?int $exceptId = null, int $entitiesId = 0, bool $isRecursive = false, array $identity = []): ?int
    {
        global $DB;

        $table = $itemtype::getTable();
        $item = new $itemtype();
        $where = ["$table.name" => $name];
        foreach ($identity as $field => $value) {
            $where["$table.$field"] = $value;
        }
        if ($exceptId !== null) {
            $where['NOT'] = ["$table.id" => $exceptId];
        }
        if ($item->maybeTemplate()) {
            $where["$table.is_template"] = 0;
        }
        if ($item->isEntityAssign()) {
            $visible = [["$table.entities_id" => $isRecursive ? getSonsOf('glpi_entities', $entitiesId) : $entitiesId]];
            $ancestors = getAncestorsOf('glpi_entities', $entitiesId);
            if ($item->maybeRecursive() && $ancestors !== []) {
                $visible[] = ["$table.entities_id" => array_values($ancestors), "$table.is_recursive" => 1];
            }
            $where[] = ['OR' => $visible];
        }
        $row = $DB->request([
            'SELECT' => ["$table.id"],
            'FROM' => $table,
            'WHERE' => array_merge($where, $itemtype::getSystemSQLCriteria($table)),
            'ORDER' => "$table.id",
            'LIMIT' => 1,
        ])->current();

        return $row === null ? null : (int) $row['id'];
    }

    private static function guard(CommonDBTM $item, ?int $exceptId): void
    {
        if (!is_array($item->input) || !isset($item->input['name'])) {
            return;
        }
        $name = self::clean((string) $item->input['name']);
        if ($name === '' || empty(Config::getConfig()->fields['dropdown_unicity_enabled'])) {
            return;
        }
        $item->input['name'] = $name;

        $entitiesId = (int) ($item->input['entities_id'] ?? $item->fields['entities_id'] ?? Session::getActiveEntity());
        $isRecursive = !empty($item->input['is_recursive'] ?? $item->fields['is_recursive'] ?? 0);
        $identity = [];
        foreach (self::IDENTITY_FIELDS[$item::class] ?? [] as $field) {
            $identity[$field] = (int) ($item->input[$field] ?? $item->fields[$field] ?? 0);
        }
        $existing = self::findSameName($item::class, $name, $exceptId, $entitiesId, $isRecursive, $identity);
        if ($existing === null) {
            return;
        }

        Session::addMessageAfterRedirect(
            sprintf(
                __('« %1$s » existe déjà (%2$s n° %3$d) : utilisez cette entrée plutôt que d\'en créer une en double.', 'configurationglpiauto'),
                $name,
                $item::getTypeName(1),
                $existing
            ),
            false,
            ERROR
        );
        $item->input = false;
    }
}
