<?php

namespace App\Leitner\Filament\Resources\Suites\Pages;

use App\Leitner\Filament\Resources\Suites\SuiteResource;
use App\Leitner\Suites\SuiteSettingsUpdater;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Création d'une suite : toutes les questions des sources sont *en banque*
 * (PRD Leitner UC-05, 4).
 */
class CreateSuite extends CreateRecord
{
    protected static string $resource = SuiteResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SuiteSettingsUpdater::class)->create(
            Arr::except($data, ['bank_ids']),
            self::bankIds($data),
        );
    }

    protected function getRedirectUrl(): string
    {
        return SuiteResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    public static function bankIds(array $data): array
    {
        return array_values(array_map('intval', (array) ($data['bank_ids'] ?? [])));
    }
}
