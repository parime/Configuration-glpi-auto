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

use Computer;
use Contract;
use DropdownVisibility;
use Line;
use Monitor;
use Peripheral;
use Phone;
use Printer;
use SoftwareLicense;
use State;
use Unmanaged;

/**
 * Turns a Config's state setting into 14 real GLPI State ("Statuts des éléments") rows, used
 * across the asset/CMDB module. Confirmed against a fresh GLPI 11.0.8 instance: `glpi_states` is
 * *empty* out of the box — GLPI ships zero default states, so this genuinely fills a gap rather
 * than duplicating something already there. Not a per-entity/per-client concept, unlike
 * calendar/SLA — instance-wide (`entities_id => 0`, `is_recursive => 1`).
 *
 * The `name` field is always plain text, per explicit instruction — an icon there would corrupt
 * matching/sorting. Icons (optional, `state_icons_enabled`) live only in `DropdownTranslation` rows
 * (one per language, see `Translations::applyIcon()`, field `name`). Confirmed against a real
 * GLPI 11.0.8 instance that the translation `value` is rendered as *escaped plain text*, not HTML —
 * an `<i class="ti ...">` tag shows up literally instead of rendering — so the icon has to be a
 * plain Unicode emoji character prepended to the name, not markup.
 *
 * Visibility (which asset types a state can be applied to) is governed by `DropdownVisibility`
 * rows (`itemtype => State::class`, `visible_itemtype => <asset class>`). Confirmed by reading
 * State::post_getFromDB(): every visibility field defaults to *not visible* unless an explicit
 * row says otherwise (`getEmpty()`'s all-visible defaults only apply to GLPI's own blank "add new
 * state" form, not to states inserted directly) — so only the itemtypes that should show "Oui"
 * need a row here, not every itemtype with "Non".
 *
 * Individually selectable (`Config.state_names`, checkboxes on the wizard step) rather than
 * all-or-nothing — same "no bundling" lesson as Sprint 26. Five of the 14 are flagged as
 * recommended: this sibling plugin's own `remise-glpi`
 * (https://github.com/parime/remise-glpi) auto-triggers a handover/return/donation/sale workflow
 * off a `State` change, so an admin running both plugins needs "En stock"/"Attribué"/"Donné"/
 * "Vendu"/"Attente restitution" to exist for the two plugins to actually interoperate — but
 * `remise-glpi` matches by *state ID* (configured in its own settings, confirmed in its
 * `ARCHITECTURE.md`), not by exact name string, so this is a recommendation to keep selected, not
 * a hardcoded name dependency.
 */
class StateBuilder
{
    private const STATES = [
        ['name' => 'Attribué', 'comment' => 'Le matériel est affecté à un utilisateur, un service ou une entité et est actuellement en utilisation.', 'icon' => '✅'],
        ['name' => 'En stock', 'comment' => 'Le matériel est disponible en stock et n\'est pas encore attribué.', 'icon' => '📦'],
        ['name' => 'Obsolète', 'comment' => 'Le matériel est obsolète et ne répond plus aux standards techniques ou fonctionnels en vigueur.', 'icon' => '⏳'],
        ['name' => 'Donné', 'comment' => 'Le matériel a été cédé (don) et ne fait plus partie du parc.', 'icon' => '🎁'],
        ['name' => 'Volé / perdu', 'comment' => 'Le matériel est déclaré volé ou perdu et n\'est plus disponible.', 'icon' => '⚠️'],
        ['name' => 'À identifier', 'comment' => 'Le matériel est présent mais son propriétaire, son affectation ou ses informations doivent être vérifiés.', 'icon' => '🔍'],
        ['name' => 'Attente restitution', 'comment' => 'Le matériel est en attente de retour par son utilisateur ou son détenteur.', 'icon' => '⏰'],
        ['name' => 'Défectueux', 'comment' => 'Le matériel présente une panne ou un dysfonctionnement et nécessite une réparation, un remplacement ou une mise au rebut.', 'icon' => '🔧'],
        ['name' => 'En service', 'comment' => 'Le matériel est opérationnel et utilisé dans son environnement de production ou d\'exploitation.', 'icon' => '✅'],
        ['name' => 'Fin de support', 'comment' => 'Le matériel n\'est plus couvert par le support constructeur ou éditeur. Son remplacement doit être envisagé.', 'icon' => '⌛'],
        ['name' => 'Retour fournisseur', 'comment' => 'Le matériel a été retourné au fournisseur dans le cadre d\'une restitution, d\'un échange, d\'une réparation, d\'une garantie ou d\'un contrat de location.', 'icon' => '🚚'],
        ['name' => 'Externe', 'comment' => 'Utilisateur externe à l\'entreprise', 'icon' => '🔗'],
        ['name' => 'Compte de service', 'comment' => 'Compte utilisé par des applications ou des bots', 'icon' => '🤖'],
        ['name' => 'Vendu', 'comment' => 'Le matériel a été cédé (Vendu) et ne fait plus partie du parc.', 'icon' => '💰'],
    ];

