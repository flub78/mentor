<?php

namespace App\Leitner\Models;

use App\Banques\Models\Question;
use App\Banques\Questions\Verdict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Question posée dans une séance (PRD Leitner UC-08). Une entrée de reprise
 * (`is_retry`) repose une question échouée et ne change pas sa boîte.
 *
 * @property int $id
 * @property int $session_id
 * @property int|null $question_id
 * @property int|null $retry_of_id
 * @property int $position
 * @property bool $is_retry
 * @property bool $is_new
 * @property int|null $box_before
 * @property int|null $failures_before
 * @property mixed $response
 * @property Verdict|null $verdict
 * @property string|null $detail
 * @property int|null $box_after
 * @property bool $mastered
 * @property Carbon|null $answered_at
 * @property Carbon|null $skipped_at
 * @property int|null $duration_ms
 */
class SessionEntry extends Model
{
    use TracksAuthor;

    protected $table = 'leitner_session_entries';

    protected $fillable = [
        'session_id', 'question_id', 'retry_of_id', 'position', 'is_retry', 'is_new',
        'box_before', 'failures_before', 'response', 'verdict', 'detail', 'box_after',
        'mastered', 'answered_at', 'skipped_at', 'duration_ms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_retry' => 'boolean',
            'is_new' => 'boolean',
            'box_before' => 'integer',
            'failures_before' => 'integer',
            'response' => 'json',
            'verdict' => Verdict::class,
            'box_after' => 'integer',
            'mastered' => 'boolean',
            'answered_at' => 'datetime',
            'skipped_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Session, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }

    public function isDone(): bool
    {
        return $this->answered_at !== null || $this->skipped_at !== null;
    }
}
