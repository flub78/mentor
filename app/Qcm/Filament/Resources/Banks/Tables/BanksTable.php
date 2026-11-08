<?php

namespace App\Qcm\Filament\Resources\Banks\Tables;

use App\Qcm\Filament\Pages\Study;
use App\Qcm\Filament\Resources\Banks\BankResource;
use App\Qcm\Models\Bank;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(80),
                TextColumn::make('questions_count')
                    ->label('Questions')
                    ->counts('questions')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('study')
                    ->label('Étudier')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->url(fn (Bank $record): string => Study::getUrl(['bank' => $record->getKey()]))
                    ->visible(fn (Bank $record): bool => $record->questions_count > 0),
                EditAction::make(),
                BankResource::deleteAction(),
            ])
            ->emptyStateHeading('Aucune banque')
            ->emptyStateDescription('Créez une banque pour y saisir vos questions.');
    }
}
