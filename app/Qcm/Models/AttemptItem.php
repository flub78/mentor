<?php

namespace App\Qcm\Models;

use Carbon\CarbonInterface;
use Database\Factories\Qcm\AttemptItemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Question posée lors d'un passage (PRD QCM UC-12). Conservée après la fin
 * pour les statistiques et une éventuelle neutralisation (plan QCM §6.2) ;
 * elle ne sert ni au certificat ni à la réédition.
 *
 * @property int $id
 * @property int $attempt_id
 * @property int $position
 * @property int|null $question_id null si la question a été supprimée depuis
 * @property list<string> $answer_order clés des réponses dans l'ordre affiché
 * @property string|null $chosen_key clé de la réponse choisie
 * @property bool $bookmarked
 * @property CarbonInterface|null $answered_at
 * @property-read Attempt $attempt
 * @property-read Question|null $question
 */
#[UseFactory(AttemptItemFactory::class)]
class AttemptItem extends Model
{
    /** @use HasFactory<AttemptItemFactory> */
    use HasFactory, TracksAuthor;

    protected $table = 'qcm_attempt_items';

    protected $fillable = ['attempt_id', 'position', 'question_id', 'answer_order', 'chosen_key', 'bookmarked', 'answered_at'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'question_id' => 'integer',
            'answer_order' => 'array',
            'bookmarked' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Attempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Réponse choisie par le candidat, ou null.
     */
    public function chosenAnswer(): ?Answer
    {
        return $this->question?->answer($this->chosen_key);
    }

    public function isAnswered(): bool
    {
        return $this->chosen_key !== null;
    }
}
