<?php

namespace App\Banques\Filament\Actions;

use App\Banques\Exchange\BankImporter;
use App\Banques\Filament\Resources\Banks\BankResource;
use App\Banques\Models\Bank;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Import de questions dans une banque (PRD Banques UC-05) : téléversement, rapport
 * avant import, choix du traitement des questions déjà présentes et des
 * doublons, confirmation.
 */
final class ImportBankAction
{
    /**
     * @param  Closure(Component): (Bank|null)  $target  banque cible ; null = nouvelle banque
     */
    public static function make(string $name, string $label, Closure $target): Action
    {
        $importer = app(BankImporter::class);

        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading($label)
            ->modalSubmitActionLabel('Importer')
            ->steps(fn (Component $livewire): array => [
                Step::make('Fichier')
                    ->description('CSV, ou ZIP avec les illustrations')
                    ->schema([ExchangeUpload::field(['banque (CSV)' => 'banque-exemple.csv', 'banque (ZIP, avec une illustration)' => 'banque-exemple.zip'])])
                    ->afterValidation(function (Get $get, Set $set) use ($importer, $target, $livewire): void {
                        $file = ExchangeUpload::open($get('file'));

                        if ($file === null) {
                            throw new Halt;
                        }

                        $set('report', $importer->analyse($file, $target($livewire))->toArray());
                        $suggested = $importer->suggestedBank($file);
                        $set('name', $suggested['name']);
                        $set('description', $suggested['description']);
                    }),
                Step::make('Rapport')
                    ->description('Vérifier avant d\'importer')
                    ->schema([
                        Hidden::make('report')->dehydrated(false),
                        View::make('banques.exchange.import-report')
                            ->viewData(fn (Get $get): array => ['report' => $get('report')]),
                        TextInput::make('name')
                            ->label('Nom de la nouvelle banque')
                            ->required()
                            ->maxLength(255)
                            ->unique(Bank::class, 'name')
                            ->validationMessages(['unique' => 'Une banque porte déjà ce nom : choisissez-en un autre.'])
                            ->visible(fn (): bool => $target($livewire) === null),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->visible(fn (): bool => $target($livewire) === null),
                        Radio::make('existing')
                            ->label('Questions déjà présentes (reconnues par leur référence)')
                            ->options([
                                'update' => 'Les mettre à jour avec le contenu du fichier',
                                'skip' => 'Les laisser telles quelles (ignorer ces lignes)',
                            ])
                            ->default('update')
                            ->visible(fn (Get $get): bool => ($get('report')['existing'] ?? []) !== []),
                        Radio::make('duplicates')
                            ->label('Doublons probables')
                            ->options([
                                'skip' => 'Ne pas importer les doublons',
                                'import' => 'Importer quand même',
                            ])
                            ->default('skip')
                            ->visible(fn (Get $get): bool => ($get('report')['duplicates'] ?? []) !== []),
                    ]),
            ])
            ->action(function (array $data, Action $action, Component $livewire) use ($importer, $target): void {
                $file = ExchangeUpload::open($data['file'] ?? null);

                if ($file === null) {
                    throw new Halt;
                }

                $bank = $target($livewire);
                $result = $importer->import(
                    $file,
                    $bank,
                    ['name' => $data['name'] ?? null, 'description' => $data['description'] ?? null],
                    ($data['duplicates'] ?? 'skip') !== 'import',
                    ($data['existing'] ?? 'update') === 'update',
                );

                Notification::make()
                    ->success()
                    ->title(match ($result['imported']) {
                        0 => 'Aucune question importée',
                        1 => '1 question importée',
                        default => "{$result['imported']} questions importées",
                    }.' dans « '.$result['bank']->name.' »')
                    ->body(self::summary($result) ?: null)
                    ->send();

                if ($bank === null) {
                    $action->redirect(BankResource::getUrl('edit', ['record' => $result['bank']]));
                }
            });
    }

    /**
     * Ce que l'import a fait en plus des créations : mises à jour, lignes
     * inchangées ou ignorées, doublons écartés, effets dans les modules.
     *
     * @param  array{updated: int, unchanged: int, ignored: int, skipped: int, report: string}  $result
     */
    private static function summary(array $result): string
    {
        return implode(' ', array_filter([
            $result['updated'] > 0 ? "{$result['updated']} question(s) déjà présente(s) mise(s) à jour." : null,
            $result['unchanged'] > 0 ? "{$result['unchanged']} question(s) déjà présente(s) inchangée(s)." : null,
            $result['ignored'] > 0 ? "{$result['ignored']} ligne(s) de questions déjà présentes ignorée(s)." : null,
            $result['skipped'] > 0 ? "{$result['skipped']} doublon(s) non importé(s)." : null,
            $result['report'] !== '' ? $result['report'] : null,
        ]));
    }
}
