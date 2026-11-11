<?php

namespace App\Banques\Exchange;

use App\Banques\Questions\Handlers\QcmHandler;
use App\Banques\Questions\Handlers\ValeurNumeriqueHandler;
use App\Banques\Questions\QuestionType;

/**
 * Colonnes propres à chaque type de question (PRD Banques §8.2) : lecture
 * d'une ligne vers la réponse attendue (`payload`) et écriture inverse.
 * La cohérence de la réponse attendue est ensuite vérifiée par le type.
 */
final class QuestionColumns
{
    public const CORRECT_ANSWER = 'bonne_reponse';

    public const WRONG_ANSWER_PREFIX = 'mauvaise_reponse_';

    public const WRONG_EXPLANATION_PREFIX = 'explication_';

    public const SHUFFLE = 'melange';

    /** Propositions d'un QCM non mélangé, dans l'ordre d'affichage. */
    public const ORDER = 'ordre';

    public const ANSWER = 'reponse';

    public const VALUE = 'valeur';

    public const TOLERANCE_KIND = 'type_tolerance';

    public const TOLERANCE = 'tolerance';

    public const UNIT = 'unite';

    /** Colonnes définies par le format pour les types des lots suivants (PRD Banques §8.2). */
    public const LATER_COLUMNS = ['variantes', 'elements', 'nombre_demande', 'ordre_impose', 'paires', 'leurres', 'paires_presentees', 'criteres'];

    /** Types du format qui ne sont pas encore disponibles (lots B2 et B3). */
    public const LATER_TYPES = ['reponse_courte', 'enumeration', 'correspondance', 'question_ouverte'];

    /**
     * Colonnes fixes propres aux types disponibles, dans l'ordre d'écriture.
     *
     * @return list<string>
     */
    public static function fixedColumns(): array
    {
        return [self::CORRECT_ANSWER, self::SHUFFLE, self::ORDER, self::ANSWER, self::VALUE, self::TOLERANCE_KIND, self::TOLERANCE, self::UNIT];
    }

    /**
     * Colonnes qu'utilise un type (hors mauvaises réponses numérotées).
     *
     * @return list<string>
     */
    public static function columnsOf(QuestionType $type): array
    {
        return match ($type) {
            QuestionType::Qcm => [self::CORRECT_ANSWER, self::SHUFFLE, self::ORDER],
            QuestionType::VraiFaux, QuestionType::AutoEvaluation => [self::ANSWER],
            QuestionType::ValeurNumerique => [self::VALUE, self::TOLERANCE_KIND, self::TOLERANCE, self::UNIT],
        };
    }

    public static function isNumberedColumn(string $name): bool
    {
        return preg_match('/^('.self::WRONG_ANSWER_PREFIX.'|'.self::WRONG_EXPLANATION_PREFIX.')[1-9]\d*$/', $name) === 1;
    }

    /**
     * Réponse attendue lue dans une ligne.
     *
     * @param  array<string, string|null>  $cells  colonne → valeur (null si vide)
     * @return array{payload: array<string, mixed>, problems: list<string>, warnings: list<string>}
     */
    public static function read(QuestionType $type, array $cells): array
    {
        $problems = [];
        $warnings = [];

        $payload = match ($type) {
            QuestionType::Qcm => self::readQcm($cells, $problems, $warnings),
            QuestionType::VraiFaux => self::readTrueFalse($cells, $problems),
            QuestionType::AutoEvaluation => ['answer' => $cells[self::ANSWER] ?? null],
            QuestionType::ValeurNumerique => self::readNumeric($cells, $problems, $warnings),
        };

        if ($type === QuestionType::AutoEvaluation && ($cells[self::ANSWER] ?? null) === null) {
            $problems[] = 'colonne `reponse` : la réponse de référence est obligatoire';
        }

        return ['payload' => $payload, 'problems' => $problems, 'warnings' => $warnings];
    }

