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

use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use PHPUnit\Framework\TestCase;

final class ConfigurationProfileTest extends TestCase
{
    public function testGetSuggestedDefaultsForMinimalOnlySetsEntityMode(): void
    {
        $defaults = ConfigurationProfile::getSuggestedDefaults('minimal');

        $this->assertSame(['entity_mode' => Config::MODE_MONO], $defaults);
    }

    public function testGetSuggestedDefaultsForCustomReturnsNoSuggestionsAtAll(): void
    {
        $this->assertSame([], ConfigurationProfile::getSuggestedDefaults('custom'));
    }

    public function testGetSuggestedDefaultsForAnUnknownTypeReturnsNoSuggestions(): void
    {
        $this->assertSame([], ConfigurationProfile::getSuggestedDefaults('does-not-exist'));
    }

    public function testGetSuggestedDefaultsForMultiSiteUsesTheStandardSlaTiersAndNoAstreinte(): void
    {
        $defaults = ConfigurationProfile::getSuggestedDefaults('multi_site');

        $this->assertSame(Config::MODE_MULTI_SAME_COMPANY, $defaults['entity_mode']);
        $this->assertTrue($defaults['sla_enabled']);
        $this->assertFalse($defaults['sla_astreinte']);
        $this->assertSame(Config::getDefaultSlaTiers(), $defaults['sla_tiers']);
        $this->assertSame(Config::CATEGORY_BRANCH_KEYS, $defaults['category_branches']);
    }

    /**
     * MSP gets round-the-clock coverage (`sla_astreinte = true`) and a strictly tighter SLA/OLA
     * table than the standard baseline at every priority level — characteristic of the MSP business
     * model, not of "being bigger" (see the class's own docblock).
     */
    public function testGetSuggestedDefaultsForMspOverridesAstreinteAndUsesATighterSlaTable(): void
    {
        $defaults = ConfigurationProfile::getSuggestedDefaults('msp');

        $this->assertSame(Config::MODE_MULTI_MSP, $defaults['entity_mode']);
        $this->assertTrue($defaults['sla_astreinte']);

        $standard = Config::getDefaultSlaTiers();
        foreach (Config::PRIORITY_LEVELS as $priority) {
            $mspTier = $defaults['sla_tiers'][(string) $priority];
            $standardTier = $standard[(string) $priority];
            $this->assertLessThanOrEqual($standardTier['tto_hours'], $mspTier['tto_hours']);
            $this->assertLessThan($standardTier['ttr_hours'], $mspTier['ttr_hours']);
        }
    }

    public function testPrepareInputForAddDefaultsAMissingOrInvalidTypeToCustom(): void
    {
        $item = new ConfigurationProfile();

        $this->assertSame('custom', $item->prepareInputForAdd([])['type']);
        $this->assertSame('custom', $item->prepareInputForAdd(['type' => 'not-a-real-type'])['type']);
        $this->assertSame('msp', $item->prepareInputForAdd(['type' => 'msp'])['type']);
    }

    public function testPrepareInputForUpdateAppliesTheSameTypeDefaulting(): void
    {
        $item = new ConfigurationProfile();

        $this->assertSame('custom', $item->prepareInputForUpdate(['type' => 'bogus'])['type']);
    }

    public function testGetSpecificValueToDisplayMapsTheTypeKeyToItsFrenchLabel(): void
    {
        $label = ConfigurationProfile::getSpecificValueToDisplay('type', ['type' => 'msp']);

        $this->assertSame(ConfigurationProfile::getTypes()['msp'], $label);
    }

    public function testGetSpecificValueToDisplayFallsBackToTheRawValueForAnUnknownType(): void
    {
        $this->assertSame('inconnu', ConfigurationProfile::getSpecificValueToDisplay('type', ['type' => 'inconnu']));
    }

    public function testRawSearchOptionsExposesNameDescriptionTypeAndActiveColumns(): void
    {
        $options = (new ConfigurationProfile())->rawSearchOptions();

        $fields = array_column(array_filter($options, static fn (array $o) => isset($o['field'])), 'field');
        $this->assertContains('name', $fields);
        $this->assertContains('description', $fields);
        $this->assertContains('type', $fields);
        $this->assertContains('is_active', $fields);
    }

    public function testGetSearchUrlPointsToTheWizardNotTheGenericCrudList(): void
    {
        $this->assertStringEndsWith('/plugins/configurationglpiauto/front/wizard.php', ConfigurationProfile::getSearchURL(false));
    }

    /**
     * `getTable()` is explicitly overridden (see the class's own docblock on the `Entity\`
     * namespace collision pitfall) — this confirms the real table actually round-trips a row,
     * not just that the override returns a plausible-looking string.
     */
    public function testCanBeAddedToAndReadBackFromItsOwnRealTable(): void
    {
        $item = new ConfigurationProfile();
        $id = $item->add(['name' => 'Test — Profil MSP', 'type' => 'msp']);

        $this->assertGreaterThan(0, $id);

        $reloaded = new ConfigurationProfile();
        $this->assertTrue($reloaded->getFromDB($id));
        $this->assertSame('msp', $reloaded->fields['type']);

        $reloaded->delete(['id' => $id], true);
    }
}
