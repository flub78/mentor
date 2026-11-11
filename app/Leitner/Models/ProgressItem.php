<?php

namespace App\Leitner\Models;

use App\Banques\Models\Question;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laraskel\Users\Models\Concerns\TracksAuthor;
use LogicException;

/**
 * Question introduite dans une progression (PRD Leitner §3, §7) : en boîte
 * (1 à N, éventuellement suspendue, elle garde alors sa boîte) ou maîtrisée
 * (plus de boîte).
 *
 * @property int $id
 * @property int $progression_id
 * @property int $question_id
 * @property int|null $box
 * @property Carbon|null $mastered_at
 * @property Carbon|null $suspended_at
 * @property int $consecutive_failures
 * @property Carbon $introduced_at
 */
class ProgressItem extends Model
{
    use TracksAuthor;

    protected $table = 'leitner_progress_items';

    protected $fillable = [
        'progression_id', 'question_id', 'box', 'mastered_at',
        'suspended_at', 'consecutive_failures', 'introduced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'box' => 'integer',
            'consecutive_failures' => 'integer',
            'mastered_at' => 'datetime',
            'suspended_at' => 'datetime',
            'introduced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProgressItem $item): void {
            if ($item->mastered_at !== null) {
                $item->box = null;

                return;
            }

            $boxCount = $item->progression->suite->box_count;

            if ($item->box === null || $item->box < 1 || $item->box > $boxCount) {
                throw new LogicException("Une question en circulation doit être dans une boîte de 1 à {$boxCount} (boîte reçue : ".($item->box ?? 'aucune').').');
            }
        });
    }

    /**
     * @return BelongsTo<Progression, $this>
     */
    public function progression(): BelongsTo
    {
        return $this->belongsTo(Progression::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function isMastered(): bool
    {
        return $this->mastered_at !== null;
    }

    public function isSuspended(): bool
    {
        return $this->mastered_at === null && $this->suspended_at !== null;
    }
}
