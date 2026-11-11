<?php

namespace App\Leitner\Filament\Resources\Suites\Tables;

use App\Leitner\Filament\Resources\Suites\SuiteResource;
use App\Leitner\Models\Suite;
use App\Leitner\Suites\SuiteState;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use WeakMap;

class SuitesTable
{
    /** @var WeakMap<Suite, SuiteState>|null état calculé une fois par suite chargée */
    private static ?WeakMap $states = null;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['banks', 'progression.suite']))
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('banks.name')
                    ->label('Banques')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Aucune banque'),
                TextColumn::make('distribution')
                    ->label('Répartition')
                    ->state(fn (Suite $record): string => SuiteResource::distributionSummary($record)),
                TextColumn::make('last_session')
                    ->label('Dernière séance')
                    ->state(fn (Suite $record): ?string => self::state($record)->lastSessionAt?->translatedFormat('j M Y'))
                    ->placeholder('Aucune'),
                IconColumn::make('regularity_warning')
                    ->label('Rythme')
                    ->state(fn (Suite $record): bool => self::state($record)->regularity->warning)
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success')
                    ->tooltip(fn (Suite $record): string => self::state($record)->regularity->message()),
            ])
            ->recordUrl(fn (Suite $record): string => SuiteResource::getUrl('view', ['record' => $record]))
            ->defaultSort('name')
            ->recordActions([
                ViewAction::make()->label('Ouvrir'),
                EditAction::make(),
                SuiteResource::deleteAction(),
            ])
            ->emptyStateHeading('Aucune suite')
            ->emptyStateDescription('Créez une suite sur une ou plusieurs banques pour commencer à réviser.');
    }

    private static function state(Suite $suite): SuiteState
    {
        self::$states ??= new WeakMap;

        return self::$states[$suite] ??= SuiteState::for($suite->progression);
    }
}
