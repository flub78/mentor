<?php

namespace Tests\Feature\Banques;

use App\Banques\Exchange\BankExporter;
use App\Banques\Exchange\BankImporter;
use App\Banques\Exchange\ExchangeFile;
use App\Banques\Exchange\QuestionCsv;
use App\Banques\Filament\Resources\Banks\Pages\EditBank;
use App\Banques\Filament\Resources\Banks\RelationManagers\QuestionsRelationManager;
use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Leitner\Models\Suite;
use App\Qcm\Exchange\QuestionnaireImporter;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Banques\Concerns\BuildsExchangeFiles;
use Tests\TestCase;

/**
 * Plan Banques, lot B1.1, étapes R2 et R3 : colonnes `reference` et `ordre`
 * du fichier d'échange, import qui met à jour les questions déjà présentes
 * (PRD Banques UC-05, §8).
 */
class ReferenceAndOrderExchangeTest extends TestCase
{
    use BuildsExchangeFiles, RefreshDatabase;

    private const HEADER = "reference;enonce;bonne_reponse;mauvaise_reponse_1;mauvaise_reponse_2;melange;ordre\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Question::IMAGE_DISK);
        Filament::setCurrentPanel('leitner');
    }

    private function file(string $csv): ExchangeFile
    {
        return ExchangeFile::open($this->csvFile($csv), 'f.csv');
    }

    /**
     * @return array<string, mixed>
     */
    private function report(string $csv, ?Bank $target = null): array
    {
        return app(BankImporter::class)->analyse($this->file($csv), $target)->toArray();
    }

    /**
     * @return array{bank: Bank, imported: int, skipped: int, updated: int, unchanged: int, ignored: int, report: string}
     */
    private function import(string $csv, Bank $target, bool $updateExisting = true): array
    {
        return app(BankImporter::class)->import($this->file($csv), $target, [], true, $updateExisting);
    }

    private function qcm(Bank $bank, string $reference, string $correct = 'Berne'): Question
    {
        return Question::factory()->for($bank)->create([
            'reference' => $reference,
            'type' => 'qcm',
            'statement' => 'Capitale de la Suisse ?',
            'payload' => ['correct' => $correct, 'wrong' => [['text' => 'Zurich', 'explanation' => null]], 'shuffle' => true],
        ]);
    }

    public function test_the_order_column_fixes_the_wrong_answers_and_the_place_of_the_correct_one(): void
    {
        $row = (new QuestionCsv)->parse(self::HEADER."q-1;Qui gagne ?;Partage;A;B;non;A | B | Partage\n")->questions[0];

        $this->assertSame('q-1', $row->reference);
        $this->assertSame(2, $row->payload['correct_position']);
        $this->assertSame(['A', 'B'], array_column($row->payload['wrong'], 'text'));

        $reordered = (new QuestionCsv)->parse("enonce;bonne_reponse;mauvaise_reponse_1;explication_1;mauvaise_reponse_2;melange;ordre\nQ ?;Oui;Non;Pourquoi non;Peut-être;non;Peut-être|Oui|Non\n")->questions[0];
        $this->assertSame([['text' => 'Peut-être', 'explanation' => null], ['text' => 'Non', 'explanation' => 'Pourquoi non']], $reordered->payload['wrong']);
        $this->assertSame(1, $reordered->payload['correct_position']);
    }

    public function test_order_errors_name_the_answer_in_cause_and_shuffled_rows_ignore_it(): void
    {
        $report = $this->report(self::HEADER
            .";Q1 ?;Oui;Non;Peut-être;non;Oui | Non\n"
            .";Q2 ?;Oui;Non;Peut-être;non;Oui | Non | Non | Peut-être\n"
            .";Q3 ?;Oui;Non;Peut-être;non;Oui | Non | Jamais | Peut-être\n"
            .";Q4 ?;Oui;Non;Peut-être;oui;Non | Oui | Peut-être\n");

        $this->assertSame([
            ['line' => 2, 'message' => 'Colonne `ordre` (toutes les propositions, chacune une fois, séparées par |) : « Peut-être » n\'y figure pas.'],
            ['line' => 3, 'message' => 'Colonne `ordre` (toutes les propositions, chacune une fois, séparées par |) : « Non » y figure deux fois.'],
            ['line' => 4, 'message' => 'Colonne `ordre` (toutes les propositions, chacune une fois, séparées par |) : « Jamais » n\'est ni la bonne réponse ni une mauvaise réponse de la ligne.'],
        ], $report['errors']);
        $this->assertSame([['line' => 5, 'message' => 'Colonne `ordre` ignorée : les réponses sont mélangées (`melange` vaut `oui`).']], $report['warnings']);
    }

    public function test_a_reference_used_twice_in_the_file_rejects_the_second_line(): void
    {
        $report = $this->report("reference;type;enonce;reponse\nq-1;auto_evaluation;A ?;a\nq-1;auto_evaluation;B ?;b\n");

        $this->assertSame(1, $report['valid']);
        $this->assertSame([['line' => 3, 'message' => 'Colonne `reference` : « q-1 » est déjà la référence de la ligne 2 ; chaque question doit avoir sa propre référence.']], $report['errors']);
    }

    public function test_reference_and_order_survive_an_export_and_an_import_into_a_new_bank(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->create(['reference' => 'pk-07', 'type' => 'qcm', 'statement' => 'Qui gagne ?', 'payload' => [
            'correct' => 'Partage', 'wrong' => [['text' => 'A', 'explanation' => null], ['text' => 'B | C', 'explanation' => null]], 'shuffle' => false, 'correct_position' => 2,
        ]]);

        $csv = app(BankExporter::class)->csv($bank);
        $this->assertStringStartsWith("\u{FEFF}reference;type;enonce;", $csv);
        $this->assertStringContainsString('A | B \| C | Partage', $csv);

        $copy = app(BankImporter::class)->import($this->file($csv), null, ['name' => 'Copie'], true)['bank'];
        $question = $copy->questions()->sole();
        $this->assertSame('pk-07', $question->reference);
        $this->assertSame(['A', 'B | C', 'Partage'], array_values($question->handler()->options($question->payload)));
    }

    public function test_a_known_reference_updates_the_question_and_a_new_answer_resets_leitner_progress(): void
    {
        $bank = Bank::factory()->create();
        $question = $this->qcm($bank, 'ch-1');
        $suite = Suite::factory()->withBanks($bank)->create();
        $suite->progression->items()->create(['question_id' => $question->id, 'box' => 3, 'introduced_at' => now()]);
        $header = "reference;enonce;bonne_reponse;mauvaise_reponse_1\n";

        $report = $this->report($header."ch-1;Quelle est la capitale de la Suisse ?;Berne;Zurich\nch-2;Capitale de la Suisse ?;Berne;Genève\n", $bank);
        $this->assertSame([['line' => 2, 'statement' => 'Quelle est la capitale de la Suisse ?', 'reference' => 'ch-1', 'difference' => 'changed', 'label' => 'modifiée']], $report['existing']);
        $this->assertSame([3], array_column($report['duplicates'], 'line'), 'Une ligne reconnue n\'est jamais un doublon ; une nouvelle question de même énoncé l\'est.');

        $minor = $this->import($header."ch-1;Quelle est la capitale de la Suisse ?;Berne;Zurich\n", $bank);
        $this->assertSame([0, 1, 0, ''], [$minor['imported'], $minor['updated'], $minor['unchanged'], $minor['report']]);
        $this->assertSame('Quelle est la capitale de la Suisse ?', $question->fresh()->statement);
        $this->assertSame(3, $suite->progression->items()->sole()->box);

        $label = $this->report($header."ch-1;Quelle est la capitale de la Suisse ?;Berne;Lausanne\n", $bank)['existing'][0]['label'];
        $this->assertSame('réponse attendue modifiée (modification de fond) — Cette question est en cours de révision dans 1 suite Leitner : une modification de fond la remet en boîte 1.', $label);

        $substantive = $this->import($header."ch-1;Quelle est la capitale de la Suisse ?;Berne;Lausanne\n", $bank);
        $this->assertSame([1, 'Question remise en boîte 1 dans 1 suite Leitner.'], [$substantive['updated'], $substantive['report']]);
        $this->assertSame(1, $suite->progression->items()->sole()->box);
        $this->assertSame(1, $bank->questions()->count());

        $again = $this->import($header."ch-1;Quelle est la capitale de la Suisse ?;Berne;Lausanne\n", $bank);
        $this->assertSame([0, 1], [$again['updated'], $again['unchanged']]);
    }

    public function test_existing_questions_can_be_left_as_they_are(): void
    {
        $bank = Bank::factory()->create();
        $question = $this->qcm($bank, 'ch-1');

        $result = $this->import("reference;enonce;bonne_reponse;mauvaise_reponse_1\nch-1;Autre énoncé ?;Berne;Zurich\nch-9;Nouvelle ?;Oui;Non\n", $bank, updateExisting: false);

        $this->assertSame([1, 0, 1], [$result['imported'], $result['updated'], $result['ignored']]);
        $this->assertSame('Capitale de la Suisse ?', $question->fresh()->statement);
        $this->assertSame(['ch-1', 'ch-9'], $bank->questions()->orderBy('reference')->pluck('reference')->all());
    }

    public function test_a_reference_to_a_question_of_another_type_is_an_error(): void
    {
        $bank = Bank::factory()->create();
        $this->qcm($bank, 'ch-1');

        $report = $this->report("reference;type;enonce;reponse\nch-1;vrai_faux;Berne est la capitale.;vrai\n", $bank);

        $this->assertSame(0, $report['valid']);
        $this->assertSame([], $report['existing']);
        $this->assertStringContainsString('« ch-1 » désigne la question « Capitale de la Suisse ? », de type QCM, et la ligne est de type Vrai / faux', $report['errors'][0]['message']);
    }

    public function test_an_empty_illustration_keeps_the_image_and_a_new_one_replaces_it(): void
    {
        $bank = Bank::factory()->create();
        Storage::disk(Question::IMAGE_DISK)->put('banques/images/carte.png', self::PNG);
        $question = Question::factory()->for($bank)->autoEvaluation()->create([
            'reference' => 'r-1', 'statement' => 'Ce relief ?', 'image_path' => 'banques/images/carte.png', 'image_alt' => 'Une carte',
        ]);

        $csvOnly = $this->import("reference;type;enonce;reponse\nr-1;auto_evaluation;Ce relief ?;Le Jura\n", $bank);
        $this->assertSame(1, $csvOnly['updated']);
        $this->assertSame(['banques/images/carte.png', 'Une carte'], [$question->fresh()->image_path, $question->fresh()->image_alt]);

        $zip = $this->zipFile([
            'questions.csv' => "reference;type;enonce;reponse;illustration;texte_alternatif\nr-1;auto_evaluation;Ce relief ?;Le Jura;autre.png;Une autre carte\n",
            'images/autre.png' => self::PNG.'autre',
        ]);
        $this->assertSame('changed', app(BankImporter::class)->analyse(ExchangeFile::open($zip, 'b.zip'), $bank)->toArray()['existing'][0]['difference']);
        app(BankImporter::class)->import(ExchangeFile::open($zip, 'b.zip'), $bank, [], true);

        $question->refresh();
        $this->assertNotSame('banques/images/carte.png', $question->image_path);
        $this->assertSame([self::PNG.'autre', 'Une autre carte'], [Storage::disk(Question::IMAGE_DISK)->get((string) $question->image_path), $question->image_alt]);
        $this->assertFalse(Storage::disk(Question::IMAGE_DISK)->exists('banques/images/carte.png'));
    }

    public function test_the_import_screen_offers_the_choice_and_tells_what_was_done(): void
    {
        $bank = Bank::factory()->create(['name' => 'Géographie']);
        $this->qcm($bank, 'ch-1');

        Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $bank, 'pageClass' => EditBank::class])
            ->callAction(TestAction::make('importQuestions')->table(), [
                'file' => UploadedFile::fake()->createWithContent('g.csv', "reference;enonce;bonne_reponse;mauvaise_reponse_1\nch-1;Capitale suisse ?;Berne;Zurich\nch-2;Capitale autrichienne ?;Vienne;Graz\n"),
                'existing' => 'update',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified(Notification::make()->success()
                ->title('1 question importée dans « Géographie »')
                ->body('1 question(s) déjà présente(s) mise(s) à jour.'));

        $this->assertSame(['Capitale suisse ?', 'Capitale autrichienne ?'], $bank->questions()->orderBy('reference')->pluck('statement')->all());
    }

    public function test_a_questionnaire_import_creates_new_questions_without_taken_references(): void
    {
        $bank = Bank::factory()->create();
        $this->qcm($bank, 'ch-1');
        $file = $this->file("reference;enonce;bonne_reponse;mauvaise_reponse_1\nch-1;Capitale de la Suisse ?;Berne;Zurich\nch-2;Capitale de l'Autriche ?;Vienne;Graz\n");

        $result = (new QuestionnaireImporter)->import($file, null, null, false, $bank->id);

        $this->assertSame([2, 1], [$result['imported'], $result['droppedReferences']]);
        $this->assertSame([null, 'ch-2'], $result['questionnaire']->questions()->pluck('reference')->all());
        $this->assertSame(3, $bank->questions()->count());
    }
}
