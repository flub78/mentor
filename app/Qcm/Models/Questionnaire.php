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
 * Questionnaire : liste ordonnée de questions, propres ou prises dans une
 * banque (PRD QCM §3, UC-08).
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
        // UC-08, 3b). Sinon, ses questions propres sont supprimées une à une
        // pour effacer leurs images ; la liste suit par la cascade de la base.
        static::deleting(function (Questionnaire $questionnaire): void {
            $exams = $questionnaire->exams()->pluck('title')->all();

            if ($exams !== []) {
                throw QuestionnaireInUseException::usedBy($questionnaire, $exams);
            }

            $questionnaire->ownQuestions()->each(fn (Question $question) => $question->delete());
        });
    }

    /**
     * Toutes les questions, propres et de banque, dans l'ordre du questionnaire.
     *
     * @return BelongsToMany<Question, $this, QuestionnaireQuestion, 'pivot'>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'qcm_questionnaire_questions')
            ->using(QuestionnaireQuestion::class)
            ->withPivot('id', 'position')
            ->withTimestamps()
            ->orderByPivot('position')
            ->orderByPivot('id');
    }

    /**
     * Questions propres : saisies dans le questionnaire, hors de toute banque.
     *
     * @return HasMany<Question, $this>
     */
    public function ownQuestions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * @return HasMany<Exam, $this>
     */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /**
     * Noms de module utilisés par les questions du questionnaire, sans doublon
     * et triés, proposés à la saisie d'une question propre (PRD QCM UC-04).
     *
     * @return list<string>
     */
    public function moduleNames(): array
    {
        return $this->questions()
            ->reorder()
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->values()
            ->all();
    }
}
