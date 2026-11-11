<?php

namespace App\Banques\Exchange;

use App\Banques\Questions\AnswerNormalizer;
use App\Banques\Questions\Difficulty;
use App\Banques\Questions\QuestionType;
use Illuminate\Support\Str;

/**
 * Lecture et écriture du CSV de questions (PRD Banques §8.1 à §8.3).
 *
 * Une ligne d'en-tête, puis une ligne par question. L'en-tête tolère
 * majuscules, accents et espaces (« Bonne réponse » vaut `bonne_reponse`).
 * Séparateur `;` ou `,`, encodage UTF-8, BOM toléré. Sans colonne `type`, une
 * ligne est un QCM : les fichiers produits par le module QCM se lisent tels
 * quels.
 */
final class QuestionCsv
{
    public const TYPE = 'type';

    public const STATEMENT = 'enonce';

    public const EXPLANATION = 'explication';

    public const THEME = 'theme';

    /** Ancien nom du thème dans les fichiers du module QCM. */
    public const MODULE = 'module';

    public const DIFFICULTY = 'difficulte';

    public const IMAGE = 'illustration';

    public const IMAGE_ALT = 'texte_alternatif';

    /** Séparateur écrit à l'export (celui des tableurs en français). */
    public const SEPARATOR = ';';

    private const BOM = "\u{FEFF}";

    private const COMMON_COLUMNS = [self::TYPE, self::STATEMENT, self::EXPLANATION, self::THEME, self::MODULE, self::DIFFICULTY, self::IMAGE, self::IMAGE_ALT];

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

        $records = $this->records($contents, $this->detectSeparator($contents));
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

