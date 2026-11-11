<?php

namespace App\Qcm\Filament\Resources\Exams\Pages;

use App\Qcm\Filament\Actions\ImportQuestionnaireAction;
use App\Qcm\Filament\Resources\Exams\ExamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExams extends ListRecords
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportQuestionnaireAction::make()->label('Importer un examen'),
            CreateAction::make(),
        ];
    }
}
