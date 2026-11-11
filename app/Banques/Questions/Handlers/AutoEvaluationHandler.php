<?php

namespace App\Banques\Questions\Handlers;

use App\Banques\Questions\Evaluation;
use App\Banques\Questions\InvalidResponse;
use App\Banques\Questions\QuestionTypeHandler;
use Filament\Forms\Components\Textarea;

/**
 * Question recto / verso en auto-évaluation (PRD Banques §6) : l'étudiant
 * compare sa réponse à la réponse de référence et donne lui-même le verdict.
 *
 * `payload` : `{answer: string}`. Réponse de l'étudiant : vrai s'il savait.
 */
final class AutoEvaluationHandler implements QuestionTypeHandler
{
    public function formSchema(): array
    {
        return [
            Textarea::make('payload.answer')
                ->label('Réponse de référence')
                ->helperText('Affichée quand l\'étudiant demande à voir la réponse ; il juge alors lui-même s\'il savait.')
                ->required()
                ->rows(3)
                ->validationMessages(['required' => 'La réponse de référence est obligatoire.']),
        ];
    }

    public function rules(): array
    {
        return [
            'answer' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'answer.required' => 'La réponse de référence est obligatoire.',
            'answer.string' => 'La réponse de référence doit être un texte.',
        ];
    }

    public function normalize(array $payload): array
    {
        $answer = $payload['answer'] ?? null;

        return [
            'answer' => is_string($answer) ? trim($answer) : $answer,
        ];
    }

    public function evaluate(array $payload, mixed $response): Evaluation
    {
        if (! is_bool($response)) {
            throw new InvalidResponse('Indiquez si vous saviez la réponse : « Je savais » ou « Je ne savais pas ».');
        }

        return $response ? Evaluation::correct() : Evaluation::wrong();
    }

    public function expectedAnswer(array $payload): string
    {
        return (string) $payload['answer'];
    }

    public function answerView(): string
    {
        return 'leitner.answers.auto-evaluation';
    }
}