        return new CsvParseResult($questions, $errors, $header['unknown'], $header['notices']);
    }

    /**
     * @param  iterable<QuestionRow>  $questions
     */
    public function write(iterable $questions): string
    {
        $questions = is_array($questions) ? array_values($questions) : iterator_to_array($questions, false);
        $rows = array_map(fn (QuestionRow $question): array => $this->cells($question), $questions);
        $onlyQcm = $questions !== [] && array_filter($questions, fn (QuestionRow $question): bool => $question->type !== QuestionType::Qcm) === [];
        $header = $this->writtenHeader($rows, $onlyQcm);

        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);
        fputcsv($handle, $header, self::SEPARATOR, '"', '', "\n");

        foreach ($rows as $cells) {
            if ($onlyQcm && array_key_exists(self::THEME, $cells)) {
                $cells[self::MODULE] = $cells[self::THEME];
            }

            fputcsv($handle, array_map(fn (string $column): string => $cells[$column] ?? '', $header), self::SEPARATOR, '"', '', "\n");
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

    /**
     * Cellules d'une question à l'export.
     *
     * @return array<string, string>
     */
    private function cells(QuestionRow $question): array
    {
        return array_filter([
            self::TYPE => $question->type->value,
            self::STATEMENT => $question->statement,
            self::EXPLANATION => (string) $question->explanation,
            self::THEME => (string) $question->theme,
            self::DIFFICULTY => $question->difficulty === null ? '' : mb_strtolower($question->difficulty->getLabel()),
            self::IMAGE => (string) $question->image,
            self::IMAGE_ALT => (string) $question->imageAlt,
            ...QuestionColumns::write($question->type, $question->payload),
        ], fn (string $value, string $column): bool => $value !== '' || in_array($column, [self::TYPE, self::STATEMENT], true), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * En-tête de l'export : colonnes communes, colonnes des types présents,
     * mauvaises réponses aussi nombreuses que nécessaire ; une colonne
     * facultative n'est écrite que si une question l'utilise. Une banque de QCM
     * seuls écrit le thème dans la colonne historique `module` (PRD Banques §8.5).
     *
     * @param  list<array<string, string>>  $rows
     * @return list<string>
     */
    private function writtenHeader(array $rows, bool $onlyQcm): array
    {
        $used = [];
        $wrongCount = 0;

        foreach ($rows as $cells) {
            foreach (array_keys($cells) as $column) {
                $used[$column] = true;

                if (preg_match('/^'.QuestionColumns::WRONG_ANSWER_PREFIX.'(\d+)$/', $column, $matches) === 1) {
                    $wrongCount = max($wrongCount, (int) $matches[1]);
                }
            }
        }

        $header = [self::TYPE, self::STATEMENT];

        foreach ([self::EXPLANATION, self::THEME, self::DIFFICULTY, self::IMAGE, self::IMAGE_ALT, ...QuestionColumns::fixedColumns()] as $column) {
            if (isset($used[$column])) {
                $header[] = $column === self::THEME && $onlyQcm ? self::MODULE : $column;
            }
        }

        for ($n = 1; $n <= $wrongCount; $n++) {
            $header[] = QuestionColumns::WRONG_ANSWER_PREFIX.$n;
            $header[] = QuestionColumns::WRONG_EXPLANATION_PREFIX.$n;
        }

        return $header;
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
     * @return array{columns: array<int, string>, unknown: list<string>, width: int, notices: list<string>}
     */
    private function header(array $cells): array
    {
        $columns = [];
        $unknown = [];
        $notices = [];

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

        if (! in_array(self::STATEMENT, $columns, true)) {
            throw ExchangeException::because('Colonne obligatoire absente de la première ligne : enonce. La première ligne doit nommer les colonnes (type, enonce, puis les colonnes propres à chaque type).');
        }

        if (! in_array(self::TYPE, $columns, true) && ! in_array(QuestionColumns::CORRECT_ANSWER, $columns, true)) {
            throw ExchangeException::because('Sans colonne `type`, toutes les questions sont des QCM, mais la colonne `bonne_reponse` est absente. Ajoutez la colonne `type` (qcm, vrai_faux, auto_evaluation, valeur_numerique) ou les colonnes d\'un QCM (bonne_reponse, mauvaise_reponse_1…).');
        }

        if (in_array(self::THEME, $columns, true) && in_array(self::MODULE, $columns, true)) {
            $notices[] = 'Le fichier a une colonne `theme` et une colonne `module` : `theme` est utilisée, `module` est ignorée.';
        }

        return ['columns' => $columns, 'unknown' => $unknown, 'width' => count($cells), 'notices' => $notices];
    }

    private function isKnownColumn(string $name): bool
    {
        return in_array($name, self::COMMON_COLUMNS, true)
            || in_array($name, QuestionColumns::fixedColumns(), true)
            || in_array($name, QuestionColumns::LATER_COLUMNS, true)
            || QuestionColumns::isNumberedColumn($name);
    }

    /**
     * @param  array<int, string>  $columns  index → nom de colonne connue
     * @param  int  $width  nombre de colonnes de l'en-tête
     * @param  list<string>  $values
     * @return QuestionRow|string question, ou motif de rejet de la ligne
     */
    private function question(int $line, array $columns, int $width, array $values): QuestionRow|string
    {
        $cells = [];

        foreach ($columns as $index => $name) {
            $value = $values[$index] ?? '';
            $cells[$name] = $value === '' ? null : $value;
        }

        $problems = [];
        $warnings = [];

        if (implode('', array_slice($values, $width)) !== '') {
            $problems[] = 'la ligne a plus de colonnes que l\'en-tête (séparateur ou guillemets mal placés ?)';
        }

        $type = $this->type($cells[self::TYPE] ?? null, $problems);

        if (($cells[self::STATEMENT] ?? null) === null) {
            $problems[] = 'colonne `enonce` : l\'énoncé est vide';
        }

        $image = $this->image($cells[self::IMAGE] ?? null, $problems);
        $difficulty = $this->difficulty($cells[self::DIFFICULTY] ?? null, $warnings);
        $payload = [];

        if ($type !== null) {
            $ignored = $this->foreignColumns($type, $cells);

            if ($ignored !== []) {
                $warnings[] = 'colonnes ignorées pour ce type : '.implode(', ', array_map(fn (string $column): string => "`{$column}`", $ignored));
            }

            $read = QuestionColumns::read($type, $cells);
            $warnings = [...$warnings, ...$read['warnings']];

            if ($read['problems'] !== []) {
                $problems = [...$problems, ...$read['problems']];
            } else {
                $payload = $type->handler()->normalize($read['payload']);
                $problems = [...$problems, ...array_map(fn (string $error): string => lcfirst(rtrim($error, '.')), $type->payloadErrors($payload))];
            }
        }

        if ($problems !== [] || $type === null) {
            return ucfirst(implode(' ; ', $problems)).'.';
        }

        return new QuestionRow(
            line: $line,
            type: $type,
            statement: (string) $cells[self::STATEMENT],
            payload: $payload,
            explanation: $cells[self::EXPLANATION] ?? null,
            theme: $cells[self::THEME] ?? $cells[self::MODULE] ?? null,
            difficulty: $difficulty,
            image: $image,
            imageAlt: $image === null ? null : ($cells[self::IMAGE_ALT] ?? null),
            warnings: $warnings,
        );
    }

    /**
     * @param  list<string>  $problems
     */
    private function type(?string $cell, array &$problems): ?QuestionType
    {
        if ($cell === null) {
            return QuestionType::Qcm;
        }

        $code = CellValues::typeCode($cell);
        $type = QuestionType::tryFrom($code);

        if ($type !== null) {
            return $type;
        }

        $problems[] = in_array($code, QuestionColumns::LATER_TYPES, true)
            ? "colonne `type` : le type « {$cell} » est prévu par le format mais pas encore disponible dans cette version"
            : "colonne `type` : « {$cell} » n'est pas un type connu (valeurs admises : ".implode(', ', array_map(fn (QuestionType $case): string => $case->value, QuestionType::cases())).')';

        return null;
    }

    /**
     * @param  list<string>  $problems
     */
    private function image(?string $cell, array &$problems): ?string
    {
        if ($cell === null) {
            return null;
        }

        $image = (string) preg_replace('#^images/#', '', $cell);

        if (str_contains($image, '/') || str_contains($image, '\\') || str_contains($image, '..')) {
            $problems[] = "colonne `illustration` : le nom « {$image} » est invalide (nom de fichier simple attendu, rangé dans le répertoire images/ de l'archive)";

            return null;
        }

        return $image;
    }

    /**
     * @param  list<string>  $warnings
     */
    private function difficulty(?string $cell, array &$warnings): ?Difficulty
    {
        if ($cell === null) {
            return null;
        }

        foreach (Difficulty::cases() as $difficulty) {
            if ($cell === (string) $difficulty->value || AnswerNormalizer::equals($cell, $difficulty->getLabel())) {
                return $difficulty;
            }
        }

        $warnings[] = "colonne `difficulte` : « {$cell} » n'est pas une valeur admise (1, 2, 3, facile, moyen, difficile) ; question importée sans difficulté";

        return null;
    }

    /**
     * Colonnes remplies qui ne concernent pas le type de la ligne.
     *
     * @param  array<string, string|null>  $cells
     * @return list<string>
     */
    private function foreignColumns(QuestionType $type, array $cells): array
    {
        $own = QuestionColumns::columnsOf($type);
        $foreign = [];

        foreach ($cells as $column => $value) {
            if ($value === null || in_array($column, self::COMMON_COLUMNS, true)) {
                continue;
            }

            $isOwn = in_array($column, $own, true) || ($type === QuestionType::Qcm && QuestionColumns::isNumberedColumn($column));

            if (! $isOwn) {
                $foreign[] = $column;
            }
        }

        return $foreign;
    }
}
