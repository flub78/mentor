<?php

namespace App\Leitner\Suites;

use App\Leitner\Models\ProgressItem;
use App\Leitner\Models\Suite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Création et modification d'une suite, sources comprises (PRD Leitner
 * UC-05, 3a) :
 *
 * - réduire le nombre de boîtes place les questions des boîtes supprimées
 *   dans la nouvelle dernière boîte ; l'augmenter ne déplace rien ;
 * - une source ajoutée arrive *en banque* : rien à écrire ;
 * - une source retirée fait perdre leur progression à ses questions.
 */
final class SuiteSettingsUpdater
{
    /**
     * @param  array<string, mixed>  $attributes  paramètres de la suite
     * @param  list<int>  $bankIds  banques sources
     */
    public function create(array $attributes, array $bankIds): Suite
    {
        return DB::transaction(function () use ($attributes, $bankIds): Suite {
            $suite = Suite::create($attributes);
            $this->syncSources($suite, $bankIds);

            return $suite;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes  paramètres de la suite
     * @param  list<int>  $bankIds  banques sources
     */
    public function update(Suite $suite, array $attributes, array $bankIds): SettingsChange
    {
        $change = $this->preview($suite, (int) ($attributes['box_count'] ?? $suite->box_count), $bankIds);

        DB::transaction(function () use ($suite, $attributes, $bankIds): void {
            $suite->update($attributes);

            $this->itemsOf($suite)
                ->whereNull('mastered_at')
                ->where('box', '>', $suite->box_count)
                ->update(['box' => $suite->box_count]);

            $this->syncSources($suite, $bankIds);
        });

        return $change;
    }

    /**
     * Ce que la modification ferait perdre ou déplacer, pour la confirmation.
     *
     * @param  list<int>  $bankIds  banques sources après modification
     */
    public function preview(Suite $suite, int $boxCount, array $bankIds): SettingsChange
    {
        $removed = array_values(array_diff($suite->bankIds(), $bankIds));

        $moved = $boxCount < $suite->box_count
            ? $this->itemsOf($suite)->whereNull('mastered_at')->where('box', '>', $boxCount)->count()
            : 0;

        $lost = $removed === []
            ? 0
            : $this->itemsOf($suite)->whereHas('question', fn ($query) => $query->whereIn('bank_id', $removed))->count();

        return new SettingsChange(
            removedBankIds: $removed,
            lostItems: $lost,
            movedItems: $moved,
            newLastBox: $boxCount,
        );
    }

    /**
     * @param  list<int>  $bankIds
     */
    private function syncSources(Suite $suite, array $bankIds): void
    {
        $current = $suite->bankIds();
        $removed = array_values(array_diff($current, $bankIds));

        if ($removed !== []) {
            $this->itemsOf($suite)
                ->whereHas('question', fn ($query) => $query->whereIn('bank_id', $removed))
                ->delete();
            $suite->sources()->whereIn('bank_id', $removed)->delete();
        }

        foreach (array_diff($bankIds, $current) as $bankId) {
            $suite->sources()->create(['bank_id' => $bankId]);
        }
    }

    /**
     * @return Builder<ProgressItem>
     */
    private function itemsOf(Suite $suite): Builder
    {
        return ProgressItem::query()->whereIn('progression_id', $suite->progressions()->select('id'));
    }
}
