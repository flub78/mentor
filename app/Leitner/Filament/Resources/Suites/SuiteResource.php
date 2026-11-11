<?php

namespace App\Leitner\Filament\Resources\Suites;

use App\Leitner\Filament\Resources\Suites\Pages\CreateSuite;
use App\Leitner\Filament\Resources\Suites\Pages\EditSuite;
use App\Leitner\Filament\Resources\Suites\Pages\ListSuites;
use App\Leitner\Filament\Resources\Suites\Schemas\SuiteForm;
use App\Leitner\Filament\Resources\Suites\Tables\SuitesTable;
use App\Leitner\Models\Suite;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Suites de révision (PRD Leitner UC-05, UC-03).
 */
class SuiteResource extends Resource
{
    protected static ?string $model = Suite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'suite';

    protected static ?string $pluralModelLabel = 'suites';

    protected static ?string $navigationLabel = 'Suites de révision';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return SuiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuitesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuites::route('/'),
            'create' => CreateSuite::route('/create'),
            'edit' => EditSuite::route('/{record}/edit'),
        ];
    }

    /**
     * Répartition en une ligne : boîtes, en banque, maîtrisées, suspendues.
     */
    public static function distributionSummary(Suite $suite): string
    {
        $counts = $suite->progression->counts();
        $boxes = implode(' · ', array_map(
            fn (int $box, int $count): string => "B{$box} : {$count}",
            array_keys($counts['boxes']),
            $counts['boxes'],
        ));

        $summary = "{$boxes} — en banque : {$counts['inBank']} — maîtrisées : {$counts['mastered']}";

        return $counts['suspended'] > 0 ? "{$summary} — suspendues : {$counts['suspended']}" : $summary;
    }

    /**
     * Suppression d'une suite : sa progression est perdue, les banques ne
     * changent pas.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalHeading(fn (Suite $record): string => "Supprimer la suite « {$record->name} » ?")
            ->modalDescription(function (Suite $record): string {
                $introduced = $record->progression->items()->count();
                $text = match ($introduced) {
                    0 => 'Aucune question n\'a encore été introduite.',
                    1 => 'La progression de la question introduite sera perdue.',
                    default => "La progression des {$introduced} questions introduites sera perdue.",
                }.' Les banques sources ne sont pas modifiées.';

                return $record->personalBank === null
                    ? $text
                    : $text." Votre banque « {$record->personalBank->name} » est conservée.";
            })
            ->successNotificationTitle('Suite supprimée');
    }
}
