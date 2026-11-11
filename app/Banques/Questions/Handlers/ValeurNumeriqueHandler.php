<?php

namespace App\Banques\Questions\Handlers;

use App\Banques\Questions\AnswerNormalizer;
use App\Banques\Questions\Evaluation;
use App\Banques\Questions\InvalidResponse;
use App\Banques\Questions\QuestionTypeHandler;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Valeur numérique avec tolérance facultative (PRD Banques §6).
 *
 * `payload` : `{value: float, tolerance_kind: 'aucune'|'absolue'|'pourcentage', tolerance: ?float, unit: ?string}`.
 * Réponse de l'étudiant : texte, avec virgule ou point décimal, unité
 * facultative en fin de saisie.
 */
final class ValeurNumeriqueHandler implements QuestionTypeHandler
{
    public const TOLERANCE_NONE = 'aucune';

    public const TOLERANCE_ABSOLUTE = 'absolue';

    public const TOLERANCE_PERCENT = 'pourcentage';

    /** Marge des comparaisons de nombres à virgule. */
    private const EPSILON = 1e-9;

    public function formSchema(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('payload.value')
                    ->label('Valeur attendue')
                    ->numeric()
                    ->required()
                    ->validationMessages([
                        'required' => 'La valeur attendue est obligatoire.',
                        'numeric' => 'La valeur attendue doit être un nombre (point décimal).',
                    ]),
                TextInput::make('payload.unit')
                    ->label('Unité (facultative)')
                    ->maxLength(50),
                Select::make('payload.tolerance_kind')
                    ->label('Tolérance')
                    ->options([
                        self::TOLERANCE_NONE => 'Aucune : valeur exacte',
                        self::TOLERANCE_ABSOLUTE => 'Écart absolu',
                        self::TOLERANCE_PERCENT => 'Écart en %',
                    ])
                    ->default(self::TOLERANCE_NONE)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),
                TextInput::make('payload.tolerance')
                    ->label('Écart accepté')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (Get $get): bool => in_array($get('payload.tolerance_kind'), [self::TOLERANCE_ABSOLUTE, self::TOLERANCE_PERCENT], true))
                    ->required()
                    ->validationMessages([
                        'required' => 'Indiquez l\'écart accepté, ou choisissez « Aucune ».',
                        'numeric' => 'L\'écart accepté doit être un nombre (point décimal).',
                        'min' => 'L\'écart accepté ne peut pas être négatif.',
                    ]),
            ]),
        ];
    }

    public function rules(): array
    {
        return [
            'value' => ['required', 'numeric'],
            'tolerance_kind' => ['required', 'in:'.implode(',', [self::TOLERANCE_NONE, self::TOLERANCE_ABSOLUTE, self::TOLERANCE_PERCENT])],
            'tolerance' => ['exclude_if:tolerance_kind,'.self::TOLERANCE_NONE, 'required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => 'La valeur attendue est obligatoire.',
            'value.numeric' => 'La valeur attendue doit être un nombre.',
            'tolerance_kind.required' => 'Choisissez le type de tolérance : aucune, écart absolu ou écart en %.',
            'tolerance_kind.in' => 'Le type de tolérance doit être « aucune », « absolue » ou « pourcentage ».',
            'tolerance.required' => 'Indiquez l\'écart accepté, ou choisissez « aucune » tolérance.',
            'tolerance.numeric' => 'L\'écart accepté doit être un nombre.',
            'tolerance.min' => 'L\'écart accepté ne peut pas être négatif.',
            'unit.string' => 'L\'unité doit être un texte.',
            'unit.max' => 'L\'unité ne doit pas dépasser 50 caractères.',
        ];
    }

    public function normalize(array $payload): array
    {
        $kind = $payload['tolerance_kind'] ?? self::TOLERANCE_NONE;
        $unit = is_string($payload['unit'] ?? null) ? trim($payload['unit']) : null;

        return [
            'value' => self::toNumber($payload['value'] ?? null),
            'tolerance_kind' => $kind,
            'tolerance' => $kind === self::TOLERANCE_NONE ? null : self::toNumber($payload['tolerance'] ?? null),
            'unit' => $unit === '' ? null : $unit,
        ];
    }

    public function evaluate(array $payload, mixed $response): Evaluation
    {
        $input = is_string($response) || is_int($response) || is_float($response) ? trim((string) $response) : '';
        $unit = $payload['unit'] ?? null;

        if (is_string($unit) && $unit !== '' && str_ends_with(AnswerNormalizer::normalize($input), AnswerNormalizer::normalize($unit))) {
            $input = mb_substr($input, 0, mb_strlen($input) - mb_strlen($unit));
        }

        $given = AnswerNormalizer::number($input);

        if ($given === null) {
            throw new InvalidResponse('Saisissez un nombre, par exemple 12,5 ou 12.5.');
        }

        $expected = (float) $payload['value'];
        $allowed = match ($payload['tolerance_kind']) {
            self::TOLERANCE_ABSOLUTE => (float) $payload['tolerance'],
            self::TOLERANCE_PERCENT => abs($expected) * (float) $payload['tolerance'] / 100,
            default => 0.0,
        };
        $gap = abs($given - $expected);

        if ($gap <= $allowed + self::EPSILON * max(1.0, abs($expected))) {
            return Evaluation::correct();
        }

        return Evaluation::wrong('Écart avec la valeur attendue : '.self::format($gap).'.');
    }

    public function expectedAnswer(array $payload): string
    {
        $text = self::format((float) $payload['value']);

        if (($payload['unit'] ?? null) !== null) {
            $text .= ' '.$payload['unit'];
        }

        return match ($payload['tolerance_kind']) {
            self::TOLERANCE_ABSOLUTE => $text.' (± '.self::format((float) $payload['tolerance']).')',
            self::TOLERANCE_PERCENT => $text.' (± '.self::format((float) $payload['tolerance']).' %)',
            default => $text,
        };
    }

    public function answerView(): string
    {
        return 'leitner.answers.valeur-numerique';
    }

    /**
     * Nombre à la française : virgule décimale, sans zéros inutiles.
     */
    public static function format(float $number): string
    {
        $text = rtrim(rtrim(number_format($number, 6, ',', "\u{202F}"), '0'), ',');

        return $text === '-0' ? '0' : $text;
    }

    private static function toNumber(mixed $value): mixed
    {
        if (is_string($value)) {
            return AnswerNormalizer::number($value) ?? $value;
        }

        return is_int($value) ? (float) $value : $value;
    }
}
