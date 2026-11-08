<?php

namespace App\Qcm\Exchange;

use Illuminate\Support\Str;

/**
 * Lecture et écriture du CSV de questions (PRD QCM §6.1).
 *
 * Une ligne d'en-tête, puis une ligne par question. Colonnes : `enonce`,
 * `bonne_reponse`, `explication`, `module`, `illustration`, puis des paires
 * `mauvaise_reponse_N` / `explication_N` (N = 1, 2…). L'en-tête tolère
 * majuscules, accents et espaces (« Bonne réponse »). Séparateur `;` ou `,`,
 * encodage UTF-8, BOM toléré.
 */
final class QuestionCsv
{
    public const STATEMENT = 'enonce';

    public const CORRECT_ANSWER = 'bonne_reponse';

    public const EXPLANATION = 'explication';

    public const MODULE = 'module';

    public const IMAGE = 'illustration';

    public const WRONG_ANSWER_PREFIX = 'mauvaise_reponse_';

    public const WRONG_EXPLANATION_PREFIX = 'explication_';

    /** Séparateur écrit à l'export (celui des tableurs en français). */
    public const SEPARATOR = ';';

    private const BOM = "\u{FEFF}";

    public function parse(string $contents): CsvParseResult
    {
        if (str_starts_with($contents, self::BOM)) {
            $contents = substr($contents, strlen(self::BOM));
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw ExchangeException::because('Le fichier CSV n\'est pas encodé en UTF-8 : depuis votre tableur, enregistrez-le au format « CSV UTF-8 ».');
        }

        if (trim($contents) === '') {
            throw ExchangeException::because('Le fichier CSV est vide.');
        }

        $separator = $this->detectSeparator($contents);
        $records = $this->records($contents, $separator);
        $header = $this->header(array_shift($records) ?? []);

        $questions = [];
        $errors = [];

        foreach ($records as $index => $record) {
            $line = $index + 2;
            $values = array_map(fn (?string $value): string => trim((string) $value), $record);

            if (implode('', $values) === '') {
                continue;
            }

            $result = $this->question($line, $header['columns'], $header['width'], $values);

            if (is_string($result)) {
                $errors[] = ['line' => $line, 'message' => $result];
            } else {
                $questions[] = $result;
            }
        }

        return new CsvParseResult($questions, $errors, $header['unknown']);
    }

    /**
     * @param  iterable<QuestionData>  $questions
     */
    public function write(iterable $questions): string
    {
        $questions = is_array($questions) ? $questions : iterator_to_array($questions, false);
        $wrongCount = max(1, ...array_map(fn (QuestionData $question): int => count($question->wrongAnswers), $questions ?: [new QuestionData(0, '', '', [])]));

        $header = [self::STATEMENT, self::CORRECT_ANSWER, self::EXPLANATION, self::MODULE, self::IMAGE];

        for ($n = 1; $n <= $wrongCount; $n++) {
            $header[] = self::WRONG_ANSWER_PREFIX.$n;
            $header[] = self::WRONG_EXPLANATION_PREFIX.$n;
        }

        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);
        fputcsv($handle, $header, self::SEPARATOR, '"', '', "\n");

        foreach ($questions as $question) {
            $row = [$question->statement, $question->correctAnswer, $question->explanation ?? '', $question->module ?? '', $question->image ?? ''];

            for ($n = 0; $n < $wrongCount; $n++) {
                $row[] = $question->wrongAnswers[$n]['text'] ?? '';
                $row[] = $question->wrongAnswers[$n]['explanation'] ?? '';
            }

            fputcsv($handle, $row, self::SEPARATOR, '"', '', "\n");
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return self::BOM.$csv;
    }

