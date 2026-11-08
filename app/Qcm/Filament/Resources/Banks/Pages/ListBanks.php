<?php

namespace App\Qcm\Filament\Resources\Banks\Pages;

use App\Qcm\Filament\Actions\ImportBankAction;
use App\Qcm\Filament\Resources\Banks\BankResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBanks extends ListRecords
{
    protected static string $resource = BankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportBankAction::make('importBank', 'Importer une banque', fn (): null => null),
            CreateAction::make(),
        ];
    }
}
