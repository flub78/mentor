<?php

namespace App\Leitner\Models;

use Database\Factories\Leitner\BankFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Banque de questions Leitner (PRD Leitner §3, UC-01). Elle est partagée
 * entre les suites et n'est jamais modifiée par l'entraînement.
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

    protected $table = 'leitner_banks';

    protected $fillable = ['name', 'description'];

    protected static function booted(): void
    {
        // Suppression question par question pour que chacune efface son image.
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
     * Thèmes déjà utilisés dans la banque, sans doublon et triés, proposés à
     * la saisie d'une question (PRD Leitner §3).
     *
     * @return list<string>
     */
    public function themeNames(): array
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
