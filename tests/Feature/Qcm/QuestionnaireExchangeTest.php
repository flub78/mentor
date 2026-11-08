<?php

namespace Tests\Feature\Qcm;

use App\Qcm\Exchange\BankImporter;
use App\Qcm\Exchange\ExchangeFile;
use App\Qcm\Exchange\QuestionnaireExporter;
use App\Qcm\Exchange\QuestionnaireImporter;
use App\Qcm\Filament\Resources\Exams\Pages\EditExam;
use App\Qcm\Filament\Resources\Exams\Pages\ListExams;
use App\Qcm\Filament\Resources\Questionnaires\Pages\EditQuestionnaire;
use App\Qcm\Filament\Resources\Questionnaires\Pages\ListQuestionnaires;
use App\Qcm\Models\Answer;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Exam;
use App\Qcm\Models\FieldRequirement;
use App\Qcm\Models\IdentificationField;
use App\Qcm\Models\Question;
use App\Qcm\Models\Questionnaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Qcm\Concerns\BuildsExchangeFiles;
use Tests\TestCase;

/**
 * Étape 6 du plan QCM : export et import des questionnaires et des examens
 * (PRD QCM UC-16, UC-17).
 */
class QuestionnaireExchangeTest extends TestCase
{
    use BuildsExchangeFiles, RefreshDatabase;

