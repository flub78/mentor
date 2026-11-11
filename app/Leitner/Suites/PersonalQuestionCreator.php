<?php

namespace App\Leitner\Suites;

use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Leitner\Models\Suite;
use Illuminate\Support\Facades\DB;

/**
 * Question créée par l'étudiant depuis une suite (PRD Leitner UC-03) : elle
 * va dans la banque personnelle de la suite, créée au besoin et ajoutée aux
 * sources, puis en boîte 1 ou en banque selon son choix.
 */
final class PersonalQuestionCreator
{
    /**
     * @param  array<string, mixed>  $attributes  champs de la question
     */
    public function create(Suite $suite, array $attributes, bool $intoBoxOne): Question
    {
        return DB::transaction(function () use ($suite, $attributes, $intoBoxOne): Question {
            $bank = $this->personalBank($suite);

            if (! in_array($bank->id, $suite->bankIds(), true)) {
                $suite->sources()->create(['bank_id' => $bank->id]);
            }

            $question = $bank->questions()->create($attributes);

            if ($intoBoxOne) {
                $suite->progression->introduce($question);
            }

            return $question;
        });
    }

    private function personalBank(Suite $suite): Bank
    {
        if ($suite->personalBank !== null) {
            return $suite->personalBank;
        }

        $base = "{$suite->name} — mes questions";
        $name = $base;

        for ($suffix = 2; Bank::where('name', $name)->exists(); $suffix++) {
            $name = "{$base} ({$suffix})";
        }

        $bank = Bank::create([
            'name' => $name,
            'description' => "Questions ajoutées depuis la suite « {$suite->name} ».",
        ]);
        $suite->personalBank()->associate($bank)->save();

        return $bank;
    }
}
