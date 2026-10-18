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

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration\Audit;

use Computer;
use GlpiPlugin\Configurationglpiauto\Audit\Checks\DuplicateSoftwareCheck;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\SoftwareMerger;
use Item_SoftwareVersion;
use Manufacturer;
use PHPUnit\Framework\TestCase;
use Software;
use SoftwareLicense;
use SoftwareVersion;

/**
 * Issue #305 : fusion des logiciels en double sans perdre une installation ni une licence.
 */
final class DuplicateSoftwareCheckTest extends TestCase
{
    private string $suffix;

    private int $publisher;

    protected function setUp(): void
    {
        $this->suffix = (string) mt_rand(100000, 999999);
        // Doublons d'avant le blocage (#298) : on les crée option désactivée.
        $config = Config::getConfig();
        $config->update(['id' => $config->getID(), 'dropdown_unicity_enabled' => 0]);
        $this->publisher = (int) (new Manufacturer())->add(['name' => 'PHPUnit Editeur ' . $this->suffix]);
    }

    protected function tearDown(): void
    {
        $config = Config::getConfig();
        $config->update(['id' => $config->getID(), 'dropdown_unicity_enabled' => 1]);
    }

    private function software(string $name, int $publisher): int
    {
        return (int) (new Software())->add(['name' => $name, 'manufacturers_id' => $publisher, 'entities_id' => 0]);
    }

    private function version(int $softwareId, string $name): int
    {
        return (int) (new SoftwareVersion())->add(['softwares_id' => $softwareId, 'name' => $name, 'entities_id' => 0]);
    }

    private function install(int $computerId, int $versionId): void
    {
        global $DB;
        $DB->insert(Item_SoftwareVersion::getTable(), ['itemtype' => Computer::class, 'items_id' => $computerId, 'softwareversions_id' => $versionId, 'entities_id' => 0]);
    }

    /**
     * @return list<string> « nom de version » installées sur la machine
     */
    private static function installed(int $computerId): array
    {
        global $DB;
        $names = [];
        foreach ($DB->request([
            'SELECT'     => [SoftwareVersion::getTable() . '.name', SoftwareVersion::getTable() . '.softwares_id'],
            'FROM'       => Item_SoftwareVersion::getTable(),
            'INNER JOIN' => [SoftwareVersion::getTable() => ['FKEY' => [Item_SoftwareVersion::getTable() => 'softwareversions_id', SoftwareVersion::getTable() => 'id']]],
            'WHERE'      => [Item_SoftwareVersion::getTable() . '.itemtype' => Computer::class, Item_SoftwareVersion::getTable() . '.items_id' => $computerId],
        ]) as $row) {
            $names[] = $row['softwares_id'] . ':' . $row['name'];
        }
        sort($names);

        return $names;
    }

    public function testDuplicatesAreMergedWithoutLosingInstallationsOrLicences(): void
    {
        $name = 'PHPUnit Outil ' . $this->suffix;
        $keep = $this->software($name, $this->publisher);
        $twin = $this->software(strtoupper($name), $this->publisher);
        $keep10 = $this->version($keep, '1.0');
        $twin10 = $this->version($twin, '1.0');
        $twin20 = $this->version($twin, '2.0');
        $both = (int) (new Computer())->add(['name' => 'PHPUNIT-DEUX-' . $this->suffix, 'entities_id' => 0]);
        $onlyTwin = (int) (new Computer())->add(['name' => 'PHPUNIT-DOUBLON-' . $this->suffix, 'entities_id' => 0]);
        $newer = (int) (new Computer())->add(['name' => 'PHPUNIT-V2-' . $this->suffix, 'entities_id' => 0]);
        $this->install($both, $keep10);
        $this->install($both, $twin10);
        $this->install($onlyTwin, $twin10);
        $this->install($newer, $twin20);
        $license = (int) (new SoftwareLicense())->add(['name' => 'PHPUnit licence ' . $this->suffix, 'softwares_id' => $twin, 'softwareversions_id_buy' => $twin10, 'entities_id' => 0]);

        $group = array_values(array_filter(SoftwareMerger::findGroups(), static fn (array $g): bool => $g['keep']['id'] === $keep));
        $this->assertCount(1, $group);
        $this->assertSame([$twin], array_column($group[0]['duplicates'], 'id'));
        $this->assertSame(3, $group[0]['installations'], 'aperçu : installations à reporter');

        (new DuplicateSoftwareCheck())->fix();

        $this->assertFalse((new Software())->getFromDB($twin), 'doublon supprimé');
        $this->assertSame(["$keep:1.0"], self::installed($both), 'une seule ligne quand les deux étaient installées');
        $this->assertSame(["$keep:1.0"], self::installed($onlyTwin));
        $this->assertSame(["$keep:2.0"], self::installed($newer), 'version propre au doublon reportée');
        $licence = new SoftwareLicense();
        $licence->getFromDB($license);
        $this->assertSame($keep, (int) $licence->fields['softwares_id']);
        $this->assertSame($keep10, (int) $licence->fields['softwareversions_id_buy']);
    }

    public function testTheSameNameFromAnotherPublisherIsNotADuplicate(): void
    {
        $name = 'PHPUnit Visionneuse ' . $this->suffix;
        $mine = $this->software($name, $this->publisher);
        $other = $this->software($name, (int) (new Manufacturer())->add(['name' => 'PHPUnit Autre ' . $this->suffix]));

        $ids = [];
        foreach (SoftwareMerger::findGroups() as $group) {
            $ids = array_merge($ids, [$group['keep']['id']], array_column($group['duplicates'], 'id'));
        }

        $this->assertNotContains($mine, $ids);
        $this->assertNotContains($other, $ids);
    }
}
