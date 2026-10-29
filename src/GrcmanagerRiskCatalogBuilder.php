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

/**
 * Issue #339 : quand le plugin jumeau GRC Manager est actif, amorce son registre des risques avec
 * les risques types de son catalogue (point de départ de l'appréciation des risques ISO 27001) :
 * risques d'infrastructure, et, sur demande séparée, risques des systèmes d'IA (ISO/IEC 27090,
 * GRC Manager 2.17 et suivants).
 *
 * L'import est celui de GRC Manager (RiskCatalog::import() : description, menaces, questions à se
 * poser, mesures de l'Annexe A liées), appelé tel quel. Chaque risque est créé « identifié », à
 * évaluer. Non destructif : un risque type déjà importé n'est jamais recréé (GRC Manager garde la
 * trace des imports), relancer l'assistant ne fait donc pas de doublon.
 *
 * Droit : RiskCatalog::import() ne vérifie aucun droit (c'est la page du catalogue qui le fait) ;
 * le droit de création du registre des risques, sensible, est donc vérifié ici.
 *
 * Options facultatives (décochées par défaut) : elles créent des données dans un registre sensible.
 */
class GrcmanagerRiskCatalogBuilder
{
    /** GRC Manager 2.17 range ses classes dans src/ : le nouveau nom d'abord, l'ancien avant 2.17. */
    private const CATALOG_CLASSES = ['GlpiPlugin\\Grcmanager\\RiskCatalog', 'PluginGrcmanagerRiskCatalog'];

    private const RISK_CLASSES = ['GlpiPlugin\\Grcmanager\\Risk', 'PluginGrcmanagerRisk'];

    /** Liste des risques types (GRC Manager 2.10 et suivants). */
    private const KEYS_CLASS = 'GlpiPlugin\\Grcmanager\\Services\\Risk\\InfrastructureRiskCatalog';

    /**
     * @return int nombre de risques ajoutés au registre
     */
    public function build(Config $config): int
    {
        $infrastructure = !empty($config->fields['grcmanager_risk_catalog_enabled']);
        $ai = !empty($config->fields['grcmanager_ai_risks_enabled']) && self::hasAiRisks();
        if ((!$infrastructure && !$ai) || !self::isAvailable()) {
            return 0;
        }
        $riskClass = self::firstExisting(self::RISK_CLASSES);
        $catalogClass = self::firstExisting(self::CATALOG_CLASSES);
        if ($riskClass === null || $catalogClass === null || !$riskClass::canCreate()) {
            return 0;
        }

        $keysClass = self::KEYS_CLASS;
        $requested = [];
        foreach ($keysClass::keys() as $key) {
            $isAi = self::hasAiRisks() && $keysClass::group($key) === $keysClass::GROUP_AI;
            if ($isAi ? $ai : $infrastructure) {
                $requested[] = $key;
            }
        }

        return count($catalogClass::import($requested));
    }

    public static function isAvailable(): bool
    {
        return GrcmanagerCisoGroupBuilder::isGrcmanagerActive()
            && self::firstExisting(self::RISK_CLASSES) !== null
            && self::firstExisting(self::CATALOG_CLASSES) !== null
            && class_exists(self::KEYS_CLASS);
    }

    /** Risques des systèmes d'IA : GRC Manager 2.17 et suivants. */
    public static function hasAiRisks(): bool
    {
        return class_exists(self::KEYS_CLASS) && method_exists(self::KEYS_CLASS, 'group');
    }

    /**
     * @param list<string> $classes
     */
    private static function firstExisting(array $classes): ?string
    {
        foreach ($classes as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }
}
