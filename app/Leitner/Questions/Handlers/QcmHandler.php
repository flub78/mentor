<?php

namespace App\Leitner\Questions\Handlers;

use App\Leitner\Questions\Evaluation;
use App\Leitner\Questions\InvalidResponse;
use App\Leitner\Questions\QuestionTypeHandler;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * Question à choix multiples (PRD Leitner §6) : une bonne réponse, au moins
 * une mauvaise réponse avec explication facultative.
 *
 * `payload` : `{correct: string, wrong: list<{text: string, explanation: ?string}>, shuffle: bool}`.
 * Réponse de l'étudiant : clé d'une proposition (`options()`).
 */
final class QcmHandler implements QuestionTypeHandler
{
    /** Clé de la bonne réponse parmi les propositions. */
    public const CORRECT_KEY = 'bonne';

    /** Préfixe des clés des mauvaises réponses (`fausse-0`, `fausse-1`…). */
    public const WRONG_KEY_PREFIX = 'fausse-';

    public function formSchema(): array
    {
        return [
            TextInput::make('payload.correct')
                ->label('Bonne réponse')
                ->required()
                ->validationMessages(['required' => 'La bonne réponse est obligatoire.']),
            Repeater::make('payload.wrong')
                ->label('Mauvaises réponses')
                ->schema([
                    TextInput::make('text')
                        ->label('Réponse')
                        ->required()
                        ->validationMessages(['required' => 'Le texte de la mauvaise réponse est obligatoire.']),
                    Textarea::make('explanation')
                        ->label('Pourquoi cette réponse est fausse (facultatif)')
                        ->rows(2),
                ])
                ->defaultItems(3)
                ->minItems(1)
                ->addActionLabel('Ajouter une mauvaise réponse')
                ->validationMessages([
                    'min' => 'Une question QCM doit avoir au moins une mauvaise réponse.',
                    'required' => 'Une question QCM doit avoir au moins une mauvaise réponse.',
                ])
                ->required(),
            Toggle::make('payload.shuffle')
                ->label('Mélanger les réponses à chaque affichage')
                ->helperText('À désactiver si les réponses se réfèrent les unes aux autres (« aucune des réponses précédentes »).')
                ->default(true),
        ];
    }

    public function rules(): array
    {
        return [
            'correct' => ['required', 'string'],
            'wrong' => ['required', 'array', 'min:1'],
            'wrong.*.text' => ['required', 'string'],
            'wrong.*.explanation' => ['nullable', 'string'],
            'shuffle' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'correct.required' => 'La bonne réponse est obligatoire.',
            'correct.string' => 'La bonne réponse doit être un texte.',
            'wrong.required' => 'Une question QCM doit avoir au moins une mauvaise réponse.',
            'wrong.array' => 'Les mauvaises réponses doivent former une liste.',
            'wrong.min' => 'Une question QCM doit avoir au moins une mauvaise réponse.',
            'wrong.*.text.required' => 'Le texte de chaque mauvaise réponse est obligatoire.',
            'wrong.*.text.string' => 'Le texte de chaque mauvaise réponse doit être un texte.',
            'wrong.*.explanation.string' => 'L\'explication d\'une mauvaise réponse doit être un texte.',
            'shuffle.boolean' => 'Le mélange des réponses doit valoir oui ou non.',
        ];
    }

    public function normalize(array $payload): array
    {
        $wrong = is_array($payload['wrong'] ?? null) ? array_values($payload['wrong']) : [];

        return [
            'correct' => is_string($payload['correct'] ?? null) ? trim($payload['correct']) : ($payload['correct'] ?? null),
            'wrong' => array_map(fn (mixed $answer): array => [
                'text' => is_array($answer) && is_string($answer['text'] ?? null) ? trim($answer['text']) : null,
                'explanation' => is_array($answer) && is_string($answer['explanation'] ?? null) && trim($answer['explanation']) !== ''
                    ? trim($answer['explanation'])
                    : null,
            ], $wrong),
            'shuffle' => (bool) ($payload['shuffle'] ?? true),
        ];
    }

    /**
     * Propositions, dans l'ordre de saisie : la bonne réponse puis les
     * mauvaises. Le mélange éventuel est fait à l'affichage.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string> clé → texte
     */
    public function options(array $payload): array
    {
        $options = [self::CORRECT_KEY => (string) $payload['correct']];

        foreach ($payload['wrong'] as $index => $answer) {
            $options[self::WRONG_KEY_PREFIX.$index] = (string) $answer['text'];
        }

        return $options;
    }

    public function evaluate(array $payload, mixed $response): Evaluation
    {
        if (! is_string($response) || ! array_key_exists($response, $this->options($payload))) {
            throw new InvalidResponse('Choisissez une des réponses proposées.');
        }

        if ($response === self::CORRECT_KEY) {
            return Evaluation::correct();
        }

        $index = (int) substr($response, strlen(self::WRONG_KEY_PREFIX));

        return Evaluation::wrong($payload['wrong'][$index]['explanation'] ?? null);
    }

    public function expectedAnswer(array $payload): string
    {
        return (string) $payload['correct'];
    }

    public function answerView(): string
    {
        return 'leitner.answers.qcm';
    }
}
