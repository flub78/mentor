<?php

namespace App\Qcm\Models;

use App\Banques\Models\Bank as CommonBank;
use App\Banques\Models\Question as BankQuestion;
use Database\Factories\Qcm\BankFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Banque vue par le module QCM (PRD QCM §3) : banque du module Banques, dont
 * seules les questions QCM et vrai / faux sont visibles.
 */
#[UseFactory(BankFactory::class)]
class Bank extends CommonBank
{
    /**
     * Questions utilisables par le module QCM.
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'bank_id');
    }

    /**
     * Nombre de questions de la banque que le module QCM ne pose pas (PRD
     * Banques §7).
     */
    public function ignoredQuestionsCount(): int
    {
        return BankQuestion::query()->where('bank_id', $this->id)->count() - $this->questions()->count();
    }

    /**
     * Modules (thèmes) des questions QCM de la banque, sans doublon et triés.
     *
     * @return list<string>
     */
    public function moduleNames(): array
    {
        return $this->questions()
            ->whereNotNull('theme')
            ->distinct()
            ->orderBy('theme')
            ->pluck('theme')
            ->values()
            ->all();
    }
}
