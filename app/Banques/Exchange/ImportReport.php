<?php

namespace App\Banques\Exchange;

use App\Banques\Questions\AnswerNormalizer;

/**
 * Rapport d'analyse d'un fichier avant import (PRD Banques §8.5, UC-05) :
 * rien n'est écrit tant que l'utilisateur n'a pas confirmé.
 */
final class ImportReport
{
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
     */
    public static function analyse(ExchangeFile $file, array $existingStatements = [], ?array $existingThemes = null): self
    {
        $report = new self($file);
        $existing = array_fill_keys(array_map(QuestionRow::normalizeStatement(...), $existingStatements), true);
        $seen = [];
        $knownThemes = array_fill_keys(array_map(AnswerNormalizer::normalize(...), $existingThemes ?? []), true);
        $newThemes = [];

        foreach ($file->csv()->questions as $question) {
            $key = QuestionRow::normalizeStatement($question->statement);

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
            fn (QuestionRow $question): bool => ! ($skipDuplicates && isset($this->duplicateLines[$question->line])),
        ));
    }

    public function validCount(): int
    {
        return count($this->file->csv()->questions);
    }

    public function hasDuplicates(): bool
    {
        return $this->duplicates !== [];
    }

    /**
     * Contenu du rapport pour l'affichage.
     *
     * @return array{file: string, valid: int, byType: array<string, int>, errors: list<array{line: int, message: string}>, warnings: list<array{line: int, message: string}>, unknownColumns: list<string>, duplicates: list<array{line: int, statement: string, reason: string}>, imageProblems: list<array{line: int, image: string, problem: string}>, newThemes: list<string>, notices: list<string>}
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file->name(),
            'valid' => $this->validCount(),
            'byType' => $this->countByType(),
            'errors' => $this->file->csv()->errors,
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

    private function addDuplicate(QuestionRow $question, string $reason): void
    {
        $this->duplicates[] = ['line' => $question->line, 'statement' => $question->statement, 'reason' => $reason];
        $this->duplicateLines[$question->line] = true;
    }
}
