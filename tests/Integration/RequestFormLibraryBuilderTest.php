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

use CommonITILActor;
use Computer;
use Glpi\Form\AnswersHandler\AnswersHandler;
use Glpi\Form\Category as FormCategory;
use Glpi\Form\Condition\ValueOperator;
use Glpi\Form\Destination\FormDestination;
use Glpi\Form\EndUserInputNameProvider;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\Section;
use GlpiPlugin\Configurationglpiauto\CategoryBuilder;
use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ManagedForm;
use GlpiPlugin\Configurationglpiauto\RequestFormLibraryBuilder;
use Item_Ticket;
use ITILCategory;
use Location;
use PHPUnit\Framework\TestCase;
use Ticket;
use Ticket_User;
use TicketValidation;
use User;

/**
 * Issues #346 et #320 : les formulaires décrits par `RequestFormLibraryBuilder` sont créés, et
 * chacun produit réellement un ticket (catégorie, type, titre calculé) quand on le soumet avec ses
 * seules réponses obligatoires. Les questions et sections conditionnelles ne sont exigées que
 * lorsqu'elles sont affichées ; l'urgence, le lieu, l'appareil, les observateurs, l'approbation et
 * la catégorie du ticket viennent des réponses. Relancer la construction met à jour un formulaire
 * intact et laisse tel quel un formulaire retouché. Indépendant de la langue : les libellés
 * viennent des définitions, jamais de chaînes écrites ici.
 */
final class RequestFormLibraryBuilderTest extends TestCase
{
    /** @var int[] */
    private array $ticketIdsToDelete = [];

    /** @var array<int, array{0: string, 1: int}> */
    private array $itemsToDelete = [];

