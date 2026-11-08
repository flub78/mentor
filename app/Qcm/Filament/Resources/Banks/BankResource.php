<?php

namespace App\Qcm\Filament\Resources\Banks;

use App\Qcm\Filament\Resources\Banks\Pages\CreateBank;
use App\Qcm\Filament\Resources\Banks\Pages\EditBank;
use App\Qcm\Filament\Resources\Banks\Pages\ListBanks;
use App\Qcm\Filament\Resources\Banks\RelationManagers\QuestionsRelationManager;
use App\Qcm\Filament\Resources\Banks\Schemas\BankForm;
use App\Qcm\Filament\Resources\Banks\Tables\BanksTable;
use App\Qcm\Models\Bank;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Banques de questions (PRD QCM UC-03) ; les questions se gèrent dans la
 * banque (UC-04, QuestionsRelationManager).
 */
class BankResource extends Resource
{
    protected static ?string $model = Bank::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'banque';

    protected static ?string $pluralModelLabel = 'banques';

    protected static ?string $navigationLabel = 'Banques de questions';

    public static function form(Schema $schema): Schema
    {
        return BankForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BanksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBanks::route('/'),
            'create' => CreateBank::route('/create'),
            'edit' => EditBank::route('/{record}/edit'),
        ];
    }

    /**
     * Suppression d'une banque, avec confirmation qui annonce ce qui sera perdu.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalHeading(fn (Bank $record): string => "Supprimer la banque « {$record->name} » ?")
            ->modalDescription(function (Bank $record): string {
                $count = $record->questions()->count();

                return match ($count) {
                    0 => 'La banque ne contient aucune question.',
                    1 => 'Sa question et ses réponses seront définitivement supprimées.',
                    default => "Ses {$count} questions et leurs réponses seront définitivement supprimées.",
                };
            });
    }
}
