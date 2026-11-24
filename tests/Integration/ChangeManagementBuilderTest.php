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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration;

use Change;
use ChangeTemplate;
use ChangeTemplateMandatoryField;
use ChangeTemplatePredefinedField;
use ChangeValidation;
use GlpiPlugin\Configurationglpiauto\ChangeManagementBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use Group;
use ITILCategory;
use PHPUnit\Framework\TestCase;
use RuleAction;
use RuleChange;
use RuleCriteria;

/**
 * Issue #472 : contre une vraie instance GLPI, jusqu'au moteur de règles des changements : un
 * changement normal créé dans Assistance > Changements doit recevoir une demande d'approbation
 * adressée au groupe CAB, un changement standard aucune.
 *
 * Tout se passe dans une transaction annulée à la fin : l'instance de test garde ses éventuels
 * objets du même nom. Les noms comparés sont ceux du builder (données en base), jamais un libellé
 * traduit : le test ne dépend pas de la langue de l'instance.
 */
final class ChangeManagementBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        global $DB;
        $DB->beginTransaction();
    }

    protected function tearDown(): void
    {
        global $DB;
        $DB->rollBack();
    }

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'change_management_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    private function categoryId(string $name, int $parentId = 0): int
    {
        $category = new ITILCategory();
        $this->assertTrue(
            $category->getFromDBByCrit(['name' => $name, 'itilcategories_id' => $parentId, 'entities_id' => 0]),
            "Category '$name' must exist."
        );

        return (int) $category->getID();
    }

    private function cabGroupId(): int
    {
        $group = new Group();
        $this->assertTrue($group->getFromDBByCrit(['name' => ChangeManagementBuilder::GROUP_NAME, 'entities_id' => 0]));

        return (int) $group->getID();
    }

    public function testDisabledByDefaultAndCreatesNothing(): void
    {
        $this->assertSame(0, (int) Config::getDefaults()['change_management_enabled']);
        $this->assertSame(
            ['group' => 0, 'categories' => 0, 'templates' => 0, 'rule' => 0],
            (new ChangeManagementBuilder())->build($this->buildConfig(false))
        );
    }

    public function testCreatesChangeOnlyCategoriesLinkedToTheirTemplates(): void
    {
        (new ChangeManagementBuilder())->build($this->buildConfig(true));

        $normal = new ITILCategory();
        $normal->getFromDB($this->categoryId(ChangeManagementBuilder::NORMAL_CATEGORY));
        $this->assertSame(1, (int) $normal->fields['is_change']);
        $this->assertSame(0, (int) $normal->fields['is_incident']);
        $this->assertSame(0, (int) $normal->fields['is_request']);
        $this->assertSame(0, (int) $normal->fields['is_problem']);

        $template = new ChangeTemplate();
        $this->assertTrue($template->getFromDB((int) $normal->fields['changetemplates_id']));
        $this->assertSame(ChangeManagementBuilder::NORMAL_TEMPLATE, $template->fields['name']);

        $searchOptions = array_flip(ChangeTemplate::getAllowedFields(true));
        foreach (['content', 'impactcontent', 'rolloutplancontent', 'backoutplancontent'] as $field) {
            $mandatory = new ChangeTemplateMandatoryField();
            $this->assertTrue(
                $mandatory->getFromDBByCrit(['changetemplates_id' => $template->getID(), 'num' => $searchOptions[$field]]),
                "'$field' must be mandatory on the normal change template."
            );
        }

        // Chaque changement standard : sous-catégorie et modèle qui pré-remplit le titre.
        $standardId = $this->categoryId(ChangeManagementBuilder::STANDARD_CATEGORY);
        $catalog = ChangeManagementBuilder::getStandardCatalogPreview();
        $this->assertGreaterThanOrEqual(5, count($catalog));
        foreach ($catalog as $name) {
            $child = new ITILCategory();
            $child->getFromDB($this->categoryId($name, $standardId));
            $standardTemplate = new ChangeTemplate();
            $this->assertTrue($standardTemplate->getFromDB((int) $child->fields['changetemplates_id']));
            $this->assertSame(ChangeManagementBuilder::standardTemplateName($name), $standardTemplate->fields['name']);

            $predefined = new ChangeTemplatePredefinedField();
            $this->assertTrue($predefined->getFromDBByCrit([
                'changetemplates_id' => $standardTemplate->getID(),
                'num'                => $searchOptions['name'],
            ]));
            $this->assertSame($name, $predefined->fields['value']);
        }
    }

    public function testRuleSendsNormalAndEmergencyChangesToTheCabGroup(): void
    {
        (new ChangeManagementBuilder())->build($this->buildConfig(true));
        $groupId = $this->cabGroupId();

        $rule = new RuleChange();
        $this->assertTrue($rule->getFromDBByCrit(['name' => ChangeManagementBuilder::RULE_NAME, 'sub_type' => RuleChange::class]));
        $this->assertSame(1, (int) $rule->fields['is_active']);

        $patterns = array_map(
            static fn (array $row): int => (int) $row['pattern'],
            array_values((new RuleCriteria())->find(['rules_id' => $rule->getID(), 'criteria' => 'itilcategories_id']))
        );
        sort($patterns);
        $expected = [
            $this->categoryId(ChangeManagementBuilder::NORMAL_CATEGORY),
            $this->categoryId(ChangeManagementBuilder::EMERGENCY_CATEGORY),
        ];
        sort($expected);
        $this->assertSame($expected, $patterns);

        $action = new RuleAction();
        $this->assertTrue($action->getFromDBByCrit(['rules_id' => $rule->getID(), 'field' => 'groups_id_validate_any']));
        $this->assertSame($groupId, (int) $action->fields['value']);
    }

    public function testNormalChangeGetsACabApprovalAndStandardChangeDoesNot(): void
    {
        (new ChangeManagementBuilder())->build($this->buildConfig(true));
        $groupId = $this->cabGroupId();

        $normalChangeId = (int) (new Change())->add([
            'name'              => 'Migration fictive du serveur de fichiers',
            'content'           => 'Changement de test',
            'impactcontent'     => 'Aucun',
            'rolloutplancontent' => 'Étapes',
            'backoutplancontent' => 'Retour',
            'itilcategories_id' => $this->categoryId(ChangeManagementBuilder::NORMAL_CATEGORY),
            'entities_id'       => 0,
        ]);
        $this->assertGreaterThan(0, $normalChangeId);
        $this->assertNotEmpty(
            (new ChangeValidation())->find([
                'changes_id'      => $normalChangeId,
                'itemtype_target' => Group::class,
                'items_id_target' => $groupId,
            ]),
            'A normal change must get an approval request addressed to the CAB group.'
        );

        $catalog = ChangeManagementBuilder::getStandardCatalogPreview();
        $standardChangeId = (int) (new Change())->add([
            'name'              => $catalog[0],
            'content'           => 'Changement standard de test',
            'itilcategories_id' => $this->categoryId($catalog[0], $this->categoryId(ChangeManagementBuilder::STANDARD_CATEGORY)),
            'entities_id'       => 0,
        ]);
        $this->assertGreaterThan(0, $standardChangeId);
        $this->assertSame([], (new ChangeValidation())->find(['changes_id' => $standardChangeId]));
    }

    public function testSecondRunCreatesNothingAndKeepsAnAdminTemplateChoice(): void
    {
        $builder = new ChangeManagementBuilder();
        $builder->build($this->buildConfig(true));

        // L'administrateur a choisi un autre modèle pour les changements urgents.
        $otherTemplateId = (int) (new ChangeTemplate())->add(['name' => 'Modèle fictif de l\'administrateur', 'entities_id' => 0]);
        $emergencyId = $this->categoryId(ChangeManagementBuilder::EMERGENCY_CATEGORY);
        (new ITILCategory())->update(['id' => $emergencyId, 'changetemplates_id' => $otherTemplateId]);

        $this->assertSame(['group' => 0, 'categories' => 0, 'templates' => 0, 'rule' => 0], $builder->build($this->buildConfig(true)));

        $emergency = new ITILCategory();
        $emergency->getFromDB($emergencyId);
        $this->assertSame($otherTemplateId, (int) $emergency->fields['changetemplates_id']);
        $this->assertCount(1, (new RuleChange())->find(['name' => ChangeManagementBuilder::RULE_NAME]));
    }
}
