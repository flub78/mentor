<?php

namespace App\Qcm\Models;

use Database\Factories\Qcm\BankFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Banque de questions (PRD QCM §3, UC-03).
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 */
#[UseFactory(BankFactory::class)]
class Bank extends Model
{
    /** @use HasFactory<BankFactory> */
    use HasFactory, TracksAuthor;

    protected $table = 'qcm_banks';

    protected $fillable = ['name', 'description'];

    protected static function booted(): void
    {
        // Suppression question par question pour que chacune efface son image ;
        // les réponses suivent par la cascade de la base.
        static::deleting(function (Bank $bank): void {
            $bank->questions()->each(fn (Question $question) => $question->delete());
        });
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Noms de module déjà utilisés dans la banque, sans doublon et triés,
     * proposés à la saisie d'une question (PRD QCM UC-04).
     *
     * @return list<string>
     */
    public function moduleNames(): array
    {
        return $this->questions()
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->values()
            ->all();
    }
}
