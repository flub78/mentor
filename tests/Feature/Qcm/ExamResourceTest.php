<?php

namespace Tests\Feature\Qcm;

use App\Qcm\Filament\Resources\Exams\ExamResource;
use App\Qcm\Filament\Resources\Exams\Pages\CreateExam;
use App\Qcm\Filament\Resources\Exams\Pages\EditExam;
use App\Qcm\Filament\Resources\Exams\Pages\ListExams;
use App\Qcm\Models\Exam;
use App\Qcm\Models\FieldRequirement;
use App\Qcm\Models\IdentificationField;
use App\Qcm\Models\Questionnaire;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Étape 5 du plan QCM : définition des examens (PRD QCM UC-20).
 */
class ExamResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_exam_with_identification_fields(): void
    {
        $questionnaire = Questionnaire::factory()->withOwnQuestions(2)->create();

        Livewire::withQueryParams(['questionnaire' => $questionnaire->id])
            ->test(CreateExam::class)
            ->assertSchemaStateSet(['questionnaire_id' => $questionnaire->id, 'pass_rate' => 75, 'shows_correction' => true])
            ->assertSee('2 question(s)')
            ->fillForm([
                'title' => 'Examen blanc planeur',
                'duration_minutes' => 60,
                'pass_rate' => 80,
                'max_attempts' => 2,
                'identification_fields' => [
                    'email' => 'obligatoire',
                    'date_naissance' => 'non_demande',
                    'organisation' => 'facultatif',
                    'identifiant' => 'facultatif',
                ],
                'identifier_label' => 'Numéro de licence',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $exam = Exam::where('title', 'Examen blanc planeur')->sole();
        $this->assertSame([60, 80, 2], [$exam->duration_minutes, $exam->pass_rate, $exam->max_attempts]);
        $this->assertSame(FieldRequirement::Required, $exam->requirement(IdentificationField::Email));
        $this->assertSame(FieldRequirement::NotAsked, $exam->requirement(IdentificationField::BirthDate));
        $this->assertSame('Numéro de licence', $exam->identifier_label);
    }

    public function test_exam_validation(): void
    {
        Livewire::test(CreateExam::class)
            ->fillForm([
                'title' => '',
                'questionnaire_id' => null,
                'pass_rate' => 150,
                'duration_minutes' => 0,
                'identification_fields' => ['identifiant' => 'obligatoire'],
                'identifier_label' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'title' => 'required',
                'questionnaire_id' => 'required',
                'pass_rate' => 'max',
                'duration_minutes' => 'min',
                'identifier_label' => 'required',
            ]);

        $this->assertSame(0, Exam::count());
    }

    public function test_identifier_label_is_cleared_when_the_identifier_is_not_asked(): void
    {
        $exam = Exam::factory()->create(['identifier_label' => 'Licence', 'identification_fields' => ['identifiant' => 'facultatif'] + Exam::defaultIdentificationFields()]);

        Livewire::test(EditExam::class, ['record' => $exam->id])
            ->fillForm(['identification_fields' => ['identifiant' => 'non_demande']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($exam->fresh()->identifier_label);
    }

    public function test_list_duplicate_and_delete(): void
    {
        $exam = Exam::factory()->create(['title' => 'Blanc']);

        $this->get(ExamResource::getUrl('index'))->assertOk()->assertSee('Blanc');

        Livewire::test(ListExams::class)
            ->callAction(TestAction::make('duplicate')->table($exam))
            ->assertNotified('Examen « Blanc (copie) » créé')
            ->callAction(TestAction::make(DeleteAction::class)->table($exam));

        $this->assertModelMissing($exam);
        $this->assertSame($exam->questionnaire_id, Exam::where('title', 'Blanc (copie)')->sole()->questionnaire_id);
    }
}
