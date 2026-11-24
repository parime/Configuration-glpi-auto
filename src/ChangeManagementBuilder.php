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

use ChangeTemplate;
use ChangeTemplateMandatoryField;
use ChangeTemplatePredefinedField;
use Group;
use ITILCategory;
use Rule;
use RuleAction;
use RuleChange;
use RuleCriteria;
use ValidationStep;

/**
 * Issue #472 : gestion des changements selon ITIL, uniquement avec les objets natifs de GLPI.
 *
 * - Groupe « CAB » (Administration > Groupes) : le comité consultatif des changements. Créé vide :
 *   l'administrateur y ajoute ses membres, l'assistant ne devine aucune personne.
 * - Trois familles de catégories ITIL réservées aux changements (Assistance > Changements) :
 *   « Changement normal » et « Changement urgent », chacune avec son modèle de changement aux
 *   champs obligatoires (analyse d'impact, plan de déploiement, plan de retour arrière), et
 *   « Changement standard (pré-approuvé) » avec un petit catalogue de sous-catégories, chacune
 *   reliée à un modèle qui pré-remplit le titre, la description et les plans.
 * - Une règle métier native sur les changements (RuleChange, à la création) : un changement normal
 *   ou urgent reçoit une demande d'approbation adressée au groupe CAB (action native
 *   `groups_id_validate_any` : un membre répond pour le groupe), à l'étape d'approbation par défaut.
 *   Les changements standard n'ont aucune règle : ils sont pré-approuvés par construction.
 *
 * Idempotent et non destructif : chaque objet est retrouvé par son nom avant d'être créé, rien
 * d'existant n'est modifié, sauf le lien catégorie vers modèle laissé vide. Décoché par défaut, comme
 * la validation N+1 : la règle change un vrai circuit (approbation des changements).
 * Appelé seulement avec le droit natif `rule_change` en modification (front/wizard.php).
 */
class ChangeManagementBuilder
{
    public const GROUP_NAME = 'CAB';

    public const NORMAL_CATEGORY = 'Changement normal';

    public const EMERGENCY_CATEGORY = 'Changement urgent';

    public const STANDARD_CATEGORY = 'Changement standard (pré-approuvé)';

    public const RULE_NAME = 'Changements normaux et urgents : approbation du CAB';

    public const NORMAL_TEMPLATE = 'Changement normal : évaluation et approbation du CAB';

    public const EMERGENCY_TEMPLATE = 'Changement urgent : approbation rapide du CAB';

    /**
     * Catalogue des changements standard : courants, à faible risque, procédure connue.
     *
     * @var array<int, array{name: string, content: string, rollout: string, backout: string}>
     */
    private const STANDARD_CATALOG = [
        [
            'name' => 'Redémarrage planifié d\'un serveur',
            'content' => "Serveur concerné :\nCréneau prévu :\nServices interrompus pendant le redémarrage :",
            'rollout' => "1. Prévenir les utilisateurs des services concernés.\n2. Redémarrer le serveur pendant le créneau prévu.\n3. Vérifier que les services ont redémarré.",
            'backout' => "Si un service ne redémarre pas : le relancer à la main, sinon ouvrir un incident.",
        ],
        [
            'name' => 'Correctifs de sécurité mensuels des postes de travail',
            'content' => "Correctifs concernés (éditeur, mois) :\nPostes visés :",
            'rollout' => "1. Déployer d'abord sur un groupe pilote.\n2. Sans anomalie après une journée, déployer sur les autres postes.",
            'backout' => "Désinstaller le correctif en cause sur les postes touchés et suspendre le déploiement.",
        ],
        [
            'name' => 'Renouvellement d\'un certificat TLS',
            'content' => "Nom du certificat et services qui l'utilisent :\nDate d'expiration actuelle :",
            'rollout' => "1. Générer ou commander le nouveau certificat.\n2. L'installer sur chaque service.\n3. Vérifier la nouvelle date d'expiration depuis un navigateur.",
            'backout' => "Réinstaller l'ancien certificat tant qu'il est valide.",
        ],
        [
            'name' => 'Remplacement d\'un poste de travail à l\'identique',
            'content' => "Utilisateur :\nAncien poste :\nNouveau poste (même modèle ou équivalent) :",
            'rollout' => "1. Préparer le nouveau poste avec l'image standard.\n2. Transférer les données de l'utilisateur.\n3. Mettre à jour l'inventaire.",
            'backout' => "Rendre l'ancien poste à l'utilisateur tant qu'il n'est pas effacé.",
        ],
        [
            'name' => 'Ajout d\'une imprimante réseau',
            'content' => "Emplacement :\nModèle :\nUtilisateurs ou services concernés :",
            'rollout' => "1. Raccorder l'imprimante et lui attribuer son adresse.\n2. La déployer sur les postes concernés.\n3. Imprimer une page de test.",
            'backout' => "Retirer l'imprimante des postes et la débrancher du réseau.",
        ],
    ];

