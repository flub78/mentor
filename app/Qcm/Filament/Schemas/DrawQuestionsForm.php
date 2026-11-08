<?php

namespace App\Qcm\Filament\Schemas;

use App\Qcm\Models\Bank;
use App\Qcm\Study\ModuleCatalog;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Exceptions\Halt;

/**
 * Choix d'un tirage au hasard dans des banques (PRD QCM UC-08, étape 2) :
 * banques, puis nombre de questions par module ou « toutes ».
 *
 * Les champs d'un module sont indexés par sa position dans le catalogue
 * (`ModuleCatalog::forBanks()`, ordre stable) : un nom de module peut
 * contenir des caractères interdits dans un chemin d'état.
 */
final class DrawQuestionsForm
{
    /**
     * @return list<Component|Field>
     */
    public static function components(): array
    {
        return [
            Select::make('bank_ids')
                ->label('Banques')
                ->multiple()
                ->required()
                ->options(fn (): array => Bank::query()->has('questions')->orderBy('name')->pluck('name', 'id')->all())
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('counts', []);
                    $set('all', []);
                })
                ->validationMessages(['required' => 'Choisissez au moins une banque.']),
            Group::make(fn (Get $get): array => self::moduleFields(self::bankIds($get('bank_ids')))),
        ];
    }

    /**
     * Banques et nombres demandés par clé de module ; signale et interrompt
     * l'action si rien n'est demandé.
     *
     * @param  array<string, mixed>  $data
     * @return array{bankIds: list<int>, requested: array<string, int|null>}
     */
    public static function request(array $data): array
    {
        $bankIds = self::bankIds($data['bank_ids'] ?? []);
        $counts = (array) ($data['counts'] ?? []);
        $all = (array) ($data['all'] ?? []);
        $requested = [];

        foreach ((new ModuleCatalog)->forBanks($bankIds) as $index => $module) {
            if ((bool) ($all[$index] ?? false)) {
                $requested[$module['key']] = null;
            } elseif ((int) ($counts[$index] ?? 0) > 0) {
                $requested[$module['key']] = (int) $counts[$index];
            }
        }

        if ($requested === []) {
            Notification::make()
                ->danger()
                ->title('Aucune question demandée')
                ->body('Indiquez un nombre de questions, ou cochez « Toutes », pour au moins un module.')
                ->send();

            throw new Halt;
        }

        return ['bankIds' => $bankIds, 'requested' => $requested];
    }

    /**
     * Message sur les modules ramenés au nombre disponible (PRD QCM UC-08, 2b).
     *
     * @param  list<int>  $bankIds
     * @param  array<string, int>  $reduced
     */
    public static function reducedMessage(array $bankIds, array $reduced): ?string
    {
        if ($reduced === []) {
            return null;
        }

        $labels = array_column((new ModuleCatalog)->forBanks($bankIds), 'label', 'key');

        return 'Pas assez de questions disponibles : '.implode(', ', array_map(
            fn (string $key, int $count): string => ($labels[$key] ?? $key)." ({$count} tirée(s))",
            array_keys($reduced),
            $reduced,
        )).'.';
    }

    /**
     * @return list<int>
     */
    private static function bankIds(mixed $state): array
    {
        return array_values(array_map('intval', array_filter((array) $state, 'is_numeric')));
    }

    /**
     * @param  list<int>  $bankIds
     * @return list<Component|Field>
     */
    private static function moduleFields(array $bankIds): array
    {
        $modules = (new ModuleCatalog)->forBanks($bankIds);

        if ($modules === []) {
            return [Text::make('Choisissez une ou plusieurs banques pour voir leurs modules.')];
        }

        $fields = [Text::make('Pour chaque module, indiquez le nombre de questions à tirer, ou cochez « Toutes ». Les questions déjà présentes dans le questionnaire ne sont pas tirées de nouveau.')];

        foreach ($modules as $index => $module) {
            $fields[] = Grid::make(2)->schema([
                TextInput::make("counts.{$index}")
                    ->label("{$module['label']} — {$module['count']} disponible(s)")
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->disabled(fn (Get $get): bool => (bool) $get("all.{$index}")),
                Checkbox::make("all.{$index}")
                    ->label('Toutes')
                    ->live()
                    ->inline(false),
            ]);
        }

        return $fields;
    }
}
