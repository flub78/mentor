<?php

namespace Tests\Feature\Banques;

use App\Banques\Filament\Resources\Banks\Pages\EditBank;
use App\Banques\Filament\Resources\Banks\RelationManagers\QuestionsRelationManager;
use App\Banques\Models\Bank;
use App\Banques\Models\Question as CommonQuestion;
use App\Banques\Questions\Handlers\QcmHandler;
use App\Banques\Questions\QuestionType;
use App\Leitner\Filament\Pages\Seance;
use App\Leitner\Models\Suite;
use App\Leitner\Suites\PresentationOrder;
use App\Qcm\Exams\AttemptStarter;
use App\Qcm\Models\Exam;
use App\Qcm\Models\Question;
use App\Qcm\Study\StudyDraw;
use App\Qcm\Study\StudySession;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Plan Banques, lot B1.1, étape R2 : ordre fixe des propositions d'un QCM non
 * mélangé, dans tous les modules (PRD Banques §6).
 */
class FixedAnswerOrderTest extends TestCase
{
    use RefreshDatabase;

    /** Bonne réponse en 3e place : « A · B · Partage ». */
    private const PAYLOAD = [
        'correct' => 'Partage',
        'wrong' => [['text' => 'A', 'explanation' => null], ['text' => 'B', 'explanation' => 'B a la plus faible main.']],
        'shuffle' => false,
        'correct_position' => 2,
    ];

    private function question(Bank $bank): CommonQuestion
    {
        return CommonQuestion::factory()->for($bank)->create(['type' => QuestionType::Qcm, 'statement' => 'Qui gagne ?', 'payload' => self::PAYLOAD]);
    }

    public function test_the_correct_answer_takes_its_place_and_shuffling_resets_it(): void
    {
        $handler = new QcmHandler;

        $this->assertSame(['fausse-0' => 'A', 'fausse-1' => 'B', 'bonne' => 'Partage'], $handler->options(self::PAYLOAD));
        $this->assertSame(0, $handler->normalize([...self::PAYLOAD, 'shuffle' => true])['correct_position']);
        $this->assertSame(1, $handler->normalize([...self::PAYLOAD, 'wrong' => [['text' => 'A']]])['correct_position'], 'Une mauvaise réponse retirée ne laisse pas la bonne réponse hors de la liste.');
        $this->assertSame(['La place de la bonne réponse ne peut pas être négative.'], QuestionType::Qcm->payloadErrors($handler->normalize([...self::PAYLOAD, 'correct_position' => -1])));
        $this->assertSame(['La place de la bonne réponse doit être un nombre entier.'], QuestionType::Qcm->payloadErrors($handler->normalize([...self::PAYLOAD, 'correct_position' => 'deux'])));
    }

    public function test_the_form_sets_the_place_of_the_correct_answer(): void
    {
        Filament::setCurrentPanel('leitner');
        Repeater::fake();
        $bank = Bank::factory()->create();

        Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $bank, 'pageClass' => EditBank::class])
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'type' => 'qcm',
                'statement' => 'Qui gagne ?',
                'payload' => ['correct' => 'Partage', 'wrong' => [['text' => 'A', 'explanation' => null], ['text' => 'B', 'explanation' => null]], 'shuffle' => false, 'correct_position' => 2],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(['A', 'B', 'Partage'], array_values((new QcmHandler)->options($bank->questions()->sole()->payload)));
    }

    public function test_qcm_study_exam_and_correction_follow_the_fixed_order(): void
    {
        $question = Question::query()->findOrFail($this->question(Bank::factory()->create())->id);

        $this->assertSame(['fausse-0', 'fausse-1', 'bonne'], $question->answers->pluck('key')->all());
        $this->assertSame([0, 1, 2], $question->answers->pluck('position')->all());
        $this->assertSame('B a la plus faible main.', $question->answer('fausse-1')?->explanation);
        $this->assertSame(['fausse-0', 'fausse-1', 'bonne'], StudySession::start([$question->id], [], new StudyDraw)->answerOrders[$question->id]);

        $exam = Exam::factory()->create();
        $exam->questionnaire->questions()->sync([$question->id]);
        $this->assertSame(['fausse-0', 'fausse-1', 'bonne'], app(AttemptStarter::class)->compose($exam->fresh())[0]['answer_order']);
    }

    public function test_a_leitner_session_shows_the_fixed_order(): void
    {
        Filament::setCurrentPanel('leitner');
        $bank = Bank::factory()->create();
        $this->question($bank);
        $suite = Suite::factory()->withBanks($bank)->create(['retry_errors' => false, 'presentation_order' => PresentationOrder::BoiteUnDAbord]);

        Livewire::test(Seance::class, ['suite' => $suite->id])
            ->call('start')
            ->assertSeeInOrder(['A', 'B', 'Partage']);
    }
}
