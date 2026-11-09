<?php

namespace Tests\Feature\Qcm;

use App\Qcm\Exams\AttemptFinisher;
use App\Qcm\Exams\AttemptStarter;
use App\Qcm\Exams\ResultsCsv;
use App\Qcm\Filament\Resources\Exams\Pages\EditExam;
use App\Qcm\Filament\Resources\Exams\RelationManagers\ResultsRelationManager;
use App\Qcm\Filament\Resources\Results\Pages\ListResults;
use App\Qcm\Filament\Resources\Results\Pages\ViewResult;
use App\Qcm\Models\Attempt;
use App\Qcm\Models\Exam;
use App\Qcm\Models\FieldRequirement;
use App\Qcm\Models\Questionnaire;
use App\Qcm\Models\Result;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Étape 10 du plan QCM : liste, export et suppression des résultats
 * (PRD QCM UC-09).
 */
class ResultResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_are_listed_including_those_of_deleted_exams(): void
    {
        $kept = $this->resultFor($this->exam('Blanc n° 1'), ['nom' => 'Martin', 'prenom' => 'Alice']);
        $deletedExam = $this->exam('Blanc supprimé');
        $orphan = $this->resultFor($deletedExam, ['nom' => 'Durand', 'prenom' => 'Bob']);
        $deletedExam->delete();

        Livewire::test(ListResults::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$kept, $orphan])
            ->assertSee('Blanc supprimé')
            ->assertSee($orphan->certificate_number)
            ->assertSee('Réussi');
    }

    public function test_results_can_be_searched_by_identity_and_certificate_number(): void
    {
        $exam = $this->exam('Blanc', [
            'email' => FieldRequirement::Optional->value,
            'organisation' => FieldRequirement::Optional->value,
            'identifiant' => FieldRequirement::Optional->value,
        ]);
        $alice = $this->resultFor($exam, ['nom' => 'Martin', 'prenom' => 'Alice', 'email' => 'alice@club.fr', 'organisation' => 'Aéroclub de Vinon', 'identifiant' => 'LIC-42']);
        $bob = $this->resultFor($exam, ['nom' => 'Durand', 'prenom' => 'Bob']);

        foreach (['martin', 'Alice Martin', 'alice@club', 'Vinon', 'LIC-42', $alice->certificate_number] as $search) {
            Livewire::test(ListResults::class)
                ->searchTable($search)
                ->assertCanSeeTableRecords([$alice])
                ->assertCanNotSeeTableRecords([$bob]);
        }

        Livewire::test(ListResults::class)->searchTable('Inconnu')->assertCountTableRecords(0);
    }

    public function test_results_can_be_filtered_by_exam_period_and_mention(): void
    {
        $first = $this->exam('Premier');
        $second = $this->exam('Second');
        $this->travelTo(now()->setDate(2026, 3, 10));
        $march = $this->resultFor($first, ['nom' => 'Mars', 'prenom' => 'A']);
        $this->travelTo(now()->setDate(2026, 6, 10));
        $june = $this->resultFor($second, ['nom' => 'Juin', 'prenom' => 'B'], correct: false);
        $this->travelBack();

        Livewire::test(ListResults::class)
            ->filterTable('exam', $first->id)
            ->assertCanSeeTableRecords([$march])
            ->assertCanNotSeeTableRecords([$june]);

        Livewire::test(ListResults::class)
            ->filterTable('period', ['from' => '2026-06-01', 'until' => '2026-06-30'])
            ->assertCanSeeTableRecords([$june])
            ->assertCanNotSeeTableRecords([$march]);

        Livewire::test(ListResults::class)
            ->filterTable('passed', false)
            ->assertCanSeeTableRecords([$june])
            ->assertCanNotSeeTableRecords([$march]);
    }

    public function test_the_detail_shows_rates_per_module(): void
    {
        $result = Result::factory()->create([
            'module_rates' => [
                ['label' => 'Météorologie', 'correct' => 2, 'total' => 3, 'rate' => 66.67],
                ['label' => 'Réglementation', 'correct' => 1, 'total' => 1, 'rate' => 100],
            ],
            'global_rate' => 75,
            'questionnaire_version' => 'v3',
        ]);

        Livewire::test(ViewResult::class, ['record' => $result->getRouteKey()])
            ->assertOk()
            ->assertSee('Météorologie : 2 / 3 — 66,67 %')
            ->assertSee('Réglementation : 1 / 1 — 100 %')
            ->assertSee('Nom : '.$result->last_name)
            ->assertSee('v3')
            ->assertSee($result->certificate_number);
    }

    public function test_the_csv_export_has_one_line_per_result_and_one_column_per_module(): void
    {
        $results = [
            Result::factory()->create([
                'identity' => [
                    ['key' => 'nom', 'label' => 'Nom', 'value' => 'Éluard'],
                    ['key' => 'prenom', 'label' => 'Prénom', 'value' => 'Hélène'],
                    ['key' => 'identifiant', 'label' => 'Numéro de licence', 'value' => 'LIC-1'],
                ],
                'taken_at' => '2026-11-09 14:05:00',
                'exam_title' => 'Blanc', 'questionnaire_title' => 'QCM', 'questionnaire_version' => 'v1',
                'module_rates' => [['label' => 'Météorologie', 'correct' => 2, 'total' => 3, 'rate' => 66.67]],
                'global_rate' => 66.67, 'pass_rate' => 75, 'passed' => false, 'certificate_number' => '20261109-AAAAAA',
            ]),
            Result::factory()->create([
                'identity' => [['key' => 'nom', 'label' => 'Nom', 'value' => 'Durand'], ['key' => 'prenom', 'label' => 'Prénom', 'value' => 'Bob']],
                'taken_at' => '2026-11-10 09:00:00',
                'exam_title' => 'Blanc', 'questionnaire_title' => 'QCM', 'questionnaire_version' => null,
                'module_rates' => [
                    ['label' => 'météorologie', 'correct' => 1, 'total' => 1, 'rate' => 100],
                    ['label' => 'Sans module', 'correct' => 0, 'total' => 1, 'rate' => 0],
                ],
                'global_rate' => 50, 'pass_rate' => 50, 'passed' => true, 'certificate_number' => '20261110-BBBBBB',
            ]),
        ];

        $csv = (new ResultsCsv)->csv($results);

        $this->assertStringStartsWith("\u{FEFF}", $csv);
        $this->assertSame([
            'Nom;Prénom;"Numéro de licence";"Date du passage";Examen;Questionnaire;Version;"Taux Météorologie";"Taux Sans module";"Taux global";"Taux minimal";Réussite;"Numéro de certificat"',
            'Éluard;Hélène;LIC-1;"09/11/2026 14:05";Blanc;QCM;v1;66,67;;66,67;75;Non;20261109-AAAAAA',
            'Durand;Bob;;"10/11/2026 09:00";Blanc;QCM;;100;0;50;50;Oui;20261110-BBBBBB',
        ], explode("\n", trim(substr($csv, 3))));
    }

    public function test_the_export_follows_the_search_and_filters(): void
    {
        $exam = $this->exam('Blanc planeur');
        $this->resultFor($exam, ['nom' => 'Martin', 'prenom' => 'Alice']);
        $this->resultFor($exam, ['nom' => 'Durand', 'prenom' => 'Bob']);

        $component = Livewire::test(ListResults::class)
            ->searchTable('Martin')
            ->callAction(TestAction::make('exportResults')->table())
            ->assertFileDownloaded();

        $csv = (new ResultsCsv)->csv($component->instance()->getFilteredSortedTableQuery()->get());
        $this->assertStringContainsString('Martin;Alice', $csv);
        $this->assertStringNotContainsString('Durand', $csv);
    }

    public function test_a_result_can_be_deleted_after_confirmation(): void
    {
        $result = Result::factory()->create();

        Livewire::test(ListResults::class)
            ->assertActionExists(TestAction::make(DeleteAction::class)->table($result), fn (DeleteAction $action): bool => str_contains((string) $action->getModalDescription(), 'ne pourra plus être réédité'))
            ->callAction(TestAction::make(DeleteAction::class)->table($result))
            ->assertNotified('Résultat supprimé');

        $this->assertModelMissing($result);
    }

    public function test_an_exam_lists_only_its_own_results(): void
    {
        $exam = $this->exam('Blanc n° 1');
        $own = $this->resultFor($exam, ['nom' => 'Martin', 'prenom' => 'Alice']);
        $other = $this->resultFor($this->exam('Autre'), ['nom' => 'Durand', 'prenom' => 'Bob']);

        Livewire::test(ResultsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other])
            ->callAction(TestAction::make('exportResults')->table())
            ->assertFileDownloaded();
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function exam(string $title, array $fields = []): Exam
    {
        return Exam::factory()->for(Questionnaire::factory()->withOwnQuestions(2))->create([
            'title' => $title,
            'pass_rate' => 50,
            'identification_fields' => [...Exam::defaultIdentificationFields(), ...$fields],
        ]);
    }

    /**
     * Passage réel terminé ; toutes les réponses justes, ou aucune réponse.
     *
     * @param  array<string, string>  $identity
     */
    private function resultFor(Exam $exam, array $identity, bool $correct = true): Result
    {
        $attempt = (new AttemptStarter)->start($exam, $identity);

        if ($correct) {
            foreach ($attempt->items as $item) {
                $item->update(['chosen_answer_id' => $item->question->answers()->where('is_correct', true)->value('id')]);
            }
        }

        return (new AttemptFinisher)->finish(Attempt::findOrFail($attempt->id));
    }
}
