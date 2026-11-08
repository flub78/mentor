<?php

namespace App\Qcm\Models;

use Database\Factories\Qcm\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Question à choix multiples : une bonne réponse, au moins une mauvaise
 * (PRD QCM §3, UC-04).
 *
 * @property int $id
 * @property int $bank_id
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

    protected $fillable = ['bank_id', 'statement', 'module', 'explanation', 'image_path'];

    protected static function booted(): void
    {
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
