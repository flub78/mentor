<?php

namespace App\Qcm\Filament\Resources\Questionnaires\Tables;

use App\Qcm\Filament\Resources\Questionnaires\QuestionnaireResource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuestionnairesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version')
                    ->label('Version')
                    ->placeholder('—'),
                TextColumn::make('questions_count')
                    ->label('Questions')
                    ->counts('questions')
                    ->sortable(),
                TextColumn::make('exams_count')
                    ->label('Examens')
                    ->counts('exams')
                    ->sortable(),
            ])
            ->defaultSort('title')
            ->recordActions([
                EditAction::make(),
                QuestionnaireResource::duplicateAction(),
                QuestionnaireResource::deleteAction(),
            ])
            ->emptyStateHeading('Aucun questionnaire')
            ->emptyStateDescription('Créez un questionnaire, générez-le par tirage dans des banques, ou importez-en un.');
    }
}
