<?php

namespace Tests\Feature\Qcm;

use App\Qcm\Models\Bank;
use App\Qcm\Models\Exam;
use App\Qcm\Models\Question;
use App\Qcm\Models\Questionnaire;
use App\Qcm\Questionnaires\QuestionnaireComposer;
use App\Qcm\Questionnaires\QuestionnaireInUseException;
use App\Qcm\Study\ModuleCatalog;
use App\Qcm\Study\StudyDraw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

/**
 * Étape 5 du plan QCM : questionnaires, questions propres et composition
 * (PRD QCM UC-08).
 */
class QuestionnaireModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_question_belongs_to_exactly_one_bank_or_questionnaire(): void
    {
        $bank = Bank::factory()->create();
        $questionnaire = Questionnaire::factory()->create();

        $this->assertTrue(Question::factory()->create(['bank_id' => null, 'questionnaire_id' => $questionnaire->id])->isOwn());
        $this->assertFalse(Question::factory()->for($bank)->create()->isOwn());

        foreach ([['bank_id' => $bank->id, 'questionnaire_id' => $questionnaire->id], ['bank_id' => null, 'questionnaire_id' => null]] as $owner) {
            try {
                Question::factory()->create($owner);
                $this->fail('Appartenance invalide acceptée.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('exactement l\'un des deux', $exception->getMessage());
            }
        }
    }

    public function test_questions_are_listed_in_order_whatever_their_origin(): void
    {
        $questionnaire = Questionnaire::factory()->withOwnQuestions(2)->withBankQuestions(2)->create();

        $questions = $questionnaire->questions()->get();
        $this->assertSame([0, 1, 2, 3], $questions->pluck('pivot.position')->all());
        $this->assertSame([true, true, false, false], $questions->map->isOwn()->all());
    }

    public function test_deleting_a_questionnaire_deletes_its_own_questions_and_images_only(): void
    {
        Storage::fake(Question::IMAGE_DISK);
        $bank = Bank::factory()->create();
        $questionnaire = Questionnaire::factory()->withOwnQuestions(2)->withBankQuestions(2, $bank)->create();
        $own = $questionnaire->ownQuestions()->first();
        Storage::disk(Question::IMAGE_DISK)->put('qcm/images/propre.png', 'x');
        $own->update(['image_path' => 'qcm/images/propre.png']);

        $questionnaire->delete();

        $this->assertSame(0, Question::whereNotNull('questionnaire_id')->count());
        $this->assertSame(2, $bank->questions()->count());
        $this->assertDatabaseCount('qcm_questionnaire_questions', 0);
        Storage::disk(Question::IMAGE_DISK)->assertMissing('qcm/images/propre.png');
    }

    public function test_a_questionnaire_used_by_an_exam_cannot_be_deleted(): void
    {
        $exam = Exam::factory()->create(['title' => 'Blanc n° 1']);

        $this->expectException(QuestionnaireInUseException::class);
        $this->expectExceptionMessage('« Blanc n° 1 »');

        $exam->questionnaire->delete();
    }

    public function test_deleting_a_bank_question_removes_it_from_questionnaires(): void
    {
        $questionnaire = Questionnaire::factory()->withBankQuestions(2)->create();

        $questionnaire->questions()->first()->delete();

        $this->assertSame(1, $questionnaire->questions()->count());
    }

    public function test_bank_questions_are_added_once_and_own_questions_are_never_added_from_a_bank(): void
    {
        $bank = Bank::factory()->create();
        $questions = Question::factory()->count(3)->for($bank)->create();
        $questionnaire = Questionnaire::factory()->withOwnQuestions(1)->create();
        $foreign = Questionnaire::factory()->withOwnQuestions(1)->create()->ownQuestions()->first();
        $composer = new QuestionnaireComposer;

        $this->assertSame(2, $composer->addBankQuestions($questionnaire, [$questions[2]->id, $questions[0]->id, $foreign->id]));
        $this->assertSame(1, $composer->addBankQuestions($questionnaire, [$questions[0]->id, $questions[1]->id]));

        $this->assertSame(
            [$questionnaire->ownQuestions()->first()->id, $questions[2]->id, $questions[0]->id, $questions[1]->id],
            $questionnaire->questions()->pluck('qcm_questions.id')->all(),
        );
    }

    public function test_draw_adds_questions_per_module_without_repeating_present_ones(): void
    {
        [$a, $b] = Bank::factory()->count(2)->create();
        Question::factory()->count(3)->for($a)->create(['module' => 'Météo']);
        Question::factory()->count(2)->for($b)->create(['module' => 'météo']);
        Question::factory()->count(2)->for($a)->create(['module' => 'Réglementation']);
        $questionnaire = Questionnaire::factory()->create();
        $composer = new QuestionnaireComposer(new StudyDraw(new Randomizer(new Mt19937(42))));

        $first = $composer->addDrawnQuestions($questionnaire, [$a->id, $b->id], ['météo' => 4, 'réglementation' => null]);
        $this->assertSame(['added' => 6, 'reduced' => []], $first);

        $second = $composer->addDrawnQuestions($questionnaire, [$a->id, $b->id], ['météo' => 3]);
        $this->assertSame(['added' => 1, 'reduced' => ['météo' => 1]], $second);
        $this->assertSame(7, $questionnaire->questions()->distinct()->count('qcm_questions.id'));
    }

    public function test_duplicate_copies_own_questions_and_shares_bank_questions(): void
    {
        Storage::fake(Question::IMAGE_DISK);
        $questionnaire = Questionnaire::factory()->withOwnQuestions(1)->withBankQuestions(1)->create(['title' => 'Blanc']);
        $own = $questionnaire->ownQuestions()->first();
        Storage::disk(Question::IMAGE_DISK)->put('qcm/images/a.png', 'image');
        $own->update(['image_path' => 'qcm/images/a.png']);

        $copy = (new QuestionnaireComposer)->duplicate($questionnaire);

        $this->assertSame('Blanc (copie)', $copy->title);
        [$copiedOwn, $shared] = $copy->questions()->get()->all();
        $this->assertNotSame($own->id, $copiedOwn->id);
        $this->assertSame($copy->id, $copiedOwn->questionnaire_id);
        $this->assertSame($own->answers()->pluck('text')->all(), $copiedOwn->answers()->pluck('text')->all());
        $this->assertNotSame('qcm/images/a.png', $copiedOwn->image_path);
        Storage::disk(Question::IMAGE_DISK)->assertExists((string) $copiedOwn->image_path);
        $this->assertSame($questionnaire->questions()->get()[1]->id, $shared->id);
    }

    public function test_own_questions_never_appear_in_free_study(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->create(['module' => 'Météo']);
        Questionnaire::factory()->withOwnQuestions(3)->create();

        $this->assertSame(1, array_sum(array_column((new ModuleCatalog)->forBanks([$bank->id]), 'count')));
        $this->assertCount(1, (new StudyDraw)->draw([$bank->id], ['météo' => null])['questionIds']);
    }

    public function test_module_catalog_of_a_questionnaire(): void
    {
        $questionnaire = Questionnaire::factory()->create();
        $own = Question::factory()->create(['bank_id' => null, 'questionnaire_id' => $questionnaire->id, 'module' => 'Météo']);
        $fromBank = Question::factory()->create(['module' => 'météo']);
        $questionnaire->questions()->attach([$own->id, $fromBank->id]);
        Question::factory()->create(['module' => 'Navigation']);

        $this->assertSame([['key' => 'météo', 'label' => 'Météo', 'count' => 2]], (new ModuleCatalog)->forQuestionnaire($questionnaire));
    }
}
