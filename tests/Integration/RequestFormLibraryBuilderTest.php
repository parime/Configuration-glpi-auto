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

use Glpi\Form\AnswersHandler\AnswersHandler;
use Glpi\Form\Category as FormCategory;
use Glpi\Form\EndUserInputNameProvider;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\Section;
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\RequestFormLibraryBuilder;
use ITILCategory;
use PHPUnit\Framework\TestCase;
use Ticket;
use TicketValidation;
use User;

/**
 * Issue #346 : les 13 formulaires décrits par `RequestFormLibraryBuilder` sont créés, et chacun
 * produit réellement un ticket (catégorie, type, titre calculé) quand on le soumet avec ses seules
 * réponses obligatoires. Les formulaires à approbation créent l'approbation au nom de la personne
 * désignée ; les questions conditionnelles ne sont exigées que lorsqu'elles sont affichées.
 */
final class RequestFormLibraryBuilderTest extends TestCase
{
    /** @var int[] */
    private array $ticketIdsToDelete = [];

    /** @var string[] */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->ticketIdsToDelete as $id) {
            (new Ticket())->delete(['id' => $id], true);
        }
        foreach (RequestFormLibraryBuilder::getFormNames() as $name) {
            $form = new Form();
            if ($form->getFromDBByCrit(['name' => $name])) {
                $form->delete(['id' => $form->getID()], true);
            }
        }
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
        unset($_POST);
    }

    private function buildConfig(): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'category_branches' => json_encode(['it']),
            'service_catalog_enabled' => 1,
        ]);

        return $config;
    }

    private function build(): int
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);

        return (new RequestFormLibraryBuilder())->build($config);
    }

    private function form(string $name): Form
    {
        $form = new Form();
        $this->assertTrue($form->getFromDBByCrit(['name' => $name]), "Le formulaire « $name » doit exister.");

        return $form;
    }

    /**
     * @return array<string, Question> clé de définition => question
     */
    private function questions(Form $form, array $definition): array
    {
        $section = new Section();
        $this->assertTrue($section->getFromDBByCrit(['forms_forms_id' => $form->getID()]));
        $questions = [];
        foreach ($definition['questions'] as $rank => $question) {
            $item = new Question();
            $this->assertTrue($item->getFromDBByCrit(['forms_sections_id' => $section->getID(), 'vertical_rank' => $rank]));
            $questions[$question['key']] = $item;
        }

        return $questions;
    }

    /**
     * Réponse valide pour une question, au format posté par le formulaire.
     */
    private function answer(array $question, Question $item): mixed
    {
        return match ($question['type']) {
            'radio' => [array_key_first($question['options'])],
            'checkbox' => [array_key_first($question['options'])],
            'date' => '2026-12-01',
            'user' => ItemAnswer::of(User::class, 2),
            'file' => [$this->uploadedFile($item)],
            default => 'Réponse de test PHPUnit',
        };
    }

    /**
     * Fichier déposé dans GLPI_TMP_DIR comme le ferait le téléversement du formulaire.
     */
    private function uploadedFile(Question $item): string
    {
        $prefix = uniqid('', true) . '_';
        $name = $prefix . 'phpunit.csv';
        $path = GLPI_TMP_DIR . '/' . $name;
        file_put_contents($path, "article;quantite\nA1;3\n");
        $this->tmpFiles[] = $path;
        $_POST['_prefix_' . $item->getEndUserInputName()] = $prefix;

        return $name;
    }

    /**
     * @param array<string, mixed> $rawAnswers
     */
    private function submit(Form $form, array $rawAnswers, bool $expectValid = true): ?Ticket
    {
        $answers = (new EndUserInputNameProvider())->getAnswers($rawAnswers);
        $handler = AnswersHandler::getInstance();
        $validation = $handler->validateAnswers($form, $answers);
        if (!$expectValid) {
            $this->assertFalse($validation->isValid(), 'La soumission aurait dû être refusée.');
            return null;
        }
        $this->assertTrue($validation->isValid(), 'Soumission refusée : ' . json_encode($validation->getErrors()));
        $created = array_values(array_filter(
            $handler->saveAnswers($form, $answers, users_id: 2)->getCreatedItems(),
            static fn($item) => $item instanceof Ticket
        ));
        $this->assertCount(1, $created, 'Une soumission doit créer exactement un ticket.');
        $this->ticketIdsToDelete[] = (int) $created[0]->getID();

        return $created[0];
    }

    private function categoryId(array $path): int
    {
        $category = new ITILCategory();
        $this->assertTrue(
            $category->getFromDBByCrit(['completename' => implode(' > ', array_merge(['IT & SI'], $path))]),
            'Catégorie introuvable : ' . implode(' > ', $path)
        );

        return (int) $category->getID();
    }

    public function testEveryFormIsCreatedOnceAndRebuildingKeepsThem(): void
    {
        $this->assertSame(13, $this->build());
        $this->assertSame(13, $this->build(), 'Reconstruire réutilise les formulaires existants.');
        foreach (RequestFormLibraryBuilder::getFormNames() as $name) {
            $this->assertSame(1, countElementsInTable(Form::getTable(), ['name' => $name]), "Un seul « $name ».");
        }

        // Les demandes d'application métier ont leur sous-rubrique sous « IT & SI ».
        foreach (RequestFormLibraryBuilder::getDefinitions() as $definition) {
            $rubric = new FormCategory();
            $rubric->getFromDB((int) $this->form($definition['name'])->fields['forms_categories_id']);
            $business = $definition['path'][0] === 'Application métier';
            $this->assertSame($business, str_ends_with($rubric->fields['name'], 'Application métier'), $definition['name']);
            $this->assertSame($business, (int) $rubric->fields['forms_categories_id'] > 0, $definition['name']);
        }
    }

    public function testEachFormCreatesATicketInItsCategoryWithItsMandatoryAnswersOnly(): void
    {
        $this->build();
        foreach (RequestFormLibraryBuilder::getDefinitions() as $definition) {
            $form = $this->form($definition['name']);
            $questions = $this->questions($form, $definition);
            // Réponses obligatoires des questions affichées, conditions évaluées comme le formulaire
            // (premier choix de chaque liste).
            $raw = [];
            $chosen = [];
            foreach ($definition['questions'] as $question) {
                $visible = true;
                if (isset($question['visible_if'])) {
                    $visible = ($chosen[$question['visible_if'][0]] ?? null) === $question['visible_if'][1];
                } elseif (isset($question['visible_if_not'])) {
                    $visible = ($chosen[$question['visible_if_not'][0]] ?? null) !== $question['visible_if_not'][1];
                }
                if ($visible && !empty($question['mandatory'])) {
                    $item = $questions[$question['key']];
                    $raw[$item->getEndUserInputName()] = $this->answer($question, $item);
                    if (isset($question['options'])) {
                        $chosen[$question['key']] = (string) array_key_first($question['options']);
                    }
                }
            }
            $ticket = $this->submit($form, $raw);

            $this->assertSame($this->categoryId($definition['path']), (int) $ticket->fields['itilcategories_id'], $definition['name']);
            $this->assertSame(
                $definition['request_type'] === 'incident' ? Ticket::INCIDENT_TYPE : Ticket::DEMAND_TYPE,
                (int) $ticket->fields['type'],
                $definition['name']
            );
            $this->assertStringStartsWith($definition['name'] . ' - ', $ticket->fields['name'], 'Titre calculé.');

            $validations = countElementsInTable(TicketValidation::getTable(), [
                'tickets_id' => $ticket->getID(),
                'items_id_target' => 2,
            ]);
            $this->assertSame(isset($definition['approval']) ? 1 : 0, $validations, 'Approbation : ' . $definition['name']);
        }
    }

    public function testAConditionalQuestionIsRequiredOnlyWhenShown(): void
    {
        $this->build();
        $definition = RequestFormLibraryBuilder::getDefinitions()['locked_account'];
        $form = $this->form($definition['name']);
        $questions = $this->questions($form, $definition);
        $account = $questions['account']->getEndUserInputName();
        $other = $questions['other']->getEndUserInputName();

        $this->submit($form, [$account => ['1']]);
        $this->submit($form, [$account => ['4']], false);
        $ticket = $this->submit($form, [$account => ['4'], $other => 'Logiciel de paie']);
        $this->assertStringContainsString('Autre', $ticket->fields['name']);
    }
}
