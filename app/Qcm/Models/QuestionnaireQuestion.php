<?php

namespace App\Qcm\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Place d'une question dans un questionnaire (PRD QCM UC-08). Une question
 * ajoutée sans position se place à la fin.
 *
 * @property int $id
 * @property int $questionnaire_id
 * @property int $question_id
 * @property int $position
 */
class QuestionnaireQuestion extends Pivot
{
    use TracksAuthor;

    protected $table = 'qcm_questionnaire_questions';

    public $incrementing = true;

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (QuestionnaireQuestion $pivot): void {
            if (! $pivot->isDirty('position')) {
                $last = static::query()->where('questionnaire_id', $pivot->questionnaire_id)->max('position');
                $pivot->position = $last === null ? 0 : (int) $last + 1;
            }
        });
    }
}
