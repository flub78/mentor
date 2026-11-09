<?php

namespace App\Qcm\Filament\Resources\Exams\Pages;

use App\Qcm\Filament\Actions\ExportQuestionnaireActions;
use App\Qcm\Filament\Resources\Exams\ExamResource;
use App\Qcm\Filament\Resources\Exams\Schemas\ExamForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExamResource::takeAction(),
            ExamResource::previewAction(),
            ExportQuestionnaireActions::exam(),
            ExamResource::duplicateAction(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ExamForm::normalize($data);
    }
}
