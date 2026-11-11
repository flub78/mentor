<?php

namespace App\Banques\Filament\Actions;

use App\Banques\Exchange\BankExporter;
use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export d'une banque ou d'une sélection de ses questions (PRD Banques UC-04).
 */
final class ExportBankActions
{
    /**
     * @param  Closure(Component): Bank  $bank
     */
    public static function group(Closure $bank): ActionGroup
    {
        return ActionGroup::make([
            Action::make('exportBankCsv')
                ->label('CSV (questions seules)')
                ->action(fn (Component $livewire): StreamedResponse => self::csv($bank($livewire))),
            Action::make('exportBankZip')
                ->label('ZIP (questions, illustrations et paramètres)')
                ->action(fn (Component $livewire): BinaryFileResponse => self::zip($bank($livewire))),
        ])
            ->label('Exporter')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->button();
    }

    /**
     * @param  Closure(Component): Bank  $bank
     */
    public static function bulkGroup(Closure $bank): BulkActionGroup
    {
        return BulkActionGroup::make([
            BulkAction::make('exportSelectionCsv')
                ->label('Exporter la sélection (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (Collection $records, Component $livewire): StreamedResponse => self::csv($bank($livewire), $records)),
            BulkAction::make('exportSelectionZip')
                ->label('Exporter la sélection (ZIP)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (Collection $records, Component $livewire): BinaryFileResponse => self::zip($bank($livewire), $records)),
        ])->label('Exporter');
    }

    /**
     * @param  Collection<int, Question>|null  $selection
     */
    public static function csv(Bank $bank, ?Collection $selection = null): StreamedResponse
    {
        $exporter = new BankExporter;
        $csv = $exporter->csv($bank, $selection);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $exporter->fileName($bank, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  Collection<int, Question>|null  $selection
     */
    public static function zip(Bank $bank, ?Collection $selection = null): BinaryFileResponse
    {
        $exporter = new BankExporter;

        return response()
            ->download($exporter->zip($bank, $selection), $exporter->fileName($bank, 'zip'), ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