    /** @var string[] */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->ticketIdsToDelete as $id) {
            (new Ticket())->delete(['id' => $id], true);
        }
        foreach ($this->itemsToDelete as [$itemtype, $id]) {
            (new $itemtype())->delete(['id' => $id], true);
        }
        foreach (RequestFormLibraryBuilder::getDefinitions() as $key => $definition) {
            $form = new Form();
            while ($form->getFromDBByCrit(['name' => $definition['name']])) {
                $form->delete(['id' => $form->getID()], true);
            }
            ManagedForm::forget(RequestFormLibraryBuilder::managedKey($key));
        }
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
        // Seules les clés posées par uploadedFile() : $_POST doit rester un tableau pour les tests
        // suivants (Request::createFromGlobals()).
        foreach (array_keys($_POST) as $key) {
            if (str_starts_with((string) $key, '_prefix_')) {
                unset($_POST[$key]);
            }
        }
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

    private function builder(): RequestFormLibraryBuilder
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);
        $builder = new RequestFormLibraryBuilder();
        $builder->build($config);

        return $builder;
    }

    private function build(): int
    {
        $config = $this->buildConfig();
        (new CategoryBuilder())->build($config);

        return (new RequestFormLibraryBuilder())->build($config);
    }

    private function form(string $key): Form
    {
        $name = RequestFormLibraryBuilder::getDefinitions()[$key]['name'];
        $form = new Form();
        $this->assertTrue($form->getFromDBByCrit(['name' => $name]), "Le formulaire « $name » doit exister.");

        return $form;
    }

    /**
     * @return array<string, Question> clé de définition => question, retrouvée par sa position
     */
    private function questions(Form $form, array $definition): array
    {
        $questions = [];
        foreach (RequestFormLibraryBuilder::getSections($definition) as $rank => $sectionDefinition) {
            $section = new Section();
            $this->assertTrue($section->getFromDBByCrit(['forms_forms_id' => $form->getID(), 'rank' => $rank]));
            foreach ($sectionDefinition['questions'] as $verticalRank => $question) {
                $item = new Question();
                $this->assertTrue($item->getFromDBByCrit(['forms_sections_id' => $section->getID(), 'vertical_rank' => $verticalRank]));
                $questions[$question['key']] = $item;
            }
        }

        return $questions;
    }

    /**
     * @return array<string, array<string, mixed>> clé => définition de question
     */
    private static function questionDefinitions(array $definition): array
    {
        $all = [];
        foreach (RequestFormLibraryBuilder::getSections($definition) as $section) {
            foreach ($section['questions'] as $question) {
                $all[$question['key']] = $question;
            }
        }

        return $all;
    }

    /**
     * Visibilité calculée comme le formulaire, à partir des valeurs choisies (null : sans réponse).
     *
     * @param array<string, mixed> $chosen
     */
    private static function isVisible(array $definition, array $chosen): bool
    {
        $rules = RequestFormLibraryBuilder::conditionRules($definition);
        if ($rules === []) {
            return true;
        }
        foreach ($rules as [$target, $operator, $value]) {
            $answer = $chosen[$target] ?? null;
            $match = match ($operator) {
                ValueOperator::EQUALS => $answer !== null && (string) $answer === (string) $value,
                ValueOperator::NOT_EQUALS => (string) $answer !== (string) $value,
                ValueOperator::CONTAINS => is_array($answer) && array_diff($value, $answer) === [],
                ValueOperator::NOT_CONTAINS => !is_array($answer) || array_diff($value, $answer) !== [],
                ValueOperator::GREATER_THAN_OR_EQUALS => $answer !== null && (int) $answer >= (int) $value,
                default => false,
            };
            if ($match) {
                return true;
            }
        }

        return false;
    }

    /**
     * Réponse valide pour une question, au format posté par le formulaire.
     */
    private function answer(array $question, Question $item): mixed
    {
        return match ($question['type']) {
            'radio', 'checkbox' => [(string) ($question['default'] ?? array_key_first($question['options']))],
            'date' => '2026-12-01',
            'user' => ItemAnswer::of(User::class, 2),
            'file' => [$this->uploadedFile($item)],
            'urgency' => (string) ($question['default'] ?? 3),
            'email' => 'expediteur@example.com',
            default => 'Réponse de test PHPUnit 42',
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
     * Réponses obligatoires des questions affichées, conditions évaluées comme le formulaire ; les
     * valeurs imposées (clé => réponse brute) remplacent celles par défaut.
     *
     * @param array<string, mixed> $forced
     * @return array<string, mixed> réponses brutes postées
     */
    private function mandatoryAnswers(array $definition, array $questions, array $forced = []): array
    {
        $raw = [];
        $chosen = [];
        foreach (RequestFormLibraryBuilder::getSections($definition) as $section) {
            if (!self::isVisible($section, $chosen)) {
                continue;
            }
            foreach ($section['questions'] as $question) {
                if (!self::isVisible($question, $chosen)) {
                    continue;
                }
                $key = $question['key'];
                if (array_key_exists($key, $forced)) {
                    $value = $forced[$key];
                } elseif (!empty($question['mandatory'])) {
                    $value = $this->answer($question, $questions[$key]);
                } else {
                    continue;
                }
                $raw[$questions[$key]->getEndUserInputName()] = $value;
                $chosen[$key] = match ($question['type']) {
                    'radio' => $value[0],
                    'checkbox' => $value,
                    'urgency' => $value,
                    default => true,
                };
            }
        }

        return $raw;
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

    private function otherUserId(): int
    {
        $user = new User();
        $this->assertTrue($user->getFromDBbyName('tech'), 'Utilisateur « tech » attendu (installation GLPI par défaut).');

        return (int) $user->getID();
    }

    private function isObserver(Ticket $ticket, int $userId): bool
    {
        return countElementsInTable(Ticket_User::getTable(), [
            'tickets_id' => $ticket->getID(),
            'users_id' => $userId,
            'type' => CommonITILActor::OBSERVER,
        ]) === 1;
    }

    private function addComputer(): int
    {
        $id = (int) (new Computer())->add(['name' => 'PHPUnit poste 320', 'entities_id' => 0, 'users_id' => 2]);
        $this->itemsToDelete[] = [Computer::class, $id];

        return $id;
    }

    public function testEveryFormIsCreatedOnceAndRebuildingKeepsThem(): void
    {
        $total = count(RequestFormLibraryBuilder::getDefinitions());
        $this->assertSame(18, $total);
        $this->assertSame($total, $this->build());
        $builder = $this->builder();
        $this->assertSame(
            array_fill_keys(array_keys(RequestFormLibraryBuilder::getDefinitions()), ManagedForm::UNCHANGED),
            $builder->getStatuses(),
            'Reconstruire sans changement ne touche à rien.'
        );
        foreach (RequestFormLibraryBuilder::getFormNames() as $name) {
            $this->assertSame(1, countElementsInTable(Form::getTable(), ['name' => $name]), "Un seul « $name ».");
        }

        foreach (RequestFormLibraryBuilder::getDefinitions() as $key => $definition) {
            $form = $this->form($key);
            // Texte de présentation dans le catalogue.
            $this->assertNotSame('', trim(strip_tags((string) $form->fields['description'])), $definition['name']);
            // Les demandes d'application métier ont leur sous-rubrique sous « IT & SI ».
            $rubric = new FormCategory();
            $rubric->getFromDB((int) $form->fields['forms_categories_id']);
            $business = $definition['path'][0] === 'Application métier';
            $this->assertSame($business, str_ends_with($rubric->fields['name'], 'Application métier'), $definition['name']);
            $this->assertSame($business, (int) $rubric->fields['forms_categories_id'] > 0, $definition['name']);
            $this->assertSame(
                count(RequestFormLibraryBuilder::getSections($definition)),
                countElementsInTable(Section::getTable(), ['forms_forms_id' => $form->getID()]),
                $definition['name']
            );
        }
    }

    public function testEachFormCreatesATicketInItsCategoryWithItsMandatoryAnswersOnly(): void
    {
        $this->build();
        foreach (RequestFormLibraryBuilder::getDefinitions() as $key => $definition) {
            $form = $this->form($key);
            $questions = $this->questions($form, $definition);
            $raw = $this->mandatoryAnswers($definition, $questions);
            $ticket = $this->submit($form, $raw);

            $path = $definition['path'];
            if (isset($definition['routes'])) {
                $routeQuestion = self::questionDefinitions($definition)[$definition['routes']['question']];
                $path = $definition['routes']['paths'][(string) array_key_first($routeQuestion['options'])];
            }
            $this->assertSame($this->categoryId($path), (int) $ticket->fields['itilcategories_id'], $definition['name']);
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
        $form = $this->form('locked_account');
        $questions = $this->questions($form, $definition);
        $account = $questions['account']->getEndUserInputName();
        $other = $questions['other']->getEndUserInputName();

        $this->submit($form, [$account => ['1']]);
        $this->submit($form, [$account => ['4']], false);
        $ticket = $this->submit($form, [$account => ['4'], $other => 'Logiciel de paie']);
        $this->assertStringContainsString('Autre', $ticket->fields['name']);
    }

    /**
     * Signalement d'un e-mail suspect : la section dédiée et ses questions n'apparaissent que pour
     * ce cas, une urgence haute exige l'impact, l'urgence choisie passe au ticket, et la catégorie
     * suit ce qui est signalé.
     */
    public function testSecurityReportFollowsTheAnswers(): void
    {
        $this->build();
        $definition = RequestFormLibraryBuilder::getDefinitions()['security_incident'];
        $form = $this->form('security_incident');
        $questions = $this->questions($form, $definition);

        // Lien cliqué : « mot de passe saisi ? » devient obligatoire.
        $base = ['kind' => ['1'], 'clicked' => ['2']];
        $raw = $this->mandatoryAnswers($definition, $questions, $base);
        unset($raw[$questions['password']->getEndUserInputName()]);
        $this->submit($form, $raw, false);

        // Urgence très haute : « qui est touché ? » devient obligatoire.
        $raw = $this->mandatoryAnswers($definition, $questions, $base + ['urgency' => '5']);
        unset($raw[$questions['impact']->getEndUserInputName()]);
        $this->submit($form, $raw, false);

        $ticket = $this->submit($form, $this->mandatoryAnswers($definition, $questions, $base + ['urgency' => '5']));
        $this->assertSame(5, (int) $ticket->fields['urgency'], "L'urgence choisie passe au ticket.");
        $this->assertSame($this->categoryId(['Sécurité SI', 'Phishing & Mails suspects']), (int) $ticket->fields['itilcategories_id']);

        // Appareil perdu : autre catégorie, section e-mail masquée (ses questions obligatoires ne
        // sont pas exigées), l'appareil choisi est associé au ticket.
        $computerId = $this->addComputer();
        $raw = $this->mandatoryAnswers($definition, $questions, [
            'kind' => ['5'],
            'device' => Computer::class . '_' . $computerId,
        ]);
        $this->assertArrayNotHasKey($questions['clicked']->getEndUserInputName(), $raw);
        $this->assertArrayHasKey($questions['lost']->getEndUserInputName(), $raw);
        $ticket = $this->submit($form, $raw);
        $this->assertSame($this->categoryId(['Sécurité SI', 'Fuite de données']), (int) $ticket->fields['itilcategories_id']);
        $this->assertSame(3, (int) $ticket->fields['urgency'], 'Urgence moyenne par défaut.');
        $this->assertTrue(
            (new Item_Ticket())->getFromDBByCrit(['tickets_id' => $ticket->getID(), 'itemtype' => Computer::class, 'items_id' => $computerId]),
            "L'appareil choisi est associé au ticket."
        );

        // Une destination par catégorie possible, créée seulement pour l'option choisie.
        $this->assertSame(
            count($definition['routes']['paths']),
            countElementsInTable(FormDestination::getTable(), ['forms_forms_id' => $form->getID()])
        );
    }

    /**
     * Arrivée : le matériel coché fait apparaître ses précisions, le responsable devient
     * observateur et le site devient le lieu du ticket.
     */
    public function testArrivalAsksOnlyForTheCheckedEquipment(): void
    {
        $this->build();
        $definition = RequestFormLibraryBuilder::getDefinitions()['arrival'];
        $form = $this->form('arrival');
        $questions = $this->questions($form, $definition);
        $managerId = $this->otherUserId();
        $locationId = (int) (new Location())->add(['name' => 'PHPUnit site 320', 'entities_id' => 0, 'is_recursive' => 1]);
        $this->itemsToDelete[] = [Location::class, $locationId];

        // Portable et téléphone cochés : le type de téléphone est exigé.
        $forced = ['equipment' => ['1', '4'], 'manager' => ItemAnswer::of(User::class, $managerId)];
        $raw = $this->mandatoryAnswers($definition, $questions, $forced);
        $this->assertArrayHasKey($questions['laptop']->getEndUserInputName(), $raw);
        unset($raw[$questions['phone']->getEndUserInputName()]);
        $this->submit($form, $raw, false);

        // Aucun matériel : ni modèle de portable ni téléphone demandés.
        $raw = $this->mandatoryAnswers($definition, $questions, ['equipment' => ['7']] + $forced);
        $this->assertArrayNotHasKey($questions['laptop']->getEndUserInputName(), $raw);
        $this->submit($form, $raw);

        $raw = $this->mandatoryAnswers($definition, $questions, $forced + ['site' => ItemAnswer::of(Location::class, $locationId)]);
        $ticket = $this->submit($form, $raw);
        $this->assertTrue($this->isObserver($ticket, $managerId), 'Le responsable suit le ticket.');
        $this->assertSame($locationId, (int) $ticket->fields['locations_id'], 'Le site devient le lieu du ticket.');

        // CDD : la date de fin est exigée.
        $raw = $this->mandatoryAnswers($definition, $questions, $forced + ['contract' => ['2']]);
        unset($raw[$questions['end']->getEndUserInputName()]);
        $this->submit($form, $raw, false);
    }

    /**
     * Départ : une mutation masque la section du matériel, une redirection de la messagerie exige
     * le collègue qui la reprend.
     */
    public function testDepartureSectionsFollowTheAnswers(): void
    {
        $this->build();
        $definition = RequestFormLibraryBuilder::getDefinitions()['departure'];
        $form = $this->form('departure');
        $questions = $this->questions($form, $definition);
        $managerId = $this->otherUserId();

        $raw = $this->mandatoryAnswers($definition, $questions, ['reason' => ['4'], 'manager' => ItemAnswer::of(User::class, $managerId)]);
        $this->assertArrayNotHasKey($questions['equipment']->getEndUserInputName(), $raw);
        $this->assertArrayHasKey($questions['destination']->getEndUserInputName(), $raw);
        $ticket = $this->submit($form, $raw);
        $this->assertTrue($this->isObserver($ticket, $managerId), 'Le responsable suit le ticket.');

        $raw = $this->mandatoryAnswers($definition, $questions, ['mailbox' => ['2']]);
        unset($raw[$questions['mail_delegate']->getEndUserInputName()]);
        $this->submit($form, $raw, false);

        $raw = $this->mandatoryAnswers($definition, $questions, ['equipment' => ['7']]);
        unset($raw[$questions['other_equipment']->getEndUserInputName()]);
        $this->submit($form, $raw, false);
    }

    /**
     * Accès à une application : approbation par le responsable désigné, collègue bénéficiaire en
     * observateur, justification renforcée pour un accès d'administration.
     */
    public function testApplicationAccessIsApprovedByTheManager(): void
    {
        $this->build();
        $definition = RequestFormLibraryBuilder::getDefinitions()['application_access'];
        $form = $this->form('application_access');
        $questions = $this->questions($form, $definition);
        $otherId = $this->otherUserId();

        $raw = $this->mandatoryAnswers($definition, $questions, [
            'beneficiary' => ['2'],
            'colleague' => ItemAnswer::of(User::class, $otherId),
            'manager' => ItemAnswer::of(User::class, $otherId),
        ]);
        $ticket = $this->submit($form, $raw);
        $this->assertTrue($this->isObserver($ticket, $otherId), 'Le collègue bénéficiaire suit le ticket.');
        $this->assertSame(1, countElementsInTable(TicketValidation::getTable(), [
            'tickets_id' => $ticket->getID(),
            'items_id_target' => $otherId,
        ]), 'Approbation au nom du responsable.');

        $raw = $this->mandatoryAnswers($definition, $questions, ['profile' => ['4']]);
        unset($raw[$questions['admin_reason']->getEndUserInputName()]);
        $this->submit($form, $raw, false);
    }

    /**
     * Droits administrateur : le poste choisi est associé au ticket ; une demande temporaire exige
     * sa date de fin.
     */
    public function testAdminRightsLinkTheWorkstation(): void
    {
        $this->build();
        $definition = RequestFormLibraryBuilder::getDefinitions()['admin_rights'];
        $form = $this->form('admin_rights');
        $questions = $this->questions($form, $definition);
        $computerId = $this->addComputer();

        $ticket = $this->submit($form, $this->mandatoryAnswers($definition, $questions, ['device' => Computer::class . '_' . $computerId]));
        $this->assertTrue(
            (new Item_Ticket())->getFromDBByCrit(['tickets_id' => $ticket->getID(), 'itemtype' => Computer::class, 'items_id' => $computerId]),
            'Le poste choisi est associé au ticket.'
        );

        $raw = $this->mandatoryAnswers($definition, $questions, ['duration' => ['2']]);
        unset($raw[$questions['end']->getEndUserInputName()]);
        $this->submit($form, $raw, false);

        // Un serveur : son nom est exigé, le poste n'est plus demandé.
        $raw = $this->mandatoryAnswers($definition, $questions, ['scope' => ['2']]);
        $this->assertArrayHasKey($questions['target']->getEndUserInputName(), $raw);
    }

    /**
     * Validations natives « Valide si... » : une justification trop courte, un nom sans prénom ou
     * une référence sans chiffre sont refusés avec le message de GLPI ; une réponse correcte passe.
     */
    public function testAnswersAreCheckedBeforeTheTicketIsCreated(): void
    {
        $this->build();
        $cases = [
            ['shared_folder', 'reason', 'Urgent'],
            ['arrival', 'person', 'Dupont'],
            ['data_correction', 'record', 'Commande client'],
        ];
        foreach ($cases as [$key, $questionKey, $invalid]) {
            $definition = RequestFormLibraryBuilder::getDefinitions()[$key];
            $form = $this->form($key);
            $questions = $this->questions($form, $definition);
            $this->assertSame('valid_if', $questions[$questionKey]->fields['validation_strategy'], "$key.$questionKey");

            $this->submit($form, $this->mandatoryAnswers($definition, $questions, [$questionKey => $invalid]), false);
            $this->submit($form, $this->mandatoryAnswers($definition, $questions));
        }
    }

    /**
     * Formulaires d'incident : l'urgence choisie passe au ticket et, au moins haute, « qui est
     * touché ? » devient obligatoire.
     */
    public function testIncidentFormsAskForTheImpactOfAnUrgentRequest(): void
    {
        $this->build();
        foreach (['locked_account', 'label_printing', 'interface_error'] as $key) {
            $definition = RequestFormLibraryBuilder::getDefinitions()[$key];
            $form = $this->form($key);
            $questions = $this->questions($form, $definition);

            $raw = $this->mandatoryAnswers($definition, $questions);
            $this->assertArrayNotHasKey($questions['reach']->getEndUserInputName(), $raw, "$key : urgence moyenne.");
            $this->assertSame(3, (int) $this->submit($form, $raw)->fields['urgency'], $key);

            $raw = $this->mandatoryAnswers($definition, $questions, ['urgency' => '4']);
            $this->assertArrayHasKey($questions['reach']->getEndUserInputName(), $raw, "$key : urgence haute.");
            $ticket = $this->submit($form, $raw);
            $this->assertSame(4, (int) $ticket->fields['urgency'], $key);
            unset($raw[$questions['reach']->getEndUserInputName()]);
            $this->submit($form, $raw, false);
        }
    }

    /**
     * Relancer l'assistant après une évolution de la définition : un formulaire intact est mis à
     * jour sur place (même identifiant, pas de doublon), un formulaire retouché dans GLPI ou créé
     * par une version précédente est laissé tel quel, un formulaire supprimé est recréé.
     */
    public function testRebuildUpdatesUntouchedFormsAndKeepsModifiedOnes(): void
    {
        global $DB;

        $this->build();
        $table = ManagedForm::TABLE;
        $outdated = static function (string $key) use ($DB, $table): void {
            $DB->update($table, ['definition_hash' => 'ancienne'], ['form_key' => RequestFormLibraryBuilder::managedKey($key)]);
        };

        // Intact : mis à jour sur place.
        $untouched = $this->form('data_restore');
        $oldQuestionIds = array_column((new Question())->find(['forms_sections_id' => array_column((new Section())->find(['forms_forms_id' => $untouched->getID()]), 'id')]), 'id');
        $outdated('data_restore');

        // Retouché par un administrateur : une question renommée.
        $modified = $this->form('arrival');
        $definition = RequestFormLibraryBuilder::getDefinitions()['arrival'];
        $job = $this->questions($modified, $definition)['job'];
        $job->update(['id' => $job->getID(), 'name' => 'Intitulé du poste (modifié)']);
        $outdated('arrival');

        // Créé par une version précédente : pas de suivi.
        ManagedForm::forget(RequestFormLibraryBuilder::managedKey('locked_account'));

        // Supprimé par un administrateur : recréé.
        $deleted = $this->form('admin_rights');
        $deleted->delete(['id' => $deleted->getID()], true);

        $statuses = $this->builder()->getStatuses();
        $this->assertSame(ManagedForm::UPDATED, $statuses['data_restore']);
        $this->assertSame(ManagedForm::KEPT, $statuses['arrival']);
        $this->assertSame(ManagedForm::KEPT, $statuses['locked_account']);
        $this->assertSame(ManagedForm::CREATED, $statuses['admin_rights']);
        $this->assertSame(ManagedForm::UNCHANGED, $statuses['departure']);

        $this->assertSame((int) $untouched->getID(), (int) $this->form('data_restore')->getID(), 'Mis à jour sur place.');
        $newQuestionIds = array_column((new Question())->find(['forms_sections_id' => array_column((new Section())->find(['forms_forms_id' => $untouched->getID()]), 'id')]), 'id');
        $this->assertSame([], array_intersect($oldQuestionIds, $newQuestionIds), 'Questions reconstruites.');
        $this->assertSame(1, countElementsInTable(FormDestination::getTable(), ['forms_forms_id' => $untouched->getID()]), 'Une seule destination.');

        $job->getFromDB($job->getID());
        $this->assertSame('Intitulé du poste (modifié)', $job->fields['name'], 'La retouche est conservée.');
        foreach (RequestFormLibraryBuilder::getFormNames() as $name) {
            $this->assertSame(1, countElementsInTable(Form::getTable(), ['name' => $name]), "Un seul « $name ».");
        }

        // Le formulaire mis à jour fonctionne toujours.
        $restore = RequestFormLibraryBuilder::getDefinitions()['data_restore'];
        $form = $this->form('data_restore');
        $ticket = $this->submit($form, $this->mandatoryAnswers($restore, $this->questions($form, $restore), ['urgency' => '4']));
        $this->assertSame(4, (int) $ticket->fields['urgency']);
    }
}
