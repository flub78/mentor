<?php

namespace App\Qcm\Models;

use Database\Factories\Qcm\AnswerFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Réponse proposée pour une question (PRD QCM §3).
 *
 * @property int $id
 * @property int $question_id
 * @property string $text
 * @property bool $is_correct
 * @property string|null $explanation
 * @property int $position
 */
#[UseFactory(AnswerFactory::class)]
class Answer extends Model
{
    /** @use HasFactory<AnswerFactory> */
    use HasFactory, TracksAuthor;

    protected $table = 'qcm_answers';

    protected $fillable = ['question_id', 'text', 'is_correct', 'explanation', 'position'];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
