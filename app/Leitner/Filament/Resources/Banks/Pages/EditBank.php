<?php

namespace App\Leitner\Filament\Resources\Banks\Pages;

use App\Leitner\Filament\Resources\Banks\BankResource;
use Filament\Resources\Pages\EditRecord;

class EditBank extends EditRecord
{
    protected static string $resource = BankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BankResource::deleteAction(),
        ];
    }
}