    /**
     * Nom de colonne normalisé : « Bonne réponse » → `bonne_reponse`.
     */
    public static function normalizeColumn(string $name): string
    {
        $ascii = Str::lower(Str::ascii(trim($name)));

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $ascii), '_');
    }

    private function detectSeparator(string $contents): string
    {
        $firstLine = strtok($contents, "\n");
        $firstLine = $firstLine === false ? '' : $firstLine;

        return substr_count($firstLine, ',') > substr_count($firstLine, ';') ? ',' : ';';
    }

    /**
     * @return list<list<string|null>>
     */
    private function records(string $contents, string $separator): array
    {
        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);
        fwrite($handle, $contents);
        rewind($handle);

        $records = [];

        while (($record = fgetcsv($handle, null, $separator, '"', '')) !== false) {
            /** @var list<string|null> $record */
            $records[] = $record;
        }

        fclose($handle);

        return $records;
    }

    /**
     * @param  list<string|null>  $cells
     * @return array{columns: array<int, string>, unknown: list<string>, width: int}
     */
    private function header(array $cells): array
    {
        $columns = [];
        $unknown = [];

        foreach ($cells as $index => $cell) {
            $name = self::normalizeColumn((string) $cell);

            if ($name === '') {
                continue;
            }

            if (in_array($name, $columns, true)) {
                throw ExchangeException::because("La colonne « {$cell} » apparaît deux fois dans l'en-tête.");
            }

            if ($this->isKnownColumn($name)) {
                $columns[$index] = $name;
            } else {
                $unknown[] = (string) $cell;
            }
        }

        $missing = array_values(array_diff([self::STATEMENT, self::CORRECT_ANSWER, self::WRONG_ANSWER_PREFIX.'1'], $columns));

        if ($missing !== []) {
            throw ExchangeException::because(sprintf(
                'Colonnes obligatoires absentes de la première ligne : %s. La première ligne doit nommer les colonnes : %s, %s, %s1 (puis %s1, %s2…), et facultativement %s, %s, %s.',
                implode(', ', $missing),
                self::STATEMENT, self::CORRECT_ANSWER, self::WRONG_ANSWER_PREFIX,
                self::WRONG_EXPLANATION_PREFIX, self::WRONG_ANSWER_PREFIX,
                self::EXPLANATION, self::MODULE, self::IMAGE,
            ));
        }

        return ['columns' => $columns, 'unknown' => $unknown, 'width' => count($cells)];
    }

    private function isKnownColumn(string $name): bool
    {
        return in_array($name, [self::STATEMENT, self::CORRECT_ANSWER, self::EXPLANATION, self::MODULE, self::IMAGE], true)
            || preg_match('/^('.self::WRONG_ANSWER_PREFIX.'|'.self::WRONG_EXPLANATION_PREFIX.')[1-9]\d*$/', $name) === 1;
    }

    /**
     * @param  array<int, string>  $columns  index → nom de colonne connue
     * @param  int  $width  nombre de colonnes de l'en-tête
     * @param  list<string>  $values
     * @return QuestionData|string question, ou motif de rejet de la ligne
     */
    private function question(int $line, array $columns, int $width, array $values): QuestionData|string
    {
        $cells = [];

        foreach ($columns as $index => $name) {
            $value = $values[$index] ?? '';
            $cells[$name] = $value === '' ? null : $value;
        }

        $problems = [];

        if (implode('', array_slice($values, $width)) !== '') {
            $problems[] = 'la ligne a plus de colonnes que l\'en-tête (séparateur ou guillemets mal placés ?)';
        }

        if (($cells[self::STATEMENT] ?? null) === null) {
            $problems[] = 'l\'énoncé est vide';
        }

        if (($cells[self::CORRECT_ANSWER] ?? null) === null) {
            $problems[] = 'la bonne réponse est vide';
        }

        $numbers = [];

        foreach (array_keys($cells) as $name) {
            if (preg_match('/^(?:'.self::WRONG_ANSWER_PREFIX.'|'.self::WRONG_EXPLANATION_PREFIX.')(\d+)$/', $name, $matches) === 1) {
                $numbers[(int) $matches[1]] = true;
            }
        }

        ksort($numbers);
        $wrongAnswers = [];

        foreach (array_keys($numbers) as $n) {
            $text = $cells[self::WRONG_ANSWER_PREFIX.$n] ?? null;
            $explanation = $cells[self::WRONG_EXPLANATION_PREFIX.$n] ?? null;

            if ($text === null && $explanation !== null) {
                $problems[] = "l'explication {$n} n'a pas de mauvaise réponse {$n}";
            } elseif ($text !== null) {
                $wrongAnswers[] = ['text' => $text, 'explanation' => $explanation];
            }
        }

        if ($wrongAnswers === []) {
            $problems[] = 'aucune mauvaise réponse (il en faut au moins une)';
        }

        $image = $cells[self::IMAGE] ?? null;

        if ($image !== null) {
            $image = (string) preg_replace('#^images/#', '', $image);

            if (str_contains($image, '/') || str_contains($image, '\\') || str_contains($image, '..')) {
                $problems[] = "le nom d'illustration « {$image} » est invalide (nom de fichier simple attendu, rangé dans le répertoire images/ de l'archive)";
            }
        }

        if ($problems !== []) {
            return ucfirst(implode(' ; ', $problems)).'.';
        }

        return new QuestionData(
            line: $line,
            statement: (string) $cells[self::STATEMENT],
            correctAnswer: (string) $cells[self::CORRECT_ANSWER],
            wrongAnswers: $wrongAnswers,
            explanation: $cells[self::EXPLANATION] ?? null,
            module: $cells[self::MODULE] ?? null,
            image: $image,
        );
    }
}
