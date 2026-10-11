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

use ComputerModel;
use Dropdown;
use Entity;
use GlpiPlugin\Configurationglpiauto\Config;
use Manufacturer;
use PHPUnit\Framework\TestCase;
use Software;
use UserTitle;

/**
 * Issue #298 : pas de fabricant / éditeur, logiciel, modèle, type ou système d'exploitation en double.
 */
final class DropdownUnicityTest extends TestCase
{
    private string $brand;

    protected function setUp(): void
    {
        $this->brand = 'Phpunitbrand' . mt_rand(100000, 999999);
        $this->setEnabled(true);
        $_SESSION['MESSAGE_AFTER_REDIRECT'] = [];
    }

    protected function tearDown(): void
    {
        $this->setEnabled(true);
    }

    private function setEnabled(bool $enabled): void
    {
        $config = Config::getConfig();
        $config->update(['id' => $config->getID(), 'dropdown_unicity_enabled' => $enabled ? 1 : 0]);
    }

    public function testADuplicateManufacturerIsRefusedWhateverItsCaseAndSpaces(): void
    {
        $id = (int) (new Manufacturer())->add(['name' => $this->brand]);
        $this->assertGreaterThan(0, $id);

        $this->assertFalse((new Manufacturer())->add(['name' => '  ' . strtoupper($this->brand) . ' ']));
        $this->assertFalse((new Manufacturer())->add(['name' => $this->brand]));
        $this->assertStringContainsString(
            'n° ' . $id,
            implode(' ', array_merge(...array_values($_SESSION['MESSAGE_AFTER_REDIRECT']))),
            'Le message indique l\'entrée existante.'
        );
    }

    public function testInnerSpacesAreReducedBeforeComparing(): void
    {
        (new Manufacturer())->add(['name' => $this->brand . ' Group']);

        $this->assertFalse((new Manufacturer())->add(['name' => $this->brand . "   \t Group"]));
    }

    public function testADifferentNameIsAccepted(): void
    {
        (new Manufacturer())->add(['name' => $this->brand]);

        $this->assertGreaterThan(0, (int) (new Manufacturer())->add(['name' => $this->brand . ' Networks']));
    }

    public function testModelsAreCoveredToo(): void
    {
        (new ComputerModel())->add(['name' => $this->brand . ' X1']);

        $this->assertFalse((new ComputerModel())->add(['name' => strtolower($this->brand) . ' x1']));
    }

    public function testListsOutsideTheScopeAreUntouched(): void
    {
        (new UserTitle())->add(['name' => $this->brand]);

        $this->assertGreaterThan(0, (int) (new UserTitle())->add(['name' => $this->brand]));
    }

    public function testNothingIsRefusedWhenTheOptionIsOff(): void
    {
        $this->setEnabled(false);
        (new Manufacturer())->add(['name' => $this->brand]);

        $this->assertGreaterThan(0, (int) (new Manufacturer())->add(['name' => $this->brand]));
    }

    public function testRenamingToAnExistingNameIsRefused(): void
    {
        (new Manufacturer())->add(['name' => $this->brand]);
        $other = new Manufacturer();
        $other->getFromDB((int) $other->add(['name' => $this->brand . ' Old']));

        $this->assertFalse($other->update(['id' => $other->getID(), 'name' => strtoupper($this->brand)]));
        $other->getFromDB($other->getID());
        $this->assertSame($this->brand . ' Old', $other->fields['name']);
    }

    public function testAnExistingDuplicateCanStillBeEdited(): void
    {
        // Doublons créés avant l'activation de l'option : on peut encore les modifier.
        $this->setEnabled(false);
        (new Manufacturer())->add(['name' => $this->brand]);
        $twin = new Manufacturer();
        $twin->getFromDB((int) $twin->add(['name' => $this->brand]));
        $this->setEnabled(true);

        $this->assertTrue($twin->update(['id' => $twin->getID(), 'name' => $this->brand, 'comment' => 'à fusionner']));
    }

    public function testTheInventoryReusesTheExistingManufacturer(): void
    {
        $id = (int) (new Manufacturer())->add(['name' => $this->brand]);

        $this->assertSame($id, (int) Dropdown::importExternal('Manufacturer', strtoupper($this->brand)));
    }

    private function software(string $name, int $publisher, int $entity = 0, int $recursive = 0): int|false
    {
        $id = (new Software())->add([
            'name' => $name, 'manufacturers_id' => $publisher, 'entities_id' => $entity, 'is_recursive' => $recursive,
        ]);

        return $id === false ? false : (int) $id;
    }

    private function childEntity(): int
    {
        return (int) (new Entity())->add(['name' => 'PHPUnit unicité ' . mt_rand(), 'entities_id' => 0]);
    }

    public function testADuplicateSoftwareFromTheSamePublisherIsRefused(): void
    {
        $publisher = (int) (new Manufacturer())->add(['name' => $this->brand]);
        $this->assertIsInt($this->software($this->brand . ' Office', $publisher));

        $this->assertFalse($this->software(strtolower($this->brand) . '  office', $publisher));
    }

    public function testTheSameSoftwareNameFromAnotherPublisherIsAccepted(): void
    {
        // Même identité que GLPI (nom + éditeur) : le rejeu du dictionnaire des logiciels n'est
        // jamais bloqué.
        $publisher = (int) (new Manufacturer())->add(['name' => $this->brand]);
        $other = (int) (new Manufacturer())->add(['name' => $this->brand . ' Labs']);
        $this->software($this->brand . ' Viewer', $publisher);

        $this->assertIsInt($this->software($this->brand . ' Viewer', $other));
    }

    public function testEachEntityCanHaveItsOwnSoftware(): void
    {
        $publisher = (int) (new Manufacturer())->add(['name' => $this->brand]);
        $this->software($this->brand . ' Agent', $publisher, 0, 0);

        $this->assertIsInt($this->software($this->brand . ' Agent', $publisher, $this->childEntity()));
    }

    public function testARecursiveSoftwareOfAParentEntityIsADuplicate(): void
    {
        $publisher = (int) (new Manufacturer())->add(['name' => $this->brand]);
        $this->software($this->brand . ' Suite', $publisher, 0, 1);

        $this->assertFalse($this->software($this->brand . ' Suite', $publisher, $this->childEntity()));
    }

    public function testTheSoftwareInventoryIsNotBlocked(): void
    {
        $publisher = (int) (new Manufacturer())->add(['name' => $this->brand]);
        $id = $this->software($this->brand . ' Tool', $publisher);

        // Chemin du cœur (rejeu du dictionnaire des logiciels) : recherche nom + éditeur, puis création.
        $this->assertSame($id, (int) (new Software())->addOrRestoreFromTrash($this->brand . ' Tool', $this->brand, 0));
    }
}
