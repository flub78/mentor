<?php

namespace App\Qcm\Filament\Resources\Questionnaires\RelationManagers;

use App\Banques\Filament\Resources\Banks\BankResource;
use App\Banques\Filament\Schemas\QuestionFormSchema;
use App\Banques\Models\Bank as CommonBank;
use App\Qcm\Filament\Schemas\DrawQuestionsForm;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use App\Qcm\Models\Questionnaire;
use App\Qcm\Questionnaires\QuestionnaireComposer;
use App\Qcm\Questionnaires\UsableQuestions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
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
 * Questions d'un questionnaire (PRD QCM UC-08) : questions des banques,
 * nouvelles (rangées dans une banque, par défaut la banque par défaut),
 * choisies ou tirées au hasard ; liste ordonnée. Une question se modifie dans
 * sa banque.
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
                Select::make('bank_id')
                    ->label('Banque où ranger la question')
                    ->helperText('Toute question vit dans une banque ; à défaut, la banque par défaut, d\'où vous pourrez la déplacer.')
                    ->options(fn (): array => CommonBank::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->default(fn (): int => CommonBank::default()->id)
                    ->required()
                    ->validationMessages(['required' => 'Choisissez la banque où ranger la question.']),
                ...QuestionFormSchema::components(fn (): array => $this->questionnaire()->moduleNames(), Question::USABLE_TYPES),
            ])
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
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('module')
                    ->label('Module')
                    ->placeholder('Sans module')
                    ->badge(),
                TextColumn::make('bank.name')
                    ->label('Banque')
                    ->color('info')
                    ->badge(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('bank'))
            ->headerActions([
                CreateAction::make()
                    ->label('Nouvelle question')
                    ->modalHeading('Nouvelle question')
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->successNotification(fn (Question $record): Notification => Notification::make()
                        ->success()
                        ->title('Question créée')
                        ->body('Elle est rangée dans la banque « '.$record->bank?->name.' ».')),
                $this->addFromBankAction(),
                $this->drawAction(),
            ])
            ->recordActions([
                Action::make('openBank')
                    ->label('Modifier dans la banque')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Question $record): string => BankResource::getUrl('edit', ['record' => $record->bank_id])),
                DetachAction::make()
                    ->label('Retirer')
                    ->modalHeading('Retirer la question du questionnaire ?')
                    ->modalDescription(fn (Question $record): string => 'La question reste dans la banque « '.$record->bank?->name.' ».'),
            ])
            ->emptyStateHeading('Aucune question')
            ->emptyStateDescription('Saisissez une nouvelle question, ajoutez des questions d\'une banque, ou tirez-en au hasard.');
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
                    ->helperText(fn (Get $get): ?string => is_numeric($get('bank_id')) ? UsableQuestions::notice([(int) $get('bank_id')]) : null)
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

                Notification::make()
                    ->success()
                    ->title("{$added} question(s) ajoutée(s)")
                    ->body(UsableQuestions::notice([(int) $data['bank_id']]))
                    ->send();
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
                $body = trim(DrawQuestionsForm::reducedMessage($request['bankIds'], $result['reduced']).' '.UsableQuestions::notice($request['bankIds']));

                Notification::make()
                    ->success()
                    ->title("{$result['added']} question(s) tirée(s) et ajoutée(s)")
                    ->body($body === '' ? null : $body)
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

        $present = $this->questionnaire()->questions()->pluck('banques_questions.id')->all();

        return Question::query()
            ->where('bank_id', (int) $bankId)
            ->when(filled($module), fn ($query) => $query->where('theme', $module))
            ->whereKeyNot($present)
            ->orderBy('id')
            ->get(['id', 'type', 'statement', 'theme'])
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