    /**
     * @return array<int, string> noms des changements standard proposés (aperçu de l'assistant)
     */
    public static function getStandardCatalogPreview(): array
    {
        return array_column(self::STANDARD_CATALOG, 'name');
    }

    /**
     * @return array{group: int, categories: int, templates: int, rule: int} nombre d'objets créés
     */
    public function build(Config $config): array
    {
        $result = ['group' => 0, 'categories' => 0, 'templates' => 0, 'rule' => 0];
        if (empty($config->fields['change_management_enabled'])) {
            return $result;
        }

        $groupId = $this->getOrCreateGroup($result);

        $normalId = $this->getOrCreateCategory(self::NORMAL_CATEGORY, 0, $result);
        $this->linkTemplate($normalId, $this->getOrCreateTemplate(
            self::NORMAL_TEMPLATE,
            ['content', 'impactcontent', 'rolloutplancontent', 'backoutplancontent'],
            [],
            $result
        ));

        $emergencyId = $this->getOrCreateCategory(self::EMERGENCY_CATEGORY, 0, $result);
        $this->linkTemplate($emergencyId, $this->getOrCreateTemplate(
            self::EMERGENCY_TEMPLATE,
            ['content', 'backoutplancontent'],
            [],
            $result
        ));

        $standardId = $this->getOrCreateCategory(self::STANDARD_CATEGORY, 0, $result);
        foreach (self::STANDARD_CATALOG as $entry) {
            $childId = $this->getOrCreateCategory($entry['name'], $standardId, $result);
            $this->linkTemplate($childId, $this->getOrCreateTemplate(
                self::standardTemplateName($entry['name']),
                [],
                [
                    'name' => $entry['name'],
                    'content' => $entry['content'],
                    'rolloutplancontent' => $entry['rollout'],
                    'backoutplancontent' => $entry['backout'],
                ],
                $result
            ));
        }

        if ($groupId > 0 && $normalId > 0 && $emergencyId > 0) {
            $result['rule'] = $this->createApprovalRule($groupId, [$normalId, $emergencyId]);
        }

        return $result;
    }

    public static function standardTemplateName(string $changeName): string
    {
        return 'Standard : ' . $changeName;
    }

    /**
     * @param array{group: int, categories: int, templates: int, rule: int} $result
     */
    private function getOrCreateGroup(array &$result): int
    {
        $group = new Group();
        if (Lookup::loadFirst($group, ['name' => self::GROUP_NAME, 'entities_id' => 0])) {
            return (int) $group->getID();
        }

        $id = (int) $group->add([
            'name'         => self::GROUP_NAME,
            'entities_id'  => 0,
            'is_recursive' => 1,
            // Groupe d'approbation, pas d'exécution : ni demandeur ni technicien attribué.
            'is_requester' => 0,
            'is_assign'    => 0,
            'is_watcher'   => 1,
            'is_notify'    => 1,
            'comment'      => 'Groupe cree par Configuration GLPI Auto : comite consultatif des changements '
                . '(CAB). Ses membres approuvent les changements normaux et urgents ; les changements '
                . 'standard sont pre-approuves. Ajoutez-y les personnes qui decident des changements.',
        ]);
        if ($id > 0) {
            $result['group']++;
        }

        return $id;
    }

    /**
     * @param array{group: int, categories: int, templates: int, rule: int} $result
     */
    private function getOrCreateCategory(string $name, int $parentId, array &$result): int
    {
        $category = new ITILCategory();
        if (Lookup::loadFirst($category, ['name' => $name, 'itilcategories_id' => $parentId, 'entities_id' => 0])) {
            return (int) $category->getID();
        }

        $id = (int) $category->add([
            'name'              => $name,
            'itilcategories_id' => $parentId,
            'entities_id'       => 0,
            'is_recursive'      => 1,
            // Réservée aux changements : absente des tickets et des problèmes.
            'is_change'         => 1,
            'is_incident'       => 0,
            'is_request'        => 0,
            'is_problem'        => 0,
            'is_helpdeskvisible' => 0,
        ]);
        if ($id > 0) {
            $result['categories']++;
        }

        return $id;
    }

