<?php

namespace App\Leitner\Filament\Resources\Suites\Pages;

use App\Leitner\Filament\Pages\Seance;
use App\Leitner\Filament\Resources\Suites\SuiteResource;
use App\Leitner\Models\Suite;
use App\Leitner\Suites\SuiteState;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * État d'une suite (PRD Leitner UC-11) : boîtes, banque, prochaine séance,
 * régularité, et lancement ou reprise d'une séance.
 *
 * @property Suite $record
 */
class ViewSuite extends ViewRecord
{
    protected static string $resource = SuiteResource::class;

    protected string $view = 'leitner.suite-state';

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function state(): SuiteState
    {
        return SuiteState::for($this->record->progression);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('session')
                ->label(fn (): string => $this->record->progression->currentSession() === null ? 'Commencer la séance' : 'Reprendre la séance')
                ->icon(Heroicon::OutlinedPlay)
                ->url(fn (): string => Seance::getUrl(['suite' => $this->record->id])),
            SuiteResource::newQuestionAction(),
            EditAction::make(),
        ];
    }
}
