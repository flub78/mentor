<?php

namespace App\Leitner\Filament\Resources\Banks\Tables;

use App\Leitner\Filament\Resources\Banks\BankResource;
use Filament\Actions\EditAction;
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
                EditAction::make(),
                BankResource::deleteAction(),
            ])
            ->emptyStateHeading('Aucune banque')
            ->emptyStateDescription('Créez une banque pour y saisir vos questions.');
    }
}
