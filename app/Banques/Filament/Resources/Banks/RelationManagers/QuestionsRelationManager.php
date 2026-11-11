<?php

namespace App\Banques\Filament\Resources\Banks\RelationManagers;

use App\Banques\Filament\Actions\ExportBankActions;
use App\Banques\Filament\Actions\ImportBankAction;
use App\Banques\Filament\Schemas\QuestionFormSchema;
use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Banques\Questions\Difficulty;
use App\Banques\Questions\QuestionMover;
use App\Banques\Questions\QuestionType;
use App\Banques\Usage\UsageRegistry;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
                ...QuestionFormSchema::components(fn (): array => $this->bank()->themeNames(), bankId: fn (): int => $this->bank()->id),
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
                $this->moveAction(),
                $this->copyAction(),
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

    /**
     * « Déplacer vers » (PRD Banques UC-03) : par exemple pour ranger les
     * questions de la banque par défaut dans une banque thématique.
     */
    private function moveAction(): BulkAction
    {
        return BulkAction::make('moveQuestions')
            ->label('Déplacer vers…')
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->modalHeading('Déplacer les questions sélectionnées')
            ->modalSubmitActionLabel('Déplacer')
            ->schema([
                $this->targetBankField()
                    ->live()
                    ->helperText(function (Get $get): string {
                        $target = Bank::query()->find($get('target_bank_id'));

                        if ($target === null) {
                            return 'Les questions gardent leurs utilisations : un questionnaire QCM continue de les poser.';
                        }

                        $notice = app(QuestionMover::class)->moveNotice($this->getSelectedTableRecords(), $target);

                        return $notice === '' ? 'Aucune conséquence ailleurs : les questions gardent leurs utilisations.' : $notice;
                    }),
            ])
            ->action(
                function (Collection $records, array $data): void {
                    $target = Bank::query()->findOrFail($data['target_bank_id']);
                    $result = app(QuestionMover::class)->move(self::fresh($records), $target);

                    Notification::make()
                        ->success()
                        ->title(($result['moved'] === 1 ? '1 question déplacée' : "{$result['moved']} questions déplacées")." vers « {$target->name} »")
                        ->body(implode(' ', array_filter([$result['report'], QuestionMover::droppedReferencesNotice($result['droppedReferences'])])) ?: null)
                        ->send();
                },
            )
            ->deselectRecordsAfterCompletion();
    }

    /**
     * « Copier vers » (PRD Banques UC-03) : copies indépendantes.
     */
    private function copyAction(): BulkAction
    {
        return BulkAction::make('copyQuestions')
            ->label('Copier vers…')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->modalHeading('Copier les questions sélectionnées')
            ->modalDescription('Chaque copie est une nouvelle question indépendante, image comprise ; elle n\'est utilisée nulle part.')
            ->modalSubmitActionLabel('Copier')
            ->schema([$this->targetBankField()])
            ->action(
                function (Collection $records, array $data): void {
                    $target = Bank::query()->findOrFail($data['target_bank_id']);
                    $count = app(QuestionMover::class)->copy(self::fresh($records), $target, $droppedReferences)->count();

                    Notification::make()
                        ->success()
                        ->title(($count === 1 ? '1 question copiée' : "{$count} questions copiées")." dans « {$target->name} »")
                        ->body(QuestionMover::droppedReferencesNotice($droppedReferences))
                        ->send();
                },
            )
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Questions sélectionnées, relues en base.
     *
     * @param  Collection<int, Model>  $records
     * @return Collection<int, Question>
     */
    private static function fresh(Collection $records): Collection
    {
        return Question::query()->whereKey($records->modelKeys())->orderBy('id')->get();
    }

    private function targetBankField(): Select
    {
        return Select::make('target_bank_id')
            ->label('Banque d\'arrivée')
            ->options(fn (): array => Bank::query()->whereKeyNot($this->bank()->id)->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->required()
            ->validationMessages(['required' => 'Choisissez la banque d\'arrivée.']);
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
