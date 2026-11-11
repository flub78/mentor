<?php

namespace App\Banques\Filament\Resources\Banks\Pages;

use App\Banques\Filament\Resources\Banks\BankResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBank extends CreateRecord
{
    protected static string $resource = BankResource::class;

    /**
     * Après création, ouvrir la banque pour y saisir les questions.
     */
    protected function getRedirectUrl(): string
    {
        return BankResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
