<?php

namespace App\Banques\Questions;

use Illuminate\Support\Str;

/**
 * Comparaison des réponses saisies (PRD Banques §6 ; PRD Leitner Q10 : choix provisoire) :
 * sans tenir compte des majuscules, des accents ni des espaces en trop.
 */
final class AnswerNormalizer
{
    public static function normalize(string $text): string
    {
        $text = Str::ascii(trim($text), 'fr');
        $text = mb_strtolower($text);

        return (string) preg_replace('/\s+/u', ' ', $text);
    }

    public static function equals(string $first, string $second): bool
    {
        return self::normalize($first) === self::normalize($second);
    }

    /**
     * Nombre saisi avec une virgule ou un point décimal, espaces (y compris
     * insécables) ignorés ; null si la saisie n'est pas un nombre.
     */
    public static function number(string $input): ?float
    {
        $input = (string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $input);
        $input = str_replace(',', '.', $input);

        if (preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $input) !== 1) {
            return null;
        }

        return (float) $input;
    }
}
