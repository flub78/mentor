<?php

namespace App\Qcm\Exchange;

/**
 * Résultat de la lecture d'un CSV de questions.
 */
final readonly class CsvParseResult
{
    /**
     * @param  list<QuestionData>  $questions  lignes valides
     * @param  list<array{line: int, message: string}>  $errors  lignes en erreur
     * @param  list<string>  $unknownColumns  colonnes ignorées
     */
    public function __construct(
        public array $questions,
        public array $errors,
        public array $unknownColumns,
    ) {}
}
