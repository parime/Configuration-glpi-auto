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

namespace GlpiPlugin\Configurationglpiauto\Tests\Unit;

use GlpiPlugin\Configurationglpiauto\Blueprint\BlueprintSerializer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BlueprintSerializerTest extends TestCase
{
    private const SAMPLE_CONFIG_FIELDS = [
        'id' => 1,
        'configurationprofiles_id' => 4,
        'date_creation' => '2026-01-01 00:00:00',
        'date_mod' => '2026-01-02 00:00:00',
        'entity_mode' => 'multi',
        'state_enabled' => 1,
        'state_names' => '["Attribué","En stock"]',
        'entity_tree' => '[{"name":"Client A","children":[]}]',
        'branding_primary_color' => '#123456',
    ];

    public function testExportOmitsInstanceSpecificFields(): void
    {
        $result = BlueprintSerializer::export(self::SAMPLE_CONFIG_FIELDS, 'Mon profil', '1.4.0');

        $this->assertArrayNotHasKey('id', $result['config']);
        $this->assertArrayNotHasKey('configurationprofiles_id', $result['config']);
        $this->assertArrayNotHasKey('date_creation', $result['config']);
        $this->assertArrayNotHasKey('date_mod', $result['config']);
    }

    public function testExportDecodesJsonEncodedFieldsIntoRealNestedJson(): void
    {
        $result = BlueprintSerializer::export(self::SAMPLE_CONFIG_FIELDS, 'Mon profil', '1.4.0');

        $this->assertSame(['Attribué', 'En stock'], $result['config']['state_names']);
        $this->assertSame([['name' => 'Client A', 'children' => []]], $result['config']['entity_tree']);
    }

    public function testExportKeepsScalarFieldsAsIs(): void
    {
        $result = BlueprintSerializer::export(self::SAMPLE_CONFIG_FIELDS, 'Mon profil', '1.4.0');

        $this->assertSame(1, $result['config']['state_enabled']);
        $this->assertSame('#123456', $result['config']['branding_primary_color']);
        $this->assertSame('multi', $result['config']['entity_mode']);
    }

    public function testExportCarriesMetadata(): void
    {
        $result = BlueprintSerializer::export(self::SAMPLE_CONFIG_FIELDS, 'Mon profil', '1.4.0');

        $this->assertSame(BlueprintSerializer::FORMAT_VERSION, $result['format_version']);
        $this->assertSame('1.4.0', $result['plugin_version']);
        $this->assertSame('Mon profil', $result['profile_name']);
        $this->assertNotSame('', $result['exported_at']);
    }

    public function testExportThenImportRoundTripsFaithfully(): void
    {
        $currentDefaults = [
            'entity_mode' => 'mono',
            'state_enabled' => 0,
            'state_names' => '[]',
            'entity_tree' => '[]',
            'branding_primary_color' => '#206bc4',
        ];

        $exported = BlueprintSerializer::export(self::SAMPLE_CONFIG_FIELDS, 'Mon profil', '1.4.0');
        $imported = BlueprintSerializer::import($exported, $currentDefaults);

        $this->assertSame('multi', $imported['entity_mode']);
        $this->assertSame(1, $imported['state_enabled']);
        $this->assertSame('#123456', $imported['branding_primary_color']);
        // Ré-encodées vers la forme chaîne JSON attendue par Config, fidèlement — sans
        // JSON_UNESCAPED_UNICODE, comme Config::prepareInput() lui-même le fait pour toute mise
        // à jour réelle (seule la toute première valeur d'usine dans getDefaults() utilise ce
        // drapeau, jamais les écritures suivantes, vérifié en lisant Config.php ligne par ligne).
        $this->assertSame('["Attribu\\u00e9","En stock"]', $imported['state_names']);
        $this->assertSame('[{"name":"Client A","children":[]}]', $imported['entity_tree']);
    }

    public function testImportFillsMissingFieldsWithRealDefaultsNeverNull(): void
    {
        $blueprint = [
            'format_version' => BlueprintSerializer::FORMAT_VERSION,
            'config' => ['state_enabled' => 1],
        ];
        $currentDefaults = [
            'state_enabled' => 0,
            'branding_primary_color' => '#206bc4',
            'entity_mode' => 'mono',
        ];

        $imported = BlueprintSerializer::import($blueprint, $currentDefaults);

        $this->assertSame(1, $imported['state_enabled']);
        // Absent from the Blueprint (e.g. exported by an older plugin version) -> real default.
        $this->assertSame('#206bc4', $imported['branding_primary_color']);
        $this->assertSame('mono', $imported['entity_mode']);
    }

    public function testImportRejectsAMissingFormatVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BlueprintSerializer::import(['config' => []], []);
    }

    public function testImportRejectsAnUnknownFormatVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BlueprintSerializer::import(['format_version' => 999, 'config' => []], []);
    }

    public function testImportRejectsAMissingConfigSection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BlueprintSerializer::import(['format_version' => BlueprintSerializer::FORMAT_VERSION], []);
    }

    public function testImportIgnoresInstanceSpecificFieldsEvenIfPresentInTheFile(): void
    {
        // configurationprofiles_id IS a real Config::getDefaults() key (the "last profile picked"
        // pointer) — without the explicit guard in import(), a Blueprint exported from instance A
        // pointing at profile #42 would silently overwrite instance B's own unrelated pointer.
        $blueprint = [
            'format_version' => BlueprintSerializer::FORMAT_VERSION,
            'config' => ['configurationprofiles_id' => 42, 'entity_mode' => 'multi'],
        ];
        $currentDefaults = ['entity_mode' => 'mono', 'configurationprofiles_id' => 7];

        $imported = BlueprintSerializer::import($blueprint, $currentDefaults);

        $this->assertSame(7, $imported['configurationprofiles_id']);
        $this->assertSame('multi', $imported['entity_mode']);
    }

    // --- onlyFields (rollback sélectif, issue #114) ---

    private const SELECTIVE_BLUEPRINT = [
        'format_version' => 1,
        'config' => [
            'entity_mode' => 'multi',
            'state_enabled' => 1,
            'branding_primary_color' => '#123456',
        ],
    ];

    private const SELECTIVE_DEFAULTS = [
        'entity_mode' => 'mono',
        'state_enabled' => 0,
        'branding_primary_color' => '#206bc4',
    ];

    public function testImportWithOnlyFieldsRestrictsWhichFieldsAreOverridden(): void
    {
        $imported = BlueprintSerializer::import(self::SELECTIVE_BLUEPRINT, self::SELECTIVE_DEFAULTS, ['entity_mode']);

        $this->assertSame('multi', $imported['entity_mode'], 'Selected field must come from the Blueprint.');
        $this->assertSame(0, $imported['state_enabled'], 'Unselected field must stay at the real default, never the Blueprint value.');
        $this->assertSame('#206bc4', $imported['branding_primary_color']);
    }

    public function testImportWithOnlyFieldsEmptyArrayChangesNothing(): void
    {
        // [] (nothing selected) is deliberately distinct from null (everything selected) — an
        // admin who unchecks every box in the restore screen must get a strict no-op, not a full
        // restore by accident.
        $imported = BlueprintSerializer::import(self::SELECTIVE_BLUEPRINT, self::SELECTIVE_DEFAULTS, []);

        $this->assertSame(self::SELECTIVE_DEFAULTS, $imported);
    }

    public function testImportWithNullOnlyFieldsIsBackwardCompatibleFullImport(): void
    {
        $withoutThirdArg = BlueprintSerializer::import(self::SELECTIVE_BLUEPRINT, self::SELECTIVE_DEFAULTS);
        $withExplicitNull = BlueprintSerializer::import(self::SELECTIVE_BLUEPRINT, self::SELECTIVE_DEFAULTS, null);

        $this->assertSame($withoutThirdArg, $withExplicitNull);
        $this->assertSame('multi', $withExplicitNull['entity_mode']);
        $this->assertSame(1, $withExplicitNull['state_enabled']);
    }

    // --- diff() (rollback sélectif, issue #114) ---

    public function testDiffReturnsOnlyFieldsThatActuallyDiffer(): void
    {
        $current = ['entity_mode' => 'mono', 'state_enabled' => 0, 'branding_primary_color' => '#206bc4'];
        $snapshot = ['entity_mode' => 'multi', 'state_enabled' => 0, 'branding_primary_color' => '#206bc4'];

        $diff = BlueprintSerializer::diff($current, $snapshot);

        $this->assertSame(['entity_mode' => ['current' => 'mono', 'snapshot' => 'multi']], $diff);
    }

    public function testDiffDecodesJsonEncodedFieldsBeforeComparingAndIgnoresKeyOrderDifferences(): void
    {
        // Same sla_tiers content, keys in a different order — must NOT show as a diff, otherwise
        // every restore screen would show spurious "changes" caused purely by PHP's own json_encode
        // key ordering rather than a real configuration difference.
        $current = ['sla_tiers' => json_encode(['6' => ['tto_hours' => 1, 'ttr_hours' => 2], '5' => ['tto_hours' => 2, 'ttr_hours' => 4]])];
        $snapshot = ['sla_tiers' => json_encode(['5' => ['tto_hours' => 2, 'ttr_hours' => 4], '6' => ['tto_hours' => 1, 'ttr_hours' => 2]])];

        $this->assertSame([], BlueprintSerializer::diff($current, $snapshot));
    }

    public function testDiffDetectsARealChangeInsideAJsonEncodedField(): void
    {
        $current = ['sla_tiers' => json_encode(['6' => ['tto_hours' => 1, 'ttr_hours' => 2]])];
        $snapshot = ['sla_tiers' => json_encode(['6' => ['tto_hours' => 9, 'ttr_hours' => 2]])];

        $diff = BlueprintSerializer::diff($current, $snapshot);

        $this->assertArrayHasKey('sla_tiers', $diff);
        $this->assertSame(1, $diff['sla_tiers']['current']['6']['tto_hours']);
        $this->assertSame(9, $diff['sla_tiers']['snapshot']['6']['tto_hours']);
    }

    public function testDiffIgnoresInstanceSpecificFieldsEvenWhenTheyDiffer(): void
    {
        $current = ['id' => 1, 'configurationprofiles_id' => 7, 'entity_mode' => 'mono'];
        $snapshot = ['id' => 1, 'configurationprofiles_id' => 42, 'entity_mode' => 'mono'];

        $this->assertSame([], BlueprintSerializer::diff($current, $snapshot));
    }

    public function testDiffReturnsEmptyArrayWhenSnapshotEqualsCurrentInEveryField(): void
    {
        $fields = ['entity_mode' => 'mono', 'state_enabled' => 1];

        $this->assertSame([], BlueprintSerializer::diff($fields, $fields));
    }

    // --- filterConfig() (export sélectif, issue #117) ---

    private const EXPORT_FOR_FILTERING = [
        'format_version' => 1,
        'plugin_version' => '1.4.0',
        'exported_at' => '2026-09-19T00:00:00+00:00',
        'profile_name' => 'Mon profil',
        'config' => ['entity_mode' => 'multi', 'state_enabled' => 1, 'branding_primary_color' => '#123456'],
    ];

    public function testFilterConfigKeepsOnlyTheRequestedFields(): void
    {
        $filtered = BlueprintSerializer::filterConfig(self::EXPORT_FOR_FILTERING, ['entity_mode']);

        $this->assertSame(['entity_mode' => 'multi'], $filtered['config']);
    }

    public function testFilterConfigPreservesMetadataUntouched(): void
    {
        $filtered = BlueprintSerializer::filterConfig(self::EXPORT_FOR_FILTERING, ['entity_mode']);

        $this->assertSame(1, $filtered['format_version']);
        $this->assertSame('1.4.0', $filtered['plugin_version']);
        $this->assertSame('2026-09-19T00:00:00+00:00', $filtered['exported_at']);
        $this->assertSame('Mon profil', $filtered['profile_name']);
    }

    public function testFilterConfigWithEmptyFieldListProducesAnEmptyConfig(): void
    {
        $filtered = BlueprintSerializer::filterConfig(self::EXPORT_FOR_FILTERING, []);

        $this->assertSame([], $filtered['config']);
    }

    public function testFilterConfigIgnoresRequestedFieldsThatDoNotExistInConfig(): void
    {
        $filtered = BlueprintSerializer::filterConfig(self::EXPORT_FOR_FILTERING, ['entity_mode', 'does_not_exist']);

        $this->assertSame(['entity_mode' => 'multi'], $filtered['config']);
    }
}
