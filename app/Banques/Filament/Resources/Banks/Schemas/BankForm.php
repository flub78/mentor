<?php

namespace App\Banques\Filament\Resources\Banks\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'required' => 'Le nom de la banque est obligatoire.',
                        'unique' => 'Une banque porte déjà ce nom : choisissez-en un autre.',
                    ]),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(3),
            ])
            ->columns(1);
    }
}
