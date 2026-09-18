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
}
