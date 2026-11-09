<?php

namespace App\Qcm\Filament\Resources\Results\Tables;

use App\Qcm\Exams\ResultsCsv;
use App\Qcm\Filament\Resources\Results\ResultResource;
use App\Qcm\Models\Exam;
use App\Qcm\Models\Result;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Liste des résultats (PRD QCM UC-09), commune à la ressource « Résultats »
 * et à l'onglet « Résultats » d'un examen.
 */
class ResultsTable
{
    /**
     * @param  Exam|null  $exam  examen de la liste ; null pour tous les résultats
     */
    public static function configure(Table $table, ?Exam $exam = null): Table
    {
        return $table
            ->columns([
                TextColumn::make('candidate')
                    ->label('Candidat')
                    ->state(fn (Result $record): string => $record->last_name.' '.$record->first_name)
                    ->description(fn (Result $record): ?string => collect([$record->email, $record->organisation, $record->identifier])->filter()->implode(' · ') ?: null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchCandidate($query, $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('last_name', $direction)->orderBy('first_name', $direction)),
                TextColumn::make('taken_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('exam_title')
                    ->label('Examen')
                    ->hidden($exam !== null)
                    ->wrap()
                    ->sortable(),
                TextColumn::make('questionnaire_title')
                    ->label('Questionnaire')
                    ->wrap()
                    ->description(fn (Result $record): ?string => $record->questionnaire_version === null ? null : 'Version '.$record->questionnaire_version)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('global_rate')
                    ->label('Taux global')
                    ->formatStateUsing(fn (float $state): string => Result::formatRate($state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('passed')
                    ->label('Mention')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Réussi' : 'Non réussi')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('certificate_number')
                    ->label('Certificat')
                    ->fontFamily('mono')
                    ->searchable(),
            ])
            ->defaultSort('taken_at', 'desc')
            ->filters([
                SelectFilter::make('exam')
                    ->label('Examen')
                    ->hidden($exam !== null)
                    ->options(fn (): array => Exam::query()->orderBy('title')->pluck('title', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, mixed $examId): Builder => $query->whereHas('attempt', fn (Builder $attempts) => $attempts->where('exam_id', $examId)),
                    )),
                Filter::make('period')
                    ->label('Période')
                    ->schema([
                        DatePicker::make('from')->label('Passé à partir du'),
                        DatePicker::make('until')->label('Passé jusqu\'au'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('taken_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('taken_at', '<=', $date)))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        ($data['from'] ?? null) ? 'À partir du '.date('d/m/Y', (int) strtotime($data['from'])) : null,
                        ($data['until'] ?? null) ? 'Jusqu\'au '.date('d/m/Y', (int) strtotime($data['until'])) : null,
                    ]))),
                TernaryFilter::make('passed')
                    ->label('Mention')
                    ->placeholder('Toutes')
                    ->trueLabel('Réussi')
                    ->falseLabel('Non réussi'),
            ])
            ->headerActions([
                self::exportAction($exam),
            ])
            ->recordUrl(fn (Result $record): string => ResultResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->url(fn (Result $record): string => ResultResource::getUrl('view', ['record' => $record])),
                self::deleteAction(),
            ])
            ->emptyStateHeading('Aucun résultat')
            ->emptyStateDescription('Un résultat est enregistré à la fin de chaque passage d\'examen.');
    }

    /**
     * Export CSV des résultats affichés : recherche et filtres appliqués,
     * dans l'ordre de la liste (PRD QCM UC-09).
     */
    public static function exportAction(?Exam $exam = null): Action
    {
        return Action::make('exportResults')
            ->label('Exporter (CSV)')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->tooltip('Résultats affichés, recherche et filtres compris')
            ->action(function (HasTable $livewire) use ($exam): StreamedResponse {
                $results = [];

                foreach ($livewire->getFilteredSortedTableQuery()?->get() ?? [] as $record) {
                    if ($record instanceof Result) {
                        $results[] = $record;
                    }
                }

                $exporter = new ResultsCsv;
                $csv = $exporter->csv($results);

                return response()->streamDownload(function () use ($csv): void {
                    echo $csv;
                }, $exporter->fileName($exam?->title), ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalHeading(fn (Result $record): string => "Supprimer le résultat de {$record->first_name} {$record->last_name} ?")
            ->modalDescription(fn (Result $record): string => "Certificat n° {$record->certificate_number}. Le certificat ne pourra plus être réédité. Cette action est définitive.")
            ->successNotificationTitle('Résultat supprimé');
    }

    /**
     * Chaque mot cherché doit figurer dans le nom, le prénom, l'e-mail,
     * l'organisation, l'identifiant libre ou le numéro de certificat :
     * « Martin Alice » et « Alice Martin » trouvent la même personne.
     *
     * @param  Builder<Result>  $query
     * @return Builder<Result>
     */
    private static function searchCandidate(Builder $query, string $search): Builder
    {
        foreach (preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $term = '%'.$word.'%';
            $query->where(function (Builder $query) use ($term): void {
                foreach (['last_name', 'first_name', 'email', 'organisation', 'identifier', 'certificate_number'] as $column) {
                    $query->orWhere($column, 'like', $term);
                }
            });
        }

        return $query;
    }
}
