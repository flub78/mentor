<?php

namespace App\Qcm\Filament\Resources\Questionnaires\Pages;

use App\Qcm\Filament\Resources\Questionnaires\QuestionnaireResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuestionnaire extends CreateRecord
{
    protected static string $resource = QuestionnaireResource::class;

    /**
     * Après création, ouvrir le questionnaire pour y ajouter les questions.
     */
    protected function getRedirectUrl(): string
    {
        return QuestionnaireResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
