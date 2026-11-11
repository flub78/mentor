<?php

namespace App\Leitner\Filament\Resources\Suites\Tables;

use App\Leitner\Filament\Resources\Suites\SuiteResource;
use App\Leitner\Models\Suite;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuitesTable
{
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
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                SuiteResource::deleteAction(),
            ])
            ->emptyStateHeading('Aucune suite')
            ->emptyStateDescription('Créez une suite sur une ou plusieurs banques pour commencer à réviser.');
    }
}
