<?php

namespace App\Qcm\Filament\Resources\Results\Pages;

use App\Qcm\Filament\Resources\Results\ResultResource;
use App\Qcm\Filament\Resources\Results\Tables\ResultsTable;
use App\Qcm\Models\Result;
use Filament\Resources\Pages\ViewRecord;

class ViewResult extends ViewRecord
{
    protected static string $resource = ResultResource::class;

    public function getTitle(): string
    {
        /** @var Result $result */
        $result = $this->getRecord();

        return "Résultat de {$result->first_name} {$result->last_name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            ResultsTable::deleteAction(),
        ];
    }
}
