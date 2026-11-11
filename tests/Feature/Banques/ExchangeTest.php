<?php

namespace Tests\Feature\Banques;

use App\Banques\Exchange\BankExporter;
use App\Banques\Exchange\BankImporter;
use App\Banques\Exchange\ExchangeException;
use App\Banques\Exchange\ExchangeFile;
use App\Banques\Exchange\QuestionCsv;
use App\Banques\Filament\Resources\Banks\Pages\EditBank;
use App\Banques\Filament\Resources\Banks\Pages\ListBanks;
use App\Banques\Filament\Resources\Banks\RelationManagers\QuestionsRelationManager;
use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Banques\Questions\Difficulty;
use App\Banques\Questions\QuestionType;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\Banques\Concerns\BuildsExchangeFiles;
use Tests\TestCase;

/**
 * Plan Banques, étape B3 : import et export des banques (PRD Banques §8,
 * UC-04, UC-05).
 */
class ExchangeTest extends TestCase
{
    use BuildsExchangeFiles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Question::IMAGE_DISK);
        Filament::setCurrentPanel('leitner');
    }

    /**
     * @return array{bank: Bank, imported: int, skipped: int}
     */
    private function importFile(string $path, string $name, ?Bank $target = null): array
    {
        return app(BankImporter::class)->import(ExchangeFile::open($path, $name), $target, ['name' => pathinfo($name, PATHINFO_FILENAME)], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function report(string $csv): array
    {
        return app(BankImporter::class)->analyse(ExchangeFile::open($this->csvFile($csv), 'f.csv'), null)->toArray();
    }

    /**
     * Contenu comparable d'une banque.
     *
     * @return list<array<string, mixed>>
     */
    private function contents(Bank $bank): array
    {
        return $bank->questions()->orderBy('id')->get()->map(fn (Question $question): array => [
            'type' => $question->type,
            'statement' => $question->statement,
            'payload' => $question->payload,
            'explanation' => $question->explanation,
            'theme' => $question->theme,
            'difficulty' => $question->difficulty,
            'image' => $question->image_path === null ? null : md5((string) Storage::disk(Question::IMAGE_DISK)->get($question->image_path)),
            'image_alt' => $question->image_alt,
        ])->all();
    }

    private function bankOfEveryType(): Bank
    {
        $bank = Bank::factory()->create(['name' => 'Tous les types', 'description' => 'Une question par type']);
        Storage::disk(Question::IMAGE_DISK)->put('banques/images/carte.png', self::PNG);
        Question::factory()->for($bank)->qcm()->difficulty(1)->create([
            'theme' => 'Europe',
            'image_path' => 'banques/images/carte.png',
            'image_alt' => 'Carte de l\'Europe',
            'payload' => ['correct' => 'Berne', 'wrong' => [['text' => 'Zurich', 'explanation' => 'Pas la capitale.'], ['text' => 'Genève', 'explanation' => null]], 'shuffle' => false],
        ]);
        Question::factory()->for($bank)->vraiFaux(false)->create(['theme' => null, 'explanation' => 'Parce que.']);
        Question::factory()->for($bank)->autoEvaluation()->difficulty(3)->create();
        Question::factory()->for($bank)->valeurNumerique(1.225, 'pourcentage', 1.5, 'kg/m³')->create();
        Question::factory()->for($bank)->valeurNumerique(366, 'aucune', null, null)->create(['theme' => 'Calendrier', 'statement' => 'Combien de jours compte une année bissextile ?']);

        return $bank;
    }

    public function test_zip_export_then_import_gives_the_same_questions(): void
    {
        $bank = $this->bankOfEveryType();
        $zip = (new BankExporter)->zip($bank);

        $result = $this->importFile($zip, 'export.zip');

        $this->assertSame(5, $result['imported']);
        $this->assertSame('Tous les types', app(BankImporter::class)->suggestedBank(ExchangeFile::open($zip, 'export.zip'))['name']);
        $this->assertEquals($this->contents($bank), $this->contents($result['bank']));
        @unlink($zip);
    }

    public function test_csv_export_is_written_for_spreadsheets(): void
    {
        $csv = (new BankExporter)->csv($this->bankOfEveryType());
        $lines = explode("\n", trim(substr($csv, 3)));

        $this->assertStringStartsWith("\u{FEFF}", $csv);
        // Un CSV seul ne contient pas les illustrations : ni `illustration` ni `texte_alternatif`.
        $this->assertSame('type;enonce;explication;theme;difficulte;bonne_reponse;melange;reponse;valeur;type_tolerance;tolerance;unite;mauvaise_reponse_1;explication_1;mauvaise_reponse_2;explication_2', $lines[0]);
        $this->assertStringContainsString(';facile;', $lines[1]);
        $this->assertStringContainsString(';non;', $lines[1], 'Mélange interdit écrit « non ».');
        $this->assertStringContainsString(';1,225;pourcentage;1,5;kg/m³;', $lines[4]);
        $this->assertStringContainsString(';faux;', $lines[2]);
    }

    public function test_bank_of_qcm_only_uses_the_historical_columns(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->qcm()->create(['theme' => 'Météo', 'explanation' => null]);

        $header = explode("\n", substr((new BankExporter)->csv($bank), 3))[0];

        $this->assertSame('type;enonce;module;bonne_reponse;mauvaise_reponse_1;explication_1;mauvaise_reponse_2;explication_2;mauvaise_reponse_3;explication_3', $header);
    }

    public function test_example_files_import_without_error(): void
    {
        $expected = ['qcm.csv' => 2, 'vrai-faux.csv' => 2, 'auto-evaluation.csv' => 2, 'valeur-numerique.csv' => 3, 'banque-exemple.csv' => 5, 'banque-exemple.zip' => 5];

        foreach ($expected as $file => $count) {
            $report = app(BankImporter::class)->analyse(ExchangeFile::open(public_path('exemples/banques/'.$file), $file), null)->toArray();
            $this->assertSame([], $report['errors'], $file);
            $this->assertSame($count, $report['valid'], $file);
        }

        $this->assertSame('Banque exemple', app(BankImporter::class)->suggestedBank(ExchangeFile::open(public_path('exemples/banques/banque-exemple.zip'), 'banque-exemple.zip'))['name']);
        $result = $this->importFile(public_path('exemples/banques/banque-exemple.zip'), 'banque-exemple.zip');
        $figure = $result['bank']->questions()->whereNotNull('image_path')->sole();
        $this->assertSame('Un triangle bleu sur fond blanc', $figure->image_alt);
        $this->assertSame(['QCM' => 2, 'Vrai / faux' => 1, 'Auto-évaluation' => 1, 'Valeur numérique' => 1], app(BankImporter::class)->analyse(ExchangeFile::open(public_path('exemples/banques/banque-exemple.zip'), 'b.zip'), null)->toArray()['byType']);
    }

    public function test_historical_qcm_files_still_import(): void
    {
        $result = $this->importFile(public_path('exemples/qcm/banque-exemple.zip'), 'banque-exemple.zip');

        $questions = $result['bank']->questions()->get();
        $this->assertGreaterThan(0, $questions->count());
        $this->assertTrue($questions->every(fn (Question $question): bool => $question->type === QuestionType::Qcm));
        $this->assertNotNull($questions->first()->theme, 'La colonne `module` devient le thème.');

        $report = app(BankImporter::class)->analyse(ExchangeFile::open(public_path('exemples/qcm/banque-exemple.csv'), 'b.csv'), null)->toArray();
        $this->assertSame([], $report['errors']);
    }

    public function test_image_without_description_gets_a_default_one_and_a_warning(): void
    {
        $path = $this->zipFile([
            'questions.csv' => "enonce;bonne_reponse;mauvaise_reponse_1;illustration\nQuel est ce monument ?;La tour Eiffel;Big Ben;tour.png\n",
            'images/tour.png' => self::PNG,
        ]);

        $report = app(BankImporter::class)->analyse(ExchangeFile::open($path, 'f.zip'), null)->toArray();
        $this->assertStringContainsString('Illustration sans description', $report['warnings'][0]['message']);

        $question = $this->importFile($path, 'f.zip')['bank']->questions()->sole();
        $this->assertSame('Illustration : Quel est ce monument ?', $question->image_alt);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidLines(): array
    {
        $header = "type;enonce;reponse;valeur;type_tolerance;tolerance;bonne_reponse;melange;mauvaise_reponse_1;explication_1;explication_2\n";

        return [
            'type inconnu' => [$header."choix_multiple;A ?;;;;;;;;;\n", 'Colonne `type` : « choix_multiple » n\'est pas un type connu (valeurs admises : qcm, vrai_faux, auto_evaluation, valeur_numerique).'],
            'type pas encore disponible' => [$header."enumeration;A ?;;;;;;;;;\n", 'Colonne `type` : le type « enumeration » est prévu par le format mais pas encore disponible dans cette version.'],
            'énoncé vide' => [$header."vrai_faux;;vrai;;;;;;;;\n", 'Colonne `enonce` : l\'énoncé est vide.'],
            'qcm sans mauvaise réponse' => [$header."qcm;A ?;;;;;Oui;;;;\n", 'Colonne `mauvaise_reponse_1` : aucune mauvaise réponse (il en faut au moins une).'],
            'explication orpheline' => [$header."qcm;A ?;;;;;Oui;;Non;;Pourquoi\n", 'Colonne `explication_2` : elle n\'a pas de mauvaise réponse 2.'],
            'mélange invalide' => [$header."qcm;A ?;;;;;Oui;peut-être;Non;;\n", 'Colonne `melange` : « peut-être » n\'est pas une valeur admise (oui ou non).'],
            'vrai faux invalide' => [$header."vrai faux;A ?;bof;;;;;;;;\n", 'Colonne `reponse` : « bof » n\'est pas une valeur admise (vrai ou faux).'],
            'auto-évaluation sans réponse' => [$header."auto_evaluation;A ?;;;;;;;;;\n", 'Colonne `reponse` : la réponse de référence est obligatoire.'],
            'valeur non numérique' => [$header."valeur_numerique;A ?;;douze;;;;;;;\n", 'Colonne `valeur` : « douze » n\'est pas un nombre (virgule ou point décimal).'],
            'type de tolérance inconnu' => [$header."valeur_numerique;A ?;;12;relative;1;;;;;\n", 'Colonne `type_tolerance` : « relative » n\'est pas une valeur admise (aucune, absolue, pourcentage).'],
            'tolérance absente' => [$header."valeur_numerique;A ?;;12;absolue;;;;;;\n", 'Colonne `tolerance` : nombre obligatoire.'],
            'tolérance négative' => [$header."valeur_numerique;A ?;;12;absolue;-1;;;;;\n", 'Colonne `tolerance` : l\'écart accepté ne peut pas être négatif.'],
        ];
    }

    #[DataProvider('invalidLines')]
    public function test_invalid_lines_are_reported_with_line_and_column(string $csv, string $message): void
    {
        $report = $this->report($csv);

        $this->assertSame(0, $report['valid']);
        $this->assertSame([['line' => 2, 'message' => $message]], $report['errors']);
    }

    public function test_warnings_do_not_prevent_the_import(): void
    {
        $report = $this->report("type;enonce;difficulte;valeur;type_tolerance;tolerance;bonne_reponse\nvaleur_numerique;A ?;très dur;12;aucune;3;Oui\n");

        $this->assertSame(1, $report['valid']);
        $this->assertSame([
            ['line' => 2, 'message' => 'Colonne `difficulte` : « très dur » n\'est pas une valeur admise (1, 2, 3, facile, moyen, difficile) ; question importée sans difficulté.'],
            ['line' => 2, 'message' => 'Colonnes ignorées pour ce type : `bonne_reponse`.'],
            ['line' => 2, 'message' => 'Colonne `tolerance` ignorée : le type de tolérance est `aucune`.'],
        ], $report['warnings']);
    }

    public function test_values_are_read_flexibly(): void
    {
        $file = ExchangeFile::open($this->csvFile("Type,Énoncé,Réponse,Valeur,Difficulté\nVrai-Faux,A ?,OUI,,2\nvaleur numérique,B ?,,\"4 806,5\",Difficile\n"), 'f.csv');

        [$trueFalse, $numeric] = $file->csv()->questions;

        $this->assertSame(['value' => true], $trueFalse->payload);
        $this->assertSame(Difficulty::Moyen, $trueFalse->difficulty);
        $this->assertSame(4806.5, $numeric->payload['value']);
        $this->assertSame(Difficulty::Difficile, $numeric->difficulty);
    }

    public function test_theme_wins_over_module_with_a_notice(): void
    {
        $report = $this->report("enonce;bonne_reponse;mauvaise_reponse_1;theme;module\nA ?;Oui;Non;Europe;Géographie\n");

        $this->assertContains('Le fichier a une colonne `theme` et une colonne `module` : `theme` est utilisée, `module` est ignorée.', $report['notices']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unusableFiles(): array
    {
        return [
            'sans énoncé' => ["type;reponse\nvrai_faux;vrai\n", 'Colonne obligatoire absente de la première ligne : enonce.'],
            'ni type ni bonne réponse' => ["enonce;reponse\nA ?;vrai\n", 'Sans colonne `type`, toutes les questions sont des QCM'],
            'colonne en double' => ["enonce;Énoncé;bonne_reponse\nA;B;C\n", 'La colonne « Énoncé » apparaît deux fois dans l\'en-tête.'],
            'pas en UTF-8' => ["enonce;bonne_reponse\n\xE9t\xE9;oui\n", 'n\'est pas encodé en UTF-8'],
        ];
    }

    #[DataProvider('unusableFiles')]
    public function test_unusable_files_are_refused_with_what_is_expected(string $csv, string $message): void
    {
        try {
            ExchangeFile::open($this->csvFile($csv), 'f.csv');
            $this->fail('Le fichier aurait dû être refusé.');
        } catch (ExchangeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
            $this->assertStringContainsString('guide d\'utilisation des banques', $exception->getMessage());
        }
    }

    public function test_analysis_writes_nothing_and_reports_duplicates_and_new_themes(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->qcm()->create(['statement' => 'Capitale de la Suisse ?', 'theme' => 'Europe']);
        $before = Question::count();

        $report = app(BankImporter::class)->analyse(ExchangeFile::open($this->csvFile("enonce;bonne_reponse;mauvaise_reponse_1;theme\nCapitale de la suisse;Berne;Zurich;Europe\nAutre ?;Oui;Non;Asie\n"), 'f.csv'), $bank)->toArray();

        $this->assertSame(Question::count(), $before);
        $this->assertSame('même énoncé qu\'une question déjà présente', $report['duplicates'][0]['reason']);
        $this->assertSame(['Asie'], $report['newThemes']);
    }

    public function test_failed_import_leaves_neither_rows_nor_images(): void
    {
        $file = ExchangeFile::open($this->zipFile([
            'questions.csv' => "enonce;bonne_reponse;mauvaise_reponse_1;illustration\nA ?;Oui;Non;carte.png\nB ?;Oui;Non;\n",
            'images/carte.png' => self::PNG,
        ]), 'f.zip');
        $calls = 0;
        Question::creating(function () use (&$calls): void {
            if (++$calls === 2) {
                throw new RuntimeException('Panne simulée');
            }
        });

        try {
            app(BankImporter::class)->import($file, null, ['name' => 'Échec'], true);
            $this->fail('L\'import aurait dû échouer.');
        } catch (RuntimeException) {
        }

        $this->assertDatabaseMissing('banques_banks', ['name' => 'Échec']);
        $this->assertSame([], Storage::disk(Question::IMAGE_DISK)->allFiles());
    }

    public function test_import_action_creates_a_new_bank(): void
    {
        Livewire::test(ListBanks::class)
            ->callAction('importBank', [
                'file' => UploadedFile::fake()->createWithContent('meteo.csv', "type;enonce;reponse\nvrai_faux;Le cumulus est un nuage de beau temps.;vrai\n"),
                'name' => 'Météo importée',
                'duplicates' => 'skip',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('1 question importée dans « Météo importée »');

        $this->assertSame(QuestionType::VraiFaux, Bank::where('name', 'Météo importée')->sole()->questions()->sole()->type);
    }

    public function test_import_action_reports_an_unusable_file(): void
    {
        Livewire::test(ListBanks::class)
            ->callAction('importBank', [
                'file' => UploadedFile::fake()->createWithContent('vide.csv', "a;b\n"),
                'name' => 'Vide',
            ])
            ->assertNotified('Fichier refusé');

        $this->assertDatabaseMissing('banques_banks', ['name' => 'Vide']);
    }

    public function test_questions_are_imported_into_the_open_bank_and_exported(): void
    {
        $bank = Bank::factory()->create(['name' => 'Planeur']);
        $manager = fn () => Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $bank, 'pageClass' => EditBank::class]);

        $manager()
            ->callAction(TestAction::make('importQuestions')->table(), [
                'file' => UploadedFile::fake()->createWithContent('q.csv', "type;enonce;reponse\nauto_evaluation;A ?;Oui\nauto_evaluation;B ?;Non\n"),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();
        $this->assertSame(2, $bank->questions()->count());

        $manager()
            ->callAction(TestAction::make('exportBankCsv')->table())
            ->assertFileDownloaded('banque-planeur-'.now()->format('Y-m-d').'.csv')
            ->callAction(TestAction::make('exportBankZip')->table())
            ->assertFileDownloaded('banque-planeur-'.now()->format('Y-m-d').'.zip');
    }

    public function test_csv_reader_normalizes_column_names(): void
    {
        $this->assertSame('texte_alternatif', QuestionCsv::normalizeColumn(' Texte alternatif '));
        $this->assertSame('bonne_reponse', QuestionCsv::normalizeColumn('Bonne réponse'));
    }
}
