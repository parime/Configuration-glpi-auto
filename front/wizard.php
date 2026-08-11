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

use GlpiPlugin\Configurationglpiauto\BrandingBuilder;
use GlpiPlugin\Configurationglpiauto\CalendarBuilder;
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\ChangeProblemTemplateBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\EntityBuilder;
use GlpiPlugin\Configurationglpiauto\FollowupLibraryBuilder;
use GlpiPlugin\Configurationglpiauto\GeneralSettingsBuilder;
use GlpiPlugin\Configurationglpiauto\HelpdeskFormBuilder;
use GlpiPlugin\Configurationglpiauto\KnowbaseCategoryBuilder;
use GlpiPlugin\Configurationglpiauto\LocationBuilder;
use GlpiPlugin\Configurationglpiauto\ManufacturerBuilder;
use GlpiPlugin\Configurationglpiauto\ProjectTaskTemplateBuilder;
use GlpiPlugin\Configurationglpiauto\ProjectTaxonomyBuilder;
use GlpiPlugin\Configurationglpiauto\RuleRightBuilder;
use GlpiPlugin\Configurationglpiauto\ServiceCatalogBuilder;
use GlpiPlugin\Configurationglpiauto\SlaBuilder;
use GlpiPlugin\Configurationglpiauto\SolutionLibraryBuilder;
use GlpiPlugin\Configurationglpiauto\StateBuilder;
use GlpiPlugin\Configurationglpiauto\SupportTierBuilder;
use GlpiPlugin\Configurationglpiauto\TaskCategoryBuilder;
use GlpiPlugin\Configurationglpiauto\TaskTemplateBuilder;
use GlpiPlugin\Configurationglpiauto\TicketTemplateBuilder;
use GlpiPlugin\Configurationglpiauto\ValidationTemplateBuilder;
use GlpiPlugin\Configurationglpiauto\WaitReasonBuilder;

/**
 * Validates an uploaded entity logo and returns it as a `data:` URI, or null if missing/invalid.
 * `getimagesize()` reads the actual image header rather than trusting the browser-supplied MIME
 * type. SVG is deliberately not in the allow-list — it can carry an embedded `<script>`, and while
 * that wouldn't execute rendered as a CSS `background-image`, excluding it removes the question
 * entirely rather than relying on that distinction being reliable across browsers.
 *
 * @param array{error?: int, size?: int, tmp_name?: string} $file One entry of $_FILES.
 */
function buildEntityLogoDataUri(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    // 1 MB cap: this ends up base64-encoded (~33% larger) inside custom_css_code, a plain text
    // field — keeps the entity row (and every page load that renders this CSS) reasonably sized.
    if (($file['size'] ?? 0) > 1_000_000 || ($file['tmp_name'] ?? '') === '') {
        return null;
    }

    $info = @getimagesize($file['tmp_name']);
    $allowedMimes = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
    if ($info === false || !in_array($info['mime'], $allowedMimes, true)) {
        return null;
    }

    $data = file_get_contents($file['tmp_name']);
    if ($data === false) {
        return null;
    }

    return 'data:' . $info['mime'] . ';base64,' . base64_encode($data);
}

Session::checkRight(Config::$rightname, READ);