    /**
     * Cellules écrites pour une question.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function write(QuestionType $type, array $payload): array
    {
        return match ($type) {
            QuestionType::Qcm => self::writeQcm($payload),
            QuestionType::VraiFaux => [self::ANSWER => $payload['value'] ? 'vrai' : 'faux'],
            QuestionType::AutoEvaluation => [self::ANSWER => (string) $payload['answer']],
            QuestionType::ValeurNumerique => array_filter([
                self::VALUE => CellValues::writeNumber((float) $payload['value']),
                self::TOLERANCE_KIND => (string) $payload['tolerance_kind'],
                self::TOLERANCE => $payload['tolerance'] === null ? '' : CellValues::writeNumber((float) $payload['tolerance']),
                self::UNIT => (string) ($payload['unit'] ?? ''),
            ], fn (string $value): bool => $value !== ''),
        };
    }

    /**
     * @param  array<string, string|null>  $cells
     * @param  list<string>  $problems
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private static function readQcm(array $cells, array &$problems, array &$warnings): array
    {
        $numbers = [];

        foreach (array_keys($cells) as $name) {
            if (preg_match('/^(?:'.self::WRONG_ANSWER_PREFIX.'|'.self::WRONG_EXPLANATION_PREFIX.')(\d+)$/', $name, $matches) === 1) {
                $numbers[(int) $matches[1]] = true;
            }
        }

        ksort($numbers);
        $wrong = [];

        foreach (array_keys($numbers) as $n) {
            $text = $cells[self::WRONG_ANSWER_PREFIX.$n] ?? null;
            $explanation = $cells[self::WRONG_EXPLANATION_PREFIX.$n] ?? null;

            if ($text === null && $explanation !== null) {
                $problems[] = "colonne `explication_{$n}` : elle n'a pas de mauvaise réponse {$n}";
            } elseif ($text !== null) {
                $wrong[] = ['text' => $text, 'explanation' => $explanation];
            }
        }

        if (($cells[self::CORRECT_ANSWER] ?? null) === null) {
            $problems[] = 'colonne `bonne_reponse` : la bonne réponse est vide';
        }

        if ($wrong === []) {
            $problems[] = 'colonne `mauvaise_reponse_1` : aucune mauvaise réponse (il en faut au moins une)';
        }

        $shuffle = true;
        $shuffleCell = $cells[self::SHUFFLE] ?? null;

        if ($shuffleCell !== null) {
            $shuffle = CellValues::bool($shuffleCell);

            if ($shuffle === null) {
                $problems[] = "colonne `melange` : « {$shuffleCell} » n'est pas une valeur admise (oui ou non)";
                $shuffle = true;
            }
        }

        $correct = $cells[self::CORRECT_ANSWER] ?? null;
        $position = 0;
        $orderCell = $cells[self::ORDER] ?? null;

        if ($orderCell !== null && $shuffle) {
            $warnings[] = 'colonne `ordre` ignorée : les réponses sont mélangées (`melange` vaut `oui`)';
        } elseif ($orderCell !== null && $correct !== null && $wrong !== []) {
            $ordered = self::readOrder($orderCell, $correct, $wrong, $problems);

            if ($ordered !== null) {
                ['wrong' => $wrong, 'position' => $position] = $ordered;
            }
        }

        return ['correct' => $correct, 'wrong' => $wrong, 'shuffle' => $shuffle, 'correct_position' => $position];
    }

    /**
     * Mauvaises réponses rangées dans l'ordre de la colonne `ordre`, et place
     * de la bonne réponse ; null si l'ordre ne cite pas chaque proposition une fois.
     *
     * @param  list<array{text: string, explanation: string|null}>  $wrong
     * @param  list<string>  $problems
     * @return array{wrong: list<array{text: string, explanation: string|null}>, position: int}|null
     */
    private static function readOrder(string $cell, string $correct, array $wrong, array &$problems): ?array
    {
        /** @var list<string|null> $remaining textes des propositions pas encore cités ; 0 = bonne réponse, n = mauvaise réponse n */
        $remaining = [$correct, ...array_column($wrong, 'text')];
        $orderedWrong = [];
        $position = null;
        $errors = [];

        foreach (CellValues::list($cell) as $rank => $text) {
            $found = array_search($text, $remaining, true);

            if ($found === false) {
                $errors[] = in_array($text, [$correct, ...array_column($wrong, 'text')], true)
                    ? "« {$text} » y figure deux fois"
                    : "« {$text} » n'est ni la bonne réponse ni une mauvaise réponse de la ligne";

                continue;
            }

            $remaining[$found] = null;

            if ($found === 0) {
                $position = $rank;
            } else {
                $orderedWrong[] = $wrong[$found - 1];
            }
        }

        foreach (array_filter($remaining, fn (?string $text): bool => $text !== null) as $text) {
            $errors[] = "« {$text} » n'y figure pas";
        }

        if ($errors !== []) {
            $problems[] = 'colonne `ordre` (toutes les propositions, chacune une fois, séparées par |) : '.implode(', ', $errors);

            return null;
        }

        return ['wrong' => $orderedWrong, 'position' => (int) $position];
    }

