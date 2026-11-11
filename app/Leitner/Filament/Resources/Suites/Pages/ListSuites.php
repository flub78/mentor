<?php

namespace App\Leitner\Filament\Resources\Suites\Pages;

use App\Leitner\Filament\Resources\Suites\SuiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuites extends ListRecords
{
    protected static string $resource = SuiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
