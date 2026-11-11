<?php

namespace App\Banques\Exchange;

use App\Banques\Questions\AnswerNormalizer;

/**
 * Valeurs d'une cellule du fichier d'échange (PRD Banques §8.3) : oui / non,
 * nombres, à la lecture comme à l'écriture.
 */
final class CellValues
{
    /**
     * `oui` / `non`, `vrai` / `faux`, `1` / `0`, sans tenir compte des
     * majuscules ; null si la valeur n'est rien de cela.
     */
    public static function bool(string $value): ?bool
    {
        return match (AnswerNormalizer::normalize($value)) {
            'oui', 'vrai', '1' => true,
            'non', 'faux', '0' => false,
            default => null,
        };
    }

    /**
     * Nombre avec virgule ou point décimal, espaces entre les milliers
     * ignorés ; null si la valeur n'est pas un nombre.
     */
    public static function number(string $value): ?float
    {
        return AnswerNormalizer::number($value);
    }

    public static function writeBool(bool $value): string
    {
        return $value ? 'oui' : 'non';
    }

    /**
     * Nombre écrit avec la virgule décimale, sans zéros inutiles ni
     * séparateur de milliers (relu tel quel par les tableurs français).
     */
    public static function writeNumber(float|int $value): string
    {
        $text = rtrim(rtrim(number_format((float) $value, 6, ',', ''), '0'), ',');

        return $text === '-0' ? '0' : $text;
    }

    /**
     * Type écrit librement (« Vrai faux », « vrai-faux ») ramené à son code.
     */
    public static function typeCode(string $value): string
    {
        return trim((string) preg_replace('/[\s\-]+/u', '_', AnswerNormalizer::normalize($value)), '_');
    }
}
