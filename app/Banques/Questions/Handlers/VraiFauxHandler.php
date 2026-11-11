<?php

namespace App\Banques\Questions\Handlers;

use App\Banques\Questions\Evaluation;
use App\Banques\Questions\InvalidResponse;
use App\Banques\Questions\QuestionTypeHandler;
use Filament\Forms\Components\ToggleButtons;

/**
 * Affirmation vraie ou fausse (PRD Banques §6).
 *
 * `payload` : `{value: bool}`. Réponse de l'étudiant : booléen, ou « vrai » /
 * « faux ».
 */
final class VraiFauxHandler implements QuestionTypeHandler
{
    public function formSchema(): array
    {
        return [
            // Options textuelles plutôt que booléennes : la règle « in » de
            // Filament refuse la valeur false.
            ToggleButtons::make('payload.value')
                ->label('L\'affirmation est')
                ->options(['vrai' => 'Vraie', 'faux' => 'Fausse'])
                ->colors(['vrai' => 'success', 'faux' => 'danger'])
                ->inline()
                ->required()
                ->afterStateHydrated(function (ToggleButtons $component, mixed $state): void {
                    $value = self::toBool($state);

                    if ($value !== null) {
                        $component->state($value ? 'vrai' : 'faux');
                    }
                })
                ->dehydrateStateUsing(fn (mixed $state): ?bool => self::toBool($state))
                ->validationMessages([
                    'required' => 'Indiquez si l\'affirmation est vraie ou fausse.',
                    'in' => 'Indiquez si l\'affirmation est vraie ou fausse.',
                ]),
        ];
    }

    public function rules(): array
    {
        return [
            'value' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => 'Indiquez si l\'affirmation est vraie ou fausse.',
            'value.boolean' => 'La valeur de l\'affirmation doit être vraie ou fausse.',
        ];
    }

    public function normalize(array $payload): array
    {
        return [
            'value' => self::toBool($payload['value'] ?? null),
        ];
    }

    /**
     * Booléen à partir de « vrai » / « faux », d'un booléen ou de 1 / 0 ;
     * null si la valeur n'est ni vraie ni fausse.
     */
    private static function toBool(mixed $value): ?bool
    {
        return match (true) {
            is_bool($value) => $value,
            $value === 'vrai' => true,
            $value === 'faux' => false,
            $value === null, $value === '' => null,
            default => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
        };
    }

    public function evaluate(array $payload, mixed $response): Evaluation
    {
        $answer = match (true) {
            is_bool($response) => $response,
            $response === 'vrai' => true,
            $response === 'faux' => false,
            default => throw new InvalidResponse('Répondez « Vrai » ou « Faux ».'),
        };

        return $answer === (bool) $payload['value'] ? Evaluation::correct() : Evaluation::wrong();
    }

    public function expectedAnswer(array $payload): string
    {
        return $payload['value'] ? 'Vrai' : 'Faux';
    }

    public function answerView(): string
    {
        return 'leitner.answers.vrai-faux';
    }
}
