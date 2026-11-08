<?php

namespace Tests\Feature\Qcm;

use App\Qcm\Exchange\BankExporter;
use App\Qcm\Exchange\BankImporter;
use App\Qcm\Exchange\ExchangeFile;
use App\Qcm\Filament\Resources\Banks\Pages\EditBank;
use App\Qcm\Filament\Resources\Banks\Pages\ListBanks;
use App\Qcm\Filament\Resources\Banks\RelationManagers\QuestionsRelationManager;
use App\Qcm\Models\Answer;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\Feature\Qcm\Concerns\BuildsExchangeFiles;
use Tests\TestCase;

/**
 * Étape 4 du plan QCM : export et import des banques (PRD QCM UC-06, UC-07).
 */
class BankExchangeTest extends TestCase
{
    use BuildsExchangeFiles, RefreshDatabase;

    private const CSV_HEADER = "enonce;bonne_reponse;explication;module;mauvaise_reponse_1;explication_1\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Question::IMAGE_DISK);
    }

    private function bankWithQuestions(): Bank
    {
        $bank = Bank::factory()->create(['name' => 'Planeur', 'description' => 'Théorie planeur']);
        Storage::disk(Question::IMAGE_DISK)->put('qcm/images/carte.png', self::PNG);
        Question::factory()->for($bank)->withAnswers(3)->create(['module' => 'Météo', 'image_path' => 'qcm/images/carte.png']);
        Question::factory()->for($bank)->withAnswers(1)->create(['module' => null, 'explanation' => 'Parce que.']);

        return $bank;
    }

    /**
     * @return list<array{statement: string, module: string|null, explanation: string|null, answers: list<array{0: string, 1: bool, 2: string|null}>}>
     */
    private function contents(Bank $bank): array
    {
        return $bank->questions()->with('answers')->orderBy('id')->get()->map(fn (Question $question): array => [
            'statement' => $question->statement,
            'module' => $question->module,
            'explanation' => $question->explanation,
            'answers' => $question->answers->map(fn (Answer $answer): array => [$answer->text, $answer->is_correct, $answer->explanation])->all(),
        ])->all();
    }

    public function test_zip_export_then_import_into_a_new_bank_gives_the_same_bank(): void
    {
        $bank = $this->bankWithQuestions();
        $path = (new BankExporter)->zip($bank);
        $this->exchangeFiles[] = $path;

        $importer = new BankImporter;
        $file = ExchangeFile::open($path, 'export.zip');
        $this->assertSame(['name' => 'Planeur', 'description' => 'Théorie planeur'], $importer->suggestedBank($file));

        $result = $importer->import($file, null, ['name' => 'Planeur (copie)', 'description' => 'Théorie planeur'], true);

        $this->assertSame(2, $result['imported']);
        $this->assertSame($this->contents($bank), $this->contents($result['bank']));
        $copy = $result['bank']->questions()->whereNotNull('image_path')->sole();
        $this->assertNotSame('qcm/images/carte.png', $copy->image_path);
        $this->assertSame(self::PNG, Storage::disk(Question::IMAGE_DISK)->get((string) $copy->image_path));
    }

    public function test_csv_export_can_be_reimported(): void
    {
        $bank = $this->bankWithQuestions();
        $file = ExchangeFile::open($this->csvFile((new BankExporter)->csv($bank)), 'export.csv');

        $result = (new BankImporter)->import($file, null, ['name' => 'Copie'], true);

        $this->assertSame($this->contents($bank), $this->contents($result['bank']));
        $this->assertSame(0, $result['bank']->questions()->whereNotNull('image_path')->count());
    }

    public function test_export_of_a_selection_contains_only_the_selected_questions(): void
    {
        $bank = $this->bankWithQuestions();
        $selected = $bank->questions()->orderBy('id')->limit(1)->get();

        $csv = (new BankExporter)->csv($bank, $selected);

        $this->assertSame(1, substr_count($csv, "\n") - 1);
        $this->assertStringContainsString($selected->first()->statement, $csv);
    }

    public function test_analysis_reports_duplicates_new_modules_and_missing_images_without_writing(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->create(['statement' => 'Quel nuage annonce un orage ?', 'module' => 'Météo']);
        $file = ExchangeFile::open($this->csvFile("enonce;bonne_reponse;module;illustration;mauvaise_reponse_1\n"
            ."quel nuage annonce  un orage;Cb;météo;;Ci\n"
            ."Nouvelle ?;Oui;Navigation;carte.png;Non\n"
            ."Nouvelle ?;Oui;Navigation;;Non\n"), 'f.csv');

        $report = (new BankImporter)->analyse($file, $bank)->toArray();

        $this->assertSame(3, $report['valid']);
        $this->assertSame([2, 4], array_column($report['duplicates'], 'line'));
        $this->assertStringContainsString('déjà présente', $report['duplicates'][0]['reason']);
        $this->assertStringContainsString('ligne 3 du fichier', $report['duplicates'][1]['reason']);
        $this->assertSame(['Navigation'], $report['newModules']);
        $this->assertSame([3], array_column($report['imageProblems'], 'line'));
        $this->assertSame(1, $bank->questions()->count());
    }

    public function test_duplicates_are_skipped_or_imported_on_demand(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->create(['statement' => 'Déjà là ?']);
        $path = $this->csvFile("enonce;bonne_reponse;mauvaise_reponse_1\nDéjà là ?;Oui;Non\nNouvelle ?;Oui;Non\n");

        $skipped = (new BankImporter)->import(ExchangeFile::open($path, 'f.csv'), $bank, [], true);
        $this->assertSame([1, 1], [$skipped['imported'], $skipped['skipped']]);

        $forced = (new BankImporter)->import(ExchangeFile::open($path, 'f.csv'), $bank, [], false);
        $this->assertSame(2, $forced['imported']);
        $this->assertSame(4, $bank->questions()->count());
    }

    public function test_questionnaire_parameters_are_ignored_with_a_notice(): void
    {
        $file = ExchangeFile::open($this->zipFile([
            'questions.csv' => self::CSV_HEADER."A ?;Oui;;;Non;\n",
            'examen.json' => json_encode(['format_version' => 1]),
        ]), 'examen.zip');

        $report = (new BankImporter)->analyse($file, null)->toArray();

        $this->assertStringContainsString('paramètres d\'un examen', $report['notices'][0]);
    }

    public function test_failed_import_leaves_neither_rows_nor_images(): void
    {
        $file = ExchangeFile::open($this->zipFile([
            'questions.csv' => "enonce;bonne_reponse;mauvaise_reponse_1;illustration\nA ?;Oui;Non;carte.png\nB ?;Oui;Non;\n",
            'images/carte.png' => self::PNG,
        ]), 'f.zip');
        $calls = 0;
        Answer::creating(function () use (&$calls): void {
            if (++$calls === 3) {
                throw new RuntimeException('Panne simulée');
            }
        });

        try {
            (new BankImporter)->import($file, null, ['name' => 'Échec'], true);
            $this->fail('L\'import aurait dû échouer.');
        } catch (RuntimeException) {
        }

        $this->assertDatabaseMissing('qcm_banks', ['name' => 'Échec']);
        $this->assertSame([], Storage::disk(Question::IMAGE_DISK)->allFiles());
    }

    public function test_import_action_creates_a_new_bank_and_opens_it(): void
    {
        Livewire::test(ListBanks::class)
            ->callAction('importBank', [
                'file' => UploadedFile::fake()->createWithContent('meteo.csv', self::CSV_HEADER."Quel nuage ?;Cb;;Météo;Ci;\n"),
                'name' => 'Météo importée',
                'duplicates' => 'skip',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('1 question importée dans « Météo importée »');

        $bank = Bank::where('name', 'Météo importée')->sole();
        $this->assertSame('Cb', $bank->questions()->sole()->correctAnswer->text);
    }

    public function test_import_action_refuses_a_taken_bank_name(): void
    {
        Bank::factory()->create(['name' => 'Météo']);

        Livewire::test(ListBanks::class)
            ->callAction('importBank', [
                'file' => UploadedFile::fake()->createWithContent('meteo.csv', self::CSV_HEADER."Quel nuage ?;Cb;;;Ci;\n"),
                'name' => 'Météo',
            ])
            ->assertHasActionErrors(['name' => 'unique']);

        $this->assertSame(1, Bank::count());
    }

    public function test_import_action_reports_an_unusable_file(): void
    {
        Livewire::test(ListBanks::class)
            ->callAction('importBank', [
                'file' => UploadedFile::fake()->createWithContent('vide.csv', "a;b\n"),
                'name' => 'Vide',
            ])
            ->assertNotified('Fichier refusé');

        $this->assertDatabaseMissing('qcm_banks', ['name' => 'Vide']);
    }

    public function test_questions_are_imported_into_the_open_bank(): void
    {
        $bank = Bank::factory()->create();

        Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $bank, 'pageClass' => EditBank::class])
            ->callAction(TestAction::make('importQuestions')->table(), [
                'file' => UploadedFile::fake()->createWithContent('q.csv', self::CSV_HEADER."A ?;Oui;;;Non;\nB ?;Oui;;;Non;\n"),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame(2, $bank->questions()->count());
    }

    public function test_export_actions_download_files(): void
    {
        $bank = $this->bankWithQuestions();

        Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $bank, 'pageClass' => EditBank::class])
            ->callAction(TestAction::make('exportBankCsv')->table())
            ->assertFileDownloaded('banque-planeur-'.now()->format('Y-m-d').'.csv')
            ->callAction(TestAction::make('exportBankZip')->table())
            ->assertFileDownloaded('banque-planeur-'.now()->format('Y-m-d').'.zip');
    }
}
