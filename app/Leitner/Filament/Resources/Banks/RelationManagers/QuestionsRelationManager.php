<?php

namespace App\Leitner\Filament\Resources\Banks\RelationManagers;

use App\Leitner\Filament\Schemas\QuestionFormSchema;
use App\Leitner\Models\Bank;
use App\Leitner\Models\Question;
use App\Leitner\Questions\Difficulty;
use App\Leitner\Questions\QuestionType;
use App\Leitner\Suites\QuestionUsage;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
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
    /** Modification d'une question introduite (UC-02, 1a). */
    private const CHANGE_MINOR = 'mineure';

    private const CHANGE_SUBSTANTIVE = 'fond';

    /** Choix fait dans le formulaire de modification, appliqué après l'enregistrement. */
    private ?string $changeKind = null;

    protected static string $relationship = 'questions';

    protected static ?string $title = 'Questions';

    protected static ?string $modelLabel = 'question';

    protected static ?string $pluralModelLabel = 'questions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...QuestionFormSchema::components(fn (): array => $this->bank()->themeNames()),
                Radio::make('change_kind')
                    ->label('Nature de la modification')
                    ->helperText(fn (?Question $record): string => self::usageText($record))
                    ->options([
                        self::CHANGE_MINOR => 'Mineure (correction de forme) : la question garde sa boîte',
                        self::CHANGE_SUBSTANTIVE => 'De fond : la question revient en boîte 1 dans toutes les suites',
                    ])
                    ->default(self::CHANGE_MINOR)
                    ->required()
                    ->visible(fn (string $operation, ?Question $record): bool => $operation === 'edit'
                        && $record !== null
                        && QuestionUsage::progressionsWithQuestion($record) > 0),
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
                    ->mutateDataUsing(function (array $data): array {
                        $this->changeKind = $data['change_kind'] ?? null;
                        unset($data['change_kind']);

                        return $data;
                    })
                    ->after(function (Question $record): void {
                        if ($this->changeKind === self::CHANGE_SUBSTANTIVE) {
                            $count = QuestionUsage::resetProgress($record);

                            Notification::make()
                                ->title('Question remise en boîte 1')
                                ->body($count === 1 ? 'Dans 1 suite.' : "Dans {$count} suites.")
                                ->info()
                                ->send();
                        }

                        $this->changeKind = null;
                    })
                    ->successNotificationTitle('Question enregistrée'),
                DeleteAction::make()
                    ->modalDescription(fn (Question $record): string => 'La question sera définitivement supprimée, ainsi que son image.'
                        .match ($count = QuestionUsage::progressionsWithQuestion($record)) {
                            0 => '',
                            1 => ' Elle disparaîtra de la suite où elle est en cours de révision.',
                            default => " Elle disparaîtra des {$count} suites où elle est en cours de révision.",
                        })
                    ->successNotificationTitle('Question supprimée'),
            ])
            ->emptyStateHeading('Aucune question')
            ->emptyStateDescription('Ajoutez la première question de cette banque.');
    }

    private static function usageText(?Question $record): string
    {
        $count = $record === null ? 0 : QuestionUsage::progressionsWithQuestion($record);

        return $count === 1
            ? 'Cette question est en cours de révision dans 1 suite.'
            : "Cette question est en cours de révision dans {$count} suites.";
    }

    private function bank(): Bank
    {
        /** @var Bank $bank */
        $bank = $this->getOwnerRecord();

        return $bank;
    }
}
