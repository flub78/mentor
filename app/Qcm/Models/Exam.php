<?php

namespace App\Qcm\Models;

use Database\Factories\Qcm\ExamFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Examen : pose toutes les questions d'un questionnaire, avec une durée
 * facultative, un taux de réussite minimal et des options de passage
 * (PRD QCM §3, UC-20).
 *
 * @property int $id
 * @property string $title
 * @property string|null $instructions
 * @property int $questionnaire_id
 * @property int|null $duration_minutes
 * @property int $pass_rate
 * @property bool $shuffle_questions
 * @property bool $shows_correction
 * @property int|null $max_attempts
 * @property array<string, string> $identification_fields
 * @property string|null $identifier_label
 * @property-read Questionnaire $questionnaire
 */
#[UseFactory(ExamFactory::class)]
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory, TracksAuthor;

    protected $table = 'qcm_exams';

    protected $fillable = [
        'title', 'instructions', 'questionnaire_id', 'duration_minutes', 'pass_rate',
        'shuffle_questions', 'shows_correction', 'max_attempts', 'identification_fields', 'identifier_label',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'pass_rate' => 'integer',
            'shuffle_questions' => 'boolean',
            'shows_correction' => 'boolean',
            'max_attempts' => 'integer',
            'identification_fields' => 'array',
        ];
    }

    /**
     * Exigences par défaut : aucun renseignement en plus du nom et du prénom.
     *
     * @return array<string, string>
     */
    public static function defaultIdentificationFields(): array
    {
        return array_fill_keys(
            array_map(fn (IdentificationField $field): string => $field->value, IdentificationField::cases()),
            FieldRequirement::NotAsked->value,
        );
    }

    /**
     * @return BelongsTo<Questionnaire, $this>
     */
    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    /**
     * Passages de l'examen, en cours et terminés.
     *
     * @return HasMany<Attempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    /**
     * Résultats des passages terminés de l'examen (PRD QCM UC-09).
     *
     * @return HasManyThrough<Result, Attempt, $this>
     */
    public function results(): HasManyThrough
    {
        return $this->hasManyThrough(Result::class, Attempt::class);
    }

    public function requirement(IdentificationField $field): FieldRequirement
    {
        return FieldRequirement::tryFrom($this->identification_fields[$field->value] ?? '') ?? FieldRequirement::NotAsked;
    }
}
