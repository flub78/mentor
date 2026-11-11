<?php

namespace App\Banques\Filament\Resources\Banks\RelationManagers;

use App\Banques\Filament\Actions\ExportBankActions;
use App\Banques\Filament\Actions\ImportBankAction;
use App\Banques\Filament\Schemas\QuestionFormSchema;
use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Banques\Questions\Difficulty;
use App\Banques\Questions\QuestionType;
use App\Banques\Usage\UsageRegistry;
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
 * Questions d'une banque (PRD Banques UC-02), de tous les types du
 * PRD Banques §6 disponibles. Les effets sur les modules qui les utilisent
 * passent par le registre des utilisations (`UsageRegistry`).
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
                    ->helperText(fn (?Question $record): string => $record === null ? '' : self::usages()->substantiveChangeNotice($record))
                    ->options([
                        self::CHANGE_MINOR => 'Mineure (correction de forme) : rien ne change pour les modules qui l\'utilisent',
                        self::CHANGE_SUBSTANTIVE => 'De fond : la question doit être réapprise là où elle est en cours de révision',
                    ])
                    ->default(self::CHANGE_MINOR)
                    ->required()
                    ->visible(fn (string $operation, ?Question $record): bool => $operation === 'edit'
                        && $record !== null
                        && self::usages()->hasSubstantiveChangeEffect($record)),
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
                ImportBankAction::make('importQuestions', 'Importer des questions', fn (): Bank => $this->bank()),
                ExportBankActions::group(fn (): Bank => $this->bank()),
            ])
            ->toolbarActions([
                ExportBankActions::bulkGroup(fn (): Bank => $this->bank()),
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
                            $report = self::usages()->applySubstantiveChange($record);

                            Notification::make()
                                ->title('Modification de fond appliquée')
                                ->body($report === '' ? 'La question n\'était en cours de révision nulle part.' : $report)
                                ->info()
                                ->send();
                        }

                        $this->changeKind = null;
                    })
                    ->successNotificationTitle('Question enregistrée'),
                DeleteAction::make()
                    ->modalDescription(fn (Question $record): string => rtrim('La question sera définitivement supprimée, ainsi que son image. '.self::usages()->questionDeletionNotice($record)))
                    ->successNotificationTitle('Question supprimée'),
            ])
            ->emptyStateHeading('Aucune question')
            ->emptyStateDescription('Ajoutez la première question de cette banque.');
    }

    private static function usages(): UsageRegistry
    {
        return app(UsageRegistry::class);
    }

    private function bank(): Bank
    {
        /** @var Bank $bank */
        $bank = $this->getOwnerRecord();

        return $bank;
    }
}
