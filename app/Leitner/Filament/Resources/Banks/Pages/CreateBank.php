<?php

namespace App\Leitner\Filament\Resources\Banks\Pages;

use App\Leitner\Filament\Resources\Banks\BankResource;
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
