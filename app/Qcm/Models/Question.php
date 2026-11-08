<?php

namespace App\Qcm\Models;

use Database\Factories\Qcm\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laraskel\Users\Models\Concerns\TracksAuthor;
use LogicException;

/**
 * Question à choix multiples : une bonne réponse, au moins une mauvaise
 * (PRD QCM §3, UC-04). Elle appartient soit à une banque, soit, question
 * propre, à un questionnaire : exactement l'un des deux.
 *
 * @property int $id
 * @property int|null $bank_id
 * @property int|null $questionnaire_id
 * @property string $statement
 * @property string|null $module
 * @property string|null $explanation
 * @property string|null $image_path
 */
#[UseFactory(QuestionFactory::class)]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, TracksAuthor;

    /** Disque de stockage des images des questions. */
    public const IMAGE_DISK = 'public';

    /** Répertoire des images sur le disque. */
    public const IMAGE_DIRECTORY = 'qcm/images';

    protected $table = 'qcm_questions';

    protected $fillable = ['bank_id', 'questionnaire_id', 'statement', 'module', 'explanation', 'image_path'];

    protected static function booted(): void
    {
        static::saving(function (Question $question): void {
            if (($question->bank_id === null) === ($question->questionnaire_id === null)) {
                throw new LogicException('Une question appartient soit à une banque, soit à un questionnaire (question propre) : exactement l\'un des deux doit être renseigné.');
            }
        });

        static::updated(function (Question $question): void {
            if ($question->wasChanged('image_path')) {
                self::deleteImage($question->getOriginal('image_path'));
            }
        });

        static::deleted(function (Question $question): void {
            self::deleteImage($question->image_path);
        });
    }

    /**
     * Module en texte libre : espaces de bord retirés, vide ramené à « sans module ».
     *
     * @return Attribute<string|null, string|null>
     */
    protected function module(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): ?string {
                $value = $value === null ? null : trim($value);

                return $value === '' ? null : $value;
            },
        );
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * Questionnaire propriétaire d'une question propre.
     *
     * @return BelongsTo<Questionnaire, $this>
     */
    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    /**
     * Questionnaires dont la liste contient la question.
     *
     * @return BelongsToMany<Questionnaire, $this, QuestionnaireQuestion, 'pivot'>
     */
    public function questionnaires(): BelongsToMany
    {
        return $this->belongsToMany(Questionnaire::class, 'qcm_questionnaire_questions')
            ->using(QuestionnaireQuestion::class)
            ->withPivot('id', 'position')
            ->withTimestamps();
    }

    /**
     * Question propre à un questionnaire, hors de toute banque.
     */
    public function isOwn(): bool
    {
        return $this->questionnaire_id !== null;
    }

    /**
     * Copie indépendante de la question, réponses et image comprises
     * (PRD QCM UC-05, UC-08 3a).
     *
     * @param  array{bank_id?: int|null, questionnaire_id?: int|null}  $owner
     */
    public function duplicate(array $owner): self
    {
        $copy = $this->replicate(['bank_id', 'questionnaire_id', 'image_path', 'created_by', 'updated_by']);
        $copy->fill(['bank_id' => null, 'questionnaire_id' => null, ...$owner]);

        if ($this->image_path !== null && Storage::disk(self::IMAGE_DISK)->exists($this->image_path)) {
            $copy->image_path = self::IMAGE_DIRECTORY.'/'.Str::ulid()->toBase32().'.'.pathinfo($this->image_path, PATHINFO_EXTENSION);
            Storage::disk(self::IMAGE_DISK)->copy($this->image_path, $copy->image_path);
        }

        $copy->save();

        foreach ($this->answers as $answer) {
            $copy->answers()->create($answer->only(['text', 'is_correct', 'explanation', 'position']));
        }

        return $copy;
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class)->orderBy('position');
    }

    /**
     * @return HasOne<Answer, $this>
     */
    public function correctAnswer(): HasOne
    {
        return $this->hasOne(Answer::class)->where('is_correct', true);
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function wrongAnswers(): HasMany
    {
        return $this->hasMany(Answer::class)->where('is_correct', false)->orderBy('position');
    }

    private static function deleteImage(mixed $path): void
    {
        if (is_string($path) && $path !== '') {
            Storage::disk(self::IMAGE_DISK)->delete($path);
        }
    }
}
