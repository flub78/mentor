<?php

namespace App\Qcm\Filament\Resources\Banks\RelationManagers;

use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
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
            ->components([
                Textarea::make('statement')
                    ->label('Énoncé')
                    ->required()
                    ->rows(3),

                FileUpload::make('image_path')
                    ->label('Image (facultative)')
                    ->helperText('Schéma, carte ou photo sur laquelle porte la question.')
                    ->image()
                    ->disk(Question::IMAGE_DISK)
                    ->directory(Question::IMAGE_DIRECTORY)
                    ->maxSize((int) config('qcm.image_max_kb'))
                    ->validationMessages([
                        'image' => 'Le fichier doit être une image (JPEG, PNG, GIF, WebP…).',
                        'max' => 'L\'image dépasse la taille maximale de :max Ko.',
                    ]),

                Group::make()
                    ->relationship('correctAnswer')
                    ->statePath('correctAnswer')
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [
                        ...$data,
                        'is_correct' => true,
                        'position' => 0,
                    ])
                    ->schema([
                        TextInput::make('text')
                            ->label('Bonne réponse')
                            ->required(),
                    ]),

                Repeater::make('wrongAnswers')
                    ->label('Mauvaises réponses')
                    ->relationship()
                    ->orderColumn('position')
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [
                        ...$data,
                        'is_correct' => false,
                    ])
                    ->schema([
                        TextInput::make('text')
                            ->label('Réponse')
                            ->required(),
                        Textarea::make('explanation')
                            ->label('Pourquoi cette réponse est fausse (facultatif)')
                            ->rows(2),
                    ])
                    ->defaultItems(3)
                    ->minItems(1)
                    ->addActionLabel('Ajouter une mauvaise réponse')
                    ->validationMessages([
                        'min' => 'Une question doit avoir au moins une mauvaise réponse.',
                    ]),

                Section::make('Compléments')
                    ->schema([
                        Textarea::make('explanation')
                            ->label('Explication de la bonne réponse (facultative)')
                            ->rows(2),
                        TextInput::make('module')
                            ->label('Module (facultatif)')
                            ->helperText('Thème de la question. Reprenez un nom déjà utilisé dans la banque pour regrouper les questions.')
                            ->maxLength(255)
                            ->datalist(fn (): array => $this->bank()->moduleNames()),
                    ]),
            ])
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
            ])
            ->recordActions([
                EditAction::make()->modalWidth(Width::ThreeExtraLarge),
                DeleteAction::make()
                    ->modalDescription('La question et ses réponses seront définitivement supprimées.'),
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
