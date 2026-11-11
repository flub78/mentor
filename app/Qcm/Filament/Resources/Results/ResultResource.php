<?php

namespace App\Qcm\Filament\Resources\Results;

use App\Qcm\Filament\Resources\Results\Pages\ListResults;
use App\Qcm\Filament\Resources\Results\Pages\ViewResult;
use App\Qcm\Filament\Resources\Results\Schemas\ResultInfolist;
use App\Qcm\Filament\Resources\Results\Tables\ResultsTable;
use App\Qcm\Models\Result;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Résultats des passages d'examen (PRD QCM UC-09), y compris ceux d'examens
 * supprimés depuis. Un résultat ne se crée ni ne se modifie : il se consulte,
 * s'exporte et se supprime.
 */
class ResultResource extends Resource
{
    protected static ?string $model = Result::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $recordTitleAttribute = 'certificate_number';

    protected static ?string $modelLabel = 'résultat';

    protected static ?string $pluralModelLabel = 'résultats';

    protected static ?string $navigationLabel = 'Résultats';

    protected static ?int $navigationSort = 5;

    public static function infolist(Schema $schema): Schema
    {
        return ResultInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResultsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResults::route('/'),
            'view' => ViewResult::route('/{record}'),
        ];
    }
}
