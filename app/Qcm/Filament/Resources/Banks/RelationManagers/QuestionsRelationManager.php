<?php

namespace App\Qcm\Filament\Resources\Banks\RelationManagers;

use App\Qcm\Filament\Actions\ExportBankActions;
use App\Qcm\Filament\Actions\ImportBankAction;
use App\Qcm\Filament\Schemas\QuestionFormSchema;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
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
 * Questions d'une banque (PRD QCM UC-04) : une bonne réponse, au moins une
 * mauvaise réponse (trois proposées), explications, image et module facultatifs.
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
            ->components(QuestionFormSchema::components(
                fn (): array => $this->bank()->moduleNames(),
                'Thème de la question. Reprenez un nom déjà utilisé dans la banque pour regrouper les questions.',
            ))
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
                TextColumn::make('module')
                    ->label('Module')
                    ->placeholder('Sans module')
                    ->badge()
                    ->sortable(),
                IconColumn::make('image_path')
                    ->label('Image')
                    ->state(fn (Question $record): bool => $record->image_path !== null)
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('module')
                    ->label('Module')
                    ->options(fn (): array => array_combine(
                        $this->bank()->moduleNames(),
                        $this->bank()->moduleNames(),
                    )),
            ])
            ->headerActions([
                CreateAction::make()->modalWidth(Width::ThreeExtraLarge),
                ImportBankAction::make('importQuestions', 'Importer des questions', fn (): Bank => $this->bank()),
                ExportBankActions::group(fn (): Bank => $this->bank()),
            ])
            ->toolbarActions([
                ExportBankActions::bulkGroup(fn (): Bank => $this->bank()),
            ])
            ->recordActions([
                EditAction::make()->modalWidth(Width::ThreeExtraLarge),
                DeleteAction::make()
                    ->modalDescription(function (Question $record): string {
                        $questionnaires = $record->questionnaires()->pluck('title')->all();

                        return 'La question et ses réponses seront définitivement supprimées.'
                            .($questionnaires === [] ? '' : ' Elle sera aussi retirée des questionnaires : « '.implode(' », « ', $questionnaires).' ».');
                    }),
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
