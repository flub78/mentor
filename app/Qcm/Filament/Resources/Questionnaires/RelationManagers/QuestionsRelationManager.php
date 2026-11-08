<?php

namespace App\Qcm\Filament\Resources\Questionnaires\RelationManagers;

use App\Qcm\Filament\Resources\Banks\BankResource;
use App\Qcm\Filament\Schemas\DrawQuestionsForm;
use App\Qcm\Filament\Schemas\QuestionFormSchema;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use App\Qcm\Models\Questionnaire;
use App\Qcm\Questionnaires\QuestionnaireComposer;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Questions d'un questionnaire (PRD QCM UC-08) : questions propres, saisies
 * ici, et questions de banque, choisies ou tirées au hasard ; liste ordonnée.
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
                fn (): array => $this->questionnaire()->moduleNames(),
                'Thème de la question. Reprenez un nom déjà utilisé dans le questionnaire pour regrouper les questions.',
            ))
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('statement')
            ->reorderable('position')
            ->columns([
                TextColumn::make('statement')
                    ->label('Énoncé')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('module')
                    ->label('Module')
                    ->placeholder('Sans module')
                    ->badge(),
                TextColumn::make('origin')
                    ->label('Origine')
                    ->state(fn (Question $record): string => $record->isOwn() ? 'Question propre' : 'Banque « '.$record->bank?->name.' »')
                    ->color(fn (Question $record): string => $record->isOwn() ? 'gray' : 'info')
                    ->badge(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('bank'))
            ->headerActions([
                CreateAction::make()
                    ->label('Nouvelle question')
                    ->modalHeading('Nouvelle question propre')
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'bank_id' => null,
                        'questionnaire_id' => $this->questionnaire()->id,
                    ]),
                $this->addFromBankAction(),
                $this->drawAction(),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->visible(fn (Question $record): bool => $record->isOwn()),
                Action::make('openBank')
                    ->label('Modifier dans la banque')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Question $record): ?string => $record->bank_id === null ? null : BankResource::getUrl('edit', ['record' => $record->bank_id]))
                    ->visible(fn (Question $record): bool => ! $record->isOwn()),
                DetachAction::make()
                    ->label('Retirer')
                    ->modalHeading('Retirer la question du questionnaire ?')
                    ->modalDescription(fn (Question $record): string => 'La question reste dans la banque « '.$record->bank?->name.' ».')
                    ->visible(fn (Question $record): bool => ! $record->isOwn()),
                DeleteAction::make()
                    ->modalDescription('Cette question propre et ses réponses seront définitivement supprimées.')
                    ->visible(fn (Question $record): bool => $record->isOwn()),
            ])
            ->emptyStateHeading('Aucune question')
            ->emptyStateDescription('Saisissez une question propre, ajoutez des questions d\'une banque, ou tirez-en au hasard.');
    }

    private function addFromBankAction(): Action
    {
        return Action::make('addFromBank')
            ->label('Ajouter depuis une banque')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Ajouter')
            ->schema([
                Select::make('bank_id')
                    ->label('Banque')
                    ->options(fn (): array => Bank::query()->has('questions')->orderBy('name')->pluck('name', 'id')->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('module', null);
                        $set('question_ids', []);
                    }),
                Select::make('module')
                    ->label('Module')
                    ->placeholder('Tous les modules')
                    ->options(fn (Get $get): array => ($bank = Bank::find($get('bank_id'))) === null ? [] : array_combine($bank->moduleNames(), $bank->moduleNames()))
                    ->live()
                    ->visible(fn (Get $get): bool => filled($get('bank_id'))),
                CheckboxList::make('question_ids')
                    ->label('Questions')
                    ->helperText('Les questions déjà présentes dans le questionnaire ne sont pas proposées.')
                    ->options(fn (Get $get): array => $this->availableBankQuestions($get('bank_id'), $get('module')))
                    ->bulkToggleable()
                    ->searchable()
                    ->required()
                    ->visible(fn (Get $get): bool => filled($get('bank_id')))
                    ->validationMessages(['required' => 'Cochez au moins une question.']),
            ])
            ->action(function (array $data): void {
                $added = (new QuestionnaireComposer)->addBankQuestions(
                    $this->questionnaire(),
                    array_map('intval', (array) $data['question_ids']),
                );

                Notification::make()->success()->title("{$added} question(s) ajoutée(s)")->send();
            });
    }

    private function drawAction(): Action
    {
        return Action::make('draw')
            ->label('Tirer au hasard')
            ->icon(Heroicon::OutlinedSparkles)
            ->modalHeading('Tirer des questions au hasard dans des banques')
            ->modalSubmitActionLabel('Tirer')
            ->schema(DrawQuestionsForm::components())
            ->action(function (array $data): void {
                $request = DrawQuestionsForm::request($data);
                $result = (new QuestionnaireComposer)->addDrawnQuestions($this->questionnaire(), $request['bankIds'], $request['requested']);

                Notification::make()
                    ->success()
                    ->title("{$result['added']} question(s) tirée(s) et ajoutée(s)")
                    ->body(DrawQuestionsForm::reducedMessage($request['bankIds'], $result['reduced']))
                    ->send();
            });
    }

    /**
     * @return array<int, string>
     */
    private function availableBankQuestions(mixed $bankId, mixed $module): array
    {
        if (! is_numeric($bankId)) {
            return [];
        }

        $present = $this->questionnaire()->questions()->pluck('qcm_questions.id')->all();

        return Question::query()
            ->where('bank_id', (int) $bankId)
            ->when(filled($module), fn ($query) => $query->where('module', $module))
            ->whereKeyNot($present)
            ->orderBy('id')
            ->get(['id', 'statement', 'module'])
            ->mapWithKeys(fn (Question $question): array => [
                $question->id => Str::limit($question->statement, 100).($question->module === null ? '' : " ({$question->module})"),
            ])
            ->all();
    }

    private function questionnaire(): Questionnaire
    {
        /** @var Questionnaire $questionnaire */
        $questionnaire = $this->getOwnerRecord();

        return $questionnaire;
    }
}
