<?php

namespace App\Qcm\Filament\Resources\Exams;

use App\Qcm\Filament\Resources\Exams\Pages\CreateExam;
use App\Qcm\Filament\Resources\Exams\Pages\EditExam;
use App\Qcm\Filament\Resources\Exams\Pages\ListExams;
use App\Qcm\Filament\Resources\Exams\Schemas\ExamForm;
use App\Qcm\Filament\Resources\Exams\Tables\ExamsTable;
use App\Qcm\Models\Exam;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Examens (PRD QCM UC-20) : un questionnaire complet, une durée, un taux de
 * réussite minimal, des options de passage et l'identification du candidat.
 */
class ExamResource extends Resource
{
    protected static ?string $model = Exam::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'examen';

    protected static ?string $pluralModelLabel = 'examens';

    protected static ?string $navigationLabel = 'Examens';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return ExamForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExams::route('/'),
            'create' => CreateExam::route('/create'),
            'edit' => EditExam::route('/{record}/edit'),
        ];
    }

    public static function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Dupliquer')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->requiresConfirmation()
            ->modalHeading(fn (Exam $record): string => "Dupliquer « {$record->title} » ?")
            ->modalDescription('La copie utilise le même questionnaire ; vous pourrez en choisir un autre.')
            ->action(function (Exam $record, Action $action): void {
                $copy = Exam::create([...$record->only($record->getFillable()), 'title' => $record->title.' (copie)']);

                Notification::make()->success()->title("Examen « {$copy->title} » créé")->send();
                $action->redirect(self::getUrl('edit', ['record' => $copy]));
            });
    }
}
