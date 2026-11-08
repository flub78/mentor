<?php

namespace App\Qcm\Exchange;

use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Export d'une banque, entière ou réduite à une sélection (PRD QCM UC-06).
 */
final class BankExporter
{
    public function __construct(private readonly ExchangeWriter $writer = new ExchangeWriter) {}

    /**
     * @param  EloquentCollection<int, Question>|null  $selection  questions choisies ; null = toute la banque
     */
    public function csv(Bank $bank, ?EloquentCollection $selection = null): string
    {
        return $this->writer->csv($this->questions($bank, $selection));
    }

    /**
     * @param  EloquentCollection<int, Question>|null  $selection
     * @return string chemin du fichier temporaire
     */
    public function zip(Bank $bank, ?EloquentCollection $selection = null): string
    {
        return $this->writer->zip($this->questions($bank, $selection), ExchangeKind::Bank, [
            'nom' => $bank->name,
            'description' => $bank->description,
        ]);
    }

    public function fileName(Bank $bank, string $extension): string
    {
        return 'banque-'.(Str::slug($bank->name) ?: $bank->id).'-'.now()->format('Y-m-d').'.'.$extension;
    }

    /**
     * @param  EloquentCollection<int, Question>|null  $selection
     * @return Collection<int, Question>
     */
    private function questions(Bank $bank, ?EloquentCollection $selection): Collection
    {
        $query = $bank->questions()->with('answers')->orderBy('id');

        if ($selection !== null) {
            $query->whereKey($selection->modelKeys());
        }

        return $query->get();
    }
}