    /**
     * @param array<int, string>    $mandatory  champs de Change rendus obligatoires
     * @param array<string, string> $predefined champs de Change pré-remplis (champ => valeur)
     * @param array{group: int, categories: int, templates: int, rule: int} $result
     */
    private function getOrCreateTemplate(string $name, array $mandatory, array $predefined, array &$result): int
    {
        $template = new ChangeTemplate();
        if (Lookup::loadFirst($template, ['name' => $name, 'entities_id' => 0])) {
            return (int) $template->getID();
        }

        $id = (int) $template->add([
            'name'         => $name,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);
        if ($id <= 0) {
            return 0;
        }
        $result['templates']++;

        $searchOptions = array_flip(ChangeTemplate::getAllowedFields(true));
        foreach ($mandatory as $field) {
            if (isset($searchOptions[$field])) {
                (new ChangeTemplateMandatoryField())->add(['changetemplates_id' => $id, 'num' => $searchOptions[$field]]);
            }
        }
        foreach ($predefined as $field => $value) {
            if (isset($searchOptions[$field])) {
                (new ChangeTemplatePredefinedField())->add([
                    'changetemplates_id' => $id,
                    'num'                => $searchOptions[$field],
                    'value'              => $value,
                ]);
            }
        }

        return $id;
    }

    /**
     * Relie la catégorie à son modèle (champ natif `changetemplates_id` de la catégorie ITIL, utilisé
     * par le formulaire de changement dès qu'on choisit la catégorie). Un modèle déjà choisi par
     * l'administrateur n'est jamais remplacé.
     */
    private function linkTemplate(int $categoryId, int $templateId): void
    {
        if ($categoryId <= 0 || $templateId <= 0) {
            return;
        }
        $category = new ITILCategory();
        if ($category->getFromDB($categoryId) && (int) $category->fields['changetemplates_id'] === 0) {
            $category->update(['id' => $categoryId, 'changetemplates_id' => $templateId]);
        }
    }

    /**
     * @param array<int, int> $categoryIds catégories dont les changements demandent l'approbation
     */
    private function createApprovalRule(int $groupId, array $categoryIds): int
    {
        $rule = new RuleChange();
        if (Lookup::loadFirst($rule, ['name' => self::RULE_NAME, 'sub_type' => RuleChange::class])) {
            return 0;
        }

        $rulesId = (int) $rule->add([
            'name'         => self::RULE_NAME,
            'sub_type'     => RuleChange::class,
            'match'        => Rule::OR_MATCHING,
            'condition'    => RuleChange::ONADD,
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
            'description'  => 'Cree par Configuration GLPI Auto : les changements normaux et urgents sont '
                . 'soumis a l\'approbation du groupe CAB ; les changements standard sont pre-approuves.',
        ]);
        if ($rulesId <= 0) {
            return 0;
        }

        foreach ($categoryIds as $categoryId) {
            (new RuleCriteria())->add([
                'rules_id'  => $rulesId,
                'criteria'  => 'itilcategories_id',
                'condition' => Rule::PATTERN_IS,
                'pattern'   => $categoryId,
            ]);
        }

        (new RuleAction())->add([
            'rules_id'    => $rulesId,
            'action_type' => 'add_validation',
            'field'       => 'groups_id_validate_any',
            'value'       => $groupId,
        ]);

        $stepId = self::defaultValidationStepId();
        if ($stepId > 0) {
            (new RuleAction())->add([
                'rules_id'    => $rulesId,
                // Action native forcée sur « add_validation » (RuleCommonITILObject::getActions()).
                'action_type' => 'add_validation',
                'field'       => 'validationsteps_id',
                'value'       => $stepId,
            ]);
        }

        return 1;
    }

    private static function defaultValidationStepId(): int
    {
        $step = new ValidationStep();

        return Lookup::loadFirst($step, ['is_default' => 1]) ? (int) $step->getID() : 0;
    }
}
