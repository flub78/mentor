<?php

namespace App\Qcm\Filament\Resources\Results\Schemas;

use App\Qcm\Models\Result;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Détail d'un résultat (PRD QCM UC-09) : contenu du certificat, taux par
 * module compris.
 */
class ResultInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Candidat')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('identity')
                            ->label('Identité')
                            ->listWithLineBreaks()
                            ->formatStateUsing(fn (array $state): string => $state['label'].' : '.$state['value']),
                        TextEntry::make('taken_at')
                            ->label('Date du passage')
                            ->dateTime('d/m/Y à H:i'),
                    ]),
                Section::make('Examen')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('exam_title')->label('Examen'),
                        TextEntry::make('questionnaire_title')->label('Questionnaire'),
                        TextEntry::make('questionnaire_version')->label('Version')->placeholder('—'),
                    ]),
                Section::make('Résultat')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('module_rates')
                            ->label('Taux par module')
                            ->columnSpanFull()
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->formatStateUsing(fn (array $state): string => sprintf(
                                '%s : %d / %d — %s',
                                $state['label'],
                                $state['correct'],
                                $state['total'],
                                Result::formatRate((float) $state['rate']),
                            )),
                        TextEntry::make('global_rate')
                            ->label('Taux global')
                            ->formatStateUsing(fn (float $state): string => Result::formatRate($state)),
                        TextEntry::make('pass_rate')
                            ->label('Taux minimal')
                            ->formatStateUsing(fn (int $state): string => Result::formatRate((float) $state)),
                        TextEntry::make('passed')
                            ->label('Mention')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Réussi' : 'Non réussi')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('certificate_number')
                            ->label('Numéro de certificat')
                            ->fontFamily('mono')
                            ->copyable(),
                    ]),
            ]);
    }
}
