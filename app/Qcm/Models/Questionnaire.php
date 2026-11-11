<?php

namespace App\Qcm\Models;

use App\Qcm\Questionnaires\QuestionnaireInUseException;
use Database\Factories\Qcm\QuestionnaireFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Questionnaire : liste ordonnée de questions prises dans les banques du
 * module Banques (PRD QCM §3, UC-08).
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string|null $version
 */
#[UseFactory(QuestionnaireFactory::class)]
class Questionnaire extends Model
{
    /** @use HasFactory<QuestionnaireFactory> */
    use HasFactory, TracksAuthor;

    protected $table = 'qcm_questionnaires';

    protected $fillable = ['title', 'description', 'version'];

    protected static function booted(): void
    {
        // Un questionnaire utilisé par un examen ne se supprime pas (PRD QCM
        // UC-08, 3b). Ses questions restent dans leurs banques ; la liste suit
        // par la cascade de la base.
        static::deleting(function (Questionnaire $questionnaire): void {
            $exams = $questionnaire->exams()->pluck('title')->all();

            if ($exams !== []) {
                throw QuestionnaireInUseException::usedBy($questionnaire, $exams);
            }
        });
    }

    /**
     * Questions dans l'ordre du questionnaire.
     *
     * @return BelongsToMany<Question, $this, QuestionnaireQuestion, 'pivot'>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'qcm_questionnaire_questions', 'questionnaire_id', 'question_id')
            ->using(QuestionnaireQuestion::class)
            ->withPivot('id', 'position')
            ->withTimestamps()
            ->orderByPivot('position')
            ->orderByPivot('id');
    }

    /**
     * @return HasMany<Exam, $this>
     */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /**
     * Noms de module (thèmes) utilisés par les questions du questionnaire,
     * sans doublon et triés, proposés à la saisie d'une question (PRD QCM UC-08).
     *
     * @return list<string>
     */
    public function moduleNames(): array
    {
        return $this->questions()
            ->reorder()
            ->whereNotNull('theme')
            ->distinct()
            ->orderBy('theme')
            ->pluck('theme')
            ->values()
            ->all();
    }
}
