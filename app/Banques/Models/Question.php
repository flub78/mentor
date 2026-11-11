<?php

namespace App\Banques\Models;

use App\Banques\Questions\Difficulty;
use App\Banques\Questions\Evaluation;
use App\Banques\Questions\InvalidQuestion;
use App\Banques\Questions\QuestionType;
use App\Banques\Questions\QuestionTypeHandler;
use Database\Factories\Banques\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Question d'une banque (PRD Banques §3, §6, UC-02). Sa réponse
 * attendue (`payload`) a la forme propre à son type ; elle est normalisée et
 * vérifiée à chaque enregistrement.
 *
 * @property int $id
 * @property int $bank_id
 * @property QuestionType $type
 * @property string $statement
 * @property array<string, mixed> $payload
 * @property string|null $explanation
 * @property Difficulty|null $difficulty
 * @property string|null $theme
 * @property string|null $image_path
 * @property string|null $image_alt
 */
#[UseFactory(QuestionFactory::class)]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, TracksAuthor;

    /** Disque de stockage des images des questions. */
    public const IMAGE_DISK = 'public';

    /** Répertoire des images sur le disque. */
    public const IMAGE_DIRECTORY = 'banques/images';

    protected $table = 'banques_questions';

    protected $fillable = [
        'bank_id', 'type', 'statement', 'payload', 'explanation',
        'difficulty', 'theme', 'image_path', 'image_alt',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'payload' => 'array',
            'difficulty' => Difficulty::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Question $question): void {
            if ($question->exists && $question->isDirty('type')) {
                throw new InvalidQuestion('Le type d\'une question ne peut pas être changé après sa création : créez une nouvelle question.');
            }

            // Lecture brute : le type est absent tant qu'une nouvelle question ne l'a pas reçu.
            $type = $question->getAttribute('type');

            if (! $type instanceof QuestionType) {
                throw new InvalidQuestion('Le type de la question est obligatoire.');
            }

            $payload = $question->handler()->normalize($question->payload ?? []);
            $errors = $type->payloadErrors($payload);

            if ($errors !== []) {
                throw new InvalidQuestion("Question « {$type->getLabel()} » invalide : ".implode(' ', $errors));
            }

            $question->payload = $payload;

            if ($question->image_path === null) {
                $question->image_alt = null;
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
     * Thème en texte libre : espaces de bord retirés, vide ramené à nul.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function theme(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => self::blankToNull($value));
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function imageAlt(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => self::blankToNull($value));
    }

    /**
     * @return BelongsTo<covariant Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function handler(): QuestionTypeHandler
    {
        return $this->type->handler();
    }

    /**
     * Corrige une réponse de l'étudiant selon le type de la question.
     */
    public function evaluate(mixed $response): Evaluation
    {
        return $this->handler()->evaluate($this->payload, $response);
    }

    public function expectedAnswer(): string
    {
        return $this->handler()->expectedAnswer($this->payload);
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private static function deleteImage(mixed $path): void
    {
        if (is_string($path) && $path !== '') {
            Storage::disk(self::IMAGE_DISK)->delete($path);
        }
    }
}
