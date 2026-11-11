<?php

namespace App\Banques\Exchange;

use App\Banques\Models\Question;
use App\Banques\Questions\AnswerNormalizer;
use App\Banques\Usage\UsageRegistry;
use Illuminate\Support\Str;

/**
 * Rapport d'analyse d'un fichier avant import (PRD Banques §8.5, UC-05) :
 * rien n'est écrit tant que l'utilisateur n'a pas confirmé. Une ligne dont
 * la référence est celle d'une question de la cible désigne cette question :
 * elle la met à jour (ou est ignorée) et n'est jamais un doublon.
 */
final class ImportReport
{
    /** Libellés des différences avec une question déjà présente. */
    public const DIFFERENCE_LABELS = [
        'unchanged' => 'inchangée',
        'changed' => 'modifiée',
        'answer_changed' => 'réponse attendue modifiée (modification de fond)',
    ];

    /** @var array<int, array{row: QuestionRow, question: Question, difference: string}> ligne → question déjà présente */
    private array $existing = [];

    /** @var array<int, string> ligne → motif de rejet trouvé à l'analyse */
    private array $rejected = [];

    /** @var list<array{line: int, statement: string, reason: string}> */
    private array $duplicates = [];

    /** @var array<int, true> lignes des doublons */
    private array $duplicateLines = [];

    /** @var list<array{line: int, image: string, problem: string}> */
    private array $imageProblems = [];

    /** @var list<string> */
    private array $newThemes = [];

    /** @var list<string> */
    private array $notices = [];

    private function __construct(private readonly ExchangeFile $file) {}

    /**
     * @param  list<string>  $existingStatements  énoncés déjà présents dans la cible
     * @param  list<string>|null  $existingThemes  thèmes de la cible ; null = cible nouvelle, pas de liste
     * @param  array<string, Question>  $existingReferences  questions de la cible qui ont une référence, par référence
     */
    public static function analyse(ExchangeFile $file, array $existingStatements = [], ?array $existingThemes = null, array $existingReferences = []): self
    {
        $report = new self($file);
        $existing = array_fill_keys(array_map(QuestionRow::normalizeStatement(...), $existingStatements), true);
        $seen = [];
        $knownThemes = array_fill_keys(array_map(AnswerNormalizer::normalize(...), $existingThemes ?? []), true);
        $newThemes = [];

        foreach ($file->csv()->questions as $question) {
            $key = QuestionRow::normalizeStatement($question->statement);
            $match = $question->reference === null ? null : ($existingReferences[$question->reference] ?? null);

            if ($match !== null) {
                $report->match($question, $match);

                continue;
            }

            if (isset($seen[$key])) {
                $report->addDuplicate($question, "même énoncé que la ligne {$seen[$key]} du fichier");
            } elseif (isset($existing[$key])) {
                $report->addDuplicate($question, 'même énoncé qu\'une question déjà présente');
            }

            $seen[$key] ??= $question->line;

            if ($question->image !== null) {
                $problem = $file->image($question->image)['problem'];

                if ($problem !== null) {
                    $report->imageProblems[] = ['line' => $question->line, 'image' => $question->image, 'problem' => $problem];
                }
            }

            if ($existingThemes !== null && $question->theme !== null && ! isset($knownThemes[AnswerNormalizer::normalize($question->theme)])) {
                $newThemes[AnswerNormalizer::normalize($question->theme)] ??= $question->theme;
            }
        }

        $report->newThemes = array_values($newThemes);

        return $report;
    }

    public function file(): ExchangeFile
    {
        return $this->file;
    }

    public function addNotice(string $notice): void
    {
        $this->notices[] = $notice;
    }

    /**
     * Questions à importer, doublons écartés ou non.
     *
     * @return list<QuestionRow>
     */
    public function questionsToImport(bool $skipDuplicates): array
    {
        return array_values(array_filter(
            $this->file->csv()->questions,
            fn (QuestionRow $question): bool => ! isset($this->existing[$question->line])
                && ! isset($this->rejected[$question->line])
                && ! ($skipDuplicates && isset($this->duplicateLines[$question->line])),
        ));
    }

    /**
     * Lignes qui désignent une question déjà présente, par leur référence.
     *
     * @return list<array{row: QuestionRow, question: Question, difference: string}>
     */
    public function existingQuestions(): array
    {
        return array_values($this->existing);
    }

