<?php

namespace App\Leitner\Models;

use App\Leitner\Sessions\SessionStatus;
use App\Leitner\Suites\PresentationOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Séance d'une progression (PRD Leitner UC-08). Son numéro, qui décide des
 * boîtes posées, est attribué à la première réponse.
 *
 * @property int $id
 * @property int $progression_id
 * @property string $kind
 * @property int|null $number
 * @property SessionStatus $status
 * @property PresentationOrder $presentation_order
 * @property bool $undo_available
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 */
class Session extends Model
{
    use TracksAuthor;

    /** Séance ordinaire ; les contrôles et tests de placement viendront au lot 2. */
    public const KIND_SESSION = 'seance';

    protected $table = 'leitner_sessions';

    protected $fillable = [
        'progression_id', 'kind', 'number', 'status', 'presentation_order',
        'undo_available', 'started_at', 'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => SessionStatus::class,
            'presentation_order' => PresentationOrder::class,
            'undo_available' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Progression, $this>
     */
    public function progression(): BelongsTo
    {
        return $this->belongsTo(Progression::class);
    }

    /**
     * @return HasMany<SessionEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(SessionEntry::class)->orderBy('position');
    }

    public function isInProgress(): bool
    {
        return $this->status === SessionStatus::EnCours;
    }

    /**
     * Première entrée encore à traiter, ou null si la séance est finie.
     */
    public function nextEntry(): ?SessionEntry
    {
        return $this->entries()->whereNull('answered_at')->whereNull('skipped_at')->first();
    }

    /**
     * Entrées traitées et total, pour la position affichée (« 7 / 23 »).
     *
     * @return array{done: int, total: int}
     */
    public function position(): array
    {
        return [
            'done' => $this->entries()->where(fn ($query) => $query->whereNotNull('answered_at')->orWhereNotNull('skipped_at'))->count(),
            'total' => $this->entries()->count(),
        ];
    }
}
