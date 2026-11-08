<?php

namespace App\Qcm\Filament\Actions;

use App\Qcm\Exchange\QuestionnaireExporter;
use App\Qcm\Models\Exam;
use App\Qcm\Models\Questionnaire;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export d'un questionnaire ou d'un examen (PRD QCM UC-16).
 */
final class ExportQuestionnaireActions
{
    public static function questionnaire(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('exportQuestionnaireCsv')
                ->label('CSV (questions seules)')
                ->action(function (Questionnaire $record): StreamedResponse {
                    $exporter = new QuestionnaireExporter;
                    $csv = $exporter->csv($record);

                    return response()->streamDownload(function () use ($csv): void {
                        echo $csv;
                    }, $exporter->fileName($record, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
            Action::make('exportQuestionnaireZip')
                ->label('ZIP (questions, illustrations et paramètres)')
                ->action(function (Questionnaire $record): BinaryFileResponse {
                    $exporter = new QuestionnaireExporter;

                    return response()
                        ->download($exporter->zip($record), $exporter->fileName($record, 'zip'), ['Content-Type' => 'application/zip'])
                        ->deleteFileAfterSend();
                }),
        ])
            ->label('Exporter')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->button();
    }

    public static function exam(): Action
    {
        return Action::make('exportExam')
            ->label('Exporter (ZIP)')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->tooltip('Questionnaire, illustrations et paramètres de l\'examen')
            ->action(function (Exam $record): BinaryFileResponse {
                $exporter = new QuestionnaireExporter;

                return response()
                    ->download($exporter->examZip($record), $exporter->fileName($record, 'zip'), ['Content-Type' => 'application/zip'])
                    ->deleteFileAfterSend();
            });
    }
}
