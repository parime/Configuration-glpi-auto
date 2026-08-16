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

use Entity;
use Profile;
use Rule;
use RuleAction;
use RuleCriteria;
use RuleRight;

/**
 * Scaffolds one `RuleRight` per leaf entity in the tree — GLPI's native mechanism for
 * auto-assigning a user's entity + profile from their LDAP/AD group membership at import/sync
 * time (`RuleRightCollection`, confirmed in source: fires automatically on every LDAP
 * import/update, no extra wiring needed once the rule rows exist — same "just works once created"
 * behavior as `SlaBuilder`'s `RuleTicket` rows).
 *
 * Directly inspired by a real production GLPI export handed over for audit: 37 `RuleRight` rows,
 * each matching an AD group name (via *both* the `_groups_id` and `memberof` criteria, `OR`-ed —
 * belt-and-braces so the rule still fires even before GLPI's own group sync has caught up) to
 * assign one site's entity + a fixed profile. Generalized here: the real AD group names
 * ("WG_AA_Bordeaux", "Ag_Rapsodi"...) are never reused — the admin supplies a naming *template*
 * (`ldap_rights_group_template`, containing the literal placeholder `{ENTITY}`) and picks one
 * profile (`ldap_rights_profile`) applied to every generated rule. This only scaffolds the
 * one-profile-per-site pattern actually confirmed with the user — the production export's other
 * pattern (an org-wide function-based profile, e.g. "Finance"/"DSI" AD groups → a global profile
 * regardless of site) is a different shape entirely and out of scope here.
 *
 * One rule per *leaf* entity (no children), not every node at every depth — matches the real
 * export's pattern (one rule per physical site, the leaves of a Client > Site tree) and avoids
 * generating a rule for intermediate "container" nodes nobody actually logs into.
 *
 * No-op entirely if there's no entity tree (mono-entité) — nothing to scaffold without at least
 * one site.
 *
 * `buildFunctionRights()` (Sprint 36) covers a genuinely different pattern, layered on top rather
 * than replacing the per-site rules above: a function/department AD group (e.g. "Finance") that
 * should always get a given profile regardless of which site the user is on. Confirmed in GLPI's
 * own `RuleRight::executeActions()` that a rule with *only* a `profiles_id` assign action (no
 * `entities_id` action) accumulates into `_ldap_rules.rules_rights`, kept separate from the
 * per-site `rules_entities_rights` — and `RuleRightCollection::$stop_on_first_match = false`
 * (confirmed in source) means both kinds of rules apply together for the same user, not one
 * overriding the other. Department names are exactly as organization-specific as the per-site
 * `{ENTITY}` group-name template above, so this is a blank, admin-supplied list
 * (`ldap_function_rights`), never a guessed set of names.
 */
class RuleRightBuilder
{
    /**
     * @return int Number of RuleRight rows created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['ldap_rights_enabled'])) {
            return 0;
        }

        $template = (string) ($config->fields['ldap_rights_group_template'] ?? '');
        if (!str_contains($template, '{ENTITY}')) {
            return 0;
        }

        $profile = new Profile();
        if (!$profile->getFromDBByCrit(['name' => (string) ($config->fields['ldap_rights_profile'] ?? '')])) {
            return 0;
        }
        $profileId = (int) $profile->getID();

        $count = 0;
        foreach ($this->collectLeafEntities($config->getEntityTree(), 0) as $leaf) {
            $groupName = str_replace('{ENTITY}', str_replace(' ', '_', $leaf['name']), $template);
            $this->createRule($leaf['entitiesId'], $leaf['name'], $groupName, $profileId);
            $count++;
        }

        return $count;
    }

    /**
     * @param array<int, array{group: string, profile: string}> $pairs Already validated by
     *        Config::sanitizeLdapFunctionRights() — group non-empty, profile a real native name.
     * @return int Number of function-right rules created/reused.
     */
    public function buildFunctionRights(array $pairs): int
    {
        if (empty($pairs)) {
            return 0;
        }

        $count = 0;
        foreach ($pairs as $pair) {
            $profile = new Profile();
            if (!$profile->getFromDBByCrit(['name' => $pair['profile']])) {
                continue;
            }
            $this->createFunctionRule($pair['group'], (int) $profile->getID());
            $count++;
        }

        return $count;
    }

