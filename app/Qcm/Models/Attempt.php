<?php

namespace App\Qcm\Models;

use Carbon\CarbonInterface;
use Database\Factories\Qcm\AttemptFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Passage d'examen : tentative d'un candidat identifié, qui tient les
 * questions posées, les réponses et les marque-pages jusqu'à la fin
 * (PRD QCM §3, UC-12 ; plan QCM §6.2).
 *
 * @property int $id
 * @property int|null $exam_id
 * @property AttemptStatus $status
 * @property array<string, string> $identity renseignements saisis (clés du fichier d'échange : nom, prenom, email…)
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $deadline_at
 * @property CarbonInterface|null $finished_at
 * @property-read Exam|null $exam
 * @property-read Result|null $result
 */
#[UseFactory(AttemptFactory::class)]
class Attempt extends Model
{
    /** @use HasFactory<AttemptFactory> */
    use HasFactory, TracksAuthor;

    protected $table = 'qcm_attempts';

    protected $fillable = ['exam_id', 'status', 'identity', 'started_at', 'deadline_at', 'finished_at'];

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'identity' => 'array',
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Questions posées, dans l'ordre du passage.
     *
     * @return HasMany<AttemptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AttemptItem::class)->orderBy('position');
    }

    /**
     * @return HasOne<Result, $this>
     */
    public function result(): HasOne
    {
        return $this->hasOne(Result::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === AttemptStatus::InProgress;
    }

    /**
     * Passage en cours dont l'échéance est atteinte : il doit être terminé.
     */
    public function isOverdue(): bool
    {
        return $this->isInProgress() && $this->deadline_at !== null && ! $this->deadline_at->isFuture();
    }

    /**
     * Temps restant en secondes selon l'horloge du serveur ; null si le
     * passage n'est pas chronométré.
     */
    public function remainingSeconds(): ?int
    {
        if ($this->deadline_at === null) {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($this->deadline_at, false));
    }
}
