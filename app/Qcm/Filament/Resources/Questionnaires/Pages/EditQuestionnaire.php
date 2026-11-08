<?php

namespace App\Qcm\Filament\Resources\Questionnaires\Pages;

use App\Qcm\Filament\Resources\Exams\ExamResource;
use App\Qcm\Filament\Resources\Questionnaires\QuestionnaireResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditQuestionnaire extends EditRecord
{
    protected static string $resource = QuestionnaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createExam')
                ->label('Créer un examen')
                ->icon(Heroicon::OutlinedAcademicCap)
                ->url(fn (): string => ExamResource::getUrl('create', ['questionnaire' => $this->getRecord()->getKey()])),
            QuestionnaireResource::duplicateAction(),
            QuestionnaireResource::deleteAction(),
        ];
    }
}