if (isset($_POST['finish'])) {
    Session::checkRight(Config::$rightname, UPDATE);

    $config = Config::getConfig();
    $config->update($_POST + ['id' => $config->getID()]);

    $created = (new EntityBuilder())->build($config);
    $entityIds = EntityBuilder::topEntityIds($created) ?: [0];

    // One pass over the top-level nodes (= MSP clients), pairing each EntityBuilder result with
    // its matching entity_tree node (same order/index) to check for a per-client calendar/SLA
    // override (Config::sanitizeTree()'s `settings.calendar`/`settings.sla`). No override on a
    // node → falls back to the plugin-wide shared calendar/SLA, built once and reused (lazy) —
    // same net effect as before this existed. Mono-entité/empty tree ($created empty) has no tree
    // node to pair with, so it always uses the shared path against the root entity, unchanged.
    $tree = $config->getEntityTree();
    $calendarBuilder = new CalendarBuilder();
    $slaBuilder = new SlaBuilder();

    // Built once, shared by every client unless overridden below — a client's own
    // settings.escalation only ever changes whether/how escalation applies, never the group
    // identities themselves (see SupportTierBuilder's docblock on that scope choice).
    $tierGroupIds = (new SupportTierBuilder())->build($config) ?: null;

    // Built eagerly (not lazily on first use) so every client that falls back to the shared
    // calendar/SLA gets the *same* pairing regardless of which order clients are processed in —
    // build() is idempotent and a cheap no-op when calendar_enabled/sla_enabled is off.
    $sharedCalendarId = $calendarBuilder->build($config);
    $sharedSlaIds = $slaBuilder->build(
        $config,
        $sharedCalendarId,
        $tierGroupIds,
        !empty($config->fields['escalation_auto_n1_n2']),
        !empty($config->fields['escalation_auto_n2_n3'])
    );

    $calendarMap = [];
    $slaMap = [];
    $entityIdToTierGroupIds = [];
    $perClientCount = 0;

    foreach (($created ?: [['name' => '', 'entities_id' => 0]]) as $i => $result) {
        $calendarOverride = $tree[$i]['settings']['calendar'] ?? null;
        $slaOverride = $tree[$i]['settings']['sla'] ?? null;
        $escalationOverride = $tree[$i]['settings']['escalation'] ?? null;
        if ($calendarOverride !== null || $slaOverride !== null || $escalationOverride !== null) {
            $perClientCount++;
        }

        $calendarId = $calendarOverride !== null
            ? $calendarBuilder->buildFromOverride($result['name'], $calendarOverride, !empty($config->fields['calendar_holidays_enabled']))
            : $sharedCalendarId;
        if ($calendarId !== null) {
            $calendarMap[$result['entities_id']] = $calendarId;
        }

        // A client override can only ever narrow escalation (opt this client out, or change which
        // hops are automatic) — it never invents tier groups of its own, see SupportTierBuilder.
        $clientTierGroupIds = $escalationOverride !== null && empty($escalationOverride['enabled']) ? null : $tierGroupIds;
        $clientAutoN1N2 = $escalationOverride !== null ? !empty($escalationOverride['auto_n1_n2']) : !empty($config->fields['escalation_auto_n1_n2']);
        $clientAutoN2N3 = $escalationOverride !== null ? !empty($escalationOverride['auto_n2_n3']) : !empty($config->fields['escalation_auto_n2_n3']);
        $entityIdToTierGroupIds[$result['entities_id']] = $clientTierGroupIds;

        $slaIds = $slaOverride !== null
            ? $slaBuilder->buildFromOverride(
                $result['name'],
                $slaOverride,
                $calendarId,
                !empty($config->fields['sla_escalation_enabled']),
                (int) ($config->fields['sla_escalation_threshold_percent'] ?? 75),
                $clientTierGroupIds,
                $clientAutoN1N2,
                $clientAutoN2N3
            )
            : $sharedSlaIds;
        if ($slaIds !== null) {
            $slaMap[$result['entities_id']] = $slaIds;
        }
    }

    if ($calendarMap !== []) {
        $calendarBuilder->assignMap($calendarMap);
    }
    if ($slaMap !== []) {
        $slaBuilder->assignMap($slaMap, $tierGroupIds, $entityIdToTierGroupIds);
    }

    $olaBuilt = false;
    foreach ($slaMap as $slaIdsByPriority) {
        foreach ($slaIdsByPriority as $ids) {
            if ($ids['ola_tto'] !== null) {
                $olaBuilt = true;
                break 2;
            }
        }
    }

    $categoriesCreated = (new CategoryBuilder())->build($config);
    $servicesCreated = (new ServiceCatalogBuilder())->build($config);
    $statesCreated = (new StateBuilder())->build($config);
    $waitReasonsCreated = (new WaitReasonBuilder())->build($config);
    $ldapRulesCreated = (new RuleRightBuilder())->build($config);
    $taskCategoriesCreated = (new TaskCategoryBuilder())->build($config);
    // Runs after TaskCategoryBuilder: resolves task categories by name lookup.
    $taskTemplatesCreated = (new TaskTemplateBuilder())->build($config);
    $solutionTemplatesCreated = (new SolutionLibraryBuilder())->build($config);
    $followupTemplatesCreated = (new FollowupLibraryBuilder())->build($config);
    $validationTemplatesCreated = (new ValidationTemplateBuilder())->build($config);
    // Runs after EntityBuilder: resolves entities by name lookup to scope each location.
    $locationsCreated = (new LocationBuilder())->build($config);
    $manufacturersCreated = (new ManufacturerBuilder())->build($config);
    $kbCategoriesCreated = (new KnowbaseCategoryBuilder())->build($config);
    $projectTaxonomyCreated = (new ProjectTaxonomyBuilder())->build($config);
    // Runs after ProjectTaxonomyBuilder: resolves project task types by name lookup.
    $projectTaskTemplatesCreated = (new ProjectTaskTemplateBuilder())->build($config);

    $brandingBuilder = new BrandingBuilder();
    $brandingApplied = $brandingBuilder->apply($config, $entityIds);

    $logosCreated = 0;
    if (!empty($config->fields['entity_logos_enabled'])) {
        $entityIdToLogoDataUri = [];
        foreach ($entityIds as $i => $entityId) {
            $dataUri = buildEntityLogoDataUri((array) ($_FILES['entity_logo_' . $i] ?? []));
            if ($dataUri !== null) {
                $entityIdToLogoDataUri[$entityId] = $dataUri;
            }
        }
        $logosCreated = $brandingBuilder->applyLogos($entityIdToLogoDataUri);
    }

    $generalSettingsApplied = (new GeneralSettingsBuilder())->apply($config);
    $ticketTemplatesApplied = (new TicketTemplateBuilder())->apply($config);
    $helpdeskFormApplied = (new HelpdeskFormBuilder())->apply($config);
    $changeProblemTemplatesApplied = (new ChangeProblemTemplateBuilder())->apply($config);

    $messages = [];
    $messages[] = empty($created)
        ? __('Aucune entité à créer (mode mono-entité, ou arborescence vide).', 'configurationglpiauto')
        : sprintf(__('Structure créée : %s.', 'configurationglpiauto'), EntityBuilder::describe($created));
    if ($calendarMap !== []) {
        $messages[] = !empty($config->fields['calendar_holidays_enabled'])
            ? __('Calendrier créé et assigné, avec les jours fériés français.', 'configurationglpiauto')
            : __('Calendrier créé et assigné.', 'configurationglpiauto');
    }
    if ($slaMap !== []) {
        $messages[] = __('SLA créés et assignés.', 'configurationglpiauto');
    }
    if ($olaBuilt) {
        $messages[] = __('OLA (engagements internes) créés et assignés.', 'configurationglpiauto');
    }
    if ($tierGroupIds !== null) {
        $messages[] = __('Groupes de support N1/N2/N3 créés, escalade automatique configurée.', 'configurationglpiauto');
    }
    if ($perClientCount > 0) {
        $messages[] = sprintf(__('%d client(s) avec des réglages personnalisés.', 'configurationglpiauto'), $perClientCount);
    }
    if ($categoriesCreated > 0) {
        $messages[] = sprintf(__('%d catégories de tickets créées.', 'configurationglpiauto'), $categoriesCreated);
    }
    if ($servicesCreated > 0) {
        $messages[] = sprintf(__('%d services créés dans le catalogue.', 'configurationglpiauto'), $servicesCreated);
    }
    if ($statesCreated !== []) {
        $messages[] = sprintf(__('%d statuts d\'éléments créés.', 'configurationglpiauto'), count($statesCreated));
    }
    if ($waitReasonsCreated > 0) {
        $messages[] = sprintf(__('%d raisons d\'attente créées.', 'configurationglpiauto'), $waitReasonsCreated);
    }
    if ($brandingApplied) {
        $messages[] = __('Personnalisation graphique appliquée.', 'configurationglpiauto');
    }
    if ($logosCreated > 0) {
        $messages[] = sprintf(__('%d logo(s) d\'entité appliqué(s).', 'configurationglpiauto'), $logosCreated);
    }
    if ($generalSettingsApplied) {
        $messages[] = __('Réglages généraux GLPI appliqués.', 'configurationglpiauto');
    }
    if ($ticketTemplatesApplied) {
        $messages[] = __('Modèles de tickets créés et assignés aux profils.', 'configurationglpiauto');
    }
    if ($helpdeskFormApplied) {
        $messages[] = __('Champs masqués sur les formulaires de création en libre-service.', 'configurationglpiauto');
    }
    if ($ldapRulesCreated > 0) {
        $messages[] = sprintf(__('%d règle(s) de droits LDAP créées.', 'configurationglpiauto'), $ldapRulesCreated);
    }
    if ($taskCategoriesCreated > 0) {
        $messages[] = sprintf(__('%d catégories de tâches créées.', 'configurationglpiauto'), $taskCategoriesCreated);
    }
    if ($taskTemplatesCreated > 0) {
        $messages[] = sprintf(__('%d gabarits de tâche créés.', 'configurationglpiauto'), $taskTemplatesCreated);
    }
    if ($solutionTemplatesCreated > 0) {
        $messages[] = sprintf(__('%d gabarits de solution créés.', 'configurationglpiauto'), $solutionTemplatesCreated);
    }
    if ($followupTemplatesCreated > 0) {
        $messages[] = sprintf(__('%d gabarits de suivis créés.', 'configurationglpiauto'), $followupTemplatesCreated);
    }
    if ($validationTemplatesCreated > 0) {
        $messages[] = sprintf(__('%d gabarits de validation créés.', 'configurationglpiauto'), $validationTemplatesCreated);
    }
    if ($changeProblemTemplatesApplied) {
        $messages[] = __('Modèles de changement et de problème créés et assignés aux profils.', 'configurationglpiauto');
    }
    if ($locationsCreated > 0) {
        $messages[] = sprintf(__('%d lieux créés.', 'configurationglpiauto'), $locationsCreated);
    }
    if ($manufacturersCreated > 0) {
        $messages[] = sprintf(__('%d fabricants créés.', 'configurationglpiauto'), $manufacturersCreated);
    }
    if ($kbCategoriesCreated > 0) {
        $messages[] = sprintf(__('%d catégories de base de connaissances créées.', 'configurationglpiauto'), $kbCategoriesCreated);
    }
    if ($projectTaxonomyCreated > 0) {
        $messages[] = sprintf(__('%d types de projet/tâche de projet créés.', 'configurationglpiauto'), $projectTaxonomyCreated);
    }
    if ($projectTaskTemplatesCreated > 0) {
        $messages[] = sprintf(__('%d gabarits de tâches de projets créés.', 'configurationglpiauto'), $projectTaskTemplatesCreated);
    }
    Session::addMessageAfterRedirect(implode(' ', $messages));

    Html::redirect(ConfigurationProfile::getSearchURL());
}