    private const CSV = "enonce;bonne_reponse;module;mauvaise_reponse_1\nA ?;Oui;Météo;Non\nB ?;Oui;;Non\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Question::IMAGE_DISK);
    }

    private function questionnaire(): Questionnaire
    {
        $questionnaire = Questionnaire::factory()->withOwnQuestions(1)->withBankQuestions(2)->create([
            'title' => 'Blanc planeur', 'description' => 'Entraînement', 'version' => '2026-1',
        ]);
        Storage::disk(Question::IMAGE_DISK)->put('qcm/images/carte.png', self::PNG);
        $questionnaire->ownQuestions()->first()->update(['image_path' => 'qcm/images/carte.png']);

        return $questionnaire;
    }

    /**
     * @return list<array{0: string, 1: string|null, 2: string|null, 3: bool, 4: list<array{0: string, 1: bool, 2: string|null}>}>
     */
    private function contents(Questionnaire $questionnaire): array
    {
        return $questionnaire->questions()->with('answers')->get()->map(fn (Question $question): array => [
            $question->statement,
            $question->module,
            $question->explanation,
            $question->image_path !== null,
            $question->answers->map(fn (Answer $answer): array => [$answer->text, $answer->is_correct, $answer->explanation])->all(),
        ])->all();
    }

    private function open(string $path, string $name): ExchangeFile
    {
        $this->exchangeFiles[] = $path;

        return ExchangeFile::open($path, $name);
    }

    public function test_questionnaire_zip_round_trip(): void
    {
        $questionnaire = $this->questionnaire();
        $file = $this->open((new QuestionnaireExporter)->zip($questionnaire), 'q.zip');

        $result = (new QuestionnaireImporter)->import($file, null, null, true);

        $copy = $result['questionnaire'];
        $this->assertNull($result['exam']);
        $this->assertSame(['Blanc planeur', 'Entraînement', '2026-1'], [$copy->title, $copy->description, $copy->version]);
        $this->assertSame($this->contents($questionnaire), $this->contents($copy));
        $this->assertSame(3, $copy->ownQuestions()->count(), 'Toutes les questions importées sont des questions propres.');
    }

    public function test_exam_zip_round_trip(): void
    {
        $exam = Exam::factory()->for($this->questionnaire())->create([
            'title' => 'Examen blanc', 'instructions' => 'Bonne chance', 'duration_minutes' => 45, 'pass_rate' => 80,
            'shuffle_questions' => false, 'shows_correction' => false, 'max_attempts' => 2,
            'identification_fields' => ['email' => 'obligatoire', 'date_naissance' => 'facultatif', 'organisation' => 'non_demande', 'identifiant' => 'obligatoire'],
            'identifier_label' => 'Numéro de licence',
        ]);
        $file = $this->open((new QuestionnaireExporter)->examZip($exam), 'e.zip');

        $result = (new QuestionnaireImporter)->import($file, null, null, true);

        $copy = $result['exam'];
        $this->assertNotNull($copy);
        $keys = ['title', 'instructions', 'duration_minutes', 'pass_rate', 'shuffle_questions', 'shows_correction', 'max_attempts', 'identification_fields', 'identifier_label'];
        $this->assertSame($exam->only($keys), $copy->only($keys));
        $this->assertSame($result['questionnaire']->id, $copy->questionnaire_id);
        $this->assertSame($this->contents($exam->questionnaire), $this->contents($result['questionnaire']));
    }

    public function test_csv_alone_gives_a_questionnaire_named_after_the_file(): void
    {
        $file = $this->open($this->csvFile(self::CSV), 'Blanc de mars.csv');

        $analysis = (new QuestionnaireImporter)->analyse($file);
        $result = (new QuestionnaireImporter)->import($file, null, null, true);

        $this->assertStringContainsString('titré d\'après le nom du fichier', $analysis['report']->toArray()['notices'][0]);
        $this->assertSame('Blanc de mars', $result['questionnaire']->title);
        $this->assertSame(['A ?', 'B ?'], $result['questionnaire']->questions()->pluck('statement')->all());
        $this->assertNull($result['exam']);
    }

    public function test_invalid_exam_parameters_are_replaced_by_defaults_and_reported(): void
    {
        $file = $this->open($this->zipFile([
            'questions.csv' => self::CSV,
            'examen.json' => json_encode(['format_version' => 1, 'examen' => [
                'taux_reussite' => 150,
                'duree_minutes' => -5,
                'melange_questions' => 'oui',
                'identification' => ['telephone' => 'obligatoire', 'email' => 'parfois', 'identifiant' => 'obligatoire'],
            ]]),
        ]), 'examen.zip');

        $analysis = (new QuestionnaireImporter)->analyse($file);
        $notices = implode("\n", $analysis['report']->toArray()['notices']);

        $this->assertSame(75, $analysis['exam']['pass_rate']);
        $this->assertNull($analysis['exam']['duration_minutes']);
        $this->assertTrue($analysis['exam']['shuffle_questions']);
        $this->assertSame('non_demande', $analysis['exam']['identification_fields']['email']);
        $this->assertSame('Identifiant', $analysis['exam']['identifier_label']);
        foreach (['"taux_reussite"', '"duree_minutes"', '"melange_questions"', '« telephone »', '« email »', 'libelle_identifiant'] as $expected) {
            $this->assertStringContainsString($expected, $notices);
        }

        $result = (new QuestionnaireImporter)->import($file, 'Blanc', 'Examen', true);
        $this->assertSame(FieldRequirement::Required, $result['exam']?->requirement(IdentificationField::Identifier));
    }

    public function test_titles_chosen_by_the_user_win(): void
    {
        $file = $this->open($this->zipFile([
            'questions.csv' => self::CSV,
            'examen.json' => json_encode(['format_version' => 1, 'questionnaire' => ['titre' => 'Q'], 'examen' => ['titre' => 'E']]),
        ]), 'e.zip');

        $result = (new QuestionnaireImporter)->import($file, 'Mon questionnaire', 'Mon examen', true);

        $this->assertSame(['Mon questionnaire', 'Mon examen'], [$result['questionnaire']->title, $result['exam']?->title]);
    }

    public function test_bank_file_can_become_a_questionnaire_and_questionnaire_file_a_bank(): void
    {
        $bankFile = $this->open($this->zipFile(['questions.csv' => self::CSV, 'banque.json' => json_encode(['format_version' => 1, 'nom' => 'Météo'])]), 'b.zip');
        $this->assertSame('Météo', (new QuestionnaireImporter)->import($bankFile, null, null, true)['questionnaire']->title);

        $questionnaireFile = $this->open((new QuestionnaireExporter)->zip($this->questionnaire()), 'q.zip');
        $bank = (new BankImporter)->import($questionnaireFile, null, ['name' => 'Depuis un questionnaire'], true)['bank'];
        $this->assertSame(3, $bank->questions()->count());
    }

    public function test_duplicates_inside_the_file_are_skipped_on_demand(): void
    {
        $file = $this->open($this->csvFile("enonce;bonne_reponse;mauvaise_reponse_1\nA ?;Oui;Non\na;Oui;Non\n"), 'd.csv');

        $this->assertSame(1, (new QuestionnaireImporter)->import($file, null, null, true)['imported']);
        $this->assertSame(2, (new QuestionnaireImporter)->import($file, null, null, false)['imported']);
    }

    public function test_example_files_import_without_errors(): void
    {
        foreach (['banque-exemple.csv', 'banque-exemple.zip', 'questionnaire-exemple.zip', 'examen-exemple.zip'] as $example) {
            $file = ExchangeFile::open(public_path('exemples/qcm/'.$example), $example);
            $report = (new QuestionnaireImporter)->analyse($file)['report']->toArray();

            $this->assertSame([], $report['errors'], $example);
            $this->assertSame([], $report['imageProblems'], $example);
            $this->assertSame(4, $report['valid'], $example);
            $this->assertCount(1, $report['notices'], "{$example} : aucun paramètre corrigé attendu.");
        }
    }

    public function test_import_action_creates_questionnaire_and_exam_then_opens_the_questionnaire(): void
    {
        Livewire::test(ListQuestionnaires::class)
            ->callAction('importQuestionnaire', [
                'file' => UploadedFile::fake()->createWithContent('examen-exemple.zip', (string) file_get_contents(public_path('exemples/qcm/examen-exemple.zip'))),
                'questionnaire_title' => 'Blanc importé',
                'has_exam' => true,
                'exam_title' => 'Examen importé',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Questionnaire « Blanc importé » importé : 4 question(s)');

        $questionnaire = Questionnaire::where('title', 'Blanc importé')->sole();
        $this->assertSame(4, $questionnaire->questions()->count());
        $this->assertSame(1, $questionnaire->questions()->whereNotNull('image_path')->count());
        $this->assertSame(30, Exam::where('title', 'Examen importé')->sole()->duration_minutes);
    }

    public function test_import_action_from_the_exam_list_and_refusal_of_an_unusable_file(): void
    {
        Livewire::test(ListExams::class)
            ->callAction('importQuestionnaire', [
                'file' => UploadedFile::fake()->createWithContent('pas-un-qcm.csv', "nom;prenom\nDupont;Jean\n"),
                'questionnaire_title' => 'Rien',
            ])
            ->assertNotified('Fichier refusé');

        $this->assertSame(0, Questionnaire::count());
    }

    public function test_export_actions_download_files(): void
    {
        $exam = Exam::factory()->for($this->questionnaire())->create(['title' => 'Blanc n° 2']);
        $date = now()->format('Y-m-d');

        Livewire::test(EditQuestionnaire::class, ['record' => $exam->questionnaire_id])
            ->callAction('exportQuestionnaireCsv')
            ->assertFileDownloaded("questionnaire-blanc-planeur-{$date}.csv")
            ->callAction('exportQuestionnaireZip')
            ->assertFileDownloaded("questionnaire-blanc-planeur-{$date}.zip");

        Livewire::test(EditExam::class, ['record' => $exam->id])
            ->callAction('exportExam')
            ->assertFileDownloaded("examen-blanc-n-2-{$date}.zip");
    }

    public function test_bank_questions_are_written_in_full_in_the_export(): void
    {
        $bank = Bank::factory()->create();
        $question = Question::factory()->for($bank)->withAnswers(2)->create(['statement' => 'Question de banque ?']);
        $questionnaire = Questionnaire::factory()->create();
        $questionnaire->questions()->attach($question->id);

        $csv = (new QuestionnaireExporter)->csv($questionnaire);

        $this->assertStringContainsString('Question de banque ?', $csv);
        $this->assertStringContainsString($question->correctAnswer->text, $csv);
    }
}