    /**
     * Live-preview equivalent of build(): the (entity name → AD group name) pairs it would
     * generate, without touching the database — used by the wizard's recap step and by build()'s
     * own tests. Entities don't need to exist yet for this to work (unlike build(), which resolves
     * real entity IDs) since it only reads the tree shape, never the database.
     *
     * @param array<int, array{name: string, children: array}> $tree
     * @return array<int, array{name: string, group: string}>
     */
    public static function preview(array $tree, string $template): array
    {
        if (!str_contains($template, '{ENTITY}')) {
            return [];
        }

        $result = [];
        $walk = static function (array $nodes) use (&$walk, &$result, $template): void {
            foreach ($nodes as $node) {
                $name = (string) ($node['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $children = is_array($node['children'] ?? null) ? $node['children'] : [];
                if ($children === []) {
                    $result[] = ['name' => $name, 'group' => str_replace('{ENTITY}', str_replace(' ', '_', $name), $template)];
                } else {
                    $walk($children);
                }
            }
        };
        $walk($tree);

        return $result;
    }

    /**
     * Re-resolves each leaf's real entity ID by walking the tree the same way EntityBuilder does
     * (name + parent lookup) rather than requiring EntityBuilder to expose its internal node
     * details — same independent-resolution pattern already used by ServiceCatalogBuilder against
     * CategoryBuilder's tree. Assumes EntityBuilder has already run in this same request; a leaf
     * whose entity doesn't exist (shouldn't happen in the normal wizard flow) is silently skipped.
     *
     * @param array<int, array{name: string, children: array}> $nodes
     * @return iterable<array{entitiesId: int, name: string}>
     */
    private function collectLeafEntities(array $nodes, int $parentId): iterable
    {
        foreach ($nodes as $node) {
            $name = (string) ($node['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $entity = new Entity();
            if (!$entity->getFromDBByCrit(['name' => $name, 'entities_id' => $parentId])) {
                continue;
            }
            $entityId = (int) $entity->getID();

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            if ($children === []) {
                yield ['entitiesId' => $entityId, 'name' => $name];
            } else {
                yield from $this->collectLeafEntities($children, $entityId);
            }
        }
    }

    private function createRule(int $entityId, string $entityName, string $groupName, int $profileId): void
    {
        $ruleName = sprintf('Droits LDAP — %s (entité #%d)', $entityName, $entityId);

        $rule = new RuleRight();
        if ($rule->getFromDBByCrit(['name' => $ruleName])) {
            return;
        }

        $rulesId = $rule->add([
            'name' => $ruleName,
            'sub_type' => RuleRight::class,
            // OR, not AND: matches the production pattern (group membership via `_groups_id`
            // *or* the raw LDAP `memberof` attribute) so the rule still fires on a user's very
            // first LDAP sync, before GLPI's own group sync has created/populated the matching
            // GLPI group yet.
            'match' => Rule::OR_MATCHING,
            'is_active' => 1,
        ]);

        (new RuleCriteria())->add([
            'rules_id' => $rulesId,
            'criteria' => '_groups_id',
            'condition' => Rule::PATTERN_CONTAIN,
            'pattern' => $groupName,
        ]);

        (new RuleCriteria())->add([
            'rules_id' => $rulesId,
            'criteria' => 'memberof',
            'condition' => Rule::PATTERN_CONTAIN,
            'pattern' => $groupName,
        ]);

        (new RuleAction())->add([
            'rules_id' => $rulesId,
            'action_type' => 'assign',
            'field' => 'entities_id',
            'value' => $entityId,
        ]);

        (new RuleAction())->add([
            'rules_id' => $rulesId,
            'action_type' => 'assign',
            'field' => 'profiles_id',
            'value' => $profileId,
        ]);

        (new RuleAction())->add([
            'rules_id' => $rulesId,
            'action_type' => 'assign',
            'field' => '_entities_id_default',
            'value' => $entityId,
        ]);

        (new RuleAction())->add([
            'rules_id' => $rulesId,
            'action_type' => 'assign',
            'field' => '_profiles_id_default',
            'value' => $profileId,
        ]);
    }

    /**
     * Same shape as createRule() above, minus every `entities_id`/`_entities_id_default` action —
     * confirmed in `RuleRight::executeActions()` that omitting the entity action entirely (not
     * setting it to some placeholder value) is what routes this rule into the entity-independent
     * `rules_rights` accumulator rather than `rules_entities_rights`.
     */
    private function createFunctionRule(string $groupName, int $profileId): void
    {
        $ruleName = sprintf('Droits LDAP — fonction %s', $groupName);

        $rule = new RuleRight();
        if ($rule->getFromDBByCrit(['name' => $ruleName])) {
            return;
        }

        $rulesId = $rule->add([
            'name' => $ruleName,
            'sub_type' => RuleRight::class,
            'match' => Rule::OR_MATCHING,
            'is_active' => 1,
        ]);

        (new RuleCriteria())->add([
            'rules_id' => $rulesId,
            'criteria' => '_groups_id',
            'condition' => Rule::PATTERN_CONTAIN,
            'pattern' => $groupName,
        ]);

        (new RuleCriteria())->add([
            'rules_id' => $rulesId,
            'criteria' => 'memberof',
            'condition' => Rule::PATTERN_CONTAIN,
            'pattern' => $groupName,
        ]);

        (new RuleAction())->add([
            'rules_id' => $rulesId,
            'action_type' => 'assign',
            'field' => 'profiles_id',
            'value' => $profileId,
        ]);

        (new RuleAction())->add([
            'rules_id' => $rulesId,
            'action_type' => 'assign',
            'field' => '_profiles_id_default',
            'value' => $profileId,
        ]);
    }
}