    public function validCount(): int
    {
        return count($this->file->csv()->questions) - count($this->rejected);
    }

    public function hasDuplicates(): bool
    {
        return $this->duplicates !== [];
    }

    /**
     * Contenu du rapport pour l'affichage.
     *
     * @return array{file: string, valid: int, byType: array<string, int>, existing: list<array{line: int, statement: string, reference: string, difference: string, label: string}>, errors: list<array{line: int, message: string}>, warnings: list<array{line: int, message: string}>, unknownColumns: list<string>, duplicates: list<array{line: int, statement: string, reason: string}>, imageProblems: list<array{line: int, image: string, problem: string}>, newThemes: list<string>, notices: list<string>}
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file->name(),
            'valid' => $this->validCount(),
            'byType' => $this->countByType(),
            'existing' => array_map(fn (array $entry): array => [
                'line' => $entry['row']->line,
                'statement' => $entry['row']->statement,
                'reference' => (string) $entry['row']->reference,
                'difference' => $entry['difference'],
                'label' => self::DIFFERENCE_LABELS[$entry['difference']].($entry['difference'] === 'answer_changed' ? self::substantiveNotice($entry['question']) : ''),
            ], array_values($this->existing)),
            'errors' => $this->errors(),
            'warnings' => $this->warnings(),
            'unknownColumns' => $this->file->csv()->unknownColumns,
            'duplicates' => $this->duplicates,
            'imageProblems' => $this->imageProblems,
            'newThemes' => $this->newThemes,
            'notices' => [...$this->file->csv()->notices, ...$this->notices],
        ];
    }

    /**
     * @return array<string, int> libellé du type → nombre de questions valides
     */
    private function countByType(): array
    {
        $counts = [];

        foreach ($this->file->csv()->questions as $question) {
            if (isset($this->rejected[$question->line])) {
                continue;
            }

            $label = $question->type->getLabel();
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return list<array{line: int, message: string}>
     */
    private function warnings(): array
    {
        $warnings = [];

        foreach ($this->file->csv()->questions as $question) {
            foreach ($question->warnings as $warning) {
                $warnings[] = ['line' => $question->line, 'message' => ucfirst($warning).'.'];
            }

            if ($question->image !== null && $question->imageAlt === null) {
                $warnings[] = ['line' => $question->line, 'message' => 'Illustration sans description (`texte_alternatif`) : « Illustration : » suivi du début de l\'énoncé la remplacera ; complétez-la ensuite.'];
            }
        }

        return $warnings;
    }

    /**
     * Ligne qui désigne une question de la cible : à mettre à jour si le type
     * est le même, en erreur sinon (le type ne change pas, UC-02).
     */
    private function match(QuestionRow $row, Question $question): void
    {
        if ($question->type !== $row->type) {
            $this->rejected[$row->line] = "Colonne `reference` : « {$row->reference} » désigne la question « ".Str::limit($question->statement, 60)." », de type {$question->type->getLabel()}, et la ligne est de type {$row->type->getLabel()} ; le type d'une question ne change pas : donnez une autre référence à cette ligne pour créer une nouvelle question.";

            return;
        }

        $this->existing[$row->line] = ['row' => $row, 'question' => $question, 'difference' => QuestionPersister::difference($question, $row, $this->file)];
    }

    private static function substantiveNotice(Question $question): string
    {
        $notice = app(UsageRegistry::class)->substantiveChangeNotice($question);

        return $notice === '' ? '' : ' — '.$notice;
    }

    /**
     * Erreurs du fichier et de l'analyse, dans l'ordre des lignes.
     *
     * @return list<array{line: int, message: string}>
     */
    private function errors(): array
    {
        $errors = $this->file->csv()->errors;

        foreach ($this->rejected as $line => $message) {
            $errors[] = ['line' => $line, 'message' => $message];
        }

        usort($errors, fn (array $a, array $b): int => $a['line'] <=> $b['line']);

        return $errors;
    }

    private function addDuplicate(QuestionRow $question, string $reason): void
    {
        $this->duplicates[] = ['line' => $question->line, 'statement' => $question->statement, 'reason' => $reason];
        $this->duplicateLines[$question->line] = true;
    }
}
