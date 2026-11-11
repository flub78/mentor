<?php

namespace App\Leitner\Filament\Resources\Suites\Pages;

use App\Leitner\Filament\Resources\Suites\SuiteResource;
use App\Leitner\Models\Suite;
use App\Leitner\Suites\SettingsChange;
use App\Leitner\Suites\SuiteSettingsUpdater;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Modification d'une suite (PRD Leitner UC-05, 3a) et ajout de questions
 * personnelles (UC-03, SuiteResource::newQuestionAction()). Une modification qui fait perdre ou déplacer des
 * questions introduites demande une confirmation qui en donne le détail.
 *
 * @property Suite $record
 */
class EditSuite extends EditRecord
{
    protected static string $resource = SuiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SuiteResource::newQuestionAction(),
            SuiteResource::deleteAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['bank_ids'] = $this->record->bankIds();

        return $data;
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->submit(null)
            ->requiresConfirmation(fn (): bool => $this->pendingChange()->isDestructive())
            ->modalHeading('Confirmer la modification de la suite')
            ->modalDescription(fn (): string => implode(' ', $this->pendingChange()->describe()))
            ->action(fn () => $this->save());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Suite $record */
        $change = app(SuiteSettingsUpdater::class)->update(
            $record,
            Arr::except($data, ['bank_ids']),
            CreateSuite::bankIds($data),
        );

        if ($change->isDestructive()) {
            Notification::make()
                ->title('Progression ajustée')
                ->body(implode(' ', $change->describe()))
                ->info()
                ->send();
        }

        return $record;
    }

    private function pendingChange(): SettingsChange
    {
        return app(SuiteSettingsUpdater::class)->preview(
            $this->record,
            (int) ($this->data['box_count'] ?? $this->record->box_count),
            CreateSuite::bankIds($this->data ?? []),
        );
    }

    /**
     * Recharge le formulaire après l'ajout d'une question personnelle, qui
     * peut ajouter la banque personnelle aux sources.
     */
    public function reloadForm(): void
    {
        $this->record->refresh();
        $this->fillForm();
    }
}
