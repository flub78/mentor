<?php

namespace App\Banques\Questions\Handlers;

use App\Banques\Questions\Evaluation;
use App\Banques\Questions\InvalidResponse;
use App\Banques\Questions\QuestionTypeHandler;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Question à choix multiples (PRD Banques §6) : une bonne réponse, au moins
 * une mauvaise réponse avec explication facultative.
 *
 * `payload` : `{correct: string, wrong: list<{text: string, explanation: ?string}>, shuffle: bool, correct_position: int}`.
 * Sans mélange, les propositions sont affichées dans un ordre fixe : les
 * mauvaises réponses dans leur ordre, la bonne réponse au rang
 * `correct_position` (0 = en premier ; toujours 0 avec mélange).
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
                ->helperText('À désactiver si les réponses se réfèrent les unes aux autres (« aucune des réponses précédentes ») ou si leur ordre aide à lire (« A · B · Partage »).')
                ->default(true)
                ->live(),
            Select::make('payload.correct_position')
                ->label('Place de la bonne réponse')
                ->helperText('Les mauvaises réponses s\'affichent dans l\'ordre de la liste ci-dessus ; la bonne réponse prend la place choisie.')
                ->options(fn (Get $get): array => self::positionOptions(count((array) $get('payload.wrong')) + 1))
                ->default(0)
                ->selectablePlaceholder(false)
                ->visible(fn (Get $get): bool => ! $get('payload.shuffle')),
        ];
    }

    /**
     * Places possibles de la bonne réponse : 0 → « 1re », 1 → « 2e »…
     *
     * @return array<int, string>
     */
    private static function positionOptions(int $count): array
    {
        $options = [];

        for ($rank = 0; $rank < max(1, $count); $rank++) {
            $options[$rank] = $rank === 0 ? '1re (en premier)' : ($rank + 1).'e';
        }

        $options[max(1, $count) - 1] .= $count > 1 ? ' (en dernier)' : '';

        return $options;
    }

    public function rules(): array
    {
        return [
            'correct' => ['required', 'string'],
            'wrong' => ['required', 'array', 'min:1'],
            'wrong.*.text' => ['required', 'string'],
            'wrong.*.explanation' => ['nullable', 'string'],
            'shuffle' => ['boolean'],
            'correct_position' => ['integer', 'min:0'],
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
            'correct_position.integer' => 'La place de la bonne réponse doit être un nombre entier.',
            'correct_position.min' => 'La place de la bonne réponse ne peut pas être négative.',
        ];
    }

    public function normalize(array $payload): array
    {
        $wrong = is_array($payload['wrong'] ?? null) ? array_values($payload['wrong']) : [];
        $shuffle = (bool) ($payload['shuffle'] ?? true);
        $position = $payload['correct_position'] ?? 0;

        if ($shuffle) {
            $position = 0;
        } elseif (is_numeric($position) && (int) $position == $position) {
            // Une mauvaise réponse retirée ne laisse pas la bonne réponse hors de la liste.
            $position = min((int) $position, count($wrong));
        }

        return [
            'correct' => is_string($payload['correct'] ?? null) ? trim($payload['correct']) : ($payload['correct'] ?? null),
            'wrong' => array_map(fn (mixed $answer): array => [
                'text' => is_array($answer) && is_string($answer['text'] ?? null) ? trim($answer['text']) : null,
                'explanation' => is_array($answer) && is_string($answer['explanation'] ?? null) && trim($answer['explanation']) !== ''
                    ? trim($answer['explanation'])
                    : null,
            ], $wrong),
            'shuffle' => $shuffle,
            'correct_position' => $position,
        ];
    }

    /**
     * Propositions dans l'ordre d'affichage sans mélange : les mauvaises
     * réponses dans leur ordre, la bonne réponse à sa place. Le mélange
     * éventuel est fait à l'affichage.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string> clé → texte
     */
    public function options(array $payload): array
    {
        $options = [];

        foreach (array_values($payload['wrong']) as $index => $answer) {
            $options[self::WRONG_KEY_PREFIX.$index] = (string) $answer['text'];
        }

        $position = max(0, min((int) ($payload['correct_position'] ?? 0), count($options)));

        return array_slice($options, 0, $position, true)
            + [self::CORRECT_KEY => (string) $payload['correct']]
            + array_slice($options, $position, null, true);
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
