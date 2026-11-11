<?php

namespace App\Qcm\Filament\Resources\Questionnaires;

use App\Qcm\Filament\Resources\Questionnaires\Pages\CreateQuestionnaire;
use App\Qcm\Filament\Resources\Questionnaires\Pages\EditQuestionnaire;
use App\Qcm\Filament\Resources\Questionnaires\Pages\ListQuestionnaires;
use App\Qcm\Filament\Resources\Questionnaires\RelationManagers\QuestionsRelationManager;
use App\Qcm\Filament\Resources\Questionnaires\Schemas\QuestionnaireForm;
use App\Qcm\Filament\Resources\Questionnaires\Tables\QuestionnairesTable;
use App\Qcm\Models\Questionnaire;
use App\Qcm\Questionnaires\QuestionnaireComposer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Questionnaires (PRD QCM UC-08) ; leurs questions se gèrent dans le
 * questionnaire (QuestionsRelationManager).
 */
class QuestionnaireResource extends Resource
{
    protected static ?string $model = Questionnaire::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'questionnaire';

    protected static ?string $pluralModelLabel = 'questionnaires';

    protected static ?string $navigationLabel = 'Questionnaires';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return QuestionnaireForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuestionnairesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuestionnaires::route('/'),
            'create' => CreateQuestionnaire::route('/create'),
            'edit' => EditQuestionnaire::route('/{record}/edit'),
        ];
    }

    /**
     * Suppression, refusée avec explication si des examens utilisent le
     * questionnaire (PRD QCM UC-08, 3b).
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalHeading(fn (Questionnaire $record): string => "Supprimer le questionnaire « {$record->title} » ?")
            ->modalDescription('Ses questions restent dans leurs banques ; seule la liste du questionnaire est supprimée.')
            ->before(function (DeleteAction $action, Questionnaire $record): void {
                $exams = $record->exams()->pluck('title')->all();

                if ($exams !== []) {
                    Notification::make()
                        ->danger()
                        ->title('Suppression impossible')
                        ->body('Ce questionnaire est utilisé par : « '.implode(' », « ', $exams).' ». Supprimez ces examens ou faites-leur utiliser un autre questionnaire, puis recommencez.')
                        ->persistent()
                        ->send();
                    $action->cancel();
                }
            });
    }

    public static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Dupliquer')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->requiresConfirmation()
            ->modalHeading(fn (Questionnaire $record): string => "Dupliquer « {$record->title} » ?")
            ->modalDescription('Les questions propres sont copiées ; les questions prises dans des banques restent partagées.')
            ->action(function (Questionnaire $record, Action $action): void {
                $copy = (new QuestionnaireComposer)->duplicate($record);
                Notification::make()->success()->title("Questionnaire « {$copy->title} » créé")->send();
                $action->redirect(self::getUrl('edit', ['record' => $copy]));
            });
    }
}