    // Kept selected by default for interoperability with remise-glpi's own donation/sale/return
    // workflow triggers — see class docblock.
    public const RECOMMENDED_NAMES = ['En stock', 'Attribué', 'Donné', 'Vendu', 'Attente restitution'];

    // Only the itemtypes that should default to "Oui" — absence of a row means "Non" at runtime
    // (see class docblock), so the much longer list of component/infrastructure types that stay
    // "Non" (network equipment, racks, device components, etc.) needs no row at all.
    private const VISIBLE_FOR = [
        Computer::class,
        Phone::class,
        SoftwareLicense::class,
        Line::class,
        Contract::class,
        Unmanaged::class,
        Monitor::class,
        Peripheral::class,
        Printer::class,
    ];

    /**
     * Exposed so the wizard can render a read-only preview (name + icon) before anything is
     * actually created.
     *
     * @return array<int, array{name: string, comment: string, icon: string}>
     */
    public static function getStatesPreview(): array
    {
        return self::STATES;
    }

    /**
     * @return string[] Every state name this builder knows how to create — used both as the
     *         "all selected" default and to validate `Config.state_names` against a whitelist
     *         (same role `Config::CATEGORY_BRANCH_KEYS` plays for category branches).
     */
    public static function getStateNames(): array
    {
        return array_column(self::STATES, 'name');
    }

    /**
     * @return string[] Names of the states created/reused, for the confirmation message.
     */
    public function build(Config $config): array
    {
        if (empty($config->fields['state_enabled'])) {
            return [];
        }

        $withIcons = !empty($config->fields['state_icons_enabled']);
        $selected = $config->getStateNames();
        $names = [];

        foreach (self::STATES as $state) {
            if (!in_array($state['name'], $selected, true)) {
                continue;
            }
            $item = new State();
            if (!$item->getFromDBByCrit(['name' => $state['name']])) {
                $id = $item->add([
                    'name' => $state['name'],
                    'comment' => $state['comment'],
                    'entities_id' => 0,
                    'is_recursive' => 1,
                ]);
                $item->getFromDB($id);
            }
            $stateId = (int) $item->getID();

            foreach (self::VISIBLE_FOR as $itemtype) {
                $visibility = new DropdownVisibility();
                if (!$visibility->getFromDBByCrit(['itemtype' => State::class, 'items_id' => $stateId, 'visible_itemtype' => $itemtype])) {
                    $visibility->add([
                        'itemtype' => State::class,
                        'items_id' => $stateId,
                        'visible_itemtype' => $itemtype,
                        'is_visible' => 1,
                    ]);
                }
            }

            // Always called (not just when withIcons): an empty icon still refreshes the
            // DropdownTranslation rows down to the plain translated text, which is how unchecking
            // the icons box after a prior run actually removes the icon instead of leaving it stuck.
            Translations::applyIcon(State::class, $stateId, $state['name'], $withIcons ? $state['icon'] : '');

            $names[] = $state['name'];
        }

        return $names;
    }
}
