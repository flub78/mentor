<?php

namespace App\Qcm\Filament\Resources\Questionnaires\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class QuestionnaireForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('version')
                    ->label('Version (facultative)')
                    ->helperText('Figure sur les certificats de résultat ; changez-la quand vous modifiez un questionnaire déjà utilisé.')
                    ->maxLength(50),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(3),
            ])
            ->columns(1);
    }
}
