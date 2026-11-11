<?php

namespace App\Qcm\Filament\Resources\Exams\RelationManagers;

use App\Qcm\Filament\Resources\Results\Tables\ResultsTable;
use App\Qcm\Models\Exam;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * Résultats d'un examen (PRD QCM UC-09).
 */
class ResultsRelationManager extends RelationManager
{
    protected static string $relationship = 'results';

    protected static ?string $title = 'Résultats';

    protected static ?string $modelLabel = 'résultat';

    protected static ?string $pluralModelLabel = 'résultats';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var Exam $exam */
        $exam = $this->getOwnerRecord();

        return ResultsTable::configure($table, $exam);
    }
}
