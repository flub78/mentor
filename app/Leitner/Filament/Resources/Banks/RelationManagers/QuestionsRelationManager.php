<?php

namespace App\Leitner\Filament\Resources\Banks\RelationManagers;

use App\Leitner\Filament\Schemas\QuestionFormSchema;
use App\Leitner\Models\Bank;
use App\Leitner\Models\Question;
use App\Leitner\Questions\Difficulty;
use App\Leitner\Questions\QuestionType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Questions d'une banque Leitner (PRD Leitner UC-02), de tous les types du
 * PRD §6 disponibles.
 */
class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Questions';

    protected static ?string $modelLabel = 'question';

    protected static ?string $pluralModelLabel = 'questions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(QuestionFormSchema::components(fn (): array => $this->bank()->themeNames()))
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('statement')
            ->columns([
                TextColumn::make('statement')
                    ->label('Énoncé')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('difficulty')
                    ->label('Difficulté')
                    ->placeholder('Non précisée')
                    ->sortable(),
                TextColumn::make('theme')
                    ->label('Thème')
                    ->placeholder('Sans thème')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                IconColumn::make('image_path')
                    ->label('Image')
                    ->state(fn (Question $record): bool => $record->image_path !== null)
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(QuestionType::class),
                SelectFilter::make('difficulty')
                    ->label('Difficulté')
                    ->options(Difficulty::class),
                SelectFilter::make('theme')
                    ->label('Thème')
                    ->options(fn (): array => array_combine(
                        $this->bank()->themeNames(),
                        $this->bank()->themeNames(),
                    )),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->successNotificationTitle('Question créée'),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->successNotificationTitle('Question enregistrée'),
                DeleteAction::make()
                    ->modalDescription('La question sera définitivement supprimée, ainsi que son image.')
                    ->successNotificationTitle('Question supprimée'),
            ])
            ->emptyStateHeading('Aucune question')
            ->emptyStateDescription('Ajoutez la première question de cette banque.');
    }

    private function bank(): Bank
    {
        /** @var Bank $bank */
        $bank = $this->getOwnerRecord();

        return $bank;
    }
}
