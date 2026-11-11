<?php

namespace App\Qcm\Models;

use App\Banques\Models\Question as BankQuestion;
use App\Banques\Questions\Handlers\QcmHandler;
use App\Banques\Questions\QuestionType;
use Database\Factories\Qcm\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Question posée par le module QCM (PRD QCM §3, §6) : question du module
 * Banques de type QCM, ou vrai / faux présenté comme un QCM à deux réponses.
 * Les autres types ne sont pas visibles par ce modèle.
 *
 * @property string|null $module thème de la question
 * @property-read Collection<int, Answer> $answers bonne réponse puis mauvaises, dans l'ordre
 * @property-read Answer|null $correctAnswer
 * @property-read Collection<int, Answer> $wrongAnswers
 */
#[UseFactory(QuestionFactory::class)]
class Question extends BankQuestion
{
    /**
     * `module` en plus des champs du module Banques : nom historique du thème.
     *
     * @var list<string>
     */
    protected $fillable = [
        'bank_id', 'type', 'statement', 'payload', 'explanation',
        'difficulty', 'theme', 'module', 'image_path', 'image_alt',
    ];

    /** Types posés par le module QCM. */
    public const USABLE_TYPES = [QuestionType::Qcm, QuestionType::VraiFaux];

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('qcm-types', function (Builder $query): void {
            $query->whereIn($query->qualifyColumn('type'), array_map(fn (QuestionType $type): string => $type->value, self::USABLE_TYPES));
        });
    }

    /**
     * Le module d'une question QCM est son thème dans le module Banques.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function module(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->theme,
            set: function (?string $value): array {
                $value = $value === null ? null : trim($value);

                return ['theme' => $value === '' ? null : $value];
            },
        );
    }

    /**
     * @return Attribute<Collection<int, Answer>, never>
     */
    protected function answers(): Attribute
    {
        return Attribute::make(get: function (): Collection {
            if ($this->type === QuestionType::VraiFaux) {
                $value = (bool) ($this->payload['value'] ?? false);

                return new Collection([
                    new Answer('vrai', 'Vrai', $value, null, 0),
                    new Answer('faux', 'Faux', ! $value, null, 1),
                ]);
            }

            $answers = [new Answer(QcmHandler::CORRECT_KEY, (string) ($this->payload['correct'] ?? ''), true, null, 0)];

            foreach (array_values($this->payload['wrong'] ?? []) as $index => $wrong) {
                $answers[] = new Answer(QcmHandler::WRONG_KEY_PREFIX.$index, (string) $wrong['text'], false, $wrong['explanation'] ?? null, $index + 1);
            }

            return new Collection($answers);
        });
    }

    /**
     * @return Attribute<Answer|null, never>
     */
    protected function correctAnswer(): Attribute
    {
        return Attribute::make(get: fn (): ?Answer => $this->answers->firstWhere('is_correct', true));
    }

    /**
     * @return Attribute<Collection<int, Answer>, never>
     */
    protected function wrongAnswers(): Attribute
    {
        return Attribute::make(get: fn (): Collection => $this->answers->where('is_correct', false)->values());
    }

    /**
     * Réponse proposée de clé donnée, ou null.
     */
    public function answer(?string $key): ?Answer
    {
        return $key === null ? null : $this->answers->firstWhere('key', $key);
    }

    /**
     * Les réponses peuvent-elles être mélangées à l'affichage ? « Vrai » et
     * « Faux » gardent leur ordre (PRD QCM §6).
     */
    public function shufflesAnswers(): bool
    {
        return $this->type === QuestionType::Qcm && (bool) ($this->payload['shuffle'] ?? true);
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * Questionnaires dont la liste contient la question.
     *
     * @return BelongsToMany<Questionnaire, $this, QuestionnaireQuestion, 'pivot'>
     */
    public function questionnaires(): BelongsToMany
    {
        return $this->belongsToMany(Questionnaire::class, 'qcm_questionnaire_questions', 'question_id')
            ->using(QuestionnaireQuestion::class)
            ->withPivot('id', 'position')
            ->withTimestamps();
    }
}
