<?php

namespace App\Leitner\Filament\Resources\Suites\Pages;

use App\Leitner\Filament\Resources\Suites\SuiteResource;
use App\Leitner\Filament\Schemas\QuestionFormSchema;
use App\Leitner\Models\Suite;
use App\Leitner\Suites\PersonalQuestionCreator;
use App\Leitner\Suites\SettingsChange;
use App\Leitner\Suites\SuiteSettingsUpdater;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Modification d'une suite (PRD Leitner UC-05, 3a) et ajout de questions
 * personnelles (UC-03). Une modification qui fait perdre ou déplacer des
 * questions introduites demande une confirmation qui en donne le détail.
 *
 * @property Suite $record
 */
class EditSuite extends EditRecord
{
    protected static string $resource = SuiteResource::class;

    /** Placement d'une question personnelle : boîte 1 ou en banque. */
    private const PLACE_BOX_ONE = 'boite_1';

    private const PLACE_BANK = 'banque';

    protected function getHeaderActions(): array
    {
        return [
            $this->newQuestionAction(),
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
     * « Nouvelle question » (UC-03) : la question va dans la banque
     * personnelle de la suite, en boîte 1 ou en banque.
     */
    private function newQuestionAction(): Action
    {
        return Action::make('newQuestion')
            ->label('Nouvelle question')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Ajouter ma propre question')
            ->modalDescription('La question est rangée dans votre banque personnelle de la suite, créée au besoin.')
            ->modalWidth(Width::ThreeExtraLarge)
            ->schema(fn (): array => [
                ...QuestionFormSchema::components(fn (): array => $this->record->personalBank?->themeNames() ?? []),
                Radio::make('placement')
                    ->label('Placement')
                    ->options([
                        self::PLACE_BOX_ONE => 'Directement en boîte 1 : posée dès la prochaine séance',
                        self::PLACE_BANK => 'En banque : introduite avec les autres nouvelles questions',
                    ])
                    ->default(self::PLACE_BOX_ONE)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $placement = $data['placement'];
                unset($data['placement']);

                app(PersonalQuestionCreator::class)->create($this->record, $data, $placement === self::PLACE_BOX_ONE);
                $this->record->refresh();
                $this->fillForm();

                Notification::make()
                    ->title('Question ajoutée')
                    ->body($placement === self::PLACE_BOX_ONE
                        ? 'Elle est en boîte 1 et sera posée à la prochaine séance.'
                        : 'Elle est en banque et sera introduite avec les autres nouvelles questions.')
                    ->success()
                    ->send();
            });
    }
}
