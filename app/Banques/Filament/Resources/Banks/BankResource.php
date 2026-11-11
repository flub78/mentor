<?php

namespace App\Banques\Filament\Resources\Banks;

use App\Banques\Filament\Resources\Banks\Pages\CreateBank;
use App\Banques\Filament\Resources\Banks\Pages\EditBank;
use App\Banques\Filament\Resources\Banks\Pages\ListBanks;
use App\Banques\Filament\Resources\Banks\RelationManagers\QuestionsRelationManager;
use App\Banques\Filament\Resources\Banks\Schemas\BankForm;
use App\Banques\Filament\Resources\Banks\Tables\BanksTable;
use App\Banques\Models\Bank;
use App\Banques\Usage\UsageRegistry;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Banques de questions (PRD Banques UC-01) ; les questions se gèrent
 * dans la banque (UC-02, QuestionsRelationManager).
 */
class BankResource extends Resource
{
    protected static ?string $model = Bank::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'banque';

    protected static ?string $pluralModelLabel = 'banques';

    protected static ?string $navigationLabel = 'Banques de questions';

    protected static ?int $navigationSort = 2;

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
     * Suppression d'une banque, avec confirmation qui annonce ce qui sera
     * perdu : ses questions et leurs utilisations par les modules. La banque
     * par défaut ne se supprime pas.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalHeading(fn (Bank $record): string => "Supprimer la banque « {$record->name} » ?")
            ->modalDescription(fn (Bank $record): string => match ($count = $record->questions()->count()) {
                0 => 'La banque ne contient aucune question.',
                1 => 'Sa question sera définitivement supprimée.',
                default => "Ses {$count} questions seront définitivement supprimées.",
            }.rtrim(' '.app(UsageRegistry::class)->bankDeletionNotice($record)))
            ->hidden(fn (Bank $record): bool => $record->is_default)
            ->successNotificationTitle('Banque supprimée');
    }
}
