<?php

namespace App\Banques\Exchange;

/**
 * Résultat de la lecture d'un CSV de questions.
 */
final readonly class CsvParseResult
{
    /**
     * @param  list<QuestionRow>  $questions  lignes valides
     * @param  list<array{line: int, message: string}>  $errors  lignes en erreur
     * @param  list<string>  $unknownColumns  colonnes ignorées
     * @param  list<string>  $notices  remarques sur l'ensemble du fichier
     */
    public function __construct(
        public array $questions,
        public array $errors,
        public array $unknownColumns,
        public array $notices = [],
    ) {}
}
