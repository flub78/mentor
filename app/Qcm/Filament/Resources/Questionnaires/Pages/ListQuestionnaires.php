<?php

namespace App\Qcm\Filament\Resources\Questionnaires\Pages;

use App\Qcm\Filament\Resources\Questionnaires\QuestionnaireResource;
use App\Qcm\Filament\Schemas\DrawQuestionsForm;
use App\Qcm\Models\Questionnaire;
use App\Qcm\Questionnaires\QuestionnaireComposer;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class ListQuestionnaires extends ListRecords
{
    protected static string $resource = QuestionnaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->generateAction(),
            CreateAction::make(),
        ];
    }

    /**
     * Générer un questionnaire par tirage dans des banques (PRD QCM UC-08, 1a).
     */
    private function generateAction(): Action
    {
        return Action::make('generate')
            ->label('Générer par tirage')
            ->icon(Heroicon::OutlinedSparkles)
            ->modalHeading('Générer un questionnaire par tirage au hasard')
            ->modalSubmitActionLabel('Générer')
            ->schema([
                TextInput::make('title')->label('Titre du questionnaire')->required()->maxLength(255),
                ...DrawQuestionsForm::components(),
            ])
            ->action(function (array $data, Action $action): void {
                $request = DrawQuestionsForm::request($data);

                [$questionnaire, $result] = DB::transaction(function () use ($data, $request): array {
                    $questionnaire = Questionnaire::create(['title' => $data['title']]);

                    return [$questionnaire, (new QuestionnaireComposer)->addDrawnQuestions($questionnaire, $request['bankIds'], $request['requested'])];
                });

                Notification::make()
                    ->success()
                    ->title("Questionnaire « {$questionnaire->title} » généré : {$result['added']} question(s)")
                    ->body(DrawQuestionsForm::reducedMessage($request['bankIds'], $result['reduced']))
                    ->send();
                $action->redirect(QuestionnaireResource::getUrl('edit', ['record' => $questionnaire]));
            });
    }
}