Html::header(__('Assistant de configuration', 'configurationglpiauto'), $_SERVER['PHP_SELF'], 'admin', ConfigurationProfile::class);

$config = Config::getConfig();
$profiles = (new ConfigurationProfile())->find(['is_active' => 1], ['sort_order ASC']);

$profileDefaults = [];
foreach ($profiles as $profile) {
    $profileDefaults[$profile['id']] = ConfigurationProfile::getSuggestedDefaults($profile['type']);
}

$priorityLabels = [];
foreach (Config::PRIORITY_LEVELS as $priority) {
    $priorityLabels[$priority] = CommonITILObject::getPriorityName($priority);
}

\Glpi\Application\View\TemplateRenderer::getInstance()->display('@configurationglpiauto/wizard.html.twig', [
    'config'           => $config->fields,
    'profiles'         => $profiles,
    'profile_defaults' => $profileDefaults,
    'modes'            => Config::getModes(),
    'max_levels'       => Config::MAX_LEVELS,
    'entity_tree'      => $config->getEntityTree(),
    'sla_tiers'        => $config->getSlaTiers(),
    'ola_tiers'        => $config->getOlaTiers(),
    'priority_levels'  => Config::PRIORITY_LEVELS,
    'priority_labels'  => $priorityLabels,
    'category_branches' => $config->getCategoryBranches(),
    'categories_preview' => CategoryBuilder::getCategoriesPreview(),
    'services_preview' => ServiceCatalogBuilder::getServicesPreview(),
    'states_preview'   => StateBuilder::getStatesPreview(),
    'state_names'      => $config->getStateNames(),
    'state_recommended_names' => StateBuilder::RECOMMENDED_NAMES,
    'wait_reasons_preview' => WaitReasonBuilder::getReasonsPreview(),
    'native_profile_names' => Config::NATIVE_PROFILE_NAMES,
    'task_categories_preview' => TaskCategoryBuilder::getCategoriesPreview(),
    'task_templates_preview' => TaskTemplateBuilder::getLibraryPreview(),
    'solution_library_preview' => SolutionLibraryBuilder::getLibraryPreview(),
    'followup_library_preview' => FollowupLibraryBuilder::getLibraryPreview(),
    'validation_templates_preview' => ValidationTemplateBuilder::getLibraryPreview(),
    'manufacturers_preview' => ManufacturerBuilder::getManufacturersPreview(),
    'project_taxonomy_preview' => ProjectTaxonomyBuilder::getPreview(),
    'project_task_templates_preview' => ProjectTaskTemplateBuilder::getLibraryPreview(),
    'support_tiers_preview' => SupportTierBuilder::getTiersPreview(),
    'csrf_token'       => Session::getNewCSRFToken(),
]);

Html::footer();
