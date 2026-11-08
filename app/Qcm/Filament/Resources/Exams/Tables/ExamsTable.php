<?php

namespace App\Qcm\Filament\Resources\Exams\Tables;

use App\Qcm\Filament\Actions\ExportQuestionnaireActions;
use App\Qcm\Filament\Resources\Exams\ExamResource;
use App\Qcm\Models\Exam;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('questionnaire.title')
                    ->label('Questionnaire')
                    ->searchable(),
                TextColumn::make('questions')
                    ->label('Questions')
                    ->state(fn (Exam $record): int => $record->questionnaire->questions()->count()),
                TextColumn::make('duration_minutes')
                    ->label('Durée')
                    ->suffix(' min')
                    ->placeholder('Sans limite'),
                TextColumn::make('pass_rate')
                    ->label('Réussite')
                    ->suffix(' %'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('questionnaire'))
            ->defaultSort('title')
            ->recordActions([
                EditAction::make(),
                ExamResource::duplicateAction(),
                ExportQuestionnaireActions::exam(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Aucun examen')
            ->emptyStateDescription('Un examen fait passer un questionnaire complet, avec une durée et un taux de réussite minimal.');
    }
}
