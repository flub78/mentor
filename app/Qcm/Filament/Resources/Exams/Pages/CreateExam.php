<?php

namespace App\Qcm\Filament\Resources\Exams\Pages;

use App\Qcm\Filament\Resources\Exams\ExamResource;
use App\Qcm\Filament\Resources\Exams\Schemas\ExamForm;
use Filament\Resources\Pages\CreateRecord;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ExamForm::normalize($data);
    }
}
