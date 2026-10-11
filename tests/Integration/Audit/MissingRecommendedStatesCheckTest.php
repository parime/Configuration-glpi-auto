<?php

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration\Audit;

use GlpiPlugin\Configurationglpiauto\Audit\Checks\MissingRecommendedStatesCheck;
use GlpiPlugin\Configurationglpiauto\StateBuilder;
use PHPUnit\Framework\TestCase;
use State;

/**
 * Issue #293 : statut recommandé « Détruit » absent d'une instance configurée avant le 21/09/2026.
 */
final class MissingRecommendedStatesCheckTest extends TestCase
{
    /** @var array<int, string> statuts recommandés renommés pendant le test, id => nom d'origine */
    private array $renamed = [];

    /** @var list<int> statuts créés par la correction */
    private array $created = [];

    protected function setUp(): void
    {
        // Au moins un statut, sinon NoStatesConfiguredCheck prend le relais et ce contrôle se tait.
        $state = new State();
        if (!$state->getFromDBByCrit(['name' => 'PHPUnit statut existant'])) {
            $this->created[] = (int) $state->add(['name' => 'PHPUnit statut existant', 'entities_id' => 0, 'is_recursive' => 1]);
        }
        // Simule une instance configurée avant l'ajout de « Détruit ».
        $destroyed = new State();
        if ($destroyed->getFromDBByCrit(['name' => 'Détruit'])) {
            $this->renamed[$destroyed->getID()] = 'Détruit';
            $destroyed->update(['id' => $destroyed->getID(), 'name' => 'PHPUnit Détruit mis de côté']);
        }
    }

    protected function tearDown(): void
    {
        $state = new State();
        foreach ($this->created as $id) {
            $state->delete(['id' => $id], true);
        }
        foreach ($this->renamed as $id => $name) {
            $state->update(['id' => $id, 'name' => $name]);
        }
    }

    public function testMissingDestroyedStateIsReportedAndFixable(): void
    {
        $this->assertContains('Détruit', StateBuilder::getMissingRecommendedNames());

        $findings = (new MissingRecommendedStatesCheck())->analyze();

        $this->assertCount(1, $findings);
        $this->assertTrue($findings[0]->fixable);
        $this->assertStringContainsString('Détruit', $findings[0]->description);
    }

    public function testFixCreatesTheMissingStates(): void
    {
        $missingBefore = StateBuilder::getMissingRecommendedNames();

        (new MissingRecommendedStatesCheck())->fix();

        foreach ($missingBefore as $name) {
            $state = new State();
            $this->assertTrue($state->getFromDBByCrit(['name' => $name]), "statut « $name » créé");
            $this->created[] = $state->getID();
        }
        $this->assertSame([], StateBuilder::getMissingRecommendedNames());
        $this->assertSame([], (new MissingRecommendedStatesCheck())->analyze());
    }
}