    /**
     * @param  array<string, string|null>  $cells
     * @param  list<string>  $problems
     * @return array<string, mixed>
     */
    private static function readTrueFalse(array $cells, array &$problems): array
    {
        $cell = $cells[self::ANSWER] ?? null;
        $value = $cell === null ? null : CellValues::bool($cell);

        if ($value === null) {
            $problems[] = $cell === null
                ? 'colonne `reponse` : indiquez `vrai` ou `faux`'
                : "colonne `reponse` : « {$cell} » n'est pas une valeur admise (vrai ou faux)";
        }

        return ['value' => $value];
    }

    /**
     * @param  array<string, string|null>  $cells
     * @param  list<string>  $problems
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private static function readNumeric(array $cells, array &$problems, array &$warnings): array
    {
        $kinds = [ValeurNumeriqueHandler::TOLERANCE_NONE, ValeurNumeriqueHandler::TOLERANCE_ABSOLUTE, ValeurNumeriqueHandler::TOLERANCE_PERCENT];
        $value = self::readNumber($cells, self::VALUE, $problems, required: true);
        $kindCell = $cells[self::TOLERANCE_KIND] ?? null;
        $kind = $kindCell === null ? ValeurNumeriqueHandler::TOLERANCE_NONE : CellValues::typeCode($kindCell);

        if (! in_array($kind, $kinds, true)) {
            $problems[] = "colonne `type_tolerance` : « {$kindCell} » n'est pas une valeur admise (".implode(', ', $kinds).')';
            $kind = ValeurNumeriqueHandler::TOLERANCE_NONE;
        }

        $tolerance = null;

        if ($kind === ValeurNumeriqueHandler::TOLERANCE_NONE) {
            if (($cells[self::TOLERANCE] ?? null) !== null) {
                $warnings[] = 'colonne `tolerance` ignorée : le type de tolérance est `aucune`';
            }
        } else {
            $tolerance = self::readNumber($cells, self::TOLERANCE, $problems, required: true);

            if ($tolerance !== null && $tolerance < 0) {
                $problems[] = 'colonne `tolerance` : l\'écart accepté ne peut pas être négatif';
            }
        }

        return ['value' => $value, 'tolerance_kind' => $kind, 'tolerance' => $tolerance, 'unit' => $cells[self::UNIT] ?? null];
    }

    /**
     * @param  array<string, string|null>  $cells
     * @param  list<string>  $problems
     */
    private static function readNumber(array $cells, string $column, array &$problems, bool $required): ?float
    {
        $cell = $cells[$column] ?? null;

        if ($cell === null) {
            if ($required) {
                $problems[] = "colonne `{$column}` : nombre obligatoire";
            }

            return null;
        }

        $number = CellValues::number($cell);

        if ($number === null) {
            $problems[] = "colonne `{$column}` : « {$cell} » n'est pas un nombre (virgule ou point décimal)";
        }

        return $number;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private static function writeQcm(array $payload): array
    {
        $cells = [self::CORRECT_ANSWER => (string) $payload['correct']];

        if (! ($payload['shuffle'] ?? true)) {
            $cells[self::SHUFFLE] = CellValues::writeBool(false);

            if ((int) ($payload['correct_position'] ?? 0) !== 0) {
                $cells[self::ORDER] = CellValues::writeList(array_values((new QcmHandler)->options($payload)));
            }
        }

        foreach (array_values($payload['wrong']) as $index => $answer) {
            $n = $index + 1;
            $cells[self::WRONG_ANSWER_PREFIX.$n] = (string) $answer['text'];

            if (($answer['explanation'] ?? null) !== null) {
                $cells[self::WRONG_EXPLANATION_PREFIX.$n] = (string) $answer['explanation'];
            }
        }

        return $cells;
    }
}
